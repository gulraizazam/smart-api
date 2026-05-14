<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B of the Cash Movements unification — adds the `staff_transfers`
 * table to record peer handovers ("Asma → Hassan: 500 PKR") as a single
 * ledger entry without touching pool balances.
 *
 * Shape mirrors `staff_advances` exactly (created 2026-03-05) plus a
 * second user FK for the destination. `void_reason` is varchar(100) to
 * stay consistent with the sibling advance/return tables. No `pool_id`
 * — a handover never debits or credits a cash pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent — some dev environments already carry an out-of-band
        // `staff_transfers` table from prior experiments. Skip in that case
        // so the migration record can be set without forcing a destructive
        // drop. Production rolls forward fresh and creates the full schema.
        if (Schema::hasTable('staff_transfers')) {
            return;
        }

        Schema::create('staff_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('from_user_id')->comment('Staff handing over the cash');
            $table->unsignedBigInteger('to_user_id')->comment('Staff receiving the cash');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 100)->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('from_user_id')->references('id')->on('users');
            $table->foreign('to_user_id')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');

            $table->index(['account_id', 'from_user_id']);
            $table->index(['account_id', 'to_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_transfers');
    }
};
