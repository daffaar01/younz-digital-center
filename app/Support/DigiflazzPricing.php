<?php

namespace App\Support;

class DigiflazzPricing
{
    public function prepaidSellingPrice(
        int $costPrice,
        ?string $category = null,
        ?string $productName = null,
        ?string $sku = null,
    ): int
    {
        $costPrice = max(0, $costPrice);
        $override = $this->prepaidPriceOverride($sku);
        if ($override !== null) {
            return max($costPrice, $override);
        }

        $pricingBase = $this->usesProductNominal($category)
            ? ($this->extractProductNominal($productName) ?? $costPrice)
            : $costPrice;
        $isPln = mb_strtolower(trim((string) $category)) === 'pln';
        if ($isPln && $pricingBase >= 500000) {
            $markup = 6000;
        } elseif ($isPln && $pricingBase >= 100000) {
            $markup = 5000;
        } else {
            $highValueThreshold = max(0, (int) config('services.digiflazz.prepaid_high_value_threshold', 200000));
            $markup = $pricingBase >= $highValueThreshold
                ? max(0, (int) config('services.digiflazz.prepaid_high_value_markup', 5000))
                : max(0, (int) config('services.digiflazz.prepaid_markup', 2000));
        }
        $roundingUnit = max(1, (int) config('services.digiflazz.prepaid_rounding_unit', 1000));
        $roundedPrice = intdiv($pricingBase + $markup, $roundingUnit) * $roundingUnit;

        return max($pricingBase, $roundedPrice);
    }

    public function postpaidAdminFee(): int
    {
        return max(0, (int) config('services.digiflazz.postpaid_admin_fee', 5500));
    }

    private function usesProductNominal(?string $category): bool
    {
        return in_array(mb_strtolower(trim((string) $category)), ['pulsa', 'pln'], true);
    }

    private function extractProductNominal(?string $productName): ?int
    {
        if (! is_string($productName)
            || preg_match('/(\d{1,3}(?:[.,]\d{3})+|\d+)\s*$/u', trim($productName), $matches) !== 1
        ) {
            return null;
        }

        $nominal = (int) str_replace(['.', ','], '', $matches[1]);

        return $nominal > 0 ? $nominal : null;
    }

    private function prepaidPriceOverride(?string $sku): ?int
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return null;
        }

        foreach (preg_split('/\s*,\s*/', trim((string) config('services.digiflazz.prepaid_price_overrides'))) ?: [] as $entry) {
            [$candidate, $price] = array_pad(explode(':', $entry, 2), 2, null);
            $price = trim((string) $price);
            if (strcasecmp(trim((string) $candidate), $sku) === 0 && ctype_digit($price)) {
                return max(0, (int) $price);
            }
        }

        return null;
    }
}
