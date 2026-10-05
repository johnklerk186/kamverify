<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Promotion;
use App\Models\Service;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for promotional pricing.
 *
 * The promotion is a temporary layer over PricingService: the normal price
 * is always computed first, then a promo target is applied per service —
 * never below the provider cost (no negative-margin sales) and never above
 * the normal price. When no promotion is active, quotes pass through
 * unchanged.
 *
 * WhatsApp uses an absolute XAF discount snapshot: at promotion creation
 * the discount is computed as (normal USA WhatsApp price − promo USA price)
 * from a live provider quote and stored in config, so every other WhatsApp
 * country gets the same absolute discount off its own normal price.
 */
class PromotionService
{
    public const SERVICES = ['facebook', 'whatsapp', 'telegram'];

    public function __construct(
        protected PricingService $pricingService,
        protected ProviderService $providerService,
    ) {}

    /**
     * The promotion in effect right now, or null. Enabling/disabling and
     * expiry are handled purely by is_enabled + the datetime window — no
     * scheduled job is required for normal pricing to return.
     */
    public function current(): ?Promotion
    {
        $now = now();

        return Promotion::where('is_enabled', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->latest('id')
            ->first();
    }

    /**
     * Effective customer price for a service+country purchase.
     *
     * Returns price (what the wallet is charged), the normal price and the
     * discount when a promotion actually lowered the price, plus the
     * promotion id so the order can record its provenance.
     */
    public function quote(Service $service, ?Country $country, float $providerCost): array
    {
        $normal = $this->pricingService->calculateSellingPrice($providerCost, $country, $service);
        $promotion = $this->current();

        $plain = [
            'price'          => $normal,
            'normal_price'   => null,
            'discount_amount'=> null,
            'promotion_id'   => null,
        ];

        if (!$promotion) {
            return $plain;
        }

        $target = $this->promoTarget($promotion, $service, $country, $normal);
        if ($target === null) {
            return $plain;
        }

        // Never sell below provider cost (floor) and never above the
        // normal price (cap) — a combination whose cost exceeds the promo
        // target simply keeps its normal price instead of making a loss.
        $price = min(max($target, usdToXaf($providerCost)), $normal);
        $discount = $normal - $price;

        if ($discount <= 0) {
            return $plain;
        }

        return [
            'price'           => $price,
            'normal_price'    => $normal,
            'discount_amount' => $discount,
            'promotion_id'    => $promotion->id,
        ];
    }

    /**
     * Promo target in XAF for a service+country, or null when the
     * promotion doesn't cover it.
     */
    protected function promoTarget(Promotion $promotion, Service $service, ?Country $country, int $normal): ?int
    {
        return match ($service->slug) {
            'facebook' => $promotion->price('facebook_price'),
            'telegram' => $promotion->price('telegram_price'),
            'whatsapp' => $this->whatsappTarget($promotion, $country, $normal),
            default    => null,
        };
    }

    protected function whatsappTarget(Promotion $promotion, ?Country $country, int $normal): ?int
    {
        if ($country && strtoupper($country->code) === 'US') {
            return $promotion->price('whatsapp_us_price');
        }

        $discount = $promotion->price('whatsapp_discount');
        if ($discount === null) {
            $discount = $this->computeWhatsappDiscount($promotion);
        }

        return $discount === null ? null : $normal - $discount;
    }

    /**
     * normal USA WhatsApp price − promo USA price, computed from a live
     * provider quote and persisted on the promotion. Returns null when it
     * can't be determined (provider down / service unmapped) — the promo
     * then silently skips non-US WhatsApp rather than guessing.
     */
    public function computeWhatsappDiscount(Promotion $promotion): ?int
    {
        $stored = $promotion->price('whatsapp_discount');
        if ($stored !== null) {
            return $stored;
        }

        $usPrice = $promotion->price('whatsapp_us_price');
        $service = Service::where('slug', 'whatsapp')->first();
        $country = Country::where('code', 'US')->first();
        if ($usPrice === null || !$service || !$country) {
            return null;
        }

        try {
            $providerModel = $this->providerService->providerFor($service, $country);
            if (!$providerModel) {
                return null;
            }
            $numbers = $this->providerService->getProviderForModel($providerModel)
                ->getAvailableNumbers('US', 'whatsapp');
            if (empty($numbers)) {
                return null;
            }

            $normal = $this->pricingService->calculateSellingPrice((float) $numbers[0]['cost'], $country, $service);
            $discount = max(0, $normal - $usPrice);

            $promotion->update(['config' => array_merge($promotion->config ?? [], ['whatsapp_discount' => $discount])]);

            return $discount;
        } catch (\Throwable $e) {
            Log::warning('PromotionService: WhatsApp discount could not be computed', [
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Headline figures for banners/admin — reads config only, no live
     * provider calls.
     */
    public function summary(?Promotion $promotion = null): ?array
    {
        $promotion = $promotion ?? $this->current();
        if (!$promotion) {
            return null;
        }

        return [
            'id'                 => $promotion->id,
            'name'               => $promotion->name,
            'status'             => $promotion->status(),
            'starts_at'          => $promotion->starts_at,
            'ends_at'            => $promotion->ends_at,
            'ends_at_iso'        => $promotion->ends_at?->toIso8601String(),
            'facebook_price'     => $promotion->price('facebook_price'),
            'whatsapp_us_price'  => $promotion->price('whatsapp_us_price'),
            'whatsapp_discount'  => $promotion->price('whatsapp_discount'),
            'telegram_price'     => $promotion->price('telegram_price'),
        ];
    }
}
