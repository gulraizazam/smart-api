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

    /**
     * Right-size varchar(191) columns based on actual max data lengths.
     * Each column altered individually so missing columns are skipped.
     */
    public function up(): void
    {
        $perColumn = [
            'accounts' => [
                ['name', 'VARCHAR(30) NOT NULL'],
                ['email', 'VARCHAR(30) DEFAULT NULL'],
                ['contact', 'VARCHAR(30) DEFAULT NULL'],
                ['resource_person', 'VARCHAR(30) DEFAULT NULL'],
                ['suspended', "VARCHAR(20) NOT NULL DEFAULT '0'"],
            ],
            'activities' => [
                ['patient', 'VARCHAR(150) DEFAULT NULL'],
                ['service', 'VARCHAR(100) DEFAULT NULL'],
            ],
            'appointmentimages' => [
                ['image_name', 'VARCHAR(100) DEFAULT NULL'],
                ['image_path', 'VARCHAR(255) DEFAULT NULL'],
            ],
            'appointments' => [
                ['consultancy_type', 'VARCHAR(20) DEFAULT NULL'],
                ['coming_from', 'VARCHAR(20) DEFAULT NULL'],
            ],
            'appointment_types' => [
                ['name', 'VARCHAR(50) NOT NULL'],
                ['slug', 'VARCHAR(50) NOT NULL'],
            ],
            'bundles' => [['name', 'VARCHAR(100) NOT NULL']],
            'cash_transfers' => [['attachment_url', 'VARCHAR(255) DEFAULT NULL']],
            'cities' => [['name', 'VARCHAR(50) NOT NULL']],
            'custom_forms' => [['name', 'VARCHAR(100) NOT NULL']],
            'discounts' => [
                ['name', 'VARCHAR(100) NOT NULL'],
                ['description', 'VARCHAR(100) DEFAULT NULL'],
                ['discount_type', 'VARCHAR(30) NOT NULL'],
            ],
            'documents' => [
                ['name', 'VARCHAR(100) DEFAULT NULL'],
                ['url', 'VARCHAR(255) DEFAULT NULL'],
            ],
            'expenses' => [['attachment_url', 'VARCHAR(255) DEFAULT NULL']],
            'global_operator_settings' => [
                ['operator_name', 'VARCHAR(50) DEFAULT NULL'],
                ['username', 'VARCHAR(50) DEFAULT NULL'],
                ['password', 'VARCHAR(50) DEFAULT NULL'],
                ['url', 'VARCHAR(100) DEFAULT NULL'],
                ['mask', 'VARCHAR(30) DEFAULT NULL'],
                ['string_1', 'VARCHAR(30) DEFAULT NULL'],
                ['string_2', 'VARCHAR(30) DEFAULT NULL'],
                ['test_mode', 'VARCHAR(20) DEFAULT NULL'],
            ],
            'heavy_lifters' => [['type', 'VARCHAR(50) DEFAULT NULL']],
            'invoice_details' => [
                ['discount_name', 'VARCHAR(100) DEFAULT NULL'],
                ['discount_type', 'VARCHAR(30) DEFAULT NULL'],
            ],
            'locations' => [
                ['image_src', 'VARCHAR(255) DEFAULT NULL'],
                ['ntn', 'VARCHAR(30) DEFAULT NULL'],
                ['stn', 'VARCHAR(30) DEFAULT NULL'],
                ['tax_percentage', 'VARCHAR(20) DEFAULT NULL'],
            ],
            'machine_types' => [['name', 'VARCHAR(50) NOT NULL']],
            'packages' => [
                ['name', 'VARCHAR(50) DEFAULT NULL'],
                ['random_id', 'VARCHAR(80) DEFAULT NULL'],
                ['sessioncount', 'VARCHAR(20) DEFAULT NULL'],
            ],
            'package_bundles' => [
                ['random_id', 'VARCHAR(80) DEFAULT NULL'],
                ['discount_name', 'VARCHAR(100) DEFAULT NULL'],
                ['discount_type', 'VARCHAR(30) DEFAULT NULL'],
            ],
            'package_services' => [['random_id', 'VARCHAR(80) DEFAULT NULL']],
            'payment_modes' => [
                ['name', 'VARCHAR(50) NOT NULL'],
                ['payment_type', 'VARCHAR(20) DEFAULT NULL'],
            ],
            'permissions' => [
                ['name', 'VARCHAR(125) NOT NULL'],
                ['guard_name', 'VARCHAR(30) NOT NULL'],
                ['title', 'VARCHAR(125) DEFAULT NULL'],
            ],
            'plans' => [['name', 'VARCHAR(50) NOT NULL']],
            'regions' => [['name', 'VARCHAR(50) NOT NULL']],
            'resources' => [['name', 'VARCHAR(80) NOT NULL']],
            'resource_has_rota' => [
                ['monday', 'VARCHAR(30) DEFAULT NULL'],
                ['monday_off', 'VARCHAR(30) DEFAULT NULL'],
                ['tuesday', 'VARCHAR(30) DEFAULT NULL'],
                ['tuesday_off', 'VARCHAR(30) DEFAULT NULL'],
                ['wednesday', 'VARCHAR(30) DEFAULT NULL'],
                ['wednesday_off', 'VARCHAR(30) DEFAULT NULL'],
                ['thursday', 'VARCHAR(30) DEFAULT NULL'],
                ['thursday_off', 'VARCHAR(30) DEFAULT NULL'],
                ['friday', 'VARCHAR(30) DEFAULT NULL'],
                ['friday_off', 'VARCHAR(30) DEFAULT NULL'],
                ['saturday', 'VARCHAR(30) DEFAULT NULL'],
                ['saturday_off', 'VARCHAR(30) DEFAULT NULL'],
                ['sunday', 'VARCHAR(30) DEFAULT NULL'],
                ['sunday_off', 'VARCHAR(30) DEFAULT NULL'],
            ],
            'resource_has_rota_days' => [
                ['start_time', 'VARCHAR(20) DEFAULT NULL'],
                ['end_time', 'VARCHAR(20) DEFAULT NULL'],
                ['start_off', 'VARCHAR(20) DEFAULT NULL'],
                ['end_off', 'VARCHAR(20) DEFAULT NULL'],
            ],
            'resource_types' => [
                ['name', 'VARCHAR(30) NOT NULL'],
                ['slug', 'VARCHAR(30) NOT NULL'],
            ],
            'roles' => [
                ['name', 'VARCHAR(50) NOT NULL'],
                ['guard_name', 'VARCHAR(30) NOT NULL'],
            ],
            'services' => [
                ['name', 'VARCHAR(100) NOT NULL'],
                ['duration', 'VARCHAR(20) DEFAULT NULL'],
                ['color', 'VARCHAR(20) DEFAULT NULL'],
            ],
            'settings' => [['slug', 'VARCHAR(100) NOT NULL']],
            'sms_templates' => [
                ['name', 'VARCHAR(100) DEFAULT NULL'],
                ['slug', 'VARCHAR(50) DEFAULT NULL'],
            ],
            'tax_treatment_type' => [['name', 'VARCHAR(50) NOT NULL']],
            'towns' => [['name', 'VARCHAR(100) NOT NULL']],
            'users' => [
                ['name', 'VARCHAR(100) NOT NULL'],
                ['email', 'VARCHAR(100) DEFAULT NULL'],
                ['phone', 'VARCHAR(30) DEFAULT NULL'],
                ['password', 'VARCHAR(100) NOT NULL'],
                ['remember_token', 'VARCHAR(100) DEFAULT NULL'],
                ['image_src', 'VARCHAR(100) DEFAULT NULL'],
            ],
            'user_operator_settings' => [
                ['operator_name', 'VARCHAR(50) DEFAULT NULL'],
                ['username', 'VARCHAR(50) DEFAULT NULL'],
                ['password', 'VARCHAR(50) DEFAULT NULL'],
                ['url', 'VARCHAR(100) DEFAULT NULL'],
                ['mask', 'VARCHAR(30) DEFAULT NULL'],
                ['string_1', 'VARCHAR(30) DEFAULT NULL'],
                ['string_2', 'VARCHAR(30) DEFAULT NULL'],
                ['test_mode', 'VARCHAR(20) DEFAULT NULL'],
            ],
            'user_types' => [
                ['name', 'VARCHAR(50) NOT NULL'],
                ['type', 'VARCHAR(50) NOT NULL'],
            ],
            'vendor_transactions' => [['attachment_url', 'VARCHAR(255) DEFAULT NULL']],
        ];

        foreach ($perColumn as $table => $cols) {
            foreach ($cols as [$col, $sql]) {
                $this->safeModify($table, $col, $sql);
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'accounts' => ['name', 'email', 'contact', 'resource_person', 'suspended'],
            'activities' => ['patient', 'service'],
            'appointmentimages' => ['image_name', 'image_path'],
            'appointments' => ['consultancy_type', 'coming_from'],
            'appointment_types' => ['name', 'slug'],
            'bundles' => ['name'],
            'cash_transfers' => ['attachment_url'],
            'cities' => ['name'],
            'custom_forms' => ['name'],
            'discounts' => ['name', 'description', 'discount_type'],
            'documents' => ['name', 'url'],
            'expenses' => ['attachment_url'],
            'global_operator_settings' => ['operator_name', 'username', 'password', 'url', 'mask', 'string_1', 'string_2', 'test_mode'],
            'heavy_lifters' => ['type'],
            'invoice_details' => ['discount_name', 'discount_type'],
            'locations' => ['image_src', 'ntn', 'stn', 'tax_percentage'],
            'machine_types' => ['name'],
            'packages' => ['name', 'random_id', 'sessioncount'],
            'package_bundles' => ['random_id', 'discount_name', 'discount_type'],
            'package_services' => ['random_id'],
            'payment_modes' => ['name', 'payment_type'],
            'permissions' => ['name', 'guard_name', 'title'],
            'plans' => ['name'],
            'regions' => ['name'],
            'resources' => ['name'],
            'resource_has_rota' => ['monday', 'monday_off', 'tuesday', 'tuesday_off', 'wednesday', 'wednesday_off', 'thursday', 'thursday_off', 'friday', 'friday_off', 'saturday', 'saturday_off', 'sunday', 'sunday_off'],
            'resource_has_rota_days' => ['start_time', 'end_time', 'start_off', 'end_off'],
            'resource_types' => ['name', 'slug'],
            'roles' => ['name', 'guard_name'],
            'services' => ['name', 'duration', 'color'],
            'settings' => ['slug'],
            'sms_templates' => ['name', 'slug'],
            'tax_treatment_type' => ['name'],
            'towns' => ['name'],
            'users' => ['name', 'email', 'phone', 'password', 'remember_token', 'image_src'],
            'user_operator_settings' => ['operator_name', 'username', 'password', 'url', 'mask', 'string_1', 'string_2', 'test_mode'],
            'user_types' => ['name', 'type'],
            'vendor_transactions' => ['attachment_url'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $col) {
                $this->safeModify($table, $col, 'VARCHAR(191)');
            }
        }
    }
};
