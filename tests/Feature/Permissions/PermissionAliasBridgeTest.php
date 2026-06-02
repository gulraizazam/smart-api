<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Support\PermissionAliasMap;
use ReflectionClass;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Exhaustive end-to-end guard for the legacy<->dotted permission bridge
 * (App\Support\PermissionAliasMap + the Gate::before in AppServiceProvider::boot).
 *
 * This is the coexistence safety net. crm2 (legacy Blade) gates on snake_case
 * slugs; crm3 (SPA/api) gates on dotted slugs; both run on one shared DB. The
 * bridge is what lets a grant on EITHER side satisfy the OTHER side's gate. If
 * a future refactor of PermissionAliasMap or the Gate::before wiring breaks one
 * direction of one map entry, a crm2- or crm3-granted role would silently 403.
 *
 * PermissionAliasMapTest pins the aliasesFor() return values; THIS test pins the
 * actual Gate resolution for EVERY map entry, in BOTH directions, and is
 * reflection-driven so new map entries are covered automatically (can't go stale).
 *
 * Scope: it guards the bridge MECHANISM, not catalog fidelity (whether a slug
 * exists/granted in prod) — that is `coexistence-check.sh`'s job against the
 * reconciled DB. See feedback_crm3_must_not_break_crm2 / project_local_coexistence_mirror.
 */
class PermissionAliasBridgeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedAllAliasPermissions();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return array<string, list<string>> */
    private function map(): array
    {
        /** @var array<string, list<string>> $map */
        $map = (new ReflectionClass(PermissionAliasMap::class))->getConstant('DOTTED_TO_LEGACY');

        return $map;
    }

    /** @return list<string> every distinct legacy slug referenced by the map */
    private function everyLegacySlug(): array
    {
        $legacies = [];
        foreach ($this->map() as $list) {
            foreach ($list as $legacy) {
                $legacies[$legacy] = true;
            }
        }

        return array_keys($legacies);
    }

    private function seedAllAliasPermissions(): void
    {
        $names = array_keys($this->map());
        foreach ($this->everyLegacySlug() as $legacy) {
            $names[] = $legacy;
        }
        foreach (array_unique($names) as $name) {
            $this->createPermission($name);
        }
    }

    /**
     * A fresh non-super user whose single role is granted ONLY $perms.
     *
     * @param list<string> $perms
     */
    private function userGrantedOnly(array $perms): User
    {
        $role = $this->createRole('alias_bridge_role_'.(++$this->seq));
        $role->givePermissionTo($perms);

        $user = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($user, $role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_alias_map_is_populated(): void
    {
        // Guard against the exhaustive loops below passing vacuously if the map
        // were ever emptied or the reflection lookup returned nothing.
        $this->assertGreaterThan(
            50,
            count($this->map()),
            'PermissionAliasMap::DOTTED_TO_LEGACY looks empty — the bridge tests would be vacuous.',
        );
    }

    public function test_a_legacy_only_grant_satisfies_every_dotted_gate_it_aliases(): void
    {
        // crm2 side: a role holding ONLY a legacy snake_case slug must pass the
        // crm3 dotted gate(s) that alias it — else a crm2-granted user 403s in crm3.
        foreach ($this->everyLegacySlug() as $legacy) {
            $dottedGates = PermissionAliasMap::aliasesFor($legacy);
            $this->assertNotEmpty($dottedGates, "legacy [$legacy] resolved to no dotted alias");

            $user = $this->userGrantedOnly([$legacy]);

            foreach ($dottedGates as $dotted) {
                $this->assertTrue(
                    $user->can($dotted),
                    "A role holding ONLY legacy [$legacy] must pass dotted gate [$dotted] via the bridge.",
                );
            }
        }
    }

    public function test_a_dotted_only_grant_satisfies_every_legacy_gate_it_aliases(): void
    {
        // crm3 side: a role holding ONLY a dotted slug must pass the legacy gate(s)
        // it replaced — the coexistence direction (still-legacy gates in shared
        // code must accept a dotted-only role).
        foreach ($this->map() as $dotted => $legacyGates) {
            $user = $this->userGrantedOnly([$dotted]);

            foreach ($legacyGates as $legacy) {
                $this->assertTrue(
                    $user->can($legacy),
                    "A role holding ONLY dotted [$dotted] must pass legacy gate [$legacy] via the bridge.",
                );
            }
        }
    }
}
