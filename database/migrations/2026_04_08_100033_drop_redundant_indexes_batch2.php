<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop redundant indexes that are leftmost prefixes of wider indexes.
     */
    public function up(): void
    {
        // bundle_has_services: bundle_id_foreign is prefix of both uq_bundle_service AND idx_composite
        //   Also idx_composite(bundle_id, service_id) duplicates uq_bundle_service(bundle_id, service_id)
        DB::statement('ALTER TABLE bundle_has_services
            DROP INDEX bundle_has_services_bundle_id_foreign,
            DROP INDEX idx_bundle_has_services_composite');

        // discount_has_locations: discount_id_foreign is prefix of idx_composite
        DB::statement('ALTER TABLE discount_has_locations
            DROP INDEX discount_has_locations_discount_id_foreign');

        // doctor_google_reviews: account_id_index is prefix of doctor_reviews_unique
        DB::statement('ALTER TABLE doctor_google_reviews
            DROP INDEX doctor_google_reviews_account_id_index');

        // doctor_has_locations: user_id_foreign is prefix of idx_composite
        DB::statement('ALTER TABLE doctor_has_locations
            DROP INDEX doctor_has_locations_user_id_foreign');

        // invoices: idx_account_deleted is prefix of idx_account_deleted_created
        DB::statement('ALTER TABLE invoices
            DROP INDEX idx_invoices_account_deleted');

        // membership_type_has_discounts: membership_type_id_index is prefix of membership_discount_unique
        DB::statement('ALTER TABLE membership_type_has_discounts
            DROP INDEX membership_type_has_discounts_membership_type_id_index');

        // resources: account_id_foreign is prefix of idx_account_active
        DB::statement('ALTER TABLE resources
            DROP INDEX resources_account_id_foreign');

        // resource_has_rota: resource_id is prefix of idx_res_loc
        DB::statement('ALTER TABLE resource_has_rota
            DROP INDEX resource_has_rota_resource');

        // services: account is prefix of idx_account_active_endnode
        DB::statement('ALTER TABLE services
            DROP INDEX services_account');

        // system_targets: account_id_index is prefix of system_targets_unique
        DB::statement('ALTER TABLE system_targets
            DROP INDEX system_targets_account_id_index');
    }

    public function down(): void
    {
        // Recreate dropped indexes
        DB::statement('ALTER TABLE bundle_has_services ADD INDEX bundle_has_services_bundle_id_foreign (bundle_id)');
        DB::statement('ALTER TABLE bundle_has_services ADD INDEX idx_bundle_has_services_composite (bundle_id, service_id)');
        DB::statement('ALTER TABLE discount_has_locations ADD INDEX discount_has_locations_discount_id_foreign (discount_id)');
        DB::statement('ALTER TABLE doctor_google_reviews ADD INDEX doctor_google_reviews_account_id_index (account_id)');
        DB::statement('ALTER TABLE doctor_has_locations ADD INDEX doctor_has_locations_user_id_foreign (user_id)');
        DB::statement('ALTER TABLE invoices ADD INDEX idx_invoices_account_deleted (account_id, deleted_at)');
        DB::statement('ALTER TABLE membership_type_has_discounts ADD INDEX membership_type_has_discounts_membership_type_id_index (membership_type_id)');
        DB::statement('ALTER TABLE resources ADD INDEX resources_account_id_foreign (account_id)');
        DB::statement('ALTER TABLE resource_has_rota ADD INDEX resource_has_rota_resource (resource_id)');
        DB::statement('ALTER TABLE services ADD INDEX services_account (account_id)');
        DB::statement('ALTER TABLE system_targets ADD INDEX system_targets_account_id_index (account_id)');
    }
};
