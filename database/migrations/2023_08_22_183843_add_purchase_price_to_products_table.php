<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPurchasePriceToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
              if (!Schema::hasColumn('products', 'purchase_price')) {
                $table->decimal('purchase_price',10,2)->before('created_at');
              }
              if (!Schema::hasColumn('products', 'product_type')) {
                $table->enum('product_type', ['in_house_use', 'for_sale'])->before('created_at');
              }
              if (!Schema::hasColumn('products', 'created_by')) {
                $table->unsignedInteger('created_by')->before('created_at');
              }
              if (!Schema::hasColumn('products', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->before('created_at');
              }
              
              
            });
          }
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
              if (Schema::hasColumn('products', 'purchase_price')) {
                $table->dropColumn('purchase_price');
              }
              if (Schema::hasColumn('products', 'product_type')) {
                $table->dropColumn('product_type');
              }
              if (Schema::hasColumn('products', 'created_by')) {
                $table->dropColumn('created_by');
              }
              if (Schema::hasColumn('products', 'updated_by')) {
                $table->dropColumn('updated_by');
              }
             
             
            });
          }
    }
      
    
}
