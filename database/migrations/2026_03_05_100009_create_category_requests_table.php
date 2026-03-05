<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('requested_by');
            $table->enum('status', ['pending', 'approved', 'dismissed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('category_id')->nullable()->comment('Linked category if approved');
            $table->timestamps();

            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('category_id')->references('id')->on('expense_categories')->onDelete('set null');

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_requests');
    }
};
