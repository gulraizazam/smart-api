<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('google_drive_url')->nullable();
            $table->string('google_drive_file_id')->nullable();
            $table->string('drive_upload_status')->default('pending');
            $table->unsignedInteger('uploaded_by');

            $table->unsignedInteger('account_id');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
