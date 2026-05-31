<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->string('fiscal_year', 7); // e.g. "2025-26"
            $table->decimal('allocated', 5, 2)->default(0);
            $table->decimal('used', 5, 2)->default(0);

            $table->unsignedInteger('account_id');

            $table->unique(['user_id', 'leave_type_id', 'fiscal_year'], 'leave_balances_unique');
            $table->timestamps();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
