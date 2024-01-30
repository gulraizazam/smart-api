<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAmountToMembershipTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('membership_types')) {
            Schema::table('membership_types', function (Blueprint $table) {
                if (!Schema::hasColumn('membership_types', 'amount')) {
                    $table->decimal('amount', 8, 2)->default(0.00)->after('active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('membership_types')) {
            Schema::table('membership_types', function (Blueprint $table) {
                if (Schema::hasColumn('membership_types', 'amount')) {
                    $table->dropColumn('amount');
                }
            });
        }
    }
}
