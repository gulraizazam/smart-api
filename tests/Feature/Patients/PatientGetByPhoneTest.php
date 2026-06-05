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

    public function test_returns_null_for_unknown_phone(): void
    {
        Patients::factory()->create(['phone' => '3215044424']);

        $this->assertNull(Patients::getByPhone('3009998887'));
    }
}
