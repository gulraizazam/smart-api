<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\WhatsAppPhoneMatch;
use PHPUnit\Framework\TestCase;

/**
 * Pins the wa_id → phone_normalized candidate reconciliation: a wa_id carries
 * the country code (923001234567) but local phones are stored 0300… — so the
 * candidates must include both the national and the local (leading-0) forms.
 */
class WhatsAppPhoneMatchTest extends TestCase
{
    public function test_generates_local_and_national_candidate_forms(): void
    {
        $this->assertSame(
            ['923001234567', '3001234567', '03001234567'],
            WhatsAppPhoneMatch::candidates('923001234567', '92'),
        );
    }

    public function test_strips_non_digits_before_matching(): void
    {
        $this->assertSame(
            ['923001234567', '3001234567', '03001234567'],
            WhatsAppPhoneMatch::candidates('+92 300 1234567', '92'),
        );
    }

    public function test_a_wa_id_without_the_country_code_yields_only_itself(): void
    {
        $this->assertSame(['03001234567'], WhatsAppPhoneMatch::candidates('03001234567', '92'));
    }

    public function test_empty_wa_id_yields_no_candidates(): void
    {
        $this->assertSame([], WhatsAppPhoneMatch::candidates('', '92'));
    }
}
