<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `department_id` FK on `leads` so leads can be segmented by
 * medical/service department (Skin / Hair / …) in the dashboard.
 *
 * Guarded with hasColumn() — safe to re-run.
 *
 * Notes on FK type + constraint:
 *   • `lead_departments.id` is `bigint unsigned` (native ->id()). So
 *     `department_id` here is `unsignedBigInteger` — matches the target.
 *   • No FK CONSTRAINT is added. Reason: several other legacy nullable
 *     references on `leads` (e.g. patient_id) predate the disciplined-FK era
 *     and are not enforced at the DB level. Adding a FK here would break the
 *     existing pattern and expose migrations to type-mismatch surprises on
 *     the legacy int-vs-bigint columns nearby. The Eloquent relation on the
 *     model enforces the reference at application level.
 *
 * Retro-populating historical leads is out of scope for phase 1 — existing
 * rows stay NULL and surface as "Unassigned" in the department split panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads') || Schema::hasColumn('leads', 'department_id')) {
            return;
        }

        // No ->after() — that forces MariaDB to fall back from ALGORITHM=INSTANT
        // to a full "copy to tmp table" reorder. On the shared dev DB (554k rows)
        // and even worse on prod, that acquires a metadata lock for minutes.
        // The visual position of a column doesn't matter to the app, so we take
        // the free INSTANT add.
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable();
        });
        // Same rationale for the index — created separately so if the ALTER
        // above is INSTANT and the index creation is not, at least each hits
        // the table once.
        Schema::table('leads', function (Blueprint $table) {
            // The dashboard's department-split query is
            // "GROUP BY department_id WHERE account_id = ?", covered by this
            // composite index.
            $table->index(['account_id', 'department_id'], 'leads_account_department_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leads', 'department_id')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_account_department_idx');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('department_id');
        });
    }
};
