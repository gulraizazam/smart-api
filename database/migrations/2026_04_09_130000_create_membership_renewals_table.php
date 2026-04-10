<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `membership_renewals` — history ledger for membership
 * renewals. Each row links an original membership to its renewal,
 * recording who performed the renewal and when.
 *
 * Background: the MembershipRenewalService was writing to this table
 * via DB::table('membership_renewals')->insert(...) but no migration
 * had ever shipped to create it. Any call to the renew flow in
 * production would have crashed on an "unknown table" error. This
 * migration closes the gap so the service contract actually holds.
 *
 * Columns mirror the shape MembershipRenewalService::logRenewal
 * writes and MembershipRenewalService::getRenewalHistory reads:
 *
 *   - original_membership_id → memberships.id
 *   - renewed_membership_id  → memberships.id
 *   - renewed_at             → timestamp the renewal happened
 *   - renewed_by             → users.id (who performed it)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('membership_renewals')) {
            return;
        }

        Schema::create('membership_renewals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('original_membership_id');
            $table->unsignedBigInteger('renewed_membership_id');
            $table->timestamp('renewed_at')->nullable();
            $table->unsignedBigInteger('renewed_by')->nullable();
            $table->timestamps();

            $table->index('original_membership_id');
            $table->index('renewed_membership_id');
            $table->index('renewed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_renewals');
    }
};
