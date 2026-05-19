<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an explicit movement-date column to the three staff-side tables
 * so the SPA's create form can backdate advances, returns, and peer
 * handovers within the same 7-day window pool→pool transfers already
 * support.
 *
 * Before today these three tables relied on `created_at` as the implicit
 * "movement date" — that gave a one-size-fits-all "today only" rule and
 * pinned the period-lock guard to `now()`. With a real date column the
 * service can validate the chosen date against the lock + threshold and
 * persist it on the row (mirror of `cash_transfers.transfer_date`).
 *
 * Migration is safe to run online — adding a nullable column never
 * locks the table for more than a metadata flip on MariaDB 11.8. The
 * follow-up UPDATE backfills existing rows so the read path can use
 * `COALESCE(date_column, DATE(created_at))` or just trust the new
 * column outright.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff_advances', function (Blueprint $table) {
            $table->date('advance_date')->nullable()->after('amount')
                ->comment('Date the advance was issued — backdating up to 7 days is allowed by StaffAdvanceService.');
            $table->index(['account_id', 'advance_date'], 'staff_advances_account_date_idx');
        });
        Schema::table('staff_returns', function (Blueprint $table) {
            $table->date('return_date')->nullable()->after('amount')
                ->comment('Date the return was received — backdating up to 7 days is allowed by StaffAdvanceService.');
            $table->index(['account_id', 'return_date'], 'staff_returns_account_date_idx');
        });
        Schema::table('staff_transfers', function (Blueprint $table) {
            $table->date('transfer_date')->nullable()->after('amount')
                ->comment('Date the handover happened — backdating up to 7 days is allowed by StaffTransferService.');
            $table->index(['account_id', 'transfer_date'], 'staff_transfers_account_date_idx');
        });

        // Backfill existing rows so the new column is always populated
        // (lets the application code drop the COALESCE fallback once the
        // last legacy NULL row is migrated). DATE(created_at) is the
        // only sensible historical guess.
        DB::statement('UPDATE staff_advances SET advance_date = DATE(created_at) WHERE advance_date IS NULL');
        DB::statement('UPDATE staff_returns SET return_date = DATE(created_at) WHERE return_date IS NULL');
        DB::statement('UPDATE staff_transfers SET transfer_date = DATE(created_at) WHERE transfer_date IS NULL');
    }

    public function down(): void
    {
        Schema::table('staff_advances', function (Blueprint $table) {
            $table->dropIndex('staff_advances_account_date_idx');
            $table->dropColumn('advance_date');
        });
        Schema::table('staff_returns', function (Blueprint $table) {
            $table->dropIndex('staff_returns_account_date_idx');
            $table->dropColumn('return_date');
        });
        Schema::table('staff_transfers', function (Blueprint $table) {
            $table->dropIndex('staff_transfers_account_date_idx');
            $table->dropColumn('transfer_date');
        });
    }
};
