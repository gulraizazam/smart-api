<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditTrailChangesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audit_trail_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audit_trail_id');

            $table->string('field_name', 500);
            $table->text('field_before');
            $table->text('field_after');

            // Manage Foreign Key Relationships
            $table->foreign('audit_trail_id')
                ->references('id')
                ->on('audit_trails');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('audit_trail_changes');
    }
}
