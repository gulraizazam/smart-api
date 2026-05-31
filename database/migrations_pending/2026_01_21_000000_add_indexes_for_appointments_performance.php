<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for appointments table performance optimization.
     * These indexes will dramatically improve consultancy datatable query performance.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Composite index for common filtering (city + location + type)
            // This covers the most common query pattern in consultancy listing
            if (!$this->indexExists('appointments', 'idx_appointments_city_location_type')) {
                $table->index(['city_id', 'location_id', 'appointment_type_id'], 'idx_appointments_city_location_type');
            }
            
            // Index for date-based queries (very common in datatable)
            if (!$this->indexExists('appointments', 'idx_appointments_scheduled_date')) {
                $table->index('scheduled_date', 'idx_appointments_scheduled_date');
            }
            
            // Index for status filtering
            if (!$this->indexExists('appointments', 'idx_appointments_status')) {
                $table->index('base_appointment_status_id', 'idx_appointments_status');
            }
            
            // Index for patient lookups (join optimization)
            if (!$this->indexExists('appointments', 'idx_appointments_patient')) {
                $table->index('patient_id', 'idx_appointments_patient');
            }
            
            // Index for doctor filtering
            if (!$this->indexExists('appointments', 'idx_appointments_doctor')) {
                $table->index('doctor_id', 'idx_appointments_doctor');
            }
            
            // Index for service filtering
            if (!$this->indexExists('appointments', 'idx_appointments_service')) {
                $table->index('service_id', 'idx_appointments_service');
            }
            
            // Composite index for created_at ordering with type filter
            if (!$this->indexExists('appointments', 'idx_appointments_created_type')) {
                $table->index(['created_at', 'appointment_type_id'], 'idx_appointments_created_type');
            }
        });
        
        // Add index on users table for phone searches
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'idx_users_phone')) {
                $table->index('phone', 'idx_users_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_city_location_type');
            $table->dropIndex('idx_appointments_scheduled_date');
            $table->dropIndex('idx_appointments_status');
            $table->dropIndex('idx_appointments_patient');
            $table->dropIndex('idx_appointments_doctor');
            $table->dropIndex('idx_appointments_service');
            $table->dropIndex('idx_appointments_created_type');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_phone');
        });
    }
    
    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = Schema::getConnection()
            ->getDoctrineSchemaManager()
            ->listTableIndexes($table);
        
        return array_key_exists($index, $indexes);
    }
};
