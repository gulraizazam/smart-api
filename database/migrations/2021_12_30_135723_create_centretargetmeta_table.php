<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCentretargetmetaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('centretargetmeta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedTinyInteger('month')->index();
            $table->year('year')->index();
            $table->unsignedBigInteger('location_id')->index();
            $table->unsignedBigInteger('centertarget_id')->index();
            $table->decimal('target_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id', 'centretargetmeta_account')
                ->references('id')->on('accounts');
            $table->foreign('location_id', 'centretargetmeta_location')
                ->references('id')->on('locations');
            $table->foreign('centertarget_id', 'centretarget_meta_id')
                ->references('id')->on('centertarget');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('centretargetmeta');
    }
}
