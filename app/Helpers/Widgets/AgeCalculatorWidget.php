<?php

declare(strict_types=1);
namespace App\Helpers\Widgets;

class AgeCalculatorWidget
{
    /*
     * Make drop down for telecomprovider
     * @return: (mixed) $result
     */
    public static function agecalculator($date)
    {
        [$year, $month, $day] = explode('-', $date);
        $year_diff = date('Y') - $year;
        $month_diff = date('m') - $month;
        $day_diff = date('d') - $day;
        if ($day_diff < 0 || $month_diff < 0) {
            $year_diff--;
        }

        return $year_diff;
    }
}
