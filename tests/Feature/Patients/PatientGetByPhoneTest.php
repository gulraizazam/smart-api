<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Patients;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Patients::getByPhone powers the consultation/treatment phone lookup. Stored
 * phones are inconsistent — most are normalized ("3110088221") but a large
 * legacy/walk-in set kept the leading 0 ("03110088221"). The lookup must find
 * the patient regardless of whether the 0 (or country code) is present on the
 * input OR in storage — otherwise a real patient reads as "no registered
 * patient with this phone" on the treatment screen.
 */
class PatientGetByPhoneTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_finds_patient_stored_with_leading_zero_for_any_input_format(): void
    {
        $patient = Patients::factory()->create(['phone' => '03110088221']);
        // The broken-data shape: stored WITH the leading 0.
        $this->assertSame('03110088221', (string) DB::table('users')->where('id', $patient->id)->value('phone'));

        foreach (['3110088221', '03110088221', '923110088221', '+92 311 0088221'] as $input) {
            $this->assertSame(
                $patient->id,
                Patients::getByPhone($input)?->id,
                "Input '{$input}' must resolve to the patient stored as 03110088221.",
            );
        }
    }

    public function test_finds_patient_stored_normalized_for_any_input_format(): void
    {
        $patient = Patients::factory()->create(['phone' => '3215044424']);

        foreach (['3215044424', '03215044424', '923215044424'] as $input) {
            $this->assertSame($patient->id, Patients::getByPhone($input)?->id, "Input '{$input}' must resolve.");
        }
    }

    public function test_finds_patient_stored_with_country_code_prefix(): void
    {
        // The case that distinguishes matchVariants() from a plain cleaned /
        // leading-0 match: the patient ROW kept the 92 country code
        // ("923110088221"). getByPhone must still resolve it from a bare local
        // number — matchVariants generates the "92"+clean stored form, which a
        // (phone = clean OR '0'.clean) lookup would miss. Pins the merge
        // resolution that kept matchVariants over the narrower variant.
        $patient = Patients::factory()->create(['phone' => '923110088221']);

        foreach (['3110088221', '03110088221', '923110088221', '+92 311 0088221'] as $input) {
            $this->assertSame(
                $patient->id,
                Patients::getByPhone($input)?->id,
                "Input '{$input}' must resolve to the patient stored as 923110088221.",
            );
        }
    }

    public function test_finds_patient_stored_with_a_formatted_phone(): void
    {
        // The exact duplicate-causing gap: a patient row saved WITH formatting
        // (spaces). The clean-variant whereIn on the raw `phone` column missed
        // it entirely — a repeat (often cross-branch) visit then minted a
        // DUPLICATE patient. The phone_normalized match resolves it regardless
        // of how the stored value is formatted.
        $patient = Patients::factory()->create(['phone' => '0311 0088 221']);
        $this->assertSame(
            '0311 0088 221',
            (string) DB::table('users')->where('id', $patient->id)->value('phone'),
        );

        foreach (['3110088221', '03110088221', '923110088221', '+923110088221', '0311-0088-221'] as $input) {
            $this->assertSame(
                $patient->id,
                Patients::getByPhone($input)?->id,
                "Input '{$input}' must resolve to the patient stored as '0311 0088 221'.",
            );
        }
    }

    public function test_returns_null_for_unknown_phone(): void
    {
        Patients::factory()->create(['phone' => '3215044424']);

        $this->assertNull(Patients::getByPhone('3009998887'));
    }

    public function test_scopes_the_lookup_to_the_given_account(): void
    {
        // A phone maps to exactly one patient WITHIN an account. When an
        // account_id is supplied the lookup must honour it — previously the
        // parameter was accepted but ignored, so a number could resolve a
        // patient from a different clinic account.
        $patient = Patients::factory()->create(['phone' => '3215044424', 'account_id' => 1]);

        // Scoped to a foreign account → invisible (this would have wrongly
        // resolved before the fix).
        $this->assertNull(Patients::getByPhone('3215044424', 999));

        // Scoped to its own account → resolves.
        $this->assertSame($patient->id, Patients::getByPhone('3215044424', 1)?->id);

        // No account given (false) → back-compat, account is not constrained.
        $this->assertSame($patient->id, Patients::getByPhone('3215044424')?->id);
    }
}
