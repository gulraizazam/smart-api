<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated daily metric rollup for the Management Dashboard.
 *
 * A nightly job writes one row per (date × scope × metric_key × dimensions)
 * combination. "Today" rows are computed live and cached. Retroactive writes
 * (a PackageAdvance edited for a past date) enqueue a RebuildDailyMetrics job
 * for the affected day so history self-heals.
 *
 * target_value is pinned at write time so that editing a target mid-month does
 * not silently rewrite historical "% to target" figures.
 *
 * Uniqueness is enforced at the application layer via updateOrCreate inside a
 * job-mutex (WithoutOverlapping) rather than a strict DB UNIQUE — MariaDB treats
 * NULL as distinct in unique indexes, which would have forced sentinel values
 * and complicated every scope query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->date('date');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('resource_type', 20)->nullable();
            $table->string('metric_key', 64);
            $table->decimal('value', 14, 2)->default(0);
            $table->decimal('target_value', 14, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'date', 'branch_id', 'metric_key'], 'ddm_acc_date_branch_metric');
            $table->index(['account_id', 'date', 'resource_type', 'resource_id', 'metric_key'], 'ddm_acc_date_resource_metric');
            $table->index(['account_id', 'metric_key', 'date'], 'ddm_acc_metric_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_daily_metrics');
    }
};
