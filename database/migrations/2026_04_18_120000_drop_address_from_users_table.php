<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop `users.address` — the field was patient-only in practice (no
 * employee/doctor FormRequest or controller writes it; a repo-wide grep
 * for `->address` finds only patient + lead + appointment-lookup call
 * sites, all of which are being removed in the same change set).
 *
 * Rollback procedure
 * ------------------
 * This migration intentionally drops the column and its data. `down()`
 * below re-creates the column as a nullable TEXT (matching the shape
 * from 2014_10_12_000000_create_users_table.php line 31), but existing
 * address values cannot be recovered from this migration alone.
 *
 * ALLURA-REVIEW: before running in production verify you have a recent
 * `users` table backup; recovery from a rollback requires restoring
 * those rows from the backup into the newly-re-added column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'address')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('address');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'address')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            // Re-add as TEXT nullable to match the original 2014 shape.
            // Data is not restored — operator must recover from backup.
            $table->text('address')->nullable()->after('dob');
        });
    }
};
