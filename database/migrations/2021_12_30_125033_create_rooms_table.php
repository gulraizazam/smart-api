<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->String('name');
            $table->unsignedTinyInteger('sort_no')->nullable();
            $table->unsignedTinyInteger('active')->default(1);

            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('resource_type_id')->nullable();

            // Foreign Key Relationships
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('resource_type_id', 'rooms_resource_type')->references('id')->on('resource_types');

            $table->timestamps();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rooms');
    }
}
