<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCardTypeToCashPoolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Originally added 'card' type but it was removed — card expenses use bank_account pools.
        // Revert enum back to original 3 types.
        DB::statement("ALTER TABLE cash_pools MODIFY COLUMN `type` ENUM('branch_cash','head_office_cash','bank_account') NOT NULL DEFAULT 'branch_cash'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // no-op
    }
}
