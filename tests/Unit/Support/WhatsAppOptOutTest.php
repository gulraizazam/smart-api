<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\WhatsAppOptOut;
use PHPUnit\Framework\TestCase;

/**
 * Pins the opt-out / opt-in keyword detection: exact whole-message match only,
 * case/space-insensitive — so genuine sentences never trip it.
 */
class WhatsAppOptOutTest extends TestCase
{
    public function test_recognises_opt_out_keywords_case_and_space_insensitively(): void
    {
        foreach (['stop', 'STOP', '  Stop  ', 'unsubscribe', 'opt out', 'OPTOUT'] as $msg) {
            $this->assertTrue(WhatsAppOptOut::isOptOut($msg), "expected opt-out for: {$msg}");
        }
    }

    public function test_recognises_opt_in_keywords(): void
    {
        foreach (['start', 'START', 'resume', 'unstop', 'opt in'] as $msg) {
            $this->assertTrue(WhatsAppOptOut::isOptIn($msg), "expected opt-in for: {$msg}");
        }
    }

    public function test_does_not_trip_on_sentences_containing_the_words(): void
    {
        foreach (["please don't stop messaging me", 'start my laser treatment', 'can I stop by tomorrow?'] as $msg) {
            $this->assertFalse(WhatsAppOptOut::isOptOut($msg), "false positive opt-out: {$msg}");
            $this->assertFalse(WhatsAppOptOut::isOptIn($msg), "false positive opt-in: {$msg}");
        }
    }

    public function test_null_and_empty_are_neither(): void
    {
        $this->assertFalse(WhatsAppOptOut::isOptOut(null));
        $this->assertFalse(WhatsAppOptOut::isOptOut(''));
        $this->assertFalse(WhatsAppOptOut::isOptIn(null));
    }
}
