<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeColumn(string $table, string $column, callable $cb): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            Schema::table($table, $cb);
        } catch (\Throwable $e) {
            \Log::warning("Skipping {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $changes = [
            ['activities', 'created_by', 100, true],
            ['activities', 'lead_status', 100, true],
            ['activities', 'patient', 191, true],
            ['activities', 'service', 191, true],
            ['appointments', 'name', 100, true],
            ['appointment_statuses', 'name', 100, true],
            ['appointment_statuses', 'is_comment', 100, true],
            ['appointment_statuses', 'parent_id', 100, true],
            ['audit_trail_actions', 'name', 100, false],
            ['audit_trail_tables', 'name', 100, false],
            ['audit_trail_tables', 'screen', 100, true],
            ['bundles', 'name', 191, true],
            ['bundles', 'bundle_type', 100, true],
            ['bundle_has_services', 'discount_type', 100, true],
            ['cancellation_reasons', 'name', 100, true],
            ['cash_transfers', 'attachment_url', 191, true],
            ['documents', 'document_type', 100, true],
            ['expenses', 'attachment_url', 191, true],
            ['invoice_statuses', 'name', 100, true],
            ['lead_sources', 'name', 100, true],
            ['lead_statuses', 'name', 100, true],
            ['lead_statuses', 'is_comment', 100, true],
            ['lead_statuses', 'parent_id', 100, true],
            ['locations', 'name', 100, true],
            ['locations', 'fdo_name', 100, true],
            ['memberships', 'parent_membership_code', 100, true],
            ['package_bundles', 'config_group_id', 100, true],
            ['services', 'name', 191, true],
            ['settings', 'name', 100, true],
            ['sms_templates', 'name', 191, true],
            ['telecomprovidernumbers', 'pre_fix', 100, true],
            ['telecomproviders', 'name', 100, true],
            ['vendor_transactions', 'attachment_url', 191, true],
            ['warehouses', 'manager_name', 100, true],
        ];

        foreach ($changes as [$table, $col, $len, $nullable]) {
            $this->safeColumn($table, $col, function (Blueprint $t) use ($col, $len, $nullable) {
                $c = $t->string($col, $len);
                if ($nullable) {
                    $c->nullable();
                }
                $c->change();
            });
        }
    }

    public function down(): void
    {
        $revertTo500 = [
            'activities' => ['created_by', 'lead_status', 'service'],
            'appointments' => ['name'],
            'appointment_statuses' => ['name', 'is_comment', 'parent_id'],
            'audit_trail_actions' => ['name'],
            'audit_trail_tables' => ['name', 'screen'],
            'bundles' => ['name', 'bundle_type'],
            'bundle_has_services' => ['discount_type'],
            'cancellation_reasons' => ['name'],
            'cash_transfers' => ['attachment_url'],
            'documents' => ['document_type'],
            'expenses' => ['attachment_url'],
            'invoice_statuses' => ['name'],
            'lead_sources' => ['name'],
            'lead_statuses' => ['name', 'is_comment', 'parent_id'],
            'locations' => ['name', 'fdo_name'],
            'package_bundles' => ['config_group_id'],
            'services' => ['name'],
            'settings' => ['name'],
            'sms_templates' => ['name'],
            'telecomprovidernumbers' => ['pre_fix'],
            'telecomproviders' => ['name'],
            'vendor_transactions' => ['attachment_url'],
            'warehouses' => ['manager_name'],
        ];

        foreach ($revertTo500 as $table => $columns) {
            foreach ($columns as $col) {
                $this->safeColumn($table, $col, function (Blueprint $t) use ($col) {
                    $t->string($col, 500)->nullable()->change();
                });
            }
        }

        $this->safeColumn('activities', 'patient', function (Blueprint $t) {
            $t->string('patient', 599)->nullable()->change();
        });
        $this->safeColumn('memberships', 'parent_membership_code', function (Blueprint $t) {
            $t->string('parent_membership_code', 555)->nullable()->change();
        });
    }
};
