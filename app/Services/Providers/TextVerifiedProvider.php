<?php

namespace App\Services\Providers;

use App\Exceptions\TextVerifiedException;
use App\Models\Provider;
use App\Models\ProviderCountry;
use App\Models\ProviderLog;
use App\Models\ProviderService as ProviderServiceModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * TextVerified provider — official API v2 (REST/JSON, bearer auth).
 * Used ONLY for Facebook/Meta verifications; all other services stay
 * on HeroSMS via the provider routing in ProviderService::providerFor.
 *
 * Protocol (docs: textverified.com/docs/api/v2):
 *   POST /api/pub/v2/auth                        → {token, expiresAt}
 *     auth via headers X-API-KEY + X-API-USERNAME (account email)
 *   GET  /api/pub/v2/account/me                  → {currentBalance, …}
 *   GET  /api/pub/v2/services                    → service catalog
 *   POST /api/pub/v2/inventory/verifications     → stock for a config
 *   POST /api/pub/v2/pricing/verifications       → price for a config
 *   POST /api/pub/v2/verifications               → create (returns an
 *     action {method, href} that resolves the verification details)
 *   GET  /api/pub/v2/verifications/{id}          → state polling
 *   GET  /api/pub/v2/verifications               → list (reconciliation)
 *   GET  /api/pub/v2/sms?to={number}&reservationType=VERIFICATION
 *   POST /api/pub/v2/verifications/{id}/cancel   → cancel + auto-refund
 *
 * States: VERIFICATION_{PENDING,COMPLETED,CANCELED,TIMED_OUT,REFUNDED,
 * REACTIVATED,REUSED,REPORTED}. TextVerified auto-refunds when no SMS
 * arrives — TIMED_OUT/REFUNDED are treated as provider_resolved.
 *
 * Config (.env only — never code):
 *   TEXTVERIFIED_ENABLED, TEXTVERIFIED_API_KEY, TEXTVERIFIED_EMAIL,
 *   TEXTVERIFIED_BASE_URL, TEXTVERIFIED_MODE (mock|production)
 */
