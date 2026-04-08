<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
        // Align database default to match all existing tables
        DB::statement("ALTER DATABASE crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Convert the one mismatched table (91 rows, safe to convert in-place)
        DB::statement("ALTER TABLE product_details CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        // Restore MariaDB 11.x default database collation
        DB::statement("ALTER DATABASE crm CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci");

        // Restore product_details to latin1
        DB::statement("ALTER TABLE product_details CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci");
    }
};
