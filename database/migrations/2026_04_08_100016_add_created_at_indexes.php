<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add created_at indexes to high-volume tables for date-range query performance.
     *
     * EXPLAIN findings:
     *   activities (399K rows): No created_at index → full scan on date filtering
     *   leads (515K rows): No created_at index → full scan on reports
     *   invoice_details (243K rows): No created_at index → full scan on date queries
     *   users (215K rows): No created_at index → full scan on user listing
     *   appointments (391K rows): No created_at index → full scan on history queries
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->index('created_at', 'idx_activities_created_at');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index('created_at', 'idx_leads_created_at');
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->index('created_at', 'idx_invoice_details_created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'idx_users_created_at');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index('created_at', 'idx_appointments_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->dropIndex('idx_activities_created_at'));
        Schema::table('leads', fn (Blueprint $table) => $table->dropIndex('idx_leads_created_at'));
        Schema::table('invoice_details', fn (Blueprint $table) => $table->dropIndex('idx_invoice_details_created_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('idx_users_created_at'));
        Schema::table('appointments', fn (Blueprint $table) => $table->dropIndex('idx_appointments_created_at'));
    }
};
