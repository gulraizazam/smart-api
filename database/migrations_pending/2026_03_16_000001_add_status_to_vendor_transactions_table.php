<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddStatusToVendorTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_transactions', function (Blueprint $table) {
            $table->enum('status', ['ordered', 'delivered'])->default('delivered');
        });

        // Back-fill existing purchase records as 'delivered'
        DB::table('vendor_transactions')
            ->where('type', 'purchase')
            ->update(['status' => 'delivered']);
    }

    public function down(): void
    {
        Schema::table('vendor_transactions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
