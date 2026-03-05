<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->unsignedInteger('locked_by');
            $table->json('balance_snapshot')->nullable();
            $table->text('unlock_reason')->nullable();
            $table->unsignedInteger('unlocked_by')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            $table->foreign('locked_by')->references('id')->on('users');
            $table->foreign('unlocked_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['account_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_locks');
    }
};
