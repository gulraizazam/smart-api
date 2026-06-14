<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source for WhatsApp opt-out / opt-in keyword detection (Meta policy:
 * honor STOP). Matches the customer's WHOLE trimmed message, case-insensitively
 * — exact match only, so "please don't stop messaging me" or "start my laser
 * treatment" don't trip it.
 */
final class WhatsAppOptOut
{
    /** @var list<string> */
    private const OUT = ['stop', 'unsubscribe', 'opt out', 'optout', 'stop promotions'];

    /** @var list<string> */
    private const IN = ['start', 'unstop', 'resume', 'opt in', 'optin'];

    public static function isOptOut(?string $message): bool
    {
        return in_array(self::normalize($message), self::OUT, true);
    }

    public static function isOptIn(?string $message): bool
    {
        return in_array(self::normalize($message), self::IN, true);
    }

    private static function normalize(?string $message): string
    {
        return mb_strtolower(trim((string) $message));
    }
}
