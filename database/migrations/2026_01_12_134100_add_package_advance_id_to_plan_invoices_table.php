<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPackageAdvanceIdToPlanInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('plan_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('package_advance_id')->nullable()->after('package_id');
            
            // Add index for better query performance
            $table->index('package_advance_id');
            
            // Add foreign key constraint
            $table->foreign('package_advance_id')
                ->references('id')
                ->on('package_advances')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plan_invoices', function (Blueprint $table) {
            $table->dropForeign(['package_advance_id']);
            $table->dropIndex(['package_advance_id']);
            $table->dropColumn('package_advance_id');
        });
    }
}
