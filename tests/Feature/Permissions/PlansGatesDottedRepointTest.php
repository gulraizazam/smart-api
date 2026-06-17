<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Support\PermissionAliasMap;
use Tests\TestCase;

/**
 * Tier P — P2 (plans sub-batch): repoint the remaining legacy plans GATES to
 * their dotted catalog twins — Api\PlansController::planLog, the live
 * Admin\AppointmentsPlansController::create gate, and PatientPolicy's
 * view/create-plan abilities.
 *
 * On prod the legacy slugs `patients_plan_log` / `patients_plan_create` have NO
 * catalog row (verified 2026-06-17), so these gates currently pass ONLY via the
 * alias bridge — repointing to the real dotted slug is required before P3 can
 * remove the bridge. Access-flip analysis across all 23 roles: zero change.
 *
 * Source-contract test for the same reason as LeadsGatesDottedRepointTest: with
 * the bridge live an HTTP test cannot tell legacy from dotted. NOTE: the
 * `patients_plan_cash_*` strings in PlansController are response-array KEYS the
 * SPA reads (their gate VALUES are already dotted) — NOT gates, left untouched.
 */
class PlansGatesDottedRepointTest extends TestCase
{
    private const FILES = [
        'app/Http/Controllers/Api/PlansController.php',
        'app/Http/Controllers/Admin/AppointmentsPlansController.php',
        'app/Policies/PatientPolicy.php',
    ];

    /** @var array<string, string> dotted gate now used => legacy slug replaced. */
    private const REPOINTS = [
        'plans.log.view' => 'patients_plan_log',
        'plans.create'   => 'patients_plan_create',
    ];

    private function source(): string
    {
        $src = '';
        foreach (self::FILES as $f) {
            $src .= (string) file_get_contents(base_path($f));
        }

        return $src;
    }

    public function test_plans_gates_use_the_dotted_catalog_slug(): void
    {
        $src = $this->source();

        foreach (self::REPOINTS as $dotted => $legacy) {
            $this->assertStringContainsString(
                "'{$dotted}'",
                $src,
                "Plans code must gate on dotted [{$dotted}].",
            );
            $this->assertStringNotContainsString(
                "'{$legacy}'",
                $src,
                "Legacy gate slug [{$legacy}] must not remain — P3 removes the bridge that masks it.",
            );
        }
    }

    public function test_non_bridge_patient_plans_ability_is_left_intact(): void
    {
        // PatientPolicy::viewPlans keeps `patient_plans` (NOT bridge-covered)
        // alongside the repointed plans.create — pins no over-reach.
        $this->assertStringContainsString(
            "'patient_plans'",
            $this->source(),
            'patient_plans is non-bridge and must be left untouched by P2.',
        );
    }

    public function test_each_repoint_target_is_the_bridges_canonical_twin(): void
    {
        foreach (self::REPOINTS as $dotted => $legacy) {
            $this->assertContains(
                $dotted,
                PermissionAliasMap::aliasesFor($legacy),
                "Dotted gate [{$dotted}] must be the bridge twin of legacy [{$legacy}] so legacy-only roles keep access.",
            );
        }
    }
}
