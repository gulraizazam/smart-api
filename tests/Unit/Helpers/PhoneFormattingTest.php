<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Services\Phone\PhoneFormattingService;
use PHPUnit\Framework\TestCase;

/**
 * PhoneFormattingService is the single seam where every user-entered
 * phone number passes through on its way to the database (`createLead`,
 * `createPatient`, imports) and out of it again (SMS dispatch, click-to-
 * call). A regression here would split the same customer across two
 * differently-formatted rows, breaking the phone-uniqueness guard in
 * `LeadService::create()` and silently skewing Marketing attribution.
 *
 * The invariants pinned here:
 *
 *   1. `cleanNumber()` strips spaces, dashes, and any other non-digit
 *      character, then peels a leading `0` or `92` country code so the
 *      stored form is the 10-digit raw number (`3001234567`).
 *
 *   2. `prepareNumber()` is the INVERSE side — it stamps `92` onto a
 *      9-to-11 digit number starting with `3` (the outbound dial path).
 *      A number that's already prefixed is returned untouched.
 *
 *   3. `prepareNumber4CallSMS()` is the SMS-gateway-specific variant:
 *      `+92` prefix (E.164) only when the input is exactly 10 digits
 *      and starts with `3`. Other inputs pass through untouched so
 *      international numbers still dial.
 *
 *   4. `clearnString()` is a looser sibling that strips space/dash/+
 *      and is used in the click-to-call flow where the input may
 *      already carry a leading `+`.
 *
 * These are pure static functions; this test extends PHPUnit's
 * TestCase directly — no Laravel container boot — so the suite runs
 * in milliseconds. `prepareNumber4Call()` is covered separately (see
 * the dedicated Feature test) because it calls `Gate::allows('contact')`
 * which requires the container.
 */
class PhoneFormattingTest extends TestCase
{
    // =========================================================================
    // cleanNumber — strips formatting + peels country code
    // =========================================================================

    public function test_clean_number_strips_spaces_from_the_input(): void
    {
        $this->assertSame('3001234567', PhoneFormattingService::cleanNumber('300 123 4567'));
    }

    public function test_clean_number_strips_dashes_from_the_input(): void
    {
        $this->assertSame('3001234567', PhoneFormattingService::cleanNumber('300-123-4567'));
    }

    public function test_clean_number_strips_mixed_spaces_and_dashes(): void
    {
        $this->assertSame('3001234567', PhoneFormattingService::cleanNumber('300 - 123 - 4567'));
    }

    public function test_clean_number_strips_a_leading_zero_country_code(): void
    {
        // 0300... → 300... — the "local trunk prefix" is peeled so the
        // database only ever stores the national-subscriber form.
        $this->assertSame('3001234567', PhoneFormattingService::cleanNumber('03001234567'));
    }

    public function test_clean_number_strips_a_leading_92_country_code(): void
    {
        // 923001234567 → 3001234567 — matches the Pakistan dialing
        // plan the form-request validators expect.
        $this->assertSame('3001234567', PhoneFormattingService::cleanNumber('923001234567'));
    }

    public function test_clean_number_strips_non_digit_characters(): void
    {
        // Brackets, slashes, and the plus sign all get dropped before
        // the country-code peel runs.
        $this->assertSame('3001234567', PhoneFormattingService::cleanNumber('(0300) 123-4567'));
    }

    public function test_clean_number_accepts_integer_input(): void
    {
        // The signature is `string|int|null`; the helper casts ints
        // to string before cleaning. A DB-returned int must round-trip.
        $this->assertSame('3001234567', PhoneFormattingService::cleanNumber(3001234567));
    }

    public function test_clean_number_with_an_already_clean_number_is_idempotent(): void
    {
        $this->assertSame('3001234567', PhoneFormattingService::cleanNumber('3001234567'));
    }

    // =========================================================================
    // prepareNumber — inverse path, stamps 92 prefix for outbound dial
    // =========================================================================

    public function test_prepare_number_prefixes_92_on_a_10_digit_number_starting_with_3(): void
    {
        $this->assertSame('923001234567', PhoneFormattingService::prepareNumber('3001234567'));
    }

    public function test_prepare_number_prefixes_92_on_a_9_digit_number_starting_with_3(): void
    {
        // The range check is inclusive: 9 to 11 digits, starting with 3.
        $this->assertSame('92300123456', PhoneFormattingService::prepareNumber('300123456'));
    }

    public function test_prepare_number_prefixes_92_on_an_11_digit_number_starting_with_3(): void
    {
        $this->assertSame('9230012345678', PhoneFormattingService::prepareNumber('30012345678'));
    }

    public function test_prepare_number_leaves_a_number_not_starting_with_3_unchanged(): void
    {
        // A landline starting with 42 (Lahore) should pass through
        // untouched — the helper is mobile-only.
        $this->assertSame('4212345678', PhoneFormattingService::prepareNumber('4212345678'));
    }

    public function test_prepare_number_leaves_a_number_outside_the_length_window_unchanged(): void
    {
        // 8-digit → too short, 12-digit → too long. Both pass through.
        $this->assertSame('30012345', PhoneFormattingService::prepareNumber('30012345'));
        $this->assertSame('300123456789', PhoneFormattingService::prepareNumber('300123456789'));
    }

