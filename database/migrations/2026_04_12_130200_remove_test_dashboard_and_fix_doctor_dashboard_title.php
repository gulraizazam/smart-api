<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $testDashboardId = DB::table('permissions')
                ->where('name', 'test_dashboard')
                ->value('id');

            if ($testDashboardId !== null) {
                DB::table('role_has_permissions')
                    ->where('permission_id', $testDashboardId)
                    ->delete();

                DB::table('permissions')
                    ->where('id', $testDashboardId)
                    ->delete();
            }

            DB::table('permissions')
                ->where('name', 'doctor_dashboard')
                ->whereNull('title')
                ->update(['title' => 'Doctor Dashboard']);
        });
    }

    public function down(): void
    {
        // Intentionally a no-op: `test_dashboard` was unused garbage with no
        // application consumer, and the title repair is not reversible without
        // the pre-migration dump at storage/backups/permissions_pre_recat_*.sql.
    }
};
