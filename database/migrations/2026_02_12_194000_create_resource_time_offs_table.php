<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resource_time_offs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('account_id');
            $table->string('type')->default('annual_leave'); // annual_leave, sick_leave, personal_leave, unpaid_leave, other
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_full_day')->default(false);
            $table->boolean('is_repeat')->default(false);
            $table->date('repeat_until')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['resource_id', 'start_date']);
            $table->index(['location_id', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_time_offs');
    }
};
