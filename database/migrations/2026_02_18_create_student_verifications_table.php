<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_verifications', function (Blueprint $table) {
            $table->id();
            $table->integer('patient_id')->unsigned();
            $table->integer('membership_id')->unsigned()->nullable();
            $table->integer('membership_type_id')->unsigned();
            $table->integer('package_id')->unsigned()->nullable();
            
            // Document paths (JSON array)
            $table->json('document_paths');
            
            // Verification status
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            
            // Approval workflow
            $table->integer('submitted_by')->unsigned();
            $table->integer('reviewed_by')->unsigned()->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_verifications');
    }
};
