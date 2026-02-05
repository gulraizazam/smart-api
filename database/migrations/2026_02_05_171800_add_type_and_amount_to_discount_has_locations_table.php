<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeAndAmountToDiscountHasLocationsTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds type and amount columns to discount_has_locations table
     * to allow override of discount values per location/service allocation.
     * If these fields are null, the system will use the default values from the discounts table.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('discount_has_locations', function (Blueprint $table) {
            $table->enum('type', ['Fixed', 'Percentage'])->nullable()->after('service_id');
            $table->double('amount', 11, 2)->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('discount_has_locations', function (Blueprint $table) {
            $table->dropColumn(['type', 'amount']);
        });
    }
}
