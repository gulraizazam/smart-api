<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->default('custom');
            $table->string('name', 500);
            $table->unsignedTinyInteger('sort_no')->nullable();

            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('tax_treatment_type_id');

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('duration')->nullable();
            $table->double('price', 11, 2)->default(0.00);
            $table->string('color')->nullable();
            $table->unsignedTinyInteger('end_node')->default(0);
            $table->unsignedBigInteger('account_id');
            $table->unsignedTinyInteger('complimentory')->default(0);

            // Foreign Key Relationships
            $table->foreign('account_id', 'services_account')->references('id')->on('accounts');

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
        Schema::dropIfExists('services');
    }
}
