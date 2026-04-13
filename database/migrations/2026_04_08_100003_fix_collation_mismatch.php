<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix collation mismatch:
     * - Database default: utf8mb4_uca1400_ai_ci (MariaDB 11.x default)
     * - 137 tables: utf8mb4_unicode_ci
     * - 1 table (product_details): latin1_swedish_ci
     *
     * Aligns everything to utf8mb4_unicode_ci to prevent implicit collation
     * casts in JOINs. product_details has only 91 rows — conversion is instant.
     */
    public function up(): void
    {
        $db = DB::getDatabaseName();

        try {
            DB::statement("ALTER DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            // Shared hosting often denies ALTER DATABASE — tables alignment below is what actually matters.
            if (! str_contains($e->getMessage(), 'Access denied')) {
                throw $e;
            }
        }

        if (Schema::hasTable('product_details')) {
            DB::statement("ALTER TABLE product_details CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }

    public function down(): void
    {
        $db = DB::getDatabaseName();

        try {
            DB::statement("ALTER DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci");
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'Access denied')) {
                throw $e;
            }
        }

        if (Schema::hasTable('product_details')) {
            DB::statement("ALTER TABLE product_details CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci");
        }
    }
};
