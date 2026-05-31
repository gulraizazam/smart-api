<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CheckValues extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::unprepared('CREATE TRIGGER check_update_time AFTER UPDATE ON `package_advances` FOR EACH ROW
        BEGIN
           INSERT INTO `track_values` (`update_time`) VALUES (`1`);
        END');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP TRIGGER `add_Item_city`');
    }
}
