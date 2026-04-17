<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            // Length of the employee's working day in hours (e.g. 6.00, 8.00, 9.00).
            // Drives hour-based leave deductions (half/short/custom). Null = not set;
            // custom-hours leave is hidden for employees without a value.
            $table->decimal('shift_hours', 4, 2)->nullable()->after('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('employee_details', function (Blueprint $table) {
            $table->dropColumn('shift_hours');
        });
    }
};
