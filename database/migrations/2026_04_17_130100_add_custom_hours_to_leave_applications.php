<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            // Hours requested when duration_type = 'custom' (single-day only).
            $table->decimal('custom_hours', 4, 2)->nullable()->after('duration_type');

            // Snapshot of the employee's shift_hours at submission time. Historical
            // applications keep their original shift even if the employee's shift
            // is later changed. Null for pre-existing rows — legacy deduction logic
            // (Full=1, Half=0.5, Short=0.25) applies.
            $table->decimal('shift_hours_snapshot', 4, 2)->nullable()->after('custom_hours');
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['custom_hours', 'shift_hours_snapshot']);
        });
    }
};
