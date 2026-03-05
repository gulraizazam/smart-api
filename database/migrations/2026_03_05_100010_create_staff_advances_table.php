<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedInteger('user_id')->comment('Staff receiving advance');
            $table->unsignedBigInteger('pool_id')->comment('Cash pool advance given from');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('pool_id')->references('id')->on('cash_pools');
            $table->foreign('created_by')->references('id')->on('users');

            $table->index(['account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_advances');
    }
};
