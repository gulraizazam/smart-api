<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('parent_id', 500);
            $table->string('name', 500);
            $table->string('is_comment', 500)->default(0);
            $table->unsignedTinyInteger('is_default')->default(0);
            $table->unsignedTinyInteger('is_arrived')->default(0);
            $table->unsignedTinyInteger('is_converted')->default(0);
            $table->unsignedTinyInteger('is_junk')->default(0);

            $table->unsignedTinyInteger('sort_no')->nullable();
            $table->unsignedTinyInteger('active')->default(1);

            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id', 'lead_statuses_account')
                ->references('id')
                ->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lead_statuses');
    }
}
