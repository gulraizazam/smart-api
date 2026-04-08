<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add indexes to tables that were completely unindexed or missing critical FK indexes.
     *
     * orders (9.6K rows): 0 secondary indexes
     * memberships (5.2K rows): 0 secondary indexes
     * inventories (3.9K rows): 0 secondary indexes
     * activities (374K rows): 10 FK columns without indexes
     */
    public function up(): void
    {
        // ─── orders ───
        DB::statement('CREATE INDEX idx_orders_account ON orders(account_id)');
        DB::statement('CREATE INDEX idx_orders_patient ON orders(patient_id)');
        DB::statement('CREATE INDEX idx_orders_location ON orders(location_id)');
        DB::statement('CREATE INDEX idx_orders_created_at ON orders(created_at)');

        // ─── memberships ───
        DB::statement('CREATE INDEX idx_memberships_patient_deleted ON memberships(patient_id, deleted_at)');
        DB::statement('CREATE INDEX idx_memberships_type ON memberships(membership_type_id)');
        DB::statement('CREATE INDEX idx_memberships_active ON memberships(active)');

        // ─── inventories ───
        DB::statement('CREATE INDEX idx_inventories_product ON inventories(product_id)');
        DB::statement('CREATE INDEX idx_inventories_warehouse ON inventories(warehouse_id)');
        DB::statement('CREATE INDEX idx_inventories_location ON inventories(location_id)');

        // ─── activities (374K rows — high-volume, many FK columns unindexed) ───
        DB::statement('CREATE INDEX idx_activities_patient ON activities(patient_id)');
        DB::statement('CREATE INDEX idx_activities_service ON activities(service_id)');
        DB::statement('CREATE INDEX idx_activities_appointment ON activities(appointment_id)');
        DB::statement('CREATE INDEX idx_activities_centre ON activities(centre_id)');
        DB::statement('CREATE INDEX idx_activities_invoice ON activities(invoice_id)');
        DB::statement('CREATE INDEX idx_activities_package ON activities(package_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX idx_orders_account ON orders');
        DB::statement('DROP INDEX idx_orders_patient ON orders');
        DB::statement('DROP INDEX idx_orders_location ON orders');
        DB::statement('DROP INDEX idx_orders_created_at ON orders');

        DB::statement('DROP INDEX idx_memberships_patient_deleted ON memberships');
        DB::statement('DROP INDEX idx_memberships_type ON memberships');
        DB::statement('DROP INDEX idx_memberships_active ON memberships');

        DB::statement('DROP INDEX idx_inventories_product ON inventories');
        DB::statement('DROP INDEX idx_inventories_warehouse ON inventories');
        DB::statement('DROP INDEX idx_inventories_location ON inventories');

        DB::statement('DROP INDEX idx_activities_patient ON activities');
        DB::statement('DROP INDEX idx_activities_service ON activities');
        DB::statement('DROP INDEX idx_activities_appointment ON activities');
        DB::statement('DROP INDEX idx_activities_centre ON activities');
        DB::statement('DROP INDEX idx_activities_invoice ON activities');
        DB::statement('DROP INDEX idx_activities_package ON activities');
    }
};
