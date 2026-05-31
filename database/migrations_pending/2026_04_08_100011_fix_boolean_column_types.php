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
            \Log::warning("Skipping change on {$table}.{$column}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        if (Schema::hasTable('sms_logs') && Schema::hasColumn('sms_logs', 'is_refund')) {
            try {
                DB::statement("UPDATE sms_logs SET is_refund = CASE WHEN is_refund = 'Yes' THEN '1' WHEN is_refund = '' THEN '0' ELSE is_refund END");
            } catch (\Throwable $e) {
                \Log::warning('sms_logs.is_refund cleanup skipped: ' . $e->getMessage());
            }
        }

        $this->safeChange('packages', 'is_refund', function (Blueprint $t) {
            $t->boolean('is_refund')->default(false)->change();
        });

        $this->safeChange('package_advances', 'is_refund', function (Blueprint $t) {
            $t->boolean('is_refund')->default(false)->change();
        });
        $this->safeChange('package_advances', 'is_adjustment', function (Blueprint $t) {
            $t->boolean('is_adjustment')->default(false)->change();
        });
        $this->safeChange('package_advances', 'is_cancel', function (Blueprint $t) {
            $t->boolean('is_cancel')->default(false)->change();
        });

        $this->safeChange('products', 'active', function (Blueprint $t) {
            $t->boolean('active')->default(true)->change();
        });

        $this->safeChange('sms_logs', 'is_refund', function (Blueprint $t) {
            $t->boolean('is_refund')->default(false)->change();
        });
    }

    public function down(): void
    {
        $this->safeChange('packages', 'is_refund', function (Blueprint $t) {
            $t->unsignedInteger('is_refund')->default(0)->change();
        });
        $this->safeChange('package_advances', 'is_refund', function (Blueprint $t) {
            $t->unsignedInteger('is_refund')->default(0)->change();
        });
        $this->safeChange('package_advances', 'is_adjustment', function (Blueprint $t) {
            $t->unsignedInteger('is_adjustment')->default(0)->change();
        });
        $this->safeChange('package_advances', 'is_cancel', function (Blueprint $t) {
            $t->unsignedInteger('is_cancel')->default(0)->change();
        });
        $this->safeChange('products', 'active', function (Blueprint $t) {
            $t->integer('active')->default(1)->change();
        });
        $this->safeChange('sms_logs', 'is_refund', function (Blueprint $t) {
            $t->string('is_refund', 191)->default('')->change();
        });
    }
};