class TextVerifiedProvider implements ProviderInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $username;
    protected bool $enabled;
    protected bool $useMock = false;
    protected int $timeout;
    protected ?Provider $providerModel = null;

    /** Bearer cache key — token never logged, never returned to callers. */
    protected const TOKEN_CACHE = 'textverified:bearer';
    /** Regenerate this many seconds before the documented expiry. */
    protected const TOKEN_SKEW = 60;
    /** Window for adopting an orphaned verification after an ambiguous
     *  create failure — prevents double-purchase. */
    protected const ORPHAN_WINDOW_SECONDS = 180;

    /** TextVerified verifications are US-only. */
    protected const COUNTRY = 'US';

    public function __construct()
    {
        $cfg = config('services.textverified', []);

        $this->enabled = filter_var($cfg['enabled'] ?? env('TEXTVERIFIED_ENABLED', false), FILTER_VALIDATE_BOOL);
        $this->apiKey = (string) ($cfg['api_key'] ?? '');
        $this->username = (string) ($cfg['username'] ?? '');
        $this->baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://www.textverified.com'), '/');
        $this->timeout = (int) ($cfg['timeout'] ?? 30);

        $mode = strtolower((string) ($cfg['mode'] ?? env('TEXTVERIFIED_MODE', '')));
        $this->useMock = $mode !== '' && $mode !== 'production';

        // Safety rail: mock data must never leak into production.
        if ($this->useMock && app()->environment('production')) {
            throw new \RuntimeException(
                'TextVerified mock provider is enabled in production. Set TEXTVERIFIED_MODE=production and configure real credentials.'
            );
        }
    }

    // =========================================================================
    // Auth — bearer token, cached for its documented lifetime
    // =========================================================================

    /**
     * Returns a valid bearer token, generating or refreshing as needed.
     * The token lives only in the cache — never in logs or responses.
     */
    protected function bearerToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE);
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + self::TOKEN_SKEW) {
            return $cached['token'];
        }

        // Single-flight: concurrent workers must not mint parallel tokens.
        return Cache::lock('textverified:auth', 15)->block(10, function () {
            $cached = Cache::get(self::TOKEN_CACHE);
            if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + self::TOKEN_SKEW) {
                return $cached['token'];
            }

            $started = microtime(true);
            $response = Http::timeout($this->timeout)
                ->withHeaders([ // credentials travel in headers, not the body
                    'X-API-KEY' => $this->apiKey,
                    'X-API-USERNAME' => $this->username,
                ])
                ->post($this->baseUrl . '/api/pub/v2/auth');

            $this->logCall('auth', $response->successful() ? 'success' : 'error',
                null, $response->successful() ? null : 'HTTP_' . $response->status(),
                null, $response->status(), $started);

            if (!$response->successful()) {
                throw new TextVerifiedException('AUTH_FAILED', 'TextVerified bearer token request failed (HTTP ' . $response->status() . ')');
            }

            $data = $response->json();
            $token = $data['token'] ?? null;
            $expiresAt = isset($data['expiresAt']) ? strtotime((string) $data['expiresAt']) : null;
            if (!$token || !$expiresAt) {
                throw new TextVerifiedException('PARSE_ERROR', 'Unexpected auth response');
            }

            Cache::put(self::TOKEN_CACHE, ['token' => $token, 'expires_at' => $expiresAt],
                max(1, $expiresAt - time() - self::TOKEN_SKEW));

            return $token;
        });
    }

    // =========================================================================
    // HTTP layer
    // =========================================================================

    /**
     * Authenticated call. On 401 the cached token is dropped and the
     * request retried ONCE with a fresh token. $retry applies only to
     * transient network/5xx failures on idempotent reads — purchases
     * pass retry=false.
     */
    protected function call(string $method, string $path, array $params = [], ?array $json = null, bool $retry = true)
    {
        $attempt = 0;
        $maxAttempts = $retry ? 3 : 1;
        $refreshed = false;

        while (true) {
            $attempt++;
            $started = microtime(true);
            try {
                $req = Http::timeout($this->timeout)
                    ->withToken($this->bearerToken());

                $url = $path === '' ? '' : (str_starts_with($path, 'http') ? $path : $this->baseUrl . $path);
                $req = match (strtoupper($method)) {
                    'GET' => $req->get($url, $params),
                    'POST' => $req->post($url, $json ?? $params),
                    default => $req->send($method, $url, ['query' => $params, 'json' => $json]),
                };
                $response = $req;

                if ($response->status() === 401 && !$refreshed) {
                    // Token rejected — drop cache, mint a fresh one, retry once.
                    $refreshed = true;
                    Cache::forget(self::TOKEN_CACHE);
                    continue;
                }

                $this->logCall("{$method} {$path}", $response->successful() ? 'success' : 'error',
                    null, $response->successful() ? null : 'HTTP_' . $response->status(),
                    null, $response->status(), $started);

                if (!$response->successful()) {
                    $err = $response->json();
                    throw new TextVerifiedException(
                        (string) ($err['errorCode'] ?? 'HTTP_' . $response->status()),
                        (string) ($err['errorDescription'] ?? 'TextVerified request failed (HTTP ' . $response->status() . ')'),
                    );
                }

                return $response->json();
            } catch (TextVerifiedException $e) {
                throw $e;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $this->logCall("{$method} {$path}", 'error', null, 'TIMEOUT', 'connection failed', null, $started);
                if ($attempt >= $maxAttempts) {
                    throw new TextVerifiedException('TIMEOUT', 'TextVerified connection failed');
                }
                usleep(400_000 * $attempt);
            }
        }
    }

    /** Safe call log — action, status, timing, error code. No tokens. */
    protected function logCall(
        string $action,
        string $status,
        ?string $verificationId = null,
        ?string $errorCode = null,
        ?string $message = null,
        ?int $httpStatus = null,
        ?float $started = null,
    ): void {
        try {
            ProviderLog::create([
                'provider_id' => $this->providerModel()?->id,
                'action' => "tv:{$action}",
                'activation_id' => $verificationId,
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

    protected function providerModel(): ?Provider
    {
        return $this->providerModel ??= Provider::where('slug', 'textverified')->first();
    }

    /**
     * KamVerify service slug → TextVerified service_name via the
     * provider_services mapping (facebook → e.g. 'facebook').
     */
    protected function serviceName(string $serviceSlug): ?string
    {
        return ProviderServiceModel::where('provider_id', $this->providerModel()?->id)
            ->where('is_active', true)
            ->whereHas('service', fn ($q) => $q->where('slug', $serviceSlug)->where('is_active', true))
            ->value('provider_service_code');
    }

    // =========================================================================
    // Reference data
    // =========================================================================

    public function getBalance(): float
    {
        if ($this->useMock) {
            return 100.00;
        }

        try {
            $data = $this->call('GET', '/api/pub/v2/account/me');
            return (float) ($data['currentBalance'] ?? $data['current_balance'] ?? 0);
        } catch (\Throwable $e) {
            $this->logCall('GET /account/me', 'error', null, 'BALANCE_FAIL', $e->getMessage());
            return 0;
        }
    }

    /** TextVerified verifications are US-only — by protocol, not assumption. */
    public function getCountries(): array
    {
        return [['id' => self::COUNTRY, 'name' => 'United States', 'code' => self::COUNTRY]];
    }

    public function getServices(): array
    {
        if ($this->useMock) {
            return [['slug' => 'facebook', 'name' => 'Facebook']];
        }

        try {
            $data = $this->call('GET', '/api/pub/v2/services', [
                'numberType' => 'MOBILE',
                'reservationType' => 'VERIFICATION',
            ]);
            $out = [];
            foreach ((array) ($data['data'] ?? $data) as $row) {
                if (!is_array($row)) continue;
                $name = $row['serviceName'] ?? $row['service_name'] ?? null;
                if ($name) {
                    $out[] = ['slug' => $name, 'name' => $row['description'] ?? $name];
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // Availability / pricing
    // =========================================================================

    /**
     * Live quote: inventory + pricing for the verification config.
     * Non-US or unmapped combinations are unsellable → [].
     */
    public function getAvailableNumbers(string $countryCode, string $serviceSlug): array
    {
        if (strtoupper($countryCode) !== self::COUNTRY) {
            return [];
        }
        if ($this->useMock) {
            return [['cost' => 0.75, 'count' => 1, 'available' => true]];
        }

        $serviceName = $this->serviceName($serviceSlug);
        if (!$serviceName) {
            return [];
        }

        try {
            $price = $this->call('POST', '/api/pub/v2/pricing/verifications', [], [
                'serviceName' => $serviceName,
                'capability' => 'SMS',
                'numberType' => 'MOBILE',
                'areaCode' => false,
                'carrier' => false,
            ]);
            $cost = (float) ($price['price'] ?? 0);
            if ($cost <= 0) {
                return [];
            }

            $count = 1; // pricing answered; treat as sellable unless inventory says 0
            try {
                $inv = $this->call('POST', '/api/pub/v2/inventory/verifications', [], [
                    'serviceName' => $serviceName,
                    'capability' => 'SMS',
                    'numberType' => 'MOBILE',
                ]);
                $count = (int) ($inv['quantity'] ?? $inv['count'] ?? $inv['available'] ?? 1);
            } catch (\Throwable $e) {
                // inventory endpoint optional — pricing alone gates the sale
            }

            return $count <= 0 ? [] : [['cost' => $cost, 'count' => $count, 'available' => true]];
        } catch (\Throwable $e) {
            $this->logCall('GET /available', 'error', null, 'QUOTE_FAIL', $e->getMessage());
            return [];
        }
    }

    /**
     * US-only provider — per-country stock ranking doesn't apply.
     * Null → callers fall back to the (single) US mapping.
     */
    public function availableCountryCounts(string $serviceSlug): ?array
    {
        return null;
    }

    // =========================================================================
    // Purchase — NEVER auto-retried; ambiguous failures reconcile first
    // =========================================================================

    public function purchaseNumber(string $countryCode, string $serviceSlug, array $options = []): array
    {
        if (strtoupper($countryCode) !== self::COUNTRY) {
            throw new TextVerifiedException('UNSUPPORTED_COUNTRY', 'TextVerified verifications are US-only');
        }

        $serviceName = $this->serviceName($serviceSlug);
        if (!$serviceName && !$this->useMock) {
            throw new TextVerifiedException('NO_MAPPING', 'No TextVerified mapping for this service');
        }

        $body = ['serviceName' => $serviceName, 'capability' => 'SMS'];
        if (!empty($options['max_price']) && $options['max_price'] > 0) {
            $body['maxPrice'] = round((float) $options['max_price'] * 1.10, 4);
        }

        $this->track('purchase');

        try {
            if ($this->useMock) {
                return $this->mockPurchaseNumber($serviceSlug);
            }
            // Response is an action to follow: {method:'GET', href:'/api/pub/v2/verifications/{id}'}
            $action = $this->call('POST', '/api/pub/v2/verifications', [], $body, retry: false);
            return $this->normalizeVerification($this->followAction($action));
        } catch (\Throwable $e) {
            // Ambiguous failure — the verification may exist server-side.
            // Reconcile before the caller can ever retry: adopt a fresh
            // pending verification for this service if one exists.
            $this->logCall('POST /verifications', 'error', null, 'AMBIGUOUS', $e->getMessage());
            $adopted = $this->findRecentVerification($serviceName);
            if ($adopted) {
                $this->track('purchase', 'success');
                return $this->normalizeVerification($adopted);
            }
            throw $e instanceof TextVerifiedException ? $e
                : new TextVerifiedException('SERVER_ERROR', $e->getMessage());
        }
    }

    /** Follow the action envelope returned by POST /verifications. */
    protected function followAction($action): array
    {
        if (is_array($action) && isset($action['id'])) {
            return $action; // already the verification payload
        }
        $href = is_array($action) ? ($action['href'] ?? null) : null;
        $method = strtoupper((string) (is_array($action) ? ($action['method'] ?? 'GET') : 'GET'));
        if (!$href) {
            throw new TextVerifiedException('PARSE_ERROR', 'Create response missing verification action');
        }
        $data = $this->call($method, $href);
        return is_array($data) ? $data : [];
    }

    /**
     * Reconcile after an ambiguous create: list recent verifications and
     * adopt one matching this service that is still pending. Returns the
     * verification payload or null — the caller must NOT retry blindly.
     */
    protected function findRecentVerification(string $serviceName): ?array
    {
        if ($this->useMock) {
            $cutoff = time() - self::ORPHAN_WINDOW_SECONDS;
            foreach ((array) Cache::get('tvmock:index', []) as $v) {
                $created = strtotime((string) ($v['createdAt'] ?? 'now'));
                if (($v['serviceName'] ?? '') === $serviceName
                    && ($v['state'] ?? '') === 'VERIFICATION_PENDING'
                    && $created >= $cutoff) {
                    return $v;
                }
            }
            return null;
        }

        try {
            $data = $this->call('GET', '/api/pub/v2/verifications');
            $items = $data['data'] ?? $data['items'] ?? (is_array($data) ? $data : []);
            $cutoff = time() - self::ORPHAN_WINDOW_SECONDS;

            foreach ((array) $items as $v) {
                if (!is_array($v)) continue;
                $state = strtoupper((string) ($v['state'] ?? ''));
                $svc = $v['serviceName'] ?? $v['service_name'] ?? '';
                $created = isset($v['createdAt']) ? strtotime((string) $v['createdAt'])
                    : (isset($v['created_at']) ? strtotime((string) $v['created_at']) : 0);

                if ($svc === $serviceName
                    && in_array($state, ['VERIFICATION_PENDING', 'VERIFICATION_REACTIVATED'], true)
                    && $created >= $cutoff) {
                    $id = $v['id'] ?? null;
                    return $id ? $this->call('GET', "/api/pub/v2/verifications/{$id}") : null;
                }
            }
            return null;
        } catch (\Throwable $e) {
            return null; // cannot prove absence → still refuse to repurchase
        }
    }

    protected function normalizeVerification(array $v): array
    {
        $id = $v['id'] ?? null;
        $number = $v['number'] ?? $v['phoneNumber'] ?? $v['phone'] ?? null;
        if (!$id || !$number) {
            throw new TextVerifiedException('PARSE_ERROR', 'Verification response missing id/number');
        }

        $this->track('purchase', 'success');
        return [
            'activation_id' => (string) $id,
            'phone_number' => $this->normalizePhone((string) $number),
            'cost' => isset($v['totalCost']) ? (float) $v['totalCost'] : (isset($v['total_cost']) ? (float) $v['total_cost'] : null),
            'status' => 'success',
        ];
    }

    protected function normalizePhone(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);
        return $digits !== '' ? '+' . $digits : $number;
    }

    // =========================================================================
    // Status / SMS
    // =========================================================================

    /**
     * Normalized to the shared status vocabulary the jobs understand:
     * STATUS_WAIT_CODE | STATUS_OK | STATUS_CANCEL — plus provider_state
     * carrying the raw TextVerified state for accounting decisions.
     */
    public function getActivationStatus(string $activationId): array
    {
        if ($this->useMock) {
            return $this->mockGetActivationStatus($activationId);
        }

        $v = $this->call('GET', "/api/pub/v2/verifications/{$activationId}");
        $state = strtoupper((string) ($v['state'] ?? 'UNKNOWN'));

        $status = match ($state) {
            'VERIFICATION_COMPLETED' => 'STATUS_OK',
            'VERIFICATION_PENDING', 'VERIFICATION_REACTIVATED' => 'STATUS_WAIT_CODE',
            'VERIFICATION_CANCELED', 'VERIFICATION_TIMED_OUT',
            'VERIFICATION_REFUNDED', 'VERIFICATION_REPORTED',
            'VERIFICATION_REUSED' => 'STATUS_CANCEL',
            default => 'UNKNOWN',
        };

        return ['status' => $status, 'provider_state' => $state];
    }

    public function getSms(string $activationId): array
    {
        if ($this->useMock) {
            return $this->mockGetSms($activationId);
        }

        $v = $this->call('GET', "/api/pub/v2/verifications/{$activationId}");
        $state = strtoupper((string) ($v['state'] ?? ''));
        $number = $this->normalizePhone((string) ($v['number'] ?? ''));

        // SMS only exist once the verification is completed
        if ($state !== 'VERIFICATION_COMPLETED' || $number === '') {
            return [];
        }

        try {
            $data = $this->call('GET', '/api/pub/v2/sms', [
                'to' => $number,
                'reservationType' => 'VERIFICATION',
            ]);
            $items = $data['data'] ?? $data['items'] ?? (is_array($data) ? $data : []);
            $out = [];
            foreach ((array) $items as $i => $sms) {
                if (!is_array($sms)) continue;
                $content = (string) ($sms['smsContent'] ?? $sms['sms_content'] ?? $sms['content'] ?? '');
                $code = $sms['parsedCode'] ?? $sms['parsed_code'] ?? null;
                $out[] = [
                    'id' => (string) ($sms['id'] ?? ($activationId . '-' . $i)),
                    'sender' => (string) ($sms['fromValue'] ?? $sms['from_value'] ?? $sms['sender'] ?? 'Unknown'),
                    'message' => $content !== '' ? $content
                        : ($code ? 'Verification code: ' . $code : 'Verification code received'),
                    'received_at' => $sms['createdAt'] ?? $sms['created_at'] ?? null,
                ];
            }
            if ($out) {
                $this->track('sms', 'success');
            }
            return $out;
        } catch (\Throwable $e) {
            return [[
                'id' => $activationId . '-otp',
                'sender' => 'SMS',
                'message' => 'Verification code received',
                'received_at' => now()->toIso8601String(),
            ]];
        }
    }

    // =========================================================================
    // Cancellation / refunds — state-aware; auto-refund documented
    // =========================================================================

    /**
     * Cancel a pending verification. TextVerified auto-refunds when no
     * SMS/call was received, so a successful cancel = cost released.
     * Terminal states throw the shared error codes the jobs branch on:
     *   COMPLETED → OTP_RECEIVED (order completes, never refunded)
     *   REFUNDED/TIMED_OUT → REFUNDED (provider already returned cost)
     *   CANCELED/REUSED/REPORTED → CANCELED (provider-side resolved)
     */
    public function cancelActivation(string $activationId): array
    {
        if ($this->useMock) {
            return $this->mockCancelActivation($activationId);
        }

        $this->track('cancel');
        $state = strtoupper((string) ($this->getActivationStatus($activationId)['provider_state'] ?? 'UNKNOWN'));

        switch ($state) {
            case 'VERIFICATION_COMPLETED':
                throw new TextVerifiedException('OTP_RECEIVED', 'Verification already completed — SMS received');
            case 'VERIFICATION_REFUNDED':
            case 'VERIFICATION_TIMED_OUT':
                throw new TextVerifiedException('REFUNDED', 'Provider already refunded this verification');
            case 'VERIFICATION_CANCELED':
            case 'VERIFICATION_REUSED':
            case 'VERIFICATION_REPORTED':
                throw new TextVerifiedException('CANCELED', 'Verification already resolved provider-side');
        }

        $this->call('POST', "/api/pub/v2/verifications/{$activationId}/cancel", retry: false);
        $this->track('cancel', 'success');

        return [
            'activation_id' => $activationId,
            'status' => 'cancelled',
            'provider_response' => 'CANCELED',
        ];
    }

    /** Same documented mechanism — cancel releases the charge. */
    public function requestRefund(string $activationId): array
    {
        return $this->cancelActivation($activationId);
    }

    /** Verifications need no readiness signal — polling is enough. */
    public function markReady(string $activationId): void
    {
    }

    public function isActive(): bool
    {
        return $this->enabled && $this->apiKey !== '' && $this->username !== '' && !$this->useMock;
    }

    public function getProviderName(): string
    {
        return 'TextVerified';
    }

    // =========================================================================
    // Mock — TEXTVERIFIED_MODE=mock (tests only, never production)
    // =========================================================================
    // Test hooks (Cache keys):
    //   tvmock:deliver:{id}   = code string → next getSms returns an SMS
    //   tvmock:orphan         = true → next purchase "fails" ambiguously
    //                          after secretly creating the verification
    //                          (tests the reconcile-before-retry path)
    // =========================================================================

    protected function mockStore(string $id, array $state): void
    {
        Cache::put("tvmock:ver:{$id}", $state, 3600);
        $index = Cache::get('tvmock:index', []);
        $index[$id] = $state;
        Cache::put('tvmock:index', $index, 3600);
    }

    protected function mockPurchaseNumber(string $serviceSlug): array
    {
        $id = 'TV-' . strtoupper(uniqid());
        $number = '+1212' . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
        $this->mockStore($id, [
            'id' => $id, 'number' => $number, 'serviceName' => $this->serviceName($serviceSlug) ?? 'facebook',
            'state' => 'VERIFICATION_PENDING', 'totalCost' => 0.75, 'createdAt' => now()->toIso8601String(),
        ]);

        if (Cache::pull('tvmock:orphan')) {
            // Server created it, but the response was lost — ambiguous.
            throw new TextVerifiedException('SERVER_ERROR', 'simulated ambiguous create failure');
        }

        $this->track('purchase', 'success');
        return ['activation_id' => $id, 'phone_number' => $number, 'cost' => 0.75, 'status' => 'success'];
    }

    protected function mockGetActivationStatus(string $activationId): array
    {
        $v = Cache::get("tvmock:ver:{$activationId}");
        $state = strtoupper((string) ($v['state'] ?? 'VERIFICATION_PENDING'));
        return [
            'status' => match ($state) {
                'VERIFICATION_COMPLETED' => 'STATUS_OK',
                'VERIFICATION_PENDING', 'VERIFICATION_REACTIVATED' => 'STATUS_WAIT_CODE',
                default => 'STATUS_CANCEL',
            },
            'provider_state' => $state,
        ];
    }

    protected function mockGetSms(string $activationId): array
    {
        $v = Cache::get("tvmock:ver:{$activationId}");
        if (!$v || ($v['state'] ?? '') !== 'VERIFICATION_COMPLETED') {
            return [];
        }
        $code = Cache::get("tvmock:deliver:{$activationId}", '483920');
        return [[
            'id' => "tvmock-{$activationId}-1",
            'sender' => '+13125550100',
            'message' => "Your Facebook confirmation code is {$code}",
            'received_at' => now()->toIso8601String(),
        ]];
    }

    protected function mockCancelActivation(string $activationId): array
    {
        $v = Cache::get("tvmock:ver:{$activationId}");
        $state = strtoupper((string) ($v['state'] ?? 'VERIFICATION_PENDING'));

        switch ($state) {
            case 'VERIFICATION_COMPLETED':
                throw new TextVerifiedException('OTP_RECEIVED', 'Verification already completed — SMS received');
            case 'VERIFICATION_REFUNDED':
            case 'VERIFICATION_TIMED_OUT':
                throw new TextVerifiedException('REFUNDED', 'Provider already refunded this verification');
            case 'VERIFICATION_CANCELED':
            case 'VERIFICATION_REUSED':
            case 'VERIFICATION_REPORTED':
                throw new TextVerifiedException('CANCELED', 'Verification already resolved provider-side');
        }

        $v['state'] = 'VERIFICATION_CANCELED';
        $this->mockStore($activationId, $v);

        return ['activation_id' => $activationId, 'status' => 'cancelled', 'provider_response' => 'CANCELED'];
    }
}
