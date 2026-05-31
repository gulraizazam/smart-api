<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeModify(string $table, string $column, string $sql): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$sql}");
        } catch (\Throwable $e) {
            \Log::warning("Skipping MODIFY {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $tables = [
            'activities', 'appointments', 'appointment_statuses', 'appointment_types',
            'bundles', 'bundle_services_price_history', 'cancellation_reasons', 'cities',
            'discounts', 'heavy_lifters', 'invoice_statuses', 'leads', 'lead_comments',
            'lead_sources', 'lead_statuses', 'locations', 'packages', 'package_advances',
            'payment_modes', 'regions', 'resources', 'resource_has_rota', 'settings',
            'sms_templates', 'towns', 'users', 'user_types',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'account_id')) {
                continue;
            }
            try {
                DB::table($table)->whereNull('account_id')->update(['account_id' => 1]);
            } catch (\Throwable $e) {
                \Log::warning("Backfill account_id on {$table}: " . $e->getMessage());
            }
        }

        foreach ($tables as $table) {
            $this->safeModify($table, 'account_id', 'INT(10) UNSIGNED NOT NULL');
        }

        if (Schema::hasTable('vouchers') && Schema::hasColumn('vouchers', 'account_id')) {
            try {
                DB::table('vouchers')->whereNull('account_id')->update(['account_id' => 1]);
            } catch (\Throwable $e) {
                \Log::warning('Backfill vouchers.account_id: ' . $e->getMessage());
            }
            $this->safeModify('vouchers', 'account_id', 'BIGINT(20) UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        $tables = [
            'activities', 'appointments', 'appointment_statuses', 'appointment_types',
            'bundles', 'bundle_services_price_history', 'cancellation_reasons', 'cities',
            'discounts', 'heavy_lifters', 'invoice_statuses', 'leads', 'lead_comments',
            'lead_sources', 'lead_statuses', 'locations', 'packages', 'package_advances',
            'payment_modes', 'regions', 'resources', 'resource_has_rota', 'settings',
            'sms_templates', 'towns', 'users', 'user_types',
        ];
        foreach ($tables as $table) {
            $this->safeModify($table, 'account_id', 'INT(10) UNSIGNED DEFAULT NULL');
        }
        $this->safeModify('vouchers', 'account_id', 'BIGINT(20) UNSIGNED DEFAULT NULL');
    }
};
