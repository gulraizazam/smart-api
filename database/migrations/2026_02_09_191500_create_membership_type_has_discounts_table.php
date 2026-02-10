<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMembershipTypeHasDiscountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('membership_type_has_discounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_type_id');
            $table->unsignedInteger('discount_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['membership_type_id', 'discount_id'], 'membership_discount_unique');
            $table->index('membership_type_id');
            $table->index('discount_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('membership_type_has_discounts');
    }
}
