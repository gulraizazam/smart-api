<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * These indexes are designed to optimize the patients datatable queries:
     * - Composite index on (user_type_id, account_id, active) for base filtering
     * - Index on created_at for date range filtering and sorting
     * - Index on phone for phone search
     * - Index on name for name search
     * - Index on memberships.patient_id for membership joins
     */
    public function up(): void
    {
        // Check and add composite index for base patient queries
        if (!$this->indexExists('users', 'idx_users_patient_base')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['user_type_id', 'account_id', 'active'], 'idx_users_patient_base');
            });
        }

        // Check and add index for created_at sorting/filtering
        if (!$this->indexExists('users', 'idx_users_created_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('created_at', 'idx_users_created_at');
            });
        }

        // Check and add index for phone search
        if (!$this->indexExists('users', 'idx_users_phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('phone', 'idx_users_phone');
            });
        }

        // Check and add index for memberships patient_id
        if (!$this->indexExists('memberships', 'idx_memberships_patient_id')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->index('patient_id', 'idx_memberships_patient_id');
            });
        }

        // Check and add composite index for memberships lookup
        if (!$this->indexExists('memberships', 'idx_memberships_patient_type')) {
            Schema::table('memberships', function (Blueprint $table) {
                $table->index(['patient_id', 'membership_type_id'], 'idx_memberships_patient_type');
            });
        }

        // Check and add index for appointments patient_id
        if (!$this->indexExists('appointments', 'idx_appointments_patient_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->index('patient_id', 'idx_appointments_patient_id');
            });
        }

        // Check and add index for packages patient_id
        if (!$this->indexExists('packages', 'idx_packages_patient_id')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->index('patient_id', 'idx_packages_patient_id');
            });
        }

        // Check and add index for package_advances patient_id
        if (!$this->indexExists('package_advances', 'idx_package_advances_patient_id')) {
            Schema::table('package_advances', function (Blueprint $table) {
                $table->index('patient_id', 'idx_package_advances_patient_id');
            });
        }

        // Indexes for patient preview page tabs
        
        // Appointments composite index for patient preview
        if (!$this->indexExists('appointments', 'idx_appointments_patient_account')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->index(['patient_id', 'account_id', 'scheduled_date'], 'idx_appointments_patient_account');
            });
        }

        // User vouchers index
        if (!$this->indexExists('user_vouchers', 'idx_user_vouchers_user_id')) {
            Schema::table('user_vouchers', function (Blueprint $table) {
                $table->index('user_id', 'idx_user_vouchers_user_id');
            });
        }

        // Documents index for patient preview
        if (!$this->indexExists('documents', 'idx_documents_user_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->index('user_id', 'idx_documents_user_id');
            });
        }

        // Invoices index for patient preview
        if (!$this->indexExists('invoices', 'idx_invoices_patient_account')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->index(['patient_id', 'account_id'], 'idx_invoices_patient_account');
            });
        }

        // Leads index for patient preview
        if (!$this->indexExists('leads', 'idx_leads_patient_account')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->index(['patient_id', 'account_id'], 'idx_leads_patient_account');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_patient_base');
            $table->dropIndex('idx_users_created_at');
            $table->dropIndex('idx_users_phone');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex('idx_memberships_patient_id');
            $table->dropIndex('idx_memberships_patient_type');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_patient_id');
            $table->dropIndex('idx_appointments_patient_account');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex('idx_packages_patient_id');
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $table->dropIndex('idx_package_advances_patient_id');
        });

        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->dropIndex('idx_user_vouchers_user_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_user_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_patient_account');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_patient_account');
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
