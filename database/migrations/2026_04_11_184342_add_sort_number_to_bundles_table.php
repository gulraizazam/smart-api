<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->unsignedInteger('sort_number')->default(0)->after('active');
        });

        // Set initial sort order based on existing IDs
        DB::statement('SET @pos := 0');
        DB::statement('UPDATE bundles SET sort_number = (@pos := @pos + 1) ORDER BY id ASC');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn('sort_number');
        });
    }
};
