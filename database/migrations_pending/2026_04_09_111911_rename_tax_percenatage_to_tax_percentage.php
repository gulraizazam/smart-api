<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'invoice_details',
        'package_bundles',
        'package_services',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $exists = DB::select(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = 'tax_percenatage'",
                [$table]
            );

            if (!empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` CHANGE `tax_percenatage` `tax_percentage` DECIMAL(11,2) NOT NULL DEFAULT 0.00");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $exists = DB::select(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = 'tax_percentage'",
                [$table]
            );

            if (!empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` CHANGE `tax_percentage` `tax_percenatage` DECIMAL(11,2) NOT NULL DEFAULT 0.00");
            }
        }
    }
};
