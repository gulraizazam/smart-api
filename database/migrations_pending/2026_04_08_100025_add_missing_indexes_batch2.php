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

    private function createRaw(string $name, string $table, string $cols): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }
        try {
            DB::statement("CREATE INDEX `{$name}` ON `{$table}`({$cols})");
        } catch (\Throwable $e) {
            \Log::warning("Skipping CREATE INDEX {$name}: " . $e->getMessage());
        }
    }

    private function dropRaw(string $name, string $table): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }
        try {
            DB::statement("DROP INDEX `{$name}` ON `{$table}`");
        } catch (\Throwable $e) {
            \Log::warning("Skipping DROP INDEX {$name}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->createRaw('idx_orders_account', 'orders', 'account_id');
        $this->createRaw('idx_orders_patient', 'orders', 'patient_id');
        $this->createRaw('idx_orders_location', 'orders', 'location_id');
        $this->createRaw('idx_orders_created_at', 'orders', 'created_at');

        $this->createRaw('idx_memberships_patient_deleted', 'memberships', 'patient_id, deleted_at');
        $this->createRaw('idx_memberships_type', 'memberships', 'membership_type_id');
        $this->createRaw('idx_memberships_active', 'memberships', 'active');

        $this->createRaw('idx_inventories_product', 'inventories', 'product_id');
        $this->createRaw('idx_inventories_warehouse', 'inventories', 'warehouse_id');
        $this->createRaw('idx_inventories_location', 'inventories', 'location_id');

        $this->createRaw('idx_activities_patient', 'activities', 'patient_id');
        $this->createRaw('idx_activities_service', 'activities', 'service_id');
        $this->createRaw('idx_activities_appointment', 'activities', 'appointment_id');
        $this->createRaw('idx_activities_centre', 'activities', 'centre_id');
        $this->createRaw('idx_activities_invoice', 'activities', 'invoice_id');
        $this->createRaw('idx_activities_package', 'activities', 'package_id');
    }

    public function down(): void
    {
        $this->dropRaw('idx_orders_account', 'orders');
        $this->dropRaw('idx_orders_patient', 'orders');
        $this->dropRaw('idx_orders_location', 'orders');
        $this->dropRaw('idx_orders_created_at', 'orders');

        $this->dropRaw('idx_memberships_patient_deleted', 'memberships');
        $this->dropRaw('idx_memberships_type', 'memberships');
        $this->dropRaw('idx_memberships_active', 'memberships');

        $this->dropRaw('idx_inventories_product', 'inventories');
        $this->dropRaw('idx_inventories_warehouse', 'inventories');
        $this->dropRaw('idx_inventories_location', 'inventories');

        $this->dropRaw('idx_activities_patient', 'activities');
        $this->dropRaw('idx_activities_service', 'activities');
        $this->dropRaw('idx_activities_appointment', 'activities');
        $this->dropRaw('idx_activities_centre', 'activities');
        $this->dropRaw('idx_activities_invoice', 'activities');
        $this->dropRaw('idx_activities_package', 'activities');
    }
};
