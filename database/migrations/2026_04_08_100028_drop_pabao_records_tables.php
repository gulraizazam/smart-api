<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop unused pabao_records and pabao_record_payments tables.
     *
     * Both tables have 0 rows. No routes, no controller, no blade views exist.
     * Feature was started but never completed. All related code removed.
     */
    public function up(): void
    {
        Schema::dropIfExists('pabao_record_payments');
        Schema::dropIfExists('pabao_records');
    }

    public function down(): void
    {
        // Tables can be recreated from the original migrations if needed:
        // 2021_12_30_135052_create_pabao_records_table.php
        // 2021_12_30_135150_create_pabao_record_payments_table.php
    }
};
