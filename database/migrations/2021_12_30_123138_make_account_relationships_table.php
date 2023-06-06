<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeAccountRelationshipsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /* // Regions
         Schema::table('regions', function (Blueprint $table) {
             $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
             $table->foreign('account_id', 'regions_account')
                 ->references('id')
                 ->on('accounts');
         });*/

        // Cities
        /*Schema::table('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
            $table->foreign('account_id', 'cities_account')
                ->references('id')
                ->on('accounts');
        });*/

        // Locations
        /*Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
            $table->foreign('account_id', 'locations_account')
                ->references('id')
                ->on('accounts');
        });*/

        // Lead Sources
        /*Schema::table('lead_sources', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
            $table->foreign('account_id','lead_sources_account')
                ->references('id')
                ->on('accounts');
        });*/

        // Lead Statuses
        /* Schema::table('lead_statuses', function (Blueprint $table) {
             $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
             $table->foreign('account_id','lead_statuses_account')
                 ->references('id')
                 ->on('accounts');
         });*/

        // Leads
        /*Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
            $table->foreign('account_id','leads_account')
                ->references('id')
                ->on('accounts');
        });*/

        // Lead Comments
        /*Schema::table('lead_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
            $table->foreign('account_id','lead_comments_account')
                ->references('id')
                ->on('accounts');
        });*/

        // Appointment Statuses
        /*Schema::table('appointment_statuses', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
            $table->foreign('account_id','appointment_statuses_account')
                ->references('id')
                ->on('accounts');
        });*/

        // Cancellation Reasons
        /* Schema::table('cancellation_reasons', function (Blueprint $table) {
             $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
             $table->foreign('account_id','cancellation_reasons_account')
                 ->references('id')
                 ->on('accounts');
         });*/

        // Appointments
        /* Schema::table('appointments', function (Blueprint $table) {
             $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
             $table->foreign('account_id','appointments_account')
                 ->references('id')
                 ->on('accounts');
         });*/

        // SMS Templates
        /*Schema::table('sms_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->before('created_at');
            $table->foreign('account_id', 'sms_templates_account')
                ->references('id')
                ->on('accounts');
        });*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropForeign('regions_account');
            $table->dropColumn('account_id');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropForeign('cities_account');
            $table->dropColumn('account_id');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign('locations_account');
            $table->dropColumn('account_id');
        });

        Schema::table('lead_sources', function (Blueprint $table) {
            $table->dropForeign('lead_sources_account');
            $table->dropColumn('account_id');
        });

        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->dropForeign('lead_statuses_account');
            $table->dropColumn('account_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign('leads_account');
            $table->dropColumn('account_id');
        });

        Schema::table('lead_comments', function (Blueprint $table) {
            $table->dropForeign('lead_comments_account');
            $table->dropColumn('account_id');
        });

        Schema::table('appointment_statuses', function (Blueprint $table) {
            $table->dropForeign('appointment_statuses_account');
            $table->dropColumn('account_id');
        });

        Schema::table('cancellation_reasons', function (Blueprint $table) {
            $table->dropForeign('cancellation_reasons_account');
            $table->dropColumn('account_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign('appointments_account');
            $table->dropColumn('account_id');
        });

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropForeign('sms_templates_account');
            $table->dropColumn('account_id');
        });
    }
}
