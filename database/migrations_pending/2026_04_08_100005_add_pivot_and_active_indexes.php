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

    private function addIndex(string $table, array|string $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($columns, $name) {
            $t->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropIndex($name);
        });
    }

    public function up(): void
    {
        $this->addIndex('leads_services', ['lead_id', 'service_id'], 'idx_leads_services_lead_service');
        $this->addIndex('package_services', ['package_id', 'service_id'], 'idx_package_services_pkg_svc');
        $this->addIndex('package_bundles', ['package_id', 'bundle_id'], 'idx_package_bundles_pkg_bundle');
        $this->addIndex('bundle_has_services', ['bundle_id', 'service_id'], 'idx_bundle_has_services_composite');
        $this->addIndex('doctor_has_locations', ['user_id', 'location_id'], 'idx_doctor_has_locations_composite');
        $this->addIndex('discount_has_locations', ['discount_id', 'location_id'], 'idx_discount_has_locations_composite');
        $this->addIndex('resource_has_rota', ['resource_id', 'location_id'], 'idx_resource_has_rota_res_loc');
        $this->addIndex('services', ['account_id', 'active', 'end_node'], 'idx_services_account_active_endnode');
        $this->addIndex('resources', ['account_id', 'active'], 'idx_resources_account_active');
        $this->addIndex('users', ['account_id', 'active', 'deleted_at'], 'idx_users_account_active_deleted');
        $this->addIndex('leads', ['account_id', 'active', 'deleted_at'], 'idx_leads_account_active_deleted');
    }

    public function down(): void
    {
        $this->dropIndex('leads_services', 'idx_leads_services_lead_service');
        $this->dropIndex('package_services', 'idx_package_services_pkg_svc');
        $this->dropIndex('package_bundles', 'idx_package_bundles_pkg_bundle');
        $this->dropIndex('bundle_has_services', 'idx_bundle_has_services_composite');
        $this->dropIndex('doctor_has_locations', 'idx_doctor_has_locations_composite');
        $this->dropIndex('discount_has_locations', 'idx_discount_has_locations_composite');
        $this->dropIndex('resource_has_rota', 'idx_resource_has_rota_res_loc');
        $this->dropIndex('services', 'idx_services_account_active_endnode');
        $this->dropIndex('resources', 'idx_resources_account_active');
        $this->dropIndex('users', 'idx_users_account_active_deleted');
        $this->dropIndex('leads', 'idx_leads_account_active_deleted');
    }
};
