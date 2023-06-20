<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lead_comments', function (Blueprint $table) {
            $table->id();
            $table->text('comment')->nullable();

            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Manage Foreign Key Relationships Mapping
            $table->foreign('account_id', 'lead_comments_account')
                ->references('id')
                ->on('accounts');
            $table->foreign('lead_id')
                ->references('id')
                ->on('leads');
            $table->foreign('created_by')
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
        Schema::dropIfExists('lead_comments');
    }
}
