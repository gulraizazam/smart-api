<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix remaining integer type issues across high-volume tables.
     *
     * Boolean columns (INT → TINYINT(1)):
     *   appointments.meta_purchase_sent: 0/1 only (390K×0, 3.9K×1)
     *   appointments.is_unscheduled: ''/1 (394K×'', 4×1) → clean empties first
     *   appointments.first_scheduled_count: 0/1 counter (394K×1, 362×0)
     *   package_advances.is_setteled: 0/1 only (831K×0, 240×1)
     *   users.can_perform_consultation: 0/1 only (217K×0, 2×1)
     *
     * Counter columns (INT → TINYINT UNSIGNED):
     *   appointments.scheduled_at_count: values 0-4+ (counter, not boolean)
     *
     * TINYINT standardization (3/4 → 1):
     *   package_advances.is_tax: TINYINT(3) → TINYINT(1)
     *   appointments.send_message: TINYINT(4) → TINYINT(1)
     *   appointments.active: TINYINT(3) → TINYINT(1)
     *   users.active: TINYINT(3) → TINYINT(1)
     */
    public function up(): void
    {
        // Clean empty strings in is_unscheduled before type change
        DB::statement("UPDATE appointments SET is_unscheduled = 0 WHERE is_unscheduled = '' OR is_unscheduled IS NULL");

        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('meta_purchase_sent')->default(false)->change();
            $table->boolean('is_unscheduled')->default(false)->change();
            $table->boolean('first_scheduled_count')->default(false)->change();
            $table->boolean('send_message')->default(false)->change();
            $table->boolean('active')->unsigned()->default(true)->change();
            $table->unsignedTinyInteger('scheduled_at_count')->default(0)->change();
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $table->boolean('is_setteled')->default(false)->change();
            $table->boolean('is_tax')->default(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_perform_consultation')->default(false)->change();
            $table->boolean('active')->unsigned()->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->integer('meta_purchase_sent')->default(0)->change();
            $table->integer('is_unscheduled')->default(0)->change();
            $table->integer('first_scheduled_count')->default(0)->change();
            $table->tinyInteger('send_message')->default(0)->change();
            $table->unsignedTinyInteger('active')->default(1)->change();
            $table->integer('scheduled_at_count')->default(0)->change();
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $table->integer('is_setteled')->default(0)->change();
            $table->unsignedTinyInteger('is_tax')->default(0)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->integer('can_perform_consultation')->default(0)->change();
            $table->unsignedTinyInteger('active')->default(1)->change();
        });
    }
};
