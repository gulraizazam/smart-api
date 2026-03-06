<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('created_by');
            $table->string('void_reason', 100)->nullable()->after('voided_at');
            $table->unsignedInteger('voided_by')->nullable()->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'void_reason', 'voided_by']);
        });
    }
};
