<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConfigGroupIdToPackageBundlesTable extends Migration
{
    public function up()
    {
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->string('`config_group_id`')->nullable()->after('discount_id');
        });
    }

    public function down()
    {
        Schema::table('package_bundles', function (Blueprint $table) {
            $table->dropColumn('config_group_id');
        });
    }
}
