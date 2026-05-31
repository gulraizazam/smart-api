<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function safeChange(string $table, string $column, callable $cb): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        try {
            Schema::table($table, $cb);
        } catch (\Throwable $e) {
            \Log::warning("Skipping {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'is_unscheduled')) {
            try {
                DB::statement("UPDATE appointments SET is_unscheduled = 0 WHERE is_unscheduled = '' OR is_unscheduled IS NULL");
            } catch (\Throwable $e) {
                \Log::warning('is_unscheduled cleanup skipped: ' . $e->getMessage());
            }
        }

        $this->safeChange('appointments', 'meta_purchase_sent', fn (Blueprint $t) => $t->boolean('meta_purchase_sent')->default(false)->change());
        $this->safeChange('appointments', 'is_unscheduled', fn (Blueprint $t) => $t->boolean('is_unscheduled')->default(false)->change());
        $this->safeChange('appointments', 'first_scheduled_count', fn (Blueprint $t) => $t->boolean('first_scheduled_count')->default(false)->change());
        $this->safeChange('appointments', 'send_message', fn (Blueprint $t) => $t->boolean('send_message')->default(false)->change());
        $this->safeChange('appointments', 'active', fn (Blueprint $t) => $t->boolean('active')->unsigned()->default(true)->change());
        $this->safeChange('appointments', 'scheduled_at_count', fn (Blueprint $t) => $t->unsignedTinyInteger('scheduled_at_count')->default(0)->change());

        $this->safeChange('package_advances', 'is_setteled', fn (Blueprint $t) => $t->boolean('is_setteled')->default(false)->change());
        $this->safeChange('package_advances', 'is_tax', fn (Blueprint $t) => $t->boolean('is_tax')->default(false)->change());

        $this->safeChange('users', 'can_perform_consultation', fn (Blueprint $t) => $t->boolean('can_perform_consultation')->default(false)->change());
        $this->safeChange('users', 'active', fn (Blueprint $t) => $t->boolean('active')->unsigned()->default(true)->change());
    }

    public function down(): void
    {
        $this->safeChange('appointments', 'meta_purchase_sent', fn (Blueprint $t) => $t->integer('meta_purchase_sent')->default(0)->change());
        $this->safeChange('appointments', 'is_unscheduled', fn (Blueprint $t) => $t->integer('is_unscheduled')->default(0)->change());
        $this->safeChange('appointments', 'first_scheduled_count', fn (Blueprint $t) => $t->integer('first_scheduled_count')->default(0)->change());
        $this->safeChange('appointments', 'send_message', fn (Blueprint $t) => $t->tinyInteger('send_message')->default(0)->change());
        $this->safeChange('appointments', 'active', fn (Blueprint $t) => $t->unsignedTinyInteger('active')->default(1)->change());
        $this->safeChange('appointments', 'scheduled_at_count', fn (Blueprint $t) => $t->integer('scheduled_at_count')->default(0)->change());

        $this->safeChange('package_advances', 'is_setteled', fn (Blueprint $t) => $t->integer('is_setteled')->default(0)->change());
        $this->safeChange('package_advances', 'is_tax', fn (Blueprint $t) => $t->unsignedTinyInteger('is_tax')->default(0)->change());

        $this->safeChange('users', 'can_perform_consultation', fn (Blueprint $t) => $t->integer('can_perform_consultation')->default(0)->change());
        $this->safeChange('users', 'active', fn (Blueprint $t) => $t->unsignedTinyInteger('active')->default(1)->change());
    }
};
