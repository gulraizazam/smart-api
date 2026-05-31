<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('type'); // leave_submitted, leave_approved, leave_rejected, leave_cancelled
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_read')->default(false);

            $table->unsignedInteger('account_id');

            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_notifications');
    }
};
