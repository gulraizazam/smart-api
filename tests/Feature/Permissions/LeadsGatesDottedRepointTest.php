<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Tests\TestCase;

/**
 * Tier P — P2 (leads sub-batch): repoint the LeadsController authorization
 * gates from the legacy snake_case slugs to their dotted catalog twins.
 *
 * Why a source-contract test and not an HTTP test:
 * While the alias bridge (PermissionAliasMap + Gate::before) is still live, a
 * legacy slug and its dotted twin are INTERCHANGEABLE at every gate — a role
 * holding either passes either gate. So no request-level test can tell whether
 * the controller gates on `leads_manage` or `leads.detail.view`: both return
 * the same status. The behavioural access contract is already pinned by
 * LeadDetailAuthorizationTest (403-not-401, dotted-only role passes). What is
 * NOT yet pinned — and what would silently revert with the bridge masking it —
 * is that each endpoint actually requires the DOTTED catalog slug. That is the
 * whole point of P2: when P3 removes the bridge, these gates must already be
 * dotted or every role 403s. This test fails the instant any leads gate is
 * reverted to legacy, which an HTTP test cannot do here.
 */
class LeadsGatesDottedRepointTest extends TestCase
{
    private const CONTROLLER = 'app/Http/Controllers/Api/LeadsController.php';

    /**
     * The repoint, as shipped: dotted gate the controller now uses => the
     * legacy slug it replaced. Every dotted key is a real catalog slug
     * (verified on live prod) and the bridge's canonical twin of its legacy.
     *
     * @var array<string, string>
     */
    private const REPOINTS = [
        'leads.detail.view'        => 'leads_manage',
        'leads.create'             => 'leads_create',
        'leads.edit'               => 'leads_edit',
        'leads.delete'             => 'leads_destroy',
        'leads.update_status'      => 'leads_lead_status',
        'leads.update_city'        => 'leads_city',
        'leads.convert'            => 'leads_convert',
        'leads.list.view_inactive' => 'view_inactive_leads',
    ];

    /**
     * Slugs deliberately LEFT as-is by P2 (pinning that the repoint did not
     * over-reach): `contact` is the cross-module "see phone numbers" slug kept
     * single per the product decision; `appointments_manage` co-guards convert;
     * `leads_active`/`leads_inactive` are not bridge-covered.
     *
     * @var list<string>
     */
    private const KEPT = ['leads_active', 'leads_inactive', 'contact', 'appointments_manage'];

    private function source(): string
    {
        return (string) file_get_contents(base_path(self::CONTROLLER));
    }

    public function test_every_leads_endpoint_gates_on_its_dotted_catalog_slug(): void
    {
        $src = $this->source();

        foreach (self::REPOINTS as $dotted => $legacy) {
            $this->assertStringContainsString(
                "'{$dotted}'",
                $src,
                "LeadsController must gate on dotted [{$dotted}] (repointed from [{$legacy}]).",
            );
            $this->assertStringNotContainsString(
                "'{$legacy}'",
                $src,
                "Legacy slug [{$legacy}] must not remain anywhere in LeadsController — P3 removes the bridge that masks it.",
            );
        }
    }

    public function test_deliberately_kept_slugs_are_untouched(): void
    {
        $src = $this->source();

        foreach (self::KEPT as $kept) {
            $this->assertStringContainsString(
                "'{$kept}'",
                $src,
                "P2 must not touch [{$kept}] — it is not part of the leads repoint.",
            );
        }
    }
}
