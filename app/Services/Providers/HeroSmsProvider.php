<?php

namespace App\Services\Providers;

use App\Exceptions\HeroSmsException;
use App\Models\Provider;
use App\Models\ProviderCountry;
use App\Models\ProviderLog;
use App\Models\ProviderService as ProviderServiceModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HeroSMS provider — implements the documented SMS-Activate-compatible
 * protocol: GET {base}?api_key=…&action=…&params, with text responses
 * (ACCESS_NUMBER:id:phone, STATUS_OK:code, ACCESS_BALANCE:x) and
 * documented error codes (NO_NUMBERS, EARLY_CANCEL_DENIED, BANNED, …).
 *
 * Config (.env only — never code):
 *   HERO_SMS_API_KEY        real key; empty = production calls disabled
 *   HERO_SMS_BASE_URL       default https://hero-sms.com/stubs/handler_api.php
 *   HERO_SMS_MODE           mock | production
 *   HERO_SMS_RESELLER_ENABLED  send the buyer's user ID with getNumberV2
 *   HERO_SMS_RESELLER_PARAM    parameter name for that ID (default userId)
 *
 * Rate limit: 50 RPS per account (hero-sms.com/rules). We self-throttle
 * below that and only retry idempotent reads — never purchases.
 */
class HeroSmsProvider implements ProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected bool $useMock = false;
    protected int $timeout;
    protected int $retryAttempts;
    protected ?Provider $providerModel = null;

    /** setStatus codes per the documented protocol */
    public const STATUS_READY = 1;   // SMS sent by us — ready to receive
    public const STATUS_RESEND = 3;  // request another SMS
    public const STATUS_COMPLETE = 6;
    public const STATUS_CANCEL = 8;

    /** Self-imposed ceiling, safely under the 50 RPS account limit. */
    protected const MAX_RPS = 40;

    public function __construct()
    {
        $cfg = config('services.hero_sms', []);

        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? env('HERO_SMS_BASE_URL', 'https://hero-sms.com/stubs/handler_api.php')), '/');
        $this->apiKey = (string) ($cfg['api_key'] ?? env('HERO_SMS_API_KEY', ''));
        $this->timeout = (int) ($cfg['timeout'] ?? 30);
        $this->retryAttempts = max(1, (int) ($cfg['retry_attempts'] ?? 3));

        // HERO_SMS_MODE takes precedence; HERO_SMS_USE_MOCK kept for BC.
        $mode = strtolower((string) ($cfg['mode'] ?? env('HERO_SMS_MODE', '')));
        if ($mode !== '') {
            $this->useMock = $mode !== 'production';
        } else {
            $this->useMock = filter_var($cfg['use_mock'] ?? env('HERO_SMS_USE_MOCK', true), FILTER_VALIDATE_BOOL);
        }

        // Safety rail: mock data must never leak into production.
        if ($this->useMock && app()->environment('production')) {
            throw new \RuntimeException(
                'HeroSMS mock provider is enabled in production. Set HERO_SMS_MODE=production and configure real credentials.'
            );
        }
    }

    // =========================================================================
    // Reference data
    // =========================================================================

    public function getBalance(): float
    {
        if ($this->useMock) {
            return $this->mockGetBalance();
        }

        try {
            $raw = $this->call('getBalance');
            // ACCESS_BALANCE:123.45
            if (is_string($raw) && preg_match('/ACCESS_BALANCE:([\d.]+)/', $raw, $m)) {
                return (float) $m[1];
            }
            if (is_array($raw) && isset($raw['balance'])) {
                return (float) $raw['balance'];
            }
            throw new HeroSmsException('PARSE_ERROR', 'Unexpected getBalance response');
        } catch (HeroSmsException $e) {
            $this->logCall('getBalance', 'error', null, $e->errorCode, $e->getMessage());
            return 0;
        }
    }

    public function getCountries(): array
    {
        if ($this->useMock) {
            return $this->mockGetCountries();
        }

        try {
            $data = $this->call('getCountries');
            // Keyed object: {"0": {"id":0,"eng":"Russia",...}, ...}
            $out = [];
            foreach ((array) $data as $row) {
                if (!is_array($row) || !isset($row['id'])) continue;
                $out[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['eng'] ?? $row['rus'] ?? (string) $row['id'],
                    'code' => (string) $row['id'],
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getServices(): array
    {
        if ($this->useMock) {
            return $this->mockGetServices();
        }

        try {
            $data = $this->call('getServicesList', ['lang' => 'en']);
            // {status:"success", services:[{code:"wa",name:"WhatsApp"}, ...]}
            $out = [];
            foreach ((array) ($data['services'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $out[] = [
                    'slug' => $row['code'] ?? null,
                    'name' => $row['name'] ?? $row['code'] ?? null,
                ];
            }
            return array_filter($out, fn ($s) => $s['slug'] !== null);
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // Availability / pricing
    // =========================================================================

    /**
     * Availability + provider cost for a country/service pair, via the
     * documented getPrices action: Record<countryId, Record<service, {cost,count}>>.
     * Returns [] when unavailable — callers treat that as "none available".
     */
    public function getAvailableNumbers(string $countryCode, string $serviceSlug): array
    {
        if ($this->useMock) {
            return $this->mockGetAvailableNumbers($countryCode, $serviceSlug);
        }

        [$heroCountry, $heroService] = $this->resolveMapping($countryCode, $serviceSlug);
        if ($heroCountry === null || $heroService === null) {
            return []; // combination not mapped = not sellable
        }

        $data = $this->call('getPrices', [
            'service' => $heroService,
            'country' => $heroCountry,
        ]);

        $priceInfo = $data[$heroCountry][$heroService] ?? null;
        if (!is_array($priceInfo)) {
            return [];
        }

        $count = (int) ($priceInfo['count'] ?? $priceInfo['physicalCount'] ?? 0);
        $cost = (float) ($priceInfo['cost'] ?? 0);

        // getPrices counts are advertised/aggregate — they can be non-zero
        // for combos the provider cannot actually allocate (e.g. US+fb
        // shows 3,598 but every purchase returns NO_NUMBERS). The ranked
        // list reports truly allocatable stock; trust it when available.
        $allocatable = $this->allocatableCount($heroService, (int) $heroCountry);
        if ($allocatable !== null) {
            $count = $allocatable;
        }

        if ($count <= 0) {
            return [];
        }

        return [[
            'cost' => $cost,
            'count' => $count,
            'available' => true,
        ]];
    }

    /**
     * Truly allocatable stock from getTopCountriesByService — the only
     * endpoint whose count matches what purchases can actually fulfil.
     * Cached briefly per service (provider rate-limits aggressively).
     * Returns null when the ranking can't answer — callers then fall back
     * to the getPrices count rather than blocking a sale on a failed call.
     */
    protected function allocatableCount(string $heroService, int $heroCountry): ?int
    {
        try {
            $list = Cache::remember("herosms:top:{$heroService}", 90, function () use ($heroService) {
                return $this->call('getTopCountriesByService', ['service' => $heroService], false);
            });

            foreach ((array) $list as $entry) {
                if ((int) ($entry['country'] ?? -1) === $heroCountry) {
                    return (int) ($entry['count'] ?? 0);
                }
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Allocatable stock for EVERY country of a service in one call —
     * [heroCountryId => count]. Powers the per-service country lists.
     * Null means the provider could not answer; callers fall back to
     * active mappings (the live quote still gates each purchase).
     */
    public function availableCountryCounts(string $serviceSlug): ?array
    {
        if ($this->useMock) {
            return null; // mock has no catalog — use mappings
        }

        $serviceMap = ProviderServiceModel::where('provider_id', $this->providerModel()?->id)
            ->where('is_active', true)
            ->whereHas('service', fn ($q) => $q->where('slug', $serviceSlug)->where('is_active', true))
            ->first();
        if (!$serviceMap) {
            return [];
        }
        $heroService = $serviceMap->provider_service_code;

        try {
            $list = Cache::remember("herosms:top:{$heroService}", 90, function () use ($heroService) {
                return $this->call('getTopCountriesByService', ['service' => $heroService], false);
            });

            $out = [];
            foreach ((array) $list as $entry) {
                $cid = (int) ($entry['country'] ?? 0);
                $count = (int) ($entry['count'] ?? 0);
                if ($cid > 0 && $count > 0) {
                    $out[$cid] = $count;
                }
            }

            // Keep a longer-lived snapshot so a rate-limit window never
            // collapses the country list — purchase-time quotes still
            // verify live, so stale entries degrade gracefully.
            Cache::put("herosms:top:{$heroService}:snapshot", $out, 3600);

            return $out;
        } catch (\Throwable $e) {
            $stale = Cache::get("herosms:top:{$heroService}:snapshot");
            return is_array($stale) ? $stale : null;
        }
    }

    // =========================================================================
    // Purchase
    // =========================================================================

    /**
     * Buy a number via getNumberV2 (rich response incl. activationCost),
     * falling back to getNumber (ACCESS_NUMBER:id:phone) if V2 misbehaves.
     * NEVER retried — a retry could double-purchase. Options:
     *   user_id   — KamVerify customer ID, sent via the reseller param
     *   max_price — ceiling; provider answers WRONG_MAX_PRICE if below min
     */
    public function purchaseNumber(string $countryCode, string $serviceSlug, array $options = []): array
    {
        if ($this->useMock) {
            $this->track('purchase', 'success');
            return $this->mockPurchaseNumber($countryCode, $serviceSlug);
        }

        [$heroCountry, $heroService, $mapping] = $this->resolveMapping($countryCode, $serviceSlug, true);
        if ($heroCountry === null || $heroService === null) {
            throw new HeroSmsException('NO_MAPPING', 'No active provider mapping for this country/service');
        }

        $params = [
            'service' => $heroService,
            'country' => $heroCountry,
        ];

        // Price ceiling protects us from paying more than quoted.
        $maxPrice = $options['max_price'] ?? ($mapping?->cost !== null ? (float) $mapping->cost : null);
        if ($maxPrice !== null && $maxPrice > 0) {
            $params['maxPrice'] = round($maxPrice * 1.10, 4); // 10% headroom over catalog cost
        }

        // Reseller: pass the buyer's KamVerify user ID so per-buyer bans
        // don't hit the account. Param name configurable — see .env docs.
        if ($this->resellerEnabled() && isset($options['user_id'])) {
            $params[$this->resellerParam()] = (string) $options['user_id'];
        }

        $this->track('purchase');

        try {
            $data = $this->call('getNumberV2', $params, retry: false);
            return $this->normalizePurchase($data);
        } catch (HeroSmsException $e) {
            // Only fall back to getNumber when the error proves the
            // purchase never executed (endpoint unknown / request
            // rejected). Never on PARSE_ERROR or SERVER_ERROR — the
            // activation may already exist and a second call would
            // double-charge the provider account.
            if (!in_array($e->errorCode, ['BAD_ACTION', 'UNPROCESSABLE_ENTITY'])) {
                throw $e;
            }
            $data = $this->call('getNumber', $params, retry: false);
            return $this->normalizePurchase($data);
        }
    }

    protected function normalizePurchase($data): array
    {
        // getNumberV2 JSON: {activationId, phoneNumber, activationCost, ...}
        if (is_array($data)) {
            $id = $data['activationId'] ?? $data['id'] ?? null;
            $phone = $data['phoneNumber'] ?? $data['phone'] ?? null;
            if ($id && $phone) {
                $this->track('purchase', 'success');
                return [
                    'activation_id' => (string) $id,
                    'phone_number' => (string) $phone,
                    'cost' => isset($data['activationCost']) ? (float) $data['activationCost'] : null,
                    'status' => 'success',
                ];
            }
            throw new HeroSmsException('PARSE_ERROR', 'getNumberV2 missing activationId/phoneNumber');
        }

        // getNumber text: ACCESS_NUMBER:activationId:phone
        if (is_string($data) && preg_match('/ACCESS_NUMBER:(\d+):(\S+)/', $data, $m)) {
            $this->track('purchase', 'success');
            return [
                'activation_id' => $m[1],
                'phone_number' => $m[2],
                'cost' => null,
                'status' => 'success',
            ];
        }

        throw new HeroSmsException('PARSE_ERROR', 'Unexpected purchase response');
    }

    // =========================================================================
    // Status / SMS
    // =========================================================================

    /**
     * Raw provider status: STATUS_WAIT_CODE | STATUS_WAIT_RETRY |
     * STATUS_WAIT_RESEND | STATUS_CANCEL | STATUS_OK[:code]
     */
    public function getActivationStatus(string $activationId): array
    {
        if ($this->useMock) {
            return $this->mockGetActivationStatus($activationId);
        }

        $raw = $this->call('getStatus', ['id' => $activationId]);

        if (is_string($raw) && str_starts_with($raw, 'STATUS_OK')) {
            $code = str_contains($raw, ':') ? explode(':', $raw, 2)[1] : null;
            return ['status' => 'STATUS_OK', 'code' => $code];
        }

        return ['status' => is_string($raw) ? $raw : 'UNKNOWN'];
    }

    /**
     * Poll for received SMS. Cheap path: getStatus. When STATUS_OK,
     * pull getAllSms for the full message/sender; fall back to the
     * bare code so the order still completes.
     */
    public function getSms(string $activationId): array
    {
        if ($this->useMock) {
            return $this->mockGetSms($activationId);
        }

        $status = $this->getActivationStatus($activationId);

        if (($status['status'] ?? '') !== 'STATUS_OK') {
            return [];
        }

        try {
            $data = $this->call('getAllSms', ['id' => $activationId]);
            $items = $data['data'] ?? (is_array($data) ? $data : []);
            $out = [];
            foreach ((array) $items as $i => $sms) {
                if (!is_array($sms)) continue;
                $out[] = [
                    'id' => (string) ($sms['id'] ?? ($activationId . '-' . $i)),
                    'sender' => (string) ($sms['phoneFrom'] ?? $sms['sender'] ?? 'Unknown'),
                    'message' => (string) ($sms['text'] ?? $sms['code'] ?? ''),
                    'received_at' => $sms['date'] ?? $sms['dateTime'] ?? null,
                ];
            }
            if ($out) {
                $this->track('sms', 'success');
                return $out;
            }
        } catch (\Throwable $e) {
            // fall through to bare-code message
        }

        // getAllSms gave nothing usable — still complete on the code.
        $code = $status['code'] ?? '';
        $this->track('sms', 'success');
        return [[
            'id' => $activationId . '-otp',
            'sender' => 'SMS',
            'message' => $code !== '' ? 'Verification code: ' . $code : 'Verification code received',
            'received_at' => now()->toIso8601String(),
        ]];
    }

    // =========================================================================
    // Cancellation / lifecycle
    // =========================================================================

    /**
     * Cancel via setStatus status=8 → ACCESS_CANCEL. Documented failures:
     * EARLY_CANCEL_DENIED (first 2 min), OTP_RECEIVED/FINISHED/CANCELED.
     */
    public function cancelActivation(string $activationId): array
    {
        if ($this->useMock) {
            $this->track('cancel', 'success');
            return $this->mockCancelActivation($activationId);
        }

        $this->track('cancel');
        $raw = $this->call('setStatus', ['id' => $activationId, 'status' => self::STATUS_CANCEL], retry: false);
        $this->track('cancel', 'success');

        return [
            'activation_id' => $activationId,
            'status' => 'cancelled',
            'provider_response' => is_string($raw) ? $raw : json_encode($raw),
        ];
    }

    /** Same documented mechanism — activation cancel releases funds. */
    public function requestRefund(string $activationId): array
    {
        return $this->cancelActivation($activationId);
    }

    /**
     * Tell HeroSMS we're ready to receive SMS (setStatus=1).
     * Non-fatal by design — callers wrap in try/catch.
     */
    public function markReady(string $activationId): void
    {
        if ($this->useMock) {
            return;
        }
        $this->call('setStatus', ['id' => $activationId, 'status' => self::STATUS_READY], retry: false);
    }

    public function isActive(): bool
    {
        return !empty($this->apiKey) && !$this->useMock;
    }

    public function getProviderName(): string
    {
        return 'HeroSMS';
    }

    // =========================================================================
    // Core HTTP layer — protocol, throttling, logging
    // =========================================================================

    /**
     * Single GET against handler_api.php. Never sends the key anywhere
     * but the configured base URL. Retries only when $retry and the
     * failure is transient (HTTP 5xx / timeout / SERVER_ERROR / ERROR_SQL).
     */
    protected function call(string $action, array $params = [], bool $retry = true)
    {
        $this->throttle();

        $started = microtime(true);
        $attempt = 0;
        $maxAttempts = $retry ? $this->retryAttempts : 1;

        while (true) {
            $attempt++;
            try {
                $response = Http::timeout($this->timeout)->get($this->baseUrl, array_merge(
                    ['api_key' => $this->apiKey, 'action' => $action],
                    array_filter($params, fn ($v) => $v !== null)
                ));

                $body = trim((string) $response->body());
                $data = $this->decode($body);

                // HTTP-level failure with no usable protocol body
                if (!$response->successful() && $data === null) {
                    throw new HeroSmsException('HTTP_' . $response->status(), 'HTTP ' . $response->status());
                }

                // Structured JSON error {title, details, info}
                if (is_array($data) && isset($data['title'], $data['details']) && is_string($data['title'])) {
                    throw new HeroSmsException($data['title'], (string) $data['details'], $data['info'] ?? null);
                }

                // Plain-text protocol errors
                if (is_string($data)) {
                    $this->raiseForProtocolError($data);
                }

                $this->logCall($action, 'success', $params['id'] ?? null, null, null, $response->status(), $started);
                return $data;

            } catch (HeroSmsException $e) {
                $this->logCall($action, 'error', $params['id'] ?? null, $e->errorCode, $e->getMessage(), null, $started);

                if ($attempt >= $maxAttempts || !$e->isRetryable()) {
                    throw $e;
                }
                usleep(400_000 * $attempt); // 0.4s, 0.8s, …
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $this->logCall($action, 'error', $params['id'] ?? null, 'TIMEOUT', 'connection failed', null, $started);

                if ($attempt >= $maxAttempts) {
                    throw new HeroSmsException('TIMEOUT', 'Provider connection failed');
                }
                usleep(400_000 * $attempt);
            }
        }
    }

    protected function decode(string $body)
    {
        if ($body === '') {
            return null;
        }
        $json = json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : $body;
    }

    /** Throws typed exceptions for documented string error codes. */
    protected function raiseForProtocolError(string $body): void
    {
        if (str_starts_with($body, 'BANNED:')) {
            throw new HeroSmsException('BANNED', 'Provider account banned until ' . substr($body, 7));
        }
        if (str_starts_with($body, 'WRONG_MAX_PRICE:')) {
            throw new HeroSmsException('WRONG_MAX_PRICE', 'Max price too low; minimum ' . substr($body, 16));
        }
        foreach (HeroSmsException::MESSAGES as $code => $message) {
            if ($body === $code) {
                throw new HeroSmsException($code, $message);
            }
        }
    }

    /**
     * Stay under HeroSMS's 50 RPS account limit. Sliding-window counter
     * in cache; when the window is full we wait it out rather than
     * firing requests that would get the account blocked for 10s.
     */
    protected function throttle(): void
    {
        $windowKey = 'herosms_rps:' . now()->format('YmdHis');
        $count = Cache::increment($windowKey);
        if ($count === 1) {
            Cache::put($windowKey, 1, 2);
        }
        if ($count > self::MAX_RPS) {
            usleep(1_000_000 - ((int) (microtime(true) * 1_000_000) % 1_000_000));
        }
    }

    /** Safe call log — ids, status, timing, error code. No secrets. */
    protected function logCall(
        string $action,
        string $status,
        ?string $activationId = null,
        ?string $errorCode = null,
        ?string $message = null,
        ?int $httpStatus = null,
        ?float $started = null,
    ): void {
        try {
            ProviderLog::create([
                'provider_id' => $this->providerModel()?->id,
                'action' => $action,
                'activation_id' => $activationId,
                'status' => $status,
                'http_status' => $httpStatus,
                'error_code' => $errorCode,
                'message' => $message ? mb_substr($message, 0, 490) : null,
                'duration_ms' => $started !== null ? (int) ((microtime(true) - $started) * 1000) : null,
            ]);
        } catch (\Throwable $e) {
            // logging must never break provider calls
        }
    }

    /**
     * Translate KamVerify's ISO country code + service slug into the
     * provider's numeric country ID + service code via the mapping
     * tables. Returns [countryCode, serviceCode, ProviderService|null].
     */
    protected function resolveMapping(string $countryCode, string $serviceSlug, bool $withServiceModel = false): array
    {
        $provider = $this->providerModel();
        if (!$provider) {
            return [null, null, null];
        }

        $countryMap = ProviderCountry::where('provider_id', $provider->id)
            ->where('is_active', true)
            ->whereHas('country', fn ($q) => $q->where('code', $countryCode)->where('is_active', true))
            ->first();

        $serviceMap = ProviderServiceModel::where('provider_id', $provider->id)
            ->where('is_active', true)
            ->whereHas('service', fn ($q) => $q->where('slug', $serviceSlug)->where('is_active', true))
            ->first();

        return [
            $countryMap?->provider_country_code,
            $serviceMap?->provider_service_code,
            $serviceMap,
        ];
    }

    protected function providerModel(): ?Provider
    {
        return $this->providerModel ??= Provider::where('slug', 'herosms')->first();
    }

    protected function resellerEnabled(): bool
    {
        return filter_var(config('services.hero_sms.reseller_enabled', env('HERO_SMS_RESELLER_ENABLED', false)), FILTER_VALIDATE_BOOL);
    }

    protected function resellerParam(): string
    {
        return (string) (config('services.hero_sms.reseller_param') ?: env('HERO_SMS_RESELLER_PARAM', 'userId'));
    }

    /**
     * Record API activity on the Provider row so admins can see last
     * request / last success / last error without secrets.
     */
    protected function track(string $operation, string $outcome = 'request', ?string $error = null): void
    {
        try {
            $provider = $this->providerModel();
            if (!$provider) {
                return;
            }
            $config = $provider->config ?? [];
            $config['last_operation'] = $operation;
            $config['last_request_at'] = now()->toIso8601String();
            if ($outcome === 'success') {
                $config['last_success_at'] = now()->toIso8601String();
            } elseif ($outcome === 'error') {
                $config['last_error'] = mb_substr($error ?? 'unknown', 0, 200);
                $config['last_error_at'] = now()->toIso8601String();
            }
            $provider->config = $config;
            $provider->save();
        } catch (\Throwable $e) {
            // telemetry must never break provider operations
        }
    }

    // =========================================================================
    // Mock methods for development (HERO_SMS_MODE=mock)
    // =========================================================================

    protected function mockGetBalance(): float
    {
        return 1000.00;
    }

    protected function mockGetCountries(): array
    {
        return [
            ['code' => 'US', 'name' => 'United States', 'dial_code' => '+1'],
            ['code' => 'GB', 'name' => 'United Kingdom', 'dial_code' => '+44'],
            ['code' => 'CA', 'name' => 'Canada', 'dial_code' => '+1'],
            ['code' => 'DE', 'name' => 'Germany', 'dial_code' => '+49'],
            ['code' => 'FR', 'name' => 'France', 'dial_code' => '+33'],
        ];
    }

    protected function mockGetServices(): array
    {
        return [
            ['slug' => 'whatsapp', 'name' => 'WhatsApp'],
            ['slug' => 'facebook', 'name' => 'Facebook'],
            ['slug' => 'telegram', 'name' => 'Telegram'],
            ['slug' => 'tiktok', 'name' => 'TikTok'],
            ['slug' => 'google', 'name' => 'Google'],
        ];
    }

    protected function mockGetAvailableNumbers(string $countryCode, string $serviceSlug): array
    {
        return [
            [
                'phone_number' => '+1' . rand(2000000000, 9999999999),
                'cost' => 0.50,
                'available' => true,
            ],
        ];
    }

    protected function mockPurchaseNumber(string $countryCode, string $serviceSlug): array
    {
        $activationId = 'ACT-' . strtoupper(uniqid());

        Cache::put("mock_activation:{$activationId}", now()->timestamp, 3600);

        return [
            'activation_id' => $activationId,
            'phone_number' => '+1' . rand(2000000000, 9999999999),
            'cost' => 0.50,
            'status' => 'success',
        ];
    }

    protected function mockGetActivationStatus(string $activationId): array
    {
        return [
            'activation_id' => $activationId,
            'status' => 'waiting_for_sms',
            'phone_number' => '+1' . rand(2000000000, 9999999999),
        ];
    }

    protected function mockGetSms(string $activationId): array
    {
        $purchasedAt = Cache::get("mock_activation:{$activationId}");

        if (!$purchasedAt) {
            return [];
        }

        $delaySeconds = 20 + (abs(crc32($activationId)) % 31);
        if (now()->timestamp - $purchasedAt < $delaySeconds) {
            return [];
        }

        $code = str_pad((string) (abs(crc32($activationId)) % 1000000), 6, '0', STR_PAD_LEFT);

        return [
            [
                'id' => 'mock-' . $activationId . '-1',
                'sender' => '+1555' . str_pad((string) (abs(crc32($activationId)) % 10000000), 7, '0', STR_PAD_LEFT),
                'message' => "Your verification code is {$code}",
                'received_at' => now()->toIso8601String(),
            ],
        ];
    }

    protected function mockCancelActivation(string $activationId): array
    {
        return [
            'activation_id' => $activationId,
            'status' => 'cancelled',
            'refund_amount' => 0.50,
        ];
    }
}
