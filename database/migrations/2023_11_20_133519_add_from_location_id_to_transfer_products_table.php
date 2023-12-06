<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class AddFromLocationIdToTransferProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transfer_products', function (Blueprint $table) {
            $table->foreignId('from_location_id')->nullable()->references('id')->on('locations');
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transfer_products', function (Blueprint $table) {
            $table->dropForeign('from_location_id');
            $table->dropColumn('from_location_id');
        });
    }
}