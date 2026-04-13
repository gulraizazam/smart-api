<?php

use Illuminate\Database\Migrations\Migration;
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

    private function dropIndex(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        } catch (\Throwable $e) {
            \Log::warning("Skipping DROP INDEX {$table}.{$index}: " . $e->getMessage());
        }
    }

    private function addIndex(string $table, string $index, string $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }
        try {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");
        } catch (\Throwable $e) {
            \Log::warning("Skipping ADD INDEX {$table}.{$index}: " . $e->getMessage());
        }
    }

    /**
     * Drop redundant indexes that are leftmost prefixes of wider indexes.
     */
    public function up(): void
    {
        $this->dropIndex('bundle_has_services', 'bundle_has_services_bundle_id_foreign');
        $this->dropIndex('bundle_has_services', 'idx_bundle_has_services_composite');
        $this->dropIndex('discount_has_locations', 'discount_has_locations_discount_id_foreign');
        $this->dropIndex('doctor_google_reviews', 'doctor_google_reviews_account_id_index');
        $this->dropIndex('doctor_has_locations', 'doctor_has_locations_user_id_foreign');
        $this->dropIndex('invoices', 'idx_invoices_account_deleted');
        $this->dropIndex('membership_type_has_discounts', 'membership_type_has_discounts_membership_type_id_index');
        $this->dropIndex('resources', 'resources_account_id_foreign');
        $this->dropIndex('resource_has_rota', 'resource_has_rota_resource');
        $this->dropIndex('services', 'services_account');
        $this->dropIndex('system_targets', 'system_targets_account_id_index');
    }

    public function down(): void
    {
        $this->addIndex('bundle_has_services', 'bundle_has_services_bundle_id_foreign', 'bundle_id');
        $this->addIndex('bundle_has_services', 'idx_bundle_has_services_composite', 'bundle_id, service_id');
        $this->addIndex('discount_has_locations', 'discount_has_locations_discount_id_foreign', 'discount_id');
        $this->addIndex('doctor_google_reviews', 'doctor_google_reviews_account_id_index', 'account_id');
        $this->addIndex('doctor_has_locations', 'doctor_has_locations_user_id_foreign', 'user_id');
        $this->addIndex('invoices', 'idx_invoices_account_deleted', 'account_id, deleted_at');
        $this->addIndex('membership_type_has_discounts', 'membership_type_has_discounts_membership_type_id_index', 'membership_type_id');
        $this->addIndex('resources', 'resources_account_id_foreign', 'account_id');
        $this->addIndex('resource_has_rota', 'resource_has_rota_resource', 'resource_id');
        $this->addIndex('services', 'services_account', 'account_id');
        $this->addIndex('system_targets', 'system_targets_account_id_index', 'account_id');
    }
};
