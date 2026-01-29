<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddActualPriceToPackageServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('package_services', function (Blueprint $table) {
            $table->decimal('actual_price', 10, 2)->nullable()->after('tax_including_price');
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
            $table->dropColumn('actual_price');
        });
    }
}
