<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_trails_archive')) {
            Schema::create('audit_trails_archive', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedInteger('audit_trail_action_name');
                $table->unsignedInteger('audit_trail_table_name');
                $table->unsignedBigInteger('table_record_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->timestamps();

                $table->index('user_id', 'idx_archive_trails_user');
                $table->index('created_at', 'idx_archive_trails_created');
            });
        }

        if (! Schema::hasTable('audit_trail_changes_archive')) {
            Schema::create('audit_trail_changes_archive', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->unsignedBigInteger('audit_trail_id');
                $table->string('field_name', 500);
                $table->text('field_before')->nullable();
                $table->text('field_after')->nullable();
                $table->timestamps();

                $table->index('audit_trail_id', 'idx_archive_changes_trail');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trail_changes_archive');
        Schema::dropIfExists('audit_trails_archive');
    }
};
