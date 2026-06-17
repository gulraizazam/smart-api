<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Support\PermissionAliasMap;
use Tests\TestCase;

/**
 * Tier P — P2 (packages sub-batch): repoint Api\PackagesController + BundleHelper
 * authorization gates from the legacy snake_case slugs to their dotted catalog
 * twins, AND correct the bridge's `packages.delete` phantom to the real
 * `packages.destroy` (id 785 on prod; `packages.delete` has no catalog row).
 *
 * See LeadsGatesDottedRepointTest for why this is a source-contract test rather
 * than an HTTP test: while the alias bridge is live, legacy and dotted are
 * interchangeable at every gate, so no request-level test goes red when the
 * repoint is reverted. Prod access-flip analysis (2026-06-17) proved all of
 * these repoints change access for ZERO of the 23 roles.
 */
class PackagesGatesDottedRepointTest extends TestCase
{
    private const FILES = [
        'app/Http/Controllers/Api/PackagesController.php',
        'app/Helpers/BundleHelper.php',
    ];

    /**
     * Dotted gate the code now uses => the legacy slug it replaced. (index and
     * show both came from the `packages_manage` umbrella; the access-flip
     * analysis confirmed neither narrowing loses a role.)
     *
     * @var array<string, string>
     */
    private const REPOINTS = [
        'packages.list.view'   => 'packages_manage',
        'packages.detail.view' => 'packages_manage',
        'packages.create'      => 'packages_create',
        'packages.edit'        => 'packages_edit',
        'packages.destroy'     => 'packages_destroy',
        'packages.activate'    => 'packages_active',
        'packages.deactivate'  => 'packages_inactive',
    ];

    /** Every bridge-covered legacy packages slug — must be gone from the code. */
    private const LEGACY = [
        'packages_manage', 'packages_create', 'packages_edit',
        'packages_destroy', 'packages_active', 'packages_inactive',
    ];

    private function source(): string
    {
        $src = '';
        foreach (self::FILES as $f) {
            $src .= (string) file_get_contents(base_path($f));
        }

        return $src;
    }

    public function test_every_packages_endpoint_gates_on_its_dotted_catalog_slug(): void
    {
        $src = $this->source();

        foreach (array_keys(self::REPOINTS) as $dotted) {
            $this->assertStringContainsString(
                "'{$dotted}'",
                $src,
                "Packages code must gate on dotted [{$dotted}].",
            );
        }

        foreach (self::LEGACY as $legacy) {
            $this->assertStringNotContainsString(
                "'{$legacy}'",
                $src,
                "Legacy slug [{$legacy}] must not remain in the packages code — P3 removes the bridge that masks it.",
            );
        }
    }

    public function test_non_bridge_view_inactive_packages_is_left_intact(): void
    {
        // `view_inactive_packages` is NOT bridge-covered, so P2 leaves it as-is
        // (pins that the repoint did not over-reach into a non-bridged slug).
        $this->assertStringContainsString(
            "'view_inactive_packages'",
            $this->source(),
            'view_inactive_packages is non-bridge and must be left untouched by P2.',
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

    public function test_bridge_phantom_packages_delete_is_corrected_to_packages_destroy(): void
    {
        // The bridge used to map the PHANTOM `packages.delete` (no catalog row)
        // to packages_destroy. P2 corrects it to the real `packages.destroy`.
        // This pins the fix discriminatingly: revert the map and these flip.
        $this->assertContains(
            'packages.destroy',
            PermissionAliasMap::aliasesFor('packages_destroy'),
            'packages_destroy must bridge to the real packages.destroy.',
        );
        $this->assertNotContains(
            'packages.delete',
            PermissionAliasMap::aliasesFor('packages_destroy'),
            'The phantom packages.delete must no longer be bridged.',
        );
        $this->assertSame(
            [],
            PermissionAliasMap::aliasesFor('packages.delete'),
            'packages.delete is a phantom — it must have no aliases now.',
        );
        $this->assertSame(
            ['packages_destroy'],
            PermissionAliasMap::aliasesFor('packages.destroy'),
            'packages.destroy must bridge to legacy packages_destroy.',
        );
    }
}
