<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_pools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->enum('type', ['branch_cash', 'head_office_cash', 'bank_account']);
            $table->unsignedInteger('location_id')->nullable();
            $table->string('name');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('cached_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(1);
            $table->boolean('opening_balance_frozen')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            $table->index(['account_id', 'type']);
            $table->index(['account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_pools');
    }
};
