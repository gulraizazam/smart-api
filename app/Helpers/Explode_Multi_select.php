<?php

declare(strict_types=1);
namespace App\Helpers;

class Explode_Multi_select
{
    public static function explode(string $locationids): array
    {
        return explode(',', $locationids);
    }
}
