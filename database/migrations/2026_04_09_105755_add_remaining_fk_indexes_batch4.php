<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remaining index gaps found during principal DB audit.
     *
     * Focused on composite indexes for common query patterns
     * on tables not yet covered by batches 1-3.
     */
    public function up(): void
    {
        $this->addIndexIfNotExists('invoice_details', 'idx_inv_details_invoice_service', ['invoice_id', 'service_id']);
        $this->addIndexIfNotExists('bundles', 'idx_bundles_account_active_type', ['account_id', 'active', 'type']);
        $this->addIndexIfNotExists('appointment_comments', 'idx_appt_comments_appt_created', ['appointment_id', 'created_at']);
        $this->addIndexIfNotExists('sms_logs', 'idx_sms_logs_lead_created', ['lead_id', 'created_at']);
        $this->addIndexIfNotExists('custom_form_feedbacks', 'idx_cff_form_account', ['custom_form_id', 'account_id']);
        $this->addIndexIfNotExists('documents', 'idx_documents_user_created', ['user_id', 'created_at']);
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropIndex('idx_inv_details_invoice_service');
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->dropIndex('idx_bundles_account_active_type');
        });

        Schema::table('appointment_comments', function (Blueprint $table) {
            $table->dropIndex('idx_appt_comments_appt_created');
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex('idx_sms_logs_lead_created');
        });

        Schema::table('custom_form_feedbacks', function (Blueprint $table) {
            $table->dropIndex('idx_cff_form_account');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_user_created');
        });
    }

    private function addIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        $exists = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        if (empty($exists)) {
            Schema::table($table, function (Blueprint $table) use ($indexName, $columns) {
                $table->index($columns, $indexName);
            });
        }
    }
};
