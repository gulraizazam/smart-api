<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLocationIdToOrdersTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    if (Schema::hasTable('orders')) {
      Schema::table('orders', function (Blueprint $table) {
        if (!Schema::hasColumn('orders', 'location_id')) {
          $table->unsignedBigInteger('location_id')->before('created_at');
        }
        if (!Schema::hasColumn('orders', 'is_refunded')) {
          $table->tinyInteger('is_refunded')->default(0)->before('created_at');
        }
        if (!Schema::hasColumn('orders', 'payment_mode')) {
          $table->unsignedBigInteger('payment_mode')->before('created_at');
        }
        if (!Schema::hasColumn('orders', 'updated_by')) {
          $table->unsignedBigInteger('updated_by')->nullable()->before('created_at');
        }

        if (!Schema::hasColumn('orders', 'quantity')) {
          $table->string('quantity')->default(0)->before('created_at');
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
    if (Schema::hasTable('orders')) {
      Schema::table('orders', function (Blueprint $table) {
        if (Schema::hasColumn('orders', 'location_id')) {
          $table->dropColumn('location_id');
        }
        if (Schema::hasColumn('orders', 'is_refunded')) {
          $table->dropColumn('is_refunded');
        }
        if (Schema::hasColumn('orders', 'payment_mode')) {
          $table->dropColumn('payment_mode');
        }
        if (Schema::hasColumn('orders', 'updated_by')) {
          $table->dropColumn('updated_by');
        }

        if (Schema::hasColumn('orders', 'quantity')) {
          $table->dropColumn('quantity');
        }
      });
    }
  }
}
