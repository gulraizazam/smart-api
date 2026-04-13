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

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        foreach ($columns as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return;
            }
        }
        if ($this->indexExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        } catch (\Throwable $e) {
            \Log::warning("Skipping index {$name}: " . $e->getMessage());
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        } catch (\Throwable $e) {
            \Log::warning("Skipping dropIndex {$name}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->addIndex('invoice_details', ['invoice_id', 'service_id'], 'idx_inv_details_invoice_service');
        $this->addIndex('bundles', ['account_id', 'active', 'type'], 'idx_bundles_account_active_type');
        $this->addIndex('appointment_comments', ['appointment_id', 'created_at'], 'idx_appt_comments_appt_created');
        $this->addIndex('sms_logs', ['lead_id', 'created_at'], 'idx_sms_logs_lead_created');
        $this->addIndex('custom_form_feedbacks', ['custom_form_id', 'account_id'], 'idx_cff_form_account');
        $this->addIndex('documents', ['user_id', 'created_at'], 'idx_documents_user_created');
    }

    public function down(): void
    {
        $this->dropIndex('invoice_details', 'idx_inv_details_invoice_service');
        $this->dropIndex('bundles', 'idx_bundles_account_active_type');
        $this->dropIndex('appointment_comments', 'idx_appt_comments_appt_created');
        $this->dropIndex('sms_logs', 'idx_sms_logs_lead_created');
        $this->dropIndex('custom_form_feedbacks', 'idx_cff_form_account');
        $this->dropIndex('documents', 'idx_documents_user_created');
    }
};
