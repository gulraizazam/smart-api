<?php

declare(strict_types=1);

namespace App\Services\Reports\Enums;

enum MediumType: string
{
    case Web = 'web';
    case Print = 'print';
    case Pdf = 'pdf';
    case Excel = 'excel';
}
