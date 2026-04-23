<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee address column. Required for the HR edit-details modal, which
 * collects a postal/home address for every employee. Address was previously
 * held on `users.address`, but that column was dropped in
 * 2026_04_18_120000_drop_address_from_users_table.php (patient-only scope).
 * HR-scoped address belongs on employee_details.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employee_details', 'address')) {
            return;
        }

        Schema::table('employee_details', function (Blueprint $table): void {
            $table->string('address', 500)->nullable()->after('emergency_contact_relation');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employee_details', 'address')) {
            return;
        }

        Schema::table('employee_details', function (Blueprint $table): void {
            $table->dropColumn('address');
        });
    }
};
