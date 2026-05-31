<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds performance indexes for leads datatable queries
     */
    public function up(): void
    {
        // Add composite index for leads table
        Schema::table('leads', function (Blueprint $table) {
            // Index for city_id filtering (most common filter)
            $table->index('city_id', 'idx_leads_city_id');
            
            // Index for lead_status_id filtering (junk vs non-junk)
            $table->index('lead_status_id', 'idx_leads_lead_status_id');
            
            // Composite index for common query pattern
            $table->index(['city_id', 'lead_status_id', 'active'], 'idx_leads_city_status_active');
            
            // Index for sorting by created_at
            $table->index(['created_at', 'id'], 'idx_leads_created_at_id');
            
            // Index for phone search
            $table->index('phone', 'idx_leads_phone');
            
            // Index for region filtering
            $table->index('region_id', 'idx_leads_region_id');
        });

        // Add indexes for leads_services table
        Schema::table('leads_services', function (Blueprint $table) {
            // Composite index for service filtering
            $table->index(['lead_id', 'status', 'service_id'], 'idx_leads_services_lead_status_service');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_city_id');
            $table->dropIndex('idx_leads_lead_status_id');
            $table->dropIndex('idx_leads_city_status_active');
            $table->dropIndex('idx_leads_created_at_id');
            $table->dropIndex('idx_leads_phone');
            $table->dropIndex('idx_leads_region_id');
        });

        Schema::table('leads_services', function (Blueprint $table) {
            $table->dropIndex('idx_leads_services_lead_status_service');
        });
    }
};
