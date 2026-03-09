<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_transactions', function (Blueprint $table) {
            $table->date('transaction_date')->nullable()->after('reference_no');
            $table->unsignedBigInteger('for_branch_id')->nullable()->after('transaction_date');
            $table->boolean('is_for_general')->default(0)->after('for_branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_transactions', function (Blueprint $table) {
            $table->dropColumn(['transaction_date', 'for_branch_id', 'is_for_general']);
        });
    }
};
