<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warm-archive table for activities past their hot-retention window.
 *
 * Schema mirrors `activities` field-for-field. Two differences:
 *   - `id` is a plain UNSIGNED BIGINT primary key (not auto-increment).
 *     Rows carry their original `activities.id` verbatim. This keeps
 *     cross-references stable (e.g. "activity #12345" in a support
 *     ticket still resolves whether the row lives in hot or archive).
 *   - `archived_at` timestamp records when the row was moved. Cold
 *     retention (deletion from archive) is computed against this, not
 *     against the original `created_at`, so a long-paused cron cannot
 *     accidentally prune rows that were just archived.
 *
 * Indexing is minimal by design — reads are rare (ad-hoc audit lookup
 * by id, or a sweep by (log_tier, archived_at) for cold pruning). No
 * general-purpose report indexes; reports query the hot table only.
 *
 * Archive rows are append-only from the app's perspective. The INSERT
 * from ArchiveAndPruneActivities uses INSERT IGNORE so a partial prior
 * run (which crashed between the INSERT and the DELETE) is idempotent
 * on rerun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activities_archive')) {
            return;
        }

        Schema::create('activities_archive', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();

            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();

            $table->string('action')->nullable();
            $table->string('activity_type')->nullable();
            $table->string('log_tier', 20)->nullable();
            $table->string('description', 2000)->nullable();

            $table->string('service')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('appointment_type')->nullable();

            $table->string('patient_name')->nullable();
            $table->string('patient')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('received_by')->nullable();

            $table->unsignedBigInteger('centre_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->date('schedule_date')->nullable();

            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('lead_status', 100)->nullable();
            $table->unsignedBigInteger('lead_status_id')->nullable();

            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->unsignedBigInteger('rescheduled_by')->nullable();
            $table->date('deleted_date')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();

            $table->index(['log_tier', 'archived_at'], 'idx_activities_archive_tier_archived');
            $table->index('created_at', 'idx_activities_archive_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities_archive');
    }
};
