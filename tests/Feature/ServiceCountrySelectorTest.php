<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\ProviderCountry;
use App\Models\Service;
use App\Models\ServiceCountry;
use App\Services\CountryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsMarketplace;
use Tests\TestCase;

class ServiceCountrySelectorTest extends TestCase
{
    use RefreshDatabase, SeedsMarketplace;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_countries_endpoint_lists_only_mapped_countries(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();

        // An active country with NO provider mapping must not appear
        Country::create(['name' => 'Nowhere', 'code' => 'NW', 'dial_code' => '+0', 'is_active' => true]);

        $response = $this->actingAs($this->customer())
            ->getJson("/orders/countries?service_id={$service->id}");

        $response->assertOk()
            ->assertJson(['count' => 1, 'service' => ['slug' => 'whatsapp']]);
        $countries = $response->json('countries');
        $this->assertCount(1, $countries);
        $this->assertSame('United States', $countries[0]['name']);
        $this->assertSame('🇺🇸', $countries[0]['flag']);
    }

    public function test_disabled_country_is_excluded_and_pinned_popular(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $cm = Country::create(['name' => 'Cameroon', 'code' => 'CM', 'dial_code' => '+237', 'is_active' => true]);
        ProviderCountry::create([
            'provider_id' => $provider->id, 'country_id' => $cm->id,
            'provider_country_code' => '39', 'is_active' => true,
        ]);

        // Pin CM popular, disable US
        ServiceCountry::create(['service_id' => $service->id, 'country_id' => $cm->id, 'is_popular' => true, 'popular_sort' => 1]);
        ServiceCountry::create(['service_id' => $service->id, 'country_id' => $country->id, 'is_enabled' => false]);

        $data = app(CountryAvailabilityService::class)->forService($service);

        $this->assertSame(1, $data['count']); // US excluded
        $this->assertSame([$cm->id], $data['popular']);
        $this->assertSame('Cameroon', $data['countries'][0]['name']);
        $this->assertTrue($data['countries'][0]['popular']);
    }

    public function test_auto_popular_fallback_when_none_pinned(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();

        $data = app(CountryAvailabilityService::class)->forService($service);

        // No manual pins → auto-promoted (up to 6) from available countries
        $this->assertContains($country->id, $data['popular']);
    }

    public function test_non_customer_service_returns_422(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $service->update(['customer_enabled' => false]);

        $this->actingAs($this->customer())
            ->getJson("/orders/countries?service_id={$service->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_pin_reorder_and_disable_countries(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $admin = $this->admin();

        // Pin US as popular
        $this->actingAs($admin, 'admin')
            ->put(route('admin.service-countries.update', [$service, $country]), ['action' => 'toggle_popular'])
            ->assertSessionHas('success');
        $this->assertTrue(ServiceCountry::where('country_id', $country->id)->first()->is_popular);

        // Reorder a non-popular country is rejected cleanly
        $other = Country::create(['name' => 'Cameroon', 'code' => 'CM', 'dial_code' => '+237', 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->put(route('admin.service-countries.update', [$service, $other]), ['action' => 'move_up'])
            ->assertSessionHas('error');

        // Disable US for this service
        $this->actingAs($admin, 'admin')
            ->put(route('admin.service-countries.update', [$service, $country]), ['action' => 'toggle_enabled'])
            ->assertSessionHas('success');

        $row = ServiceCountry::where('country_id', $country->id)->first();
        $this->assertFalse($row->is_enabled);
        $this->assertFalse($row->is_popular); // auto-unpinned

        $this->assertDatabaseHas('audit_logs', ['action' => 'pricing.country_service.update']);
    }

    public function test_customer_cannot_manage_service_countries(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();

        $response = $this->actingAs($this->customer())
            ->put(route('admin.service-countries.update', [$service, $country]), ['action' => 'toggle_popular']);

        $this->assertNotSame(200, $response->status());
        $this->assertDatabaseMissing('service_countries', ['country_id' => $country->id]);
    }

    public function test_purchase_still_works_after_selector_change(): void
    {
        [$provider, $country, $service] = $this->seedMarketplace();
        $user = $this->customer();

        $this->actingAs($user)->post('/orders', [
            'service_id' => $service->id,
            'country_id' => $country->id,
        ]);

        $order = \App\Models\Order::latest('id')->first();
        $this->assertSame('waiting_for_sms', $order->status);
        $this->assertNotNull($order->phone_number);
    }
}
