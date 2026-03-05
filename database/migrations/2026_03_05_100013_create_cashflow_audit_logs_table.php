<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedInteger('user_id')->nullable()->comment('NULL for system/auto actions');
            $table->enum('action', [
                'created', 'updated', 'voided', 'approved', 'rejected',
                'resubmitted', 'locked', 'unlocked', 'deactivated',
                'auto_created', 'reset'
            ]);
            $table->string('entity_type', 50)->comment('expense, transfer, vendor, etc.');
            $table->unsignedBigInteger('entity_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // NO updated_at, NO soft deletes — write-only, immutable
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['account_id', 'entity_type', 'entity_id'], 'audit_entity_idx');
            $table->index(['account_id', 'action'], 'audit_action_idx');
            $table->index(['account_id', 'user_id'], 'audit_user_idx');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_audit_logs');
    }
};
