<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('paid_from_pool_id');
            $table->unsignedInteger('for_branch_id')->nullable()->comment('NULL = General / Company-wide');
            $table->unsignedInteger('payment_method_id');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedInteger('staff_id')->nullable()->comment('Expense By staff member');
            $table->text('description');
            $table->string('reference_no')->nullable();
            $table->string('attachment_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['approved', 'pending', 'rejected'])->default('pending');
            $table->unsignedInteger('verified_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_flagged')->default(0);
            $table->string('flag_reason')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('voided_at')->nullable();
            $table->unsignedInteger('voided_by')->nullable();
            $table->text('void_reason')->nullable();
            $table->text('edit_reason')->nullable();
            $table->boolean('is_for_general')->default(0)->comment('1 = General/Company-wide expense');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('expense_categories');
            $table->foreign('paid_from_pool_id')->references('id')->on('cash_pools');
            $table->foreign('for_branch_id')->references('id')->on('locations')->onDelete('set null');
            $table->foreign('payment_method_id')->references('id')->on('payment_modes');
            $table->foreign('vendor_id')->references('id')->on('cashflow_vendors')->onDelete('set null');
            $table->foreign('staff_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('voided_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['account_id', 'expense_date', 'paid_from_pool_id'], 'exp_date_pool_idx');
            $table->index(['account_id', 'expense_date', 'for_branch_id'], 'exp_date_branch_idx');
            $table->index(['account_id', 'vendor_id'], 'exp_vendor_idx');
            $table->index(['account_id', 'staff_id'], 'exp_staff_idx');
            $table->index(['account_id', 'status'], 'exp_status_idx');
            $table->index(['account_id', 'is_flagged'], 'exp_flagged_idx');
            $table->index('voided_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
