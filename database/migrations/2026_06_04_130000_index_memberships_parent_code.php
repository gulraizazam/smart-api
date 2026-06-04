<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index `memberships(parent_membership_code, is_referral)`.
 *
 * The referral lifecycle cascade looks referrals up by parent code:
 *   - MembershipObserver::updated mirrors a parent end_date edit onto
 *     `WHERE parent_membership_code = ? AND is_referral = 1`.
 *   - MembershipService::cancelMembership cascades on the same filter.
 * Neither had a supporting index, so each cascade did a full scan of
 * `memberships`. This adds the composite index they need.
 *
 * No foreign key: `memberships.code` is deliberately NOT unique among
 * parents (stock cards + referrals legitimately repeat a code), so a
 * FK on parent_membership_code -> code is impossible. Index only.
 *
 * Coexistence: pure additive index, invisible to crm2 reads/writes.
 * Idempotent guard mirrors 2026_05_15_140000_add_order_id_to_package_advances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('memberships'))->pluck('name')->all();
            if (! in_array('idx_memberships_parent_referral', $existing, true)) {
                $table->index(['parent_membership_code', 'is_referral'], 'idx_memberships_parent_referral');
            }
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $existing = collect(Schema::getIndexes('memberships'))->pluck('name')->all();
            if (in_array('idx_memberships_parent_referral', $existing, true)) {
                $table->dropIndex('idx_memberships_parent_referral');
            }
        });
    }
};