    // =========================================================================
    // prepareNumber4CallSMS — E.164 format for the SMS gateway
    // =========================================================================

    public function test_prepare_number_4_call_sms_prefixes_plus_92_for_a_10_digit_mobile(): void
    {
        $this->assertSame('+923001234567', PhoneFormattingService::prepareNumber4CallSMS('3001234567'));
    }

    public function test_prepare_number_4_call_sms_passes_through_a_non_mobile_untouched(): void
    {
        // A landline shouldn't get the mobile +92 stamp — the SMS
        // gateway would reject it. Helper must defer to the caller.
        $this->assertSame('4212345678', PhoneFormattingService::prepareNumber4CallSMS('4212345678'));
    }

    public function test_prepare_number_4_call_sms_passes_through_a_non_10_digit_mobile(): void
    {
        // 9-digit mobile slips the +92 stamp because the guard is a
        // strict equality on length. Documented on the spec side.
        $this->assertSame('300123456', PhoneFormattingService::prepareNumber4CallSMS('300123456'));
    }

    // =========================================================================
    // matchVariants — every equivalent stored form, for a patient lookup
    // =========================================================================

    public function test_match_variants_covers_canonical_leading_zero_and_country_code(): void
    {
        // The `phone` column isn't normalised consistently across the
        // shared DB. A lookup must match whichever form was stored — the
        // canonical 10-digit number, the raw leading-zero form, and the
        // 92 / +92 country-code forms — all from one cleaned key.
        $this->assertSame(
            ['3077168463', '03077168463', '923077168463', '+923077168463'],
            PhoneFormattingService::matchVariants('03077168463'),
        );
    }

    public function test_match_variants_is_input_format_agnostic(): void
    {
        // Same patient, four different ways an operator might type the
        // number → the SAME variant set every time. This is what lets the
        // SPA's load/lead search and the treatment-submit resolver agree.
        $expected = ['3077168463', '03077168463', '923077168463', '+923077168463'];

        $this->assertSame($expected, PhoneFormattingService::matchVariants('3077168463'));
        $this->assertSame($expected, PhoneFormattingService::matchVariants('0307-716-8463'));
        $this->assertSame($expected, PhoneFormattingService::matchVariants('+92 307 7168463'));
        $this->assertSame($expected, PhoneFormattingService::matchVariants('923077168463'));
    }

    public function test_match_variants_returns_empty_for_a_blank_number(): void
    {
        // An empty phone has no meaningful variants — the caller decides
        // what "no phone" means rather than matching an empty column.
        $this->assertSame([], PhoneFormattingService::matchVariants(''));
        $this->assertSame([], PhoneFormattingService::matchVariants(null));
    }

    // =========================================================================
    // normalizedVariants — digits-only forms for a phone_normalized lookup
    // =========================================================================

    public function test_normalized_variants_covers_canonical_leading_zero_and_country_code(): void
    {
        // Targets the digits-only `phone_normalized` column, so there is no
        // `+92` entry (its digits are the `92`+clean form already listed).
        $this->assertSame(
            ['3077168463', '03077168463', '923077168463'],
            PhoneFormattingService::normalizedVariants('03077168463'),
        );
    }

    public function test_normalized_variants_is_input_format_agnostic(): void
    {
        // Every way an operator might type the number — including spaced and
        // dashed forms — yields the SAME digit-variant set. This is what makes
        // getByPhone immune to formatting stored in the row.
        $expected = ['3077168463', '03077168463', '923077168463'];

        $this->assertSame($expected, PhoneFormattingService::normalizedVariants('3077168463'));
        $this->assertSame($expected, PhoneFormattingService::normalizedVariants('0307-716-8463'));
        $this->assertSame($expected, PhoneFormattingService::normalizedVariants('+92 307 7168463'));
        $this->assertSame($expected, PhoneFormattingService::normalizedVariants('923077168463'));
    }

    public function test_normalized_variants_returns_empty_for_a_blank_number(): void
    {
        $this->assertSame([], PhoneFormattingService::normalizedVariants(''));
        $this->assertSame([], PhoneFormattingService::normalizedVariants(null));
    }

    // =========================================================================
    // clearnString — loose variant used in click-to-call paths
    // =========================================================================

    public function test_clearn_string_strips_spaces_dashes_and_plus(): void
    {
        $this->assertSame('923001234567', PhoneFormattingService::clearnString('+92 300-123-4567'));
    }

    public function test_clearn_string_leaves_a_plain_number_unchanged(): void
    {
        $this->assertSame('3001234567', PhoneFormattingService::clearnString('3001234567'));
    }

    public function test_clearn_string_preserves_digits_other_than_the_stripped_set(): void
    {
        // Parentheses are NOT in the strip list — clearnString is
        // intentionally weaker than cleanNumber. The call-dialler
        // contract depends on this; don't "fix" the helper.
        $this->assertSame('(300)1234567', PhoneFormattingService::clearnString('(300) 1234567'));
    }
}
