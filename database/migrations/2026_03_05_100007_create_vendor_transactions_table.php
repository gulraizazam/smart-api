<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('vendor_id');
            $table->enum('type', ['purchase', 'payment']);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('expense_id')->nullable()->comment('Linked expense for payments');
            $table->text('description')->nullable();
            $table->string('reference_no')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('cashflow_vendors');
            $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');

            $table->index(['account_id', 'vendor_id']);
            $table->index(['account_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_transactions');
    }
};
