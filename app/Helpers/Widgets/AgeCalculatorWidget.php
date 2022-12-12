<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Widgets;

class AgeCalculatorWidget
{
    /*
     * Make drop down for telecomprovider
     * @return: (mixed) $result
     */
    static function agecalculator($date)
    {
        list($year, $month, $day) = explode("-", $date);
        $year_diff = date("Y") - $year;
        $month_diff = date("m") - $month;
        $day_diff = date("d") - $day;
        if ($day_diff < 0 || $month_diff < 0) $year_diff--;
        return $year_diff;
    }
}