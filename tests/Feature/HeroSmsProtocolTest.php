<?php

namespace Tests\Feature;

use App\Exceptions\HeroSmsException;
use App\Models\Provider;
use App\Models\ProviderCountry;
use App\Models\ProviderService;
use App\Services\Providers\HeroSmsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Protocol tests for the real HeroSMS API layer — SMS-Activate-compatible
 * handler_api.php contract. Http::fake intercepts every call; no live
 * requests are made.
 */
class HeroSmsProtocolTest extends TestCase
{
    use RefreshDatabase;

    protected function liveProvider(): HeroSmsProvider
    {
        config()->set('services.hero_sms.use_mock', false);
        config()->set('services.hero_sms.mode', 'production');
        config()->set('services.hero_sms.api_key', 'test-key-123');
        config()->set('services.hero_sms.base_url', 'https://hero-sms.com/stubs/handler_api.php');
        config()->set('services.hero_sms.retry_attempts', 2);

        return new HeroSmsProvider();
    }

    protected function seedMappings(): Provider
    {
        $provider = Provider::create([
            'name' => 'HeroSMS', 'slug' => 'herosms', 'is_active' => true,
        ]);
        $country = \App\Models\Country::create([
            'name' => 'United States', 'code' => 'US', 'dial_code' => '+1', 'is_active' => true,
        ]);
        $service = \App\Models\Service::create([
            'name' => 'WhatsApp', 'slug' => 'whatsapp', 'is_active' => true,
        ]);
        // Real HeroSMS uses NUMERIC country IDs and short service codes
        ProviderCountry::create([
            'provider_id' => $provider->id, 'country_id' => $country->id,
            'provider_country_code' => '187', 'is_active' => true,
        ]);
        ProviderService::create([
            'provider_id' => $provider->id, 'service_id' => $service->id,
            'provider_service_code' => 'wa', 'cost' => 0.50, 'is_active' => true,
        ]);

        return $provider;
    }

    public function test_get_balance_parses_access_balance(): void
    {
        Http::fake(['*' => Http::response('ACCESS_BALANCE:42.50', 200)]);

        $this->assertSame(42.50, $this->liveProvider()->getBalance());
    }

