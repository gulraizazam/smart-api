<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly rollup of UPSELL COLLECTED — the cash actually attributed to each
 * upsold line's seller, the basis for monthly upsell incentives. Distinct from
 * the live "Booked" number (`UpsellingService::getDoctorUpsellingData`, which
 * counts at add-time with no payment check). Full model: memory
 * `project_upsell_collected_incentive`.
 *
 * A nightly job (`RebuildUpsellCollected`) recomputes recent months via the
 * per-plan, 90-day-windowed, line-value cash-constrained waterfall and writes
 * one row per (account_id, month, doctor_id, location_id). Recent months are
 * re-run because in-window cash keeps arriving and refunds reverse forever.
 *
 * `is_closed` freezes a month once its incentive is paid (manual payroll
 * close): the builder skips closed (doctor, month) rows, so later
 * reassignments/refunds never rewrite a paid month — they flow into the
 * current open month via the per-doctor clawback carry instead.
 *
 * Uniqueness is enforced at the app layer (updateOrCreate inside a job mutex),
 * NOT a DB UNIQUE — mirrors `dashboard_daily_metrics` (MariaDB treats NULL as
 * distinct, and `location_id` is nullable for the company-wide scope).
 *
 * COEXISTENCE: brand-new tables, additive, crm2-invisible — no shared
 * column/permission/response-shape touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upsell_collected_monthly', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->date('month');                                  // first-of-month, e.g. 2026-06-01
            $table->unsignedBigInteger('doctor_id');                // credited seller = package_services.sold_by
            $table->unsignedBigInteger('location_id')->nullable();  // packages.location_id (null = company scope)
            $table->decimal('collected_amount', 14, 2)->default(0); // final per-month payout (>= 0, after carry)
            $table->boolean('is_closed')->default(false);           // frozen once incentive paid (payroll close)
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'month', 'location_id', 'doctor_id'], 'ucm_acc_month_loc_doc');
            $table->index(['account_id', 'doctor_id', 'month'], 'ucm_acc_doc_month');
        });

        // Per-doctor unabsorbed clawback carried forward across months. Lives in
        // its own table so a carry survives months where the doctor has no
        // collected row at all. carry_amount is <= 0 (a debt the next positive
        // month absorbs first).
        Schema::create('upsell_clawback_carry', function (Blueprint $table): void {
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('doctor_id');
            $table->decimal('carry_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->primary(['account_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upsell_clawback_carry');
        Schema::dropIfExists('upsell_collected_monthly');
    }
};
