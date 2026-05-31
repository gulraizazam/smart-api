<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );
        return ! empty($rows);
    }

    private function dropIfExists(string $table, array $indexes): void
    {
        foreach ($indexes as $index) {
            if ($this->indexExists($table, $index)) {
                Schema::table($table, function (Blueprint $t) use ($index) {
                    $t->dropIndex($index);
                });
            }
        }
    }

    /**
     * Drop duplicate indexes across 6 tables.
     *
     * Each pair has two indexes on the same column(s) — we keep the *_foreign
     * named ones (Laravel convention) and drop the manually-added idx_* duplicates.
     * For package_advances.package_id (triple duplicate), we keep the composite
     * idx_pa_pkg_deleted (package_id, deleted_at) and drop all three single-column ones.
     *
     * Verified: NONE of these tables have actual FK constraints — only PRIMARY KEY
     * (and one UNIQUE on users). Dropping any index is safe.
     */
    public function up(): void
    {
        $this->dropIfExists('package_advances', [
            'idx_patient_id',
            'idx_payment_mode_id',
            'idx_account_id',
            'idx_created_by',
            'idx_updated_by',
            'idx_invoice_id',
            'idx_location_id',
            'idx_appointment_type_id',
            'idx_appointment_id',
            'package_advances_package_id_foreign',
            'idx_package_advances_package_id',
            'idx_package_id',
        ]);

        $this->dropIfExists('sms_logs', [
            'idx_lead_id',
            'idx_appointment_id',
            'idx_created_by',
            'idx_invoice_id',
            'idx_package_id',
        ]);

        $this->dropIfExists('audit_trail_changes', ['idx_audit_trail_id']);
        $this->dropIfExists('package_bundles', ['idx_package_bundles_package_id']);
        $this->dropIfExists('package_services', ['idx_package_services_package_id']);
        $this->dropIfExists('users', ['idx_user_type']);
    }

    /**
     * Re-create the dropped indexes on rollback.
     */
    private function addIfMissing(string $table, string $column, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            Schema::table($table, function (Blueprint $t) use ($column, $index) {
                $t->index($column, $index);
            });
        }
    }

    public function down(): void
    {
        $this->addIfMissing('package_advances', 'patient_id', 'idx_patient_id');
        $this->addIfMissing('package_advances', 'payment_mode_id', 'idx_payment_mode_id');
        $this->addIfMissing('package_advances', 'account_id', 'idx_account_id');
        $this->addIfMissing('package_advances', 'created_by', 'idx_created_by');
        $this->addIfMissing('package_advances', 'updated_by', 'idx_updated_by');
        $this->addIfMissing('package_advances', 'invoice_id', 'idx_invoice_id');
        $this->addIfMissing('package_advances', 'location_id', 'idx_location_id');
        $this->addIfMissing('package_advances', 'appointment_type_id', 'idx_appointment_type_id');
        $this->addIfMissing('package_advances', 'appointment_id', 'idx_appointment_id');
        $this->addIfMissing('package_advances', 'package_id', 'package_advances_package_id_foreign');
        $this->addIfMissing('package_advances', 'package_id', 'idx_package_advances_package_id');
        $this->addIfMissing('package_advances', 'package_id', 'idx_package_id');

        $this->addIfMissing('sms_logs', 'lead_id', 'idx_lead_id');
        $this->addIfMissing('sms_logs', 'appointment_id', 'idx_appointment_id');
        $this->addIfMissing('sms_logs', 'created_by', 'idx_created_by');
        $this->addIfMissing('sms_logs', 'invoice_id', 'idx_invoice_id');
        $this->addIfMissing('sms_logs', 'package_id', 'idx_package_id');

        $this->addIfMissing('audit_trail_changes', 'audit_trail_id', 'idx_audit_trail_id');
        $this->addIfMissing('package_bundles', 'package_id', 'idx_package_bundles_package_id');
        $this->addIfMissing('package_services', 'package_id', 'idx_package_services_package_id');
        $this->addIfMissing('users', 'user_type_id', 'idx_user_type');
    }
};