    public function test_purchase_sends_documented_params_and_normalizes_v2(): void
    {
        $this->seedMappings();
        config()->set('services.hero_sms.reseller_enabled', true);
        config()->set('services.hero_sms.reseller_param', 'userId');

        Http::fake(['*' => Http::response([
            'activationId' => '99123456',
            'phoneNumber' => '15550123456',
            'activationCost' => 0.42,
            'countryCode' => 187,
        ], 200)]);

        $result = $this->liveProvider()->purchaseNumber('US', 'whatsapp', [
            'user_id' => 77, 'max_price' => 0.50,
        ]);

        $this->assertSame('99123456', $result['activation_id']);
        $this->assertSame('15550123456', $result['phone_number']);
        $this->assertSame(0.42, $result['cost']);

        Http::assertSent(function ($request) {
            $q = [];
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $q);
            return $q['action'] === 'getNumberV2'
                && $q['api_key'] === 'test-key-123'
                && $q['service'] === 'wa'
                && $q['country'] === '187'
                && $q['userId'] === '77'
                && isset($q['maxPrice']);
        });
    }

    public function test_purchase_v1_fallback_parses_access_number(): void
    {
        $this->seedMappings();

        Http::fake(fn ($request) => str_contains($request->url(), 'getNumberV2')
            ? Http::response('BAD_ACTION', 200)
            : Http::response('ACCESS_NUMBER:555777:447700900123', 200));

        $result = $this->liveProvider()->purchaseNumber('US', 'whatsapp');

        $this->assertSame('555777', $result['activation_id']);
        $this->assertSame('447700900123', $result['phone_number']);
    }

    public function test_purchase_never_retried_on_server_error(): void
    {
        $this->seedMappings();

        // SERVER_ERROR could mean the purchase already executed — the
        // provider must NOT be retried nor fall back to V1 (double-buy
        // risk). Exactly one HTTP call, then a typed failure.
        Http::fake(['*' => Http::response('SERVER_ERROR', 200)]);

        try {
            $this->liveProvider()->purchaseNumber('US', 'whatsapp');
            $this->fail('Expected HeroSmsException');
        } catch (HeroSmsException $e) {
            $this->assertSame('SERVER_ERROR', $e->errorCode);
        }

        $this->assertSame(1, count(Http::recorded()));
    }

    public function test_malformed_v2_response_does_not_double_purchase(): void
    {
        $this->seedMappings();

        // V2 answered with garbage — the activation may exist server-side.
        // Must throw, must NOT call getNumber (would double-buy).
        Http::fake(['*' => Http::response('{"unexpected":true}', 200)]);

        try {
            $this->liveProvider()->purchaseNumber('US', 'whatsapp');
            $this->fail('Expected HeroSmsException');
        } catch (HeroSmsException $e) {
            $this->assertSame('PARSE_ERROR', $e->errorCode);
        }

        $this->assertSame(1, count(Http::recorded()));
    }

    public function test_unmapped_combination_throws_no_mapping(): void
    {
        $this->seedMappings();
        Http::fake(['*' => Http::response('OK', 200)]);

        try {
            $this->liveProvider()->purchaseNumber('ZZ', 'whatsapp');
            $this->fail('Expected HeroSmsException');
        } catch (HeroSmsException $e) {
            $this->assertSame('NO_MAPPING', $e->errorCode);
        }

        Http::assertNothingSent();
    }

    public function test_get_sms_returns_empty_while_waiting(): void
    {
        Http::fake(['*' => Http::response('STATUS_WAIT_CODE', 200)]);

        $this->assertSame([], $this->liveProvider()->getSms('123'));
    }

    public function test_get_sms_fetches_full_messages_on_status_ok(): void
    {
        Http::fake(fn ($request) => str_contains($request->url(), 'getAllSms')
            ? Http::response(['data' => [[
                'id' => 'm1', 'phoneFrom' => 'WhatsApp',
                'code' => '483920', 'text' => 'Your code: 483920', 'date' => '2026-09-20 10:00:00',
            ]]], 200)
            : Http::response('STATUS_OK:483920', 200));

        $sms = $this->liveProvider()->getSms('123');

        $this->assertCount(1, $sms);
        $this->assertSame('WhatsApp', $sms[0]['sender']);
        $this->assertSame('Your code: 483920', $sms[0]['message']);
    }

    public function test_get_sms_falls_back_to_bare_code(): void
    {
        Http::fake(fn ($request) => str_contains($request->url(), 'getAllSms')
            ? Http::response(['data' => []], 200)
            : Http::response('STATUS_OK:112233', 200));

        $sms = $this->liveProvider()->getSms('123');

        $this->assertCount(1, $sms);
        $this->assertStringContainsString('112233', $sms[0]['message']);
    }

    public function test_cancel_activation_sends_status_8(): void
    {
        Http::fake(['*' => Http::response('ACCESS_CANCEL', 200)]);

        $result = $this->liveProvider()->cancelActivation('99123456');

        $this->assertSame('cancelled', $result['status']);
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), 'action=setStatus')
            && str_contains($request->url(), 'status=8')
            && str_contains($request->url(), 'id=99123456'));
    }

    public function test_early_cancel_throws_typed_error(): void
    {
        Http::fake(['*' => Http::response('EARLY_CANCEL_DENIED', 200)]);

        try {
            $this->liveProvider()->cancelActivation('99123456');
            $this->fail('Expected HeroSmsException');
        } catch (HeroSmsException $e) {
            $this->assertSame('EARLY_CANCEL_DENIED', $e->errorCode);
        }
    }

    public function test_json_error_response_throws_typed_error(): void
    {
        Http::fake(['*' => Http::response([
            'title' => 'NO_BALANCE', 'details' => 'Insufficient balance',
        ], 200)]);

        try {
            $this->liveProvider()->getSms('123');
            $this->fail('Expected HeroSmsException');
        } catch (HeroSmsException $e) {
            $this->assertSame('NO_BALANCE', $e->errorCode);
        }
    }

    public function test_availability_uses_get_prices_with_mapped_codes(): void
    {
        $this->seedMappings();

        Http::fake(['*' => Http::response([
            '187' => ['wa' => ['cost' => 0.42, 'count' => 512, 'physicalCount' => 512]],
        ], 200)]);

        $numbers = $this->liveProvider()->getAvailableNumbers('US', 'whatsapp');

        $this->assertNotEmpty($numbers);
        $this->assertSame(0.42, $numbers[0]['cost']);
        $this->assertSame(512, $numbers[0]['count']);
    }

    public function test_provider_logs_are_written_without_secrets(): void
    {
        $this->seedMappings();
        Http::fake(['*' => Http::response('STATUS_WAIT_CODE', 200)]);

        $this->liveProvider()->getSms('act-1');

        $log = \App\Models\ProviderLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('getStatus', $log->action);
        $this->assertSame('success', $log->status);
        $this->assertStringNotContainsString('test-key-123', json_encode($log->toArray()));
    }

    public function test_mock_mode_never_calls_http(): void
    {
        config()->set('services.hero_sms.use_mock', true);
        config()->set('services.hero_sms.mode', 'mock');
        Http::fake();

        $provider = new HeroSmsProvider();
        $provider->getBalance();
        $provider->getSms('x');

        Http::assertNothingSent();
    }
}
