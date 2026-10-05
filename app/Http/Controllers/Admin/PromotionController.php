<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Promotion;
use App\Services\AuditService;
use App\Services\CountryAvailabilityService;
use App\Services\PricingService;
use App\Services\PromotionService;
use App\Services\ProviderService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        protected PromotionService $promotionService,
        protected PricingService $pricingService,
        protected ProviderService $providerService,
        protected AuditService $auditService,
    ) {}

    public function index()
    {
        $promotions = Promotion::latest('id')->get()->map(function ($promo) {
            $orders = Order::where('promotion_id', $promo->id);
            $promo->stats = [
                'orders'  => (clone $orders)->count(),
                'revenue' => (int) (clone $orders)->sum('selling_price'),
                'facebook' => (clone $orders)->whereHas('service', fn ($q) => $q->where('slug', 'facebook'))->count(),
                'whatsapp' => (clone $orders)->whereHas('service', fn ($q) => $q->where('slug', 'whatsapp'))->count(),
                'telegram' => (clone $orders)->whereHas('service', fn ($q) => $q->where('slug', 'telegram'))->count(),
            ];
            return $promo;
        });

        return view('admin.promotions.index', [
            'promotions' => $promotions,
            'live'       => $this->promotionService->current(),
        ]);
    }

    public function create()
    {
        return view('admin.promotions.create', [
            'defaults' => [
                'name'              => 'KamVerify Weekly Promo',
                'facebook_price'    => 750,
                'whatsapp_us_price' => 1500,
                'telegram_price'    => 800,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:120',
            'starts_at'         => 'required|date',
            'facebook_price'    => 'required|integer|min:1|max:10000000',
            'whatsapp_us_price' => 'required|integer|min:1|max:10000000',
            'telegram_price'    => 'required|integer|min:1|max:10000000',
            'whatsapp_discount' => 'nullable|integer|min:0|max:10000000',
        ]);

        $startsAt = Carbon::parse($validated['starts_at'], config('app.timezone'));

        $promotion = Promotion::create([
            'name'       => $validated['name'],
            'is_enabled' => true,
            'starts_at'  => $startsAt,
            'ends_at'    => $startsAt->copy()->addDays(7), // exactly one week
            'config'     => [
                'facebook_price'    => (int) $validated['facebook_price'],
                'whatsapp_us_price' => (int) $validated['whatsapp_us_price'],
                'telegram_price'    => (int) $validated['telegram_price'],
                'whatsapp_discount' => $request->filled('whatsapp_discount')
                    ? (int) $validated['whatsapp_discount'] : null,
            ],
        ]);

        // Snapshot the WhatsApp discount from a live USA quote when the
        // admin didn't supply one; non-US countries reuse this absolute
        // XAF discount off their own normal price.
        if ($promotion->price('whatsapp_discount') === null) {
            $this->promotionService->computeWhatsappDiscount($promotion);
        }

        $this->auditService->log('promotion.create', $promotion, null, [
            'starts_at' => $promotion->starts_at,
            'ends_at'   => $promotion->ends_at,
            'config'    => $promotion->config,
        ]);

        $message = $promotion->fresh()->price('whatsapp_discount') !== null
            ? 'Promotion created — runs for exactly 7 days from the start time.'
            : 'Promotion created, but the USA WhatsApp discount could not be auto-calculated (provider unreachable) — edit the promotion to set it.';

        return redirect()->route('admin.promotions.index')->with('success', $message);
    }

    public function toggle(Promotion $promotion)
    {
        $promotion->update(['is_enabled' => !$promotion->is_enabled]);

        $this->auditService->log('promotion.toggle', $promotion,
            ['is_enabled' => !$promotion->is_enabled], ['is_enabled' => $promotion->is_enabled]);

        return back()->with('success', $promotion->is_enabled ? 'Promotion enabled.' : 'Promotion disabled.');
    }

    /**
     * Live margin check: for every promoted service × routed country,
     * compare the promo target against the provider cost and flag the
     * combinations where the cost floor clamps the price (zero-margin,
     * never negative). On-demand — it performs provider calls per combo.
     */
    public function coverage(Promotion $promotion)
    {
        $rows = [];
        $services = \App\Models\Service::whereIn('slug', PromotionService::SERVICES)->get();

        foreach ($services as $service) {
            $data = app(CountryAvailabilityService::class)->forServiceAdmin($service);
            foreach ($data['countries'] as $c) {
                $country = \App\Models\Country::find($c['id']);
                if (!$country || !$c['enabled']) {
                    continue;
                }
                try {
                    $providerModel = $this->providerService->providerFor($service, $country);
                    if (!$providerModel) {
                        continue;
                    }
                    $numbers = $this->providerService->getProviderForModel($providerModel)
                        ->getAvailableNumbers($country->code, $service->slug);
                    if (empty($numbers)) {
                        continue;
                    }
                    $cost = (float) $numbers[0]['cost'];
                    $normal = $this->pricingService->calculateSellingPrice($cost, $country, $service);

                    // Same math PromotionService::quote applies, run
                    // against this promotion even if it isn't live yet.
                    $target = match ($service->slug) {
                        'facebook' => $promotion->price('facebook_price'),
                        'telegram' => $promotion->price('telegram_price'),
                        'whatsapp' => strtoupper($country->code) === 'US'
                            ? $promotion->price('whatsapp_us_price')
                            : (($promotion->price('whatsapp_discount') ?? 0) > 0
                                ? $normal - $promotion->price('whatsapp_discount') : null),
                        default => null,
                    };
                    if ($target === null) {
                        continue;
                    }
                    $floor = usdToXaf($cost);
                    $rows[] = [
                        'service'  => $service->name,
                        'country'  => $c['name'],
                        'code'     => $c['code'],
                        'provider' => $providerModel->name,
                        'normal'   => $normal,
                        'promo'    => min(max($target, $floor), $normal),
                        'target'   => $target,
                        'floor'    => $floor,
                        'clamped'  => $target < $floor,
                    ];
                } catch (\Throwable $e) {
                    continue; // provider unreachable for this combo — skip
                }
            }
        }

        return view('admin.promotions.coverage', compact('promotion', 'rows'));
    }
}
