<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTelecomprovidernumbersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('telecomprovidernumbers', function (Blueprint $table) {
            $table->id();
            $table->string('pre_fix', 500);
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedBigInteger('telecomprovider_id');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('telecomprovider_id')->references('id')->on('telecomproviders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('telecomprovidernumbers');
    }
}
