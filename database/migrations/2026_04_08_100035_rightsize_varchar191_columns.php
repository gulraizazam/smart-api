<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Right-size varchar(191) columns based on actual max data lengths.
     * varchar(191) was a Laravel 5.x workaround for MySQL 5.7 utf8mb4 index limits.
     * MariaDB 11.8 has no such limit — downsize to save buffer pool memory.
     */
    public function up(): void
    {
        $alterations = [
            'accounts' => 'MODIFY name VARCHAR(30) NOT NULL, MODIFY email VARCHAR(30) DEFAULT NULL, MODIFY contact VARCHAR(30) DEFAULT NULL, MODIFY resource_person VARCHAR(30) DEFAULT NULL, MODIFY suspended VARCHAR(20) NOT NULL DEFAULT \'0\'',

            'activities' => 'MODIFY patient VARCHAR(150) DEFAULT NULL, MODIFY service VARCHAR(100) DEFAULT NULL',

            'appointmentimages' => 'MODIFY image_name VARCHAR(100) DEFAULT NULL, MODIFY image_path VARCHAR(255) DEFAULT NULL',

            'appointments' => 'MODIFY consultancy_type VARCHAR(20) DEFAULT NULL, MODIFY coming_from VARCHAR(20) DEFAULT NULL',

            'appointment_types' => 'MODIFY name VARCHAR(50) NOT NULL, MODIFY slug VARCHAR(50) NOT NULL',

            'bundles' => 'MODIFY name VARCHAR(100) NOT NULL',

            'cash_transfers' => 'MODIFY attachment_url VARCHAR(255) DEFAULT NULL',

            'cities' => 'MODIFY name VARCHAR(50) NOT NULL',

            'custom_forms' => 'MODIFY name VARCHAR(100) NOT NULL',

            'discounts' => 'MODIFY name VARCHAR(100) NOT NULL, MODIFY description VARCHAR(100) DEFAULT NULL, MODIFY discount_type VARCHAR(30) NOT NULL',

            'documents' => 'MODIFY name VARCHAR(100) DEFAULT NULL, MODIFY url VARCHAR(255) DEFAULT NULL',

            'expenses' => 'MODIFY attachment_url VARCHAR(255) DEFAULT NULL',

            'global_operator_settings' => 'MODIFY operator_name VARCHAR(50) DEFAULT NULL, MODIFY username VARCHAR(50) DEFAULT NULL, MODIFY password VARCHAR(50) DEFAULT NULL, MODIFY url VARCHAR(100) DEFAULT NULL, MODIFY mask VARCHAR(30) DEFAULT NULL, MODIFY string_1 VARCHAR(30) DEFAULT NULL, MODIFY string_2 VARCHAR(30) DEFAULT NULL, MODIFY test_mode VARCHAR(20) DEFAULT NULL',

            'heavy_lifters' => 'MODIFY type VARCHAR(50) DEFAULT NULL',

            'invoice_details' => 'MODIFY discount_name VARCHAR(100) DEFAULT NULL, MODIFY discount_type VARCHAR(30) DEFAULT NULL',

            'locations' => 'MODIFY image_src VARCHAR(255) DEFAULT NULL, MODIFY ntn VARCHAR(30) DEFAULT NULL, MODIFY stn VARCHAR(30) DEFAULT NULL, MODIFY tax_percentage VARCHAR(20) DEFAULT NULL',

            'machine_types' => 'MODIFY name VARCHAR(50) NOT NULL',

            'packages' => 'MODIFY name VARCHAR(50) DEFAULT NULL, MODIFY random_id VARCHAR(80) DEFAULT NULL, MODIFY sessioncount VARCHAR(20) DEFAULT NULL',

            'package_bundles' => 'MODIFY random_id VARCHAR(80) DEFAULT NULL, MODIFY discount_name VARCHAR(100) DEFAULT NULL, MODIFY discount_type VARCHAR(30) DEFAULT NULL',

            'package_services' => 'MODIFY random_id VARCHAR(80) DEFAULT NULL',

            'payment_modes' => 'MODIFY name VARCHAR(50) NOT NULL, MODIFY payment_type VARCHAR(20) DEFAULT NULL',

            'permissions' => 'MODIFY name VARCHAR(125) NOT NULL, MODIFY guard_name VARCHAR(30) NOT NULL, MODIFY title VARCHAR(125) DEFAULT NULL',

            'plans' => 'MODIFY name VARCHAR(50) NOT NULL',

            'regions' => 'MODIFY name VARCHAR(50) NOT NULL',

            'resources' => 'MODIFY name VARCHAR(80) NOT NULL',

            'resource_has_rota' => 'MODIFY monday VARCHAR(30) DEFAULT NULL, MODIFY monday_off VARCHAR(30) DEFAULT NULL, MODIFY tuesday VARCHAR(30) DEFAULT NULL, MODIFY tuesday_off VARCHAR(30) DEFAULT NULL, MODIFY wednesday VARCHAR(30) DEFAULT NULL, MODIFY wednesday_off VARCHAR(30) DEFAULT NULL, MODIFY thursday VARCHAR(30) DEFAULT NULL, MODIFY thursday_off VARCHAR(30) DEFAULT NULL, MODIFY friday VARCHAR(30) DEFAULT NULL, MODIFY friday_off VARCHAR(30) DEFAULT NULL, MODIFY saturday VARCHAR(30) DEFAULT NULL, MODIFY saturday_off VARCHAR(30) DEFAULT NULL, MODIFY sunday VARCHAR(30) DEFAULT NULL, MODIFY sunday_off VARCHAR(30) DEFAULT NULL',

            'resource_has_rota_days' => 'MODIFY start_time VARCHAR(20) DEFAULT NULL, MODIFY end_time VARCHAR(20) DEFAULT NULL, MODIFY start_off VARCHAR(20) DEFAULT NULL, MODIFY end_off VARCHAR(20) DEFAULT NULL',

            'resource_types' => 'MODIFY name VARCHAR(30) NOT NULL, MODIFY slug VARCHAR(30) NOT NULL',

            'roles' => 'MODIFY name VARCHAR(50) NOT NULL, MODIFY guard_name VARCHAR(30) NOT NULL',

            'services' => 'MODIFY name VARCHAR(100) NOT NULL, MODIFY duration VARCHAR(20) DEFAULT NULL, MODIFY color VARCHAR(20) DEFAULT NULL',

            'settings' => 'MODIFY slug VARCHAR(100) NOT NULL',

            'sms_templates' => 'MODIFY name VARCHAR(100) DEFAULT NULL, MODIFY slug VARCHAR(50) DEFAULT NULL',

            'tax_treatment_type' => 'MODIFY name VARCHAR(50) NOT NULL',

            'towns' => 'MODIFY name VARCHAR(100) NOT NULL',

            'users' => 'MODIFY name VARCHAR(100) NOT NULL, MODIFY email VARCHAR(100) DEFAULT NULL, MODIFY phone VARCHAR(30) DEFAULT NULL, MODIFY password VARCHAR(100) NOT NULL, MODIFY remember_token VARCHAR(100) DEFAULT NULL, MODIFY image_src VARCHAR(100) DEFAULT NULL',

            'user_operator_settings' => 'MODIFY operator_name VARCHAR(50) DEFAULT NULL, MODIFY username VARCHAR(50) DEFAULT NULL, MODIFY password VARCHAR(50) DEFAULT NULL, MODIFY url VARCHAR(100) DEFAULT NULL, MODIFY mask VARCHAR(30) DEFAULT NULL, MODIFY string_1 VARCHAR(30) DEFAULT NULL, MODIFY string_2 VARCHAR(30) DEFAULT NULL, MODIFY test_mode VARCHAR(20) DEFAULT NULL',

            'user_types' => 'MODIFY name VARCHAR(50) NOT NULL, MODIFY type VARCHAR(50) NOT NULL',

            'vendor_transactions' => 'MODIFY attachment_url VARCHAR(255) DEFAULT NULL',
        ];

        foreach ($alterations as $table => $sql) {
            DB::statement("ALTER TABLE `$table` $sql");
        }
    }

    public function down(): void
    {
        // Revert all back to varchar(191) — safe since 191 > any new size
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
            $mods = implode(', ', array_map(fn($c) => "MODIFY `$c` VARCHAR(191)", $columns));
            DB::statement("ALTER TABLE `$table` $mods");
        }
    }
};
