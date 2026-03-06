<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_advances', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('created_by');
            $table->string('void_reason', 100)->nullable()->after('voided_at');
            $table->unsignedInteger('voided_by')->nullable()->after('void_reason');
        });

        Schema::table('staff_returns', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('created_by');
            $table->string('void_reason', 100)->nullable()->after('voided_at');
            $table->unsignedInteger('voided_by')->nullable()->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('staff_advances', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'void_reason', 'voided_by']);
        });
        Schema::table('staff_returns', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'void_reason', 'voided_by']);
        });
    }
};
