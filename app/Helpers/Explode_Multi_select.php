<?php

declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: shehbaz@redsignal@biz
 * Date: 6/27/18
 * Time: 4:13 PM
 */

namespace App\Helpers;

class Explode_Multi_select
{
    public static function explode(string $locationids): array
    {
        return explode(',', $locationids);
    }
}
