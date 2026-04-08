<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Right-size 35 oversized VARCHAR(500+) columns based on actual data max lengths.
     *
     * All columns verified: no data exceeds the new limit.
     * Sizes chosen with 2-4x headroom above current max.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('created_by', 100)->nullable()->change();    // max 20
            $table->string('lead_status', 100)->nullable()->change();   // max 9
            $table->string('patient', 191)->nullable()->change();       // max 91
            $table->string('service', 191)->nullable()->change();       // max 51
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 50
        });

        Schema::table('appointment_statuses', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 15
            $table->string('is_comment', 100)->nullable()->change();    // max 1
            $table->string('parent_id', 100)->nullable()->change();     // max 1
        });

        Schema::table('audit_trail_actions', function (Blueprint $table) {
            $table->string('name', 100)->change();                      // max 8
        });

        Schema::table('audit_trail_tables', function (Blueprint $table) {
            $table->string('name', 100)->change();                      // max 28
            $table->string('screen', 100)->nullable()->change();        // max 28
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->string('name', 191)->nullable()->change();          // max 77
            $table->string('bundle_type', 100)->nullable()->change();   // max 12
        });

        Schema::table('bundle_has_services', function (Blueprint $table) {
            $table->string('discount_type', 100)->nullable()->change(); // max 0
        });

        Schema::table('cancellation_reasons', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 19
        });

        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->string('attachment_url', 191)->nullable()->change(); // max 85
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->string('document_type', 100)->nullable()->change(); // max 17
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('attachment_url', 191)->nullable()->change(); // max 85
        });

        Schema::table('invoice_statuses', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 9
        });

        Schema::table('lead_sources', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 15
        });

        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 14
            $table->string('is_comment', 100)->nullable()->change();    // max 1
            $table->string('parent_id', 100)->nullable()->change();     // max 1
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 26
            $table->string('fdo_name', 100)->nullable()->change();      // max 35
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->string('parent_membership_code', 100)->nullable()->change(); // max 6
        });

        Schema::table('package_bundles', function (Blueprint $table) {
            $table->string('config_group_id', 100)->nullable()->change(); // max 0
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('name', 191)->nullable()->change();          // max 61
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 45
        });

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->string('name', 191)->nullable()->change();          // max 65
        });

        Schema::table('telecomprovidernumbers', function (Blueprint $table) {
            $table->string('pre_fix', 100)->nullable()->change();       // max 4
        });

        Schema::table('telecomproviders', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();          // max 8
        });

        Schema::table('vendor_transactions', function (Blueprint $table) {
            $table->string('attachment_url', 191)->nullable()->change(); // max 85
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('manager_name', 100)->nullable()->change();  // max 0
        });
    }

    public function down(): void
    {
        // Revert all to original varchar(500+)
        $revertTo500 = [
            'activities' => ['created_by', 'lead_status', 'service'],
            'appointments' => ['name'],
            'appointment_statuses' => ['name', 'is_comment', 'parent_id'],
            'audit_trail_actions' => ['name'],
            'audit_trail_tables' => ['name', 'screen'],
            'bundles' => ['name', 'bundle_type'],
            'bundle_has_services' => ['discount_type'],
            'cancellation_reasons' => ['name'],
            'cash_transfers' => ['attachment_url'],
            'documents' => ['document_type'],
            'expenses' => ['attachment_url'],
            'invoice_statuses' => ['name'],
            'lead_sources' => ['name'],
            'lead_statuses' => ['name', 'is_comment', 'parent_id'],
            'locations' => ['name', 'fdo_name'],
            'package_bundles' => ['config_group_id'],
            'services' => ['name'],
            'settings' => ['name'],
            'sms_templates' => ['name'],
            'telecomprovidernumbers' => ['pre_fix'],
            'telecomproviders' => ['name'],
            'vendor_transactions' => ['attachment_url'],
            'warehouses' => ['manager_name'],
        ];

        foreach ($revertTo500 as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $col) {
                    $blueprint->string($col, 500)->nullable()->change();
                }
            });
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->string('patient', 599)->nullable()->change();
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->string('parent_membership_code', 555)->nullable()->change();
        });
    }
};
