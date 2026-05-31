<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExists(string $table, string $name): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY' LIMIT 1",
            [$table, $name]
        );
        return ! empty($rows);
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );
        return ! empty($rows);
    }

    private function addFk(string $table, string $column, string $refTable, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($refTable) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $t) use ($column, $refTable, $name, $onDelete) {
                $t->foreign($column, $name)->references('id')->on($refTable)->onDelete($onDelete);
            });
        } catch (\Throwable $e) {
            \Log::warning("Skipping FK {$name}: " . $e->getMessage());
        }
    }

    private function dropFk(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->foreignKeyExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($name));
        } catch (\Throwable $e) {
            \Log::warning("Skipping dropForeign {$name}: " . $e->getMessage());
        }
    }

    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            \Log::warning('Statement failed: ' . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->safeStatement('DELETE FROM bundle_has_services WHERE bundle_id NOT IN (SELECT id FROM bundles)');
        $this->safeStatement('DELETE FROM bundle_has_services WHERE service_id NOT IN (SELECT id FROM services)');
        $this->safeStatement('DELETE FROM role_has_permissions WHERE permission_id NOT IN (SELECT id FROM permissions)');
        $this->safeStatement('DELETE FROM user_has_locations WHERE location_id NOT IN (SELECT id FROM locations)');
        $this->safeStatement('DELETE FROM discount_has_locations WHERE location_id NOT IN (SELECT id FROM locations)');

        // Rebuild bundle_has_services only if `id` column not yet present
        if (Schema::hasTable('bundle_has_services') && ! Schema::hasColumn('bundle_has_services', 'id')) {
            try {
                Schema::dropIfExists('bundle_has_services_tmp');
                DB::statement('CREATE TABLE bundle_has_services_tmp LIKE bundle_has_services');
                DB::statement('INSERT INTO bundle_has_services_tmp SELECT DISTINCT * FROM bundle_has_services');
                DB::statement('DROP TABLE bundle_has_services');
                DB::statement('RENAME TABLE bundle_has_services_tmp TO bundle_has_services');
                DB::statement('ALTER TABLE bundle_has_services ADD COLUMN id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
            } catch (\Throwable $e) {
                \Log::warning('bundle_has_services rebuild skipped: ' . $e->getMessage());
            }
        }

        if (Schema::hasTable('bundle_has_services') && ! $this->indexExists('bundle_has_services', 'uq_bundle_service')) {
            try {
                DB::statement('CREATE UNIQUE INDEX uq_bundle_service ON bundle_has_services(bundle_id, service_id)');
            } catch (\Throwable $e) {
                \Log::warning('uq_bundle_service skipped: ' . $e->getMessage());
            }
        }

        $this->addFk('bundle_has_services', 'bundle_id', 'bundles', 'fk_bhs_bundle', 'cascade');
        $this->addFk('bundle_has_services', 'service_id', 'services', 'fk_bhs_service', 'cascade');

        $this->addFk('role_has_permissions', 'permission_id', 'permissions', 'fk_rhp_permission', 'cascade');
        $this->addFk('role_has_permissions', 'role_id', 'roles', 'fk_rhp_role', 'cascade');

        $this->addFk('user_has_locations', 'user_id', 'users', 'fk_uhl_user', 'cascade');
        $this->addFk('user_has_locations', 'location_id', 'locations', 'fk_uhl_location', 'cascade');

        $this->addFk('doctor_has_locations', 'user_id', 'users', 'fk_dhl_user', 'cascade');
        $this->addFk('doctor_has_locations', 'location_id', 'locations', 'fk_dhl_location', 'cascade');
        $this->addFk('doctor_has_locations', 'service_id', 'services', 'fk_dhl_service', 'cascade');

        $this->addFk('discount_has_locations', 'discount_id', 'discounts', 'fk_discloc_discount', 'cascade');
        $this->addFk('discount_has_locations', 'location_id', 'locations', 'fk_discloc_location', 'cascade');
        $this->addFk('discount_has_locations', 'service_id', 'services', 'fk_discloc_service', 'cascade');
    }

    public function down(): void
    {
        $this->dropFk('bundle_has_services', 'fk_bhs_bundle');
        $this->dropFk('bundle_has_services', 'fk_bhs_service');

        if (Schema::hasTable('bundle_has_services') && $this->indexExists('bundle_has_services', 'uq_bundle_service')) {
            try {
                Schema::table('bundle_has_services', fn (Blueprint $t) => $t->dropIndex('uq_bundle_service'));
            } catch (\Throwable $e) {
                \Log::warning('drop uq_bundle_service skipped: ' . $e->getMessage());
            }
        }

        if (Schema::hasTable('bundle_has_services') && Schema::hasColumn('bundle_has_services', 'id')) {
            try {
                DB::statement('ALTER TABLE bundle_has_services DROP COLUMN id');
            } catch (\Throwable $e) {
                \Log::warning('drop id column skipped: ' . $e->getMessage());
            }
        }

        $this->dropFk('role_has_permissions', 'fk_rhp_permission');
        $this->dropFk('role_has_permissions', 'fk_rhp_role');
        $this->dropFk('user_has_locations', 'fk_uhl_user');
        $this->dropFk('user_has_locations', 'fk_uhl_location');
        $this->dropFk('doctor_has_locations', 'fk_dhl_user');
        $this->dropFk('doctor_has_locations', 'fk_dhl_location');
        $this->dropFk('doctor_has_locations', 'fk_dhl_service');
        $this->dropFk('discount_has_locations', 'fk_discloc_discount');
        $this->dropFk('discount_has_locations', 'fk_discloc_location');
        $this->dropFk('discount_has_locations', 'fk_discloc_service');
    }
};
