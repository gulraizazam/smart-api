<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('active')->default(1);
            $table->unsignedTinyInteger('msg_count')->default(0);

            // Foreign Key Relationships
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('lead_source_id')->nullable();
            $table->unsignedBigInteger('lead_status_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('converted_by')->nullable();
            $table->unsignedBigInteger('town_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id', 'leads_account')
                ->references('id')
                ->on('accounts');
            $table->foreign('town_id')
                ->references('id')
                ->on('towns');
            $table->foreign('patient_id')
                ->references('id')
                ->on('users');
            $table->foreign('city_id')
                ->references('id')
                ->on('cities');
            $table->foreign('region_id')
                ->references('id')
                ->on('regions');
            $table->foreign('lead_source_id')
                ->references('id')
                ->on('lead_sources');
            $table->foreign('lead_status_id')
                ->references('id')
                ->on('lead_statuses');
            $table->foreign('service_id')
                ->references('id')
                ->on('services');
            $table->foreign('created_by')
                ->references('id')
                ->on('users');
            $table->foreign('updated_by')
                ->references('id')
                ->on('users');
            $table->foreign('converted_by')
                ->references('id')
                ->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leads');
    }
}
