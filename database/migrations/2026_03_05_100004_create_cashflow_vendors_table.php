<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->enum('payment_terms', ['upfront', 'net_7', 'net_15', 'net_30', 'custom'])->default('upfront');
            $table->string('category')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('cached_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(1);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['account_id', 'name']);
            $table->index(['account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_vendors');
    }
};
