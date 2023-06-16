<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadSourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500);
            $table->unsignedTinyInteger('sort_no')->nullable();

            $table->unsignedTinyInteger('active')->default(1);

            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id', 'lead_sources_account')
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
        Schema::dropIfExists('lead_sources');
    }
}
