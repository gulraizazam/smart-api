<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('requested_by');
            $table->enum('status', ['pending', 'approved', 'dismissed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable()->comment('Linked vendor if approved');
            $table->timestamps();

            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('vendor_id')->references('id')->on('cashflow_vendors')->onDelete('set null');

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_requests');
    }
};
