<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConsumptionOrderToPackageServicesTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * consumption_order values:
     * 0 = normal (no restriction)
     * 1 = BUY service (configurable discount - consume first)
     * 2 = discounted GET service (consume after BUY)
     * 3 = free GET service (consume last)
     *
     * @return void
     */
    public function up()
    {
        Schema::table('package_services', function (Blueprint $table) {
            $table->tinyInteger('consumption_order')->default(0)->after('is_consumed')
                  ->comment('0=normal, 1=BUY, 2=discounted GET, 3=free GET');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('package_services', function (Blueprint $table) {
            $table->dropColumn('consumption_order');
        });
    }
}
