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

    /**
     * Equivalent stored forms of a number, for a patient/lead lookup.
     *
     * The `phone` column is NOT normalised consistently across the shared
     * DB: the vast majority of rows are canonical (`cleanNumber` form, e.g.
     * `3077168463`), but a subset of consultation-created rows kept the raw
     * leading zero (`03077168463`) or a country code (`923077168463` /
     * `+923077168463`). A lookup that only matches the canonical form misses
     * those rows and wrongly reports "patient not registered".
     *
     * Given any input, return the set of equivalent stored forms so the
     * lookup matches the patient regardless of which format was saved.
     * Returns `[]` for an empty/blank number (the caller decides what an
     * empty phone means). Equality/`whereIn` keeps the `phone` index usable
     * — no leading-wildcard scan.
     *
     * @return array<int, string>
     */
    public static function matchVariants(string|int|null $phoneNumber): array
    {
        $clean = self::cleanNumber($phoneNumber);
        if ($clean === '') {
            return [];
        }

        return array_values(array_unique([
            $clean,         // 3077168463   — canonical (cleanNumber form)
            '0' . $clean,   // 03077168463  — raw leading zero
            '92' . $clean,  // 923077168463 — country code
            '+92' . $clean, // +923077168463 — E.164 country code
        ]));
    }

    private static function cleanCountryCodes(string $phoneNumber): string
    {
        // Guard the index access — an empty/short string (e.g. a blank
        // phone field) would otherwise raise an "uninitialized string
        // offset" warning. Behaviour for every real number is unchanged.
        if ($phoneNumber === '') {
            return '';
        }
        if ($phoneNumber[0] == '0') {
            return substr($phoneNumber, 1);
        }
        if ($phoneNumber[0] == '9' && ($phoneNumber[1] ?? '') == '2') {
            return substr($phoneNumber, 2);
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
