<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletedByToActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
              if (!Schema::hasColumn('activities', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('created_by');
              }
              if (!Schema::hasColumn('activities', 'rescheduled_by')) {
                $table->unsignedBigInteger('rescheduled_by')->nullable()->after('deleted_by');
              }
              if (!Schema::hasColumn('activities', 'deleted_date')) {
                $table->date('deleted_date')->nullable()->after('deleted_by');
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
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table) {
              if (Schema::hasColumn('activities', 'deleted_by')) {
                $table->dropColumn('deleted_by');
              }
              if (Schema::hasColumn('activities', 'rescheduled_by')) {
                $table->dropColumn('rescheduled_by');
              }
              if (Schema::hasColumn('activities', 'deleted_date')) {
                $table->dropColumn('deleted_date');
              }
            });
          }
    }
}
