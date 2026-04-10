<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for slow queries identified in slow_query_log.
     *
     * Slow query 1: ArrivedNotConvertedService — patients with no cash inflow
     *   SELECT patient_id, SUM(cash_amount) FROM package_advances
     *   WHERE cash_flow = 'in' GROUP BY patient_id
     *   Was: type=ALL, 827K rows scanned, ~1.3-2.5s
     *   Fix: covering index (cash_flow, patient_id, cash_amount)
     *
     * Slow query 2: latest appointment per patient by type
     *   SELECT patient_id, MAX(id) FROM appointments
     *   WHERE appointment_type_id = 1 GROUP BY patient_id
     *   Was: type=ref, 391K rows + temp table + filesort
     *   Fix: composite (appointment_type_id, patient_id, id) — covers WHERE,
     *        GROUP BY, and aggregate column for index-only access.
     */
    public function up(): void
    {
        // Slow query 1 fix: covering index for cash_flow GROUP BY scans
        Schema::table('package_advances', function ($t) {
            $t->index(
                ['cash_flow', 'patient_id', 'cash_amount'],
                'idx_pa_cashflow_patient_amount'
            );
        });

        // Slow query 2 fix: covering index for appointment_type GROUP BY patient
        Schema::table('appointments', function ($t) {
            $t->index(
                ['appointment_type_id', 'patient_id', 'id'],
                'idx_appt_type_patient_id'
            );
        });
    }

    public function down(): void
    {
        Schema::table('package_advances', function ($t) {
            $t->dropIndex('idx_pa_cashflow_patient_amount');
        });
        Schema::table('appointments', function ($t) {
            $t->dropIndex('idx_appt_type_patient_id');
        });
    }
};
