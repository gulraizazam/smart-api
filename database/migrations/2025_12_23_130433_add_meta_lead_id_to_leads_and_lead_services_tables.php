<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMetaLeadIdToLeadsAndLeadServicesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add meta_lead_id to leads table (if not exists)
        if (!Schema::hasColumn('leads', 'meta_lead_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('meta_lead_id')->nullable()->after('id');
            });
        }

        // Add meta_lead_id and lead_status_id to leads_services table (if not exists)
        Schema::table('leads_services', function (Blueprint $table) {
            if (!Schema::hasColumn('leads_services', 'meta_lead_id')) {
                $table->string('meta_lead_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('leads_services', 'lead_status_id')) {
                $table->unsignedBigInteger('lead_status_id')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads_services', function (Blueprint $table) {
            $table->dropColumn(['meta_lead_id', 'lead_status_id']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('meta_lead_id');
        });
    }
}
