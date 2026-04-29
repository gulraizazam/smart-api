<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the user_branches pivot. Management Dashboard scoping now derives from
 * the existing user_has_locations pivot (mirrors the CashflowHelper rule:
 * users.select_all = 1 or pivot contains the virtual "All Centres" id ⇒
 * company-wide; otherwise scoped to the listed location ids).
 *
 * Forward-fix for 2026_04_14_120100_create_user_branches_table.php.
 *
 * Rollback: re-run the original create migration; data is not preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_branches');
    }

    public function down(): void
    {
        Schema::create('user_branches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('location_id');
            $table->timestamps();

            $table->unique(['account_id', 'user_id', 'location_id'], 'ub_account_user_location_unique');
            $table->index(['account_id', 'user_id'], 'ub_account_user');
            $table->index(['account_id', 'location_id'], 'ub_account_location');
        });
    }
};
