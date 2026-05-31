<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('duration_type'); // full, half, short
            $table->decimal('total_days', 5, 2);
            $table->text('reason');
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->unsignedInteger('account_id');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'account_id']);
            $table->index(['status', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
