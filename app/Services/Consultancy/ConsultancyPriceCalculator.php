<?php

declare(strict_types=1);

namespace App\Services\Consultancy;

use App\Models\Locations;

class ConsultancyPriceCalculator
{
    /**
     * Calculate consultancy price with tax based on treatment type.
     *
     * @return array{price: int, tax: int, tax_amt: int, settle_amount: int, outstanding: int}
     */
    public function calculate(
        float $basePrice,
        Locations $location,
        ?int $taxTreatmentTypeId,
        bool $isExclusive,
        float $cashPaid = 0,
        float $balance = 0,
    ): array {
        $taxExclusiveId = (int) config('constants.tax_is_exclusive');
        $taxBothId = (int) config('constants.tax_both');
        $taxPercentage = (float) $location->tax_percentage;

        $useExclusive = match ($taxTreatmentTypeId) {
            $taxExclusiveId => true,
            $taxBothId => $isExclusive,
            default => false,
        };

        if ($useExclusive) {
            $price = $basePrice;
            $tax = (int) ceil($price * ($taxPercentage / 100));
            $taxAmt = (int) ceil($price + $tax);
        } else {
            $taxAmt = $basePrice;
            $price = (int) ceil((100 * $taxAmt) / ($taxPercentage + 100));
            $tax = (int) ceil($taxAmt - $price);
        }

        $outstanding = max(0, (int) ($taxAmt - $cashPaid - $balance));

        $settleAmount = min(
            (int) ($price - $cashPaid),
            (int) $balance
        );

        return [
            'price' => (int) $price,
            'tax' => $tax,
            'tax_amt' => (int) $taxAmt,
            'settle_amount' => $settleAmount,
            'outstanding' => $outstanding,
        ];
    }

    /**
     * Apply a discount to the base price before tax calculation.
     */
    public function applyDiscount(
        float $basePrice,
        string $discountType,
        float $discountAmount,
    ): float {
        $fixedType = config('constants.Fixed');

        $netAmount = ($discountType === $fixedType)
            ? $basePrice - $discountAmount
            : $basePrice - ($basePrice * ($discountAmount / 100));

        return max(0.0, $netAmount);
    }
}
