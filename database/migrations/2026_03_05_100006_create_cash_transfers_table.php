<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->date('transfer_date');
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('from_pool_id');
            $table->unsignedBigInteger('to_pool_id');
            $table->enum('method', ['physical_cash', 'bank_deposit']);
            $table->string('reference_no');
            $table->string('attachment_url', 500);
            $table->text('description')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('from_pool_id')->references('id')->on('cash_pools');
            $table->foreign('to_pool_id')->references('id')->on('cash_pools');
            $table->foreign('created_by')->references('id')->on('users');

            $table->index(['account_id', 'transfer_date']);
            $table->index(['account_id', 'from_pool_id']);
            $table->index(['account_id', 'to_pool_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
