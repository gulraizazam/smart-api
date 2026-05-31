<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCancellationReasonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cancellation_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500);
            $table->unsignedTinyInteger('sort_no')->nullable();
            $table->unsignedTinyInteger('active')->default(1);

            $table->unsignedBigInteger('appointment_type_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id', 'cancellation_reasons_account')
                ->references('id')
                ->on('accounts');
            $table->foreign('appointment_type_id',
                'cancellation_reasons_appointment_type')
                ->references('id')
                ->on('appointment_types');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cancellation_reasons');
    }
}
