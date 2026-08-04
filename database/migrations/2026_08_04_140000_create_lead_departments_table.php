<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medical / service departments for lead segmentation (Skin / Hair / Aesthetics / …).
 *
 * Semantically distinct from the HR `departments` table (which describes
 * *where employees work*, not what a lead's interest area is). Kept as its own
 * table so the two never leak into each other's UI or reports.
 *
 * Per-tenant name uniqueness is enforced by a UNIQUE index that INCLUDES
 * account_id; combined with SoftDeletes we accept the trade-off that a
 * soft-deleted "Skin" prevents a new "Skin" in the same tenant until the
 * old row is force-deleted (matches how lead_sources / lead_statuses behave).
 *
 * FK to accounts is the only structural constraint; department_id on the
 * `leads` table is left FK-less (see the next migration for why — legacy
 * leads.id column type mismatch).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_departments')) {
            return;
        }

        Schema::create('lead_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);

            // accounts.id is unsignedInteger in this legacy DB (see the
            // expense_attachments migration for the same note). users.id
            // likewise. FK types must match exactly.
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');

            // Query pattern is "active departments for this tenant, in sort order"
            // (the dropdown fill on the lead form + the dashboard split panel).
            $table->index(['account_id', 'active', 'sort_order'], 'lead_departments_active_sort_idx');

            // Per-tenant unique name. Composite so multiple tenants can each
            // have their own "Skin" without collision.
            $table->unique(['account_id', 'name'], 'lead_departments_account_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_departments');
    }
};
