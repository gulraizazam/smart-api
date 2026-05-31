<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->unsignedInteger('reporting_manager_id')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('employment_type')->nullable();
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('bank_name')->nullable();
            $table->text('bank_account_number')->nullable(); // Encrypted at rest
            $table->string('tax_id')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();

            $table->unsignedInteger('account_id');
            $table->unsignedInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('department_id');
            $table->index('designation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_details');
    }
};
