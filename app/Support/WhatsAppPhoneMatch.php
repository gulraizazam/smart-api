<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reconciles a WhatsApp wa_id (E.164 digits, no '+', e.g. 923001234567) with
 * the phone forms we store locally. Patients save `phone_normalized` as
 * digits-only, but typically in national form with a leading 0 (03001234567)
 * — so an exact match needs the country code stripped and the 0 re-added.
 *
 * Returns the exact candidate strings to match against `phone_normalized`
 * (whereIn → uses the index; no leading-wildcard LIKE).
 */
final class WhatsAppPhoneMatch
{
    /** @return list<string> */
    public static function candidates(string $waId, string $countryCode): array
    {
        $digits = preg_replace('/\D/', '', $waId) ?? '';
        if ($digits === '') {
            return [];
        }

        $forms = [$digits];                       // 923001234567 (as Meta sends it)

        if ($countryCode !== '' && str_starts_with($digits, $countryCode)) {
            $national = substr($digits, strlen($countryCode));
            if ($national !== '') {
                $forms[] = $national;             // 3001234567
                $forms[] = '0'.$national;         // 03001234567 (local form)
            }
        }

        return array_values(array_unique($forms));
    }
}
