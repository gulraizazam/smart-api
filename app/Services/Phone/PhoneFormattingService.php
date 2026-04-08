<?php

declare(strict_types=1);

namespace App\Services\Phone;

use Illuminate\Support\Facades\Gate;

class PhoneFormattingService
{
    public static function cleanNumber(string|int|null $phoneNumber): string
    {
        $phoneNumber = (string) $phoneNumber;
        $phoneNumber = str_replace(' ', '', $phoneNumber);
        $phoneNumber = str_replace('-', '', $phoneNumber);

        return self::cleanCountryCodes(preg_replace('/[^0-9\-]/', '', $phoneNumber));
    }

    private static function cleanCountryCodes(string $phoneNumber): string
    {
        if ($phoneNumber[0] == '0') {
            return substr($phoneNumber, 1);
        }
        if ($phoneNumber[0] == '9' && $phoneNumber[1] == '2') {
            return substr($phoneNumber, 2);
        }
        if ($phoneNumber[0] == '0') {
            return substr($phoneNumber, 1);
        }

        return $phoneNumber;
    }

    public static function prepareNumber(string $phoneNumber): string
    {
        if ($phoneNumber[0] == '3' && (strlen($phoneNumber) >= 9 && strlen($phoneNumber) <= 11)) {
            return '92' . $phoneNumber;
        }

        return $phoneNumber;
    }

    public static function prepareNumber4Call(string $phoneNumber, int $type = 0): string
    {
        if (!Gate::allows('contact')) {
            return '***********';
        }

        if ($phoneNumber !== '') {
            if ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type === 0) {
                return '+92' . $phoneNumber;
            } elseif ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type === 1) {
                return '0' . $phoneNumber;
            }

            return $phoneNumber;
        }

        return $phoneNumber;
    }

    public static function prepareNumber4CallSMS(string $phoneNumber): string
    {
        if ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10) {
            return '+92' . $phoneNumber;
        }

        return $phoneNumber;
    }

    public static function clearnString(string $string): string
    {
        return str_replace([' ', '-', '+'], '', $string);
    }
}
