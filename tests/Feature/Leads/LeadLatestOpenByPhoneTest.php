<?php

declare(strict_types=1);

namespace Tests\Feature\Leads;

use App\Models\Leads;
use App\Models\LeadStatuses;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Leads::latestOpenByPhone powers the consultation lookup's lead fallback —
 * when a phone has no patient row, we still attach the consultation to the
 * existing lead instead of minting a duplicate. Mirrors PatientGetByPhoneTest:
 * stored phones are inconsistent (normalized vs leading-zero vs 92 country
 * code), so the match must be variant-tolerant. "Open" = not junk; converted
 * and null-status leads stay eligible; the latest (by id) wins.
 */
class LeadLatestOpenByPhoneTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_finds_lead_for_any_input_format(): void
    {
        // Stored with the leading zero (the broken-data shape).
        $lead = Leads::factory()->create(['phone' => '03110088221', 'account_id' => 1]);

        foreach (['3110088221', '03110088221', '923110088221', '+92 311 0088221'] as $input) {
            $this->assertSame(
                $lead->id,
                Leads::latestOpenByPhone($input, 1)?->id,
                "Input '{$input}' must resolve to the lead stored as 03110088221.",
            );
        }
    }

    public function test_finds_lead_stored_with_country_code_prefix(): void
    {
        $lead = Leads::factory()->create(['phone' => '923110088221', 'account_id' => 1]);

        foreach (['3110088221', '03110088221', '923110088221'] as $input) {
            $this->assertSame($lead->id, Leads::latestOpenByPhone($input, 1)?->id, "Input '{$input}' must resolve.");
        }
    }

    public function test_excludes_junk_leads_but_keeps_converted(): void
    {
        $junk = LeadStatuses::factory()->create(['account_id' => 1, 'is_junk' => 1]);
        $converted = LeadStatuses::factory()->create(['account_id' => 1, 'is_converted' => 1]);

        // A junk lead on the phone must be ignored…
        Leads::factory()->create(['phone' => '3215044424', 'account_id' => 1, 'lead_status_id' => $junk->id]);
        // …but a converted lead on the same phone is a valid match.
        $convertedLead = Leads::factory()->create(['phone' => '3215044424', 'account_id' => 1, 'lead_status_id' => $converted->id]);

        $this->assertSame($convertedLead->id, Leads::latestOpenByPhone('3215044424', 1)?->id);
    }

    public function test_returns_null_when_only_a_junk_lead_matches(): void
    {
        $junk = LeadStatuses::factory()->create(['account_id' => 1, 'is_junk' => 1]);
        Leads::factory()->create(['phone' => '3008887766', 'account_id' => 1, 'lead_status_id' => $junk->id]);

        $this->assertNull(Leads::latestOpenByPhone('3008887766', 1));
    }

    public function test_picks_the_latest_lead_when_several_share_a_phone(): void
    {
        $older = Leads::factory()->create(['phone' => '3001112222', 'account_id' => 1]);
        $newer = Leads::factory()->create(['phone' => '3001112222', 'account_id' => 1]);

        $this->assertGreaterThan($older->id, $newer->id);
        $this->assertSame($newer->id, Leads::latestOpenByPhone('3001112222', 1)?->id);
    }

    public function test_treats_a_null_status_lead_as_open(): void
    {
        $lead = Leads::factory()->create(['phone' => '3004445555', 'account_id' => 1, 'lead_status_id' => null]);

        $this->assertSame($lead->id, Leads::latestOpenByPhone('3004445555', 1)?->id);
    }

    public function test_is_scoped_to_the_account(): void
    {
        Leads::factory()->create(['phone' => '3006667777', 'account_id' => 2]);

        $this->assertNull(Leads::latestOpenByPhone('3006667777', 1));
    }

    public function test_ignores_soft_deleted_leads(): void
    {
        $lead = Leads::factory()->create(['phone' => '3007778888', 'account_id' => 1]);
        $lead->delete();

        $this->assertNull(Leads::latestOpenByPhone('3007778888', 1));
    }

    public function test_returns_null_for_unknown_phone(): void
    {
        Leads::factory()->create(['phone' => '3215044424', 'account_id' => 1]);

        $this->assertNull(Leads::latestOpenByPhone('3009998887', 1));
    }
}
