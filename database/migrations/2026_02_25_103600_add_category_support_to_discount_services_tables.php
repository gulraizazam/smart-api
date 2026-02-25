<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategorySupportToDiscountServicesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('base_discount_services', function (Blueprint $table) {
            $table->boolean('is_category')->default(0)->after('service_id');
        });

        Schema::table('get_discount_services', function (Blueprint $table) {
            $table->boolean('same_service')->default(0)->after('service_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('base_discount_services', function (Blueprint $table) {
            $table->dropColumn('is_category');
        });

        Schema::table('get_discount_services', function (Blueprint $table) {
            $table->dropColumn('same_service');
        });
    }
}
