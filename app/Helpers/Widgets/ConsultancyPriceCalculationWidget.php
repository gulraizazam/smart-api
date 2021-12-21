<?php

namespace App\Helpers\Widgets;
use Illuminate\Support\Facades\Config;

class ConsultancyPriceCalculationWidget
{
    /*
     * Function that filter discount for consultancy
     *
     * @param:  $location_id $service_id $account_id
     * @return: (mixed)
     */

    static function ConsultancyPriceCalculation($request, $price_for_calculation, $location_info, $cash, $balance)
    {
        if ($request->tax_treatment_type_id == Config::get('constants.tax_both')) {
            if ($request->is_exclusive_consultancy == '1') {
                $price = $price_for_calculation;
                $tax = ceil(($price * ($location_info->tax_percentage / 100)));
                $tax_amt = ceil(($price + (($price * $location_info->tax_percentage) / 100)));
            } else {
                $tax_amt = $price_for_calculation;
                $price = ceil(((100 * $tax_amt) / ($location_info->tax_percentage + 100)));
                $tax = ceil(($tax_amt - $price));
            }
        } else if ($request->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
            $price = $price_for_calculation;
            $tax = ceil(($price * ($location_info->tax_percentage / 100)));
            $tax_amt = ceil(($price + (($price * $location_info->tax_percentage) / 100)));
        } else {
            $tax_amt = $price_for_calculation;
            $price = ceil(((100 * $tax_amt) / ($location_info->tax_percentage + 100)));
            $tax = ceil(($tax_amt - $price));
        }

        $outstanding = $tax_amt - $cash - $balance;

        if ($outstanding < 0) {
            $outstanding = 0;
        }

        $settleamount_1 = $price - $cash;
        $settleamount = min($settleamount_1, $balance);

        $data = array(
            'price' => $price,
            'tax' => $tax,
            'tax_amt' => $tax_amt,
            'settleamount' => $settleamount,
            'outstanding' => $outstanding
        );
        return $data;
    }
}