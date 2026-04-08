<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix boolean columns using wrong data types.
     *
     * Data audit (all columns contain only 0/1 values):
     *   packages.is_refund:           INT unsigned → TINYINT (46785×0, 248×1)
     *   package_advances.is_refund:   INT unsigned → TINYINT (831070×0, 274×1)
     *   package_advances.is_adjustment: INT unsigned → TINYINT (831317×0, 27×1)
     *   package_advances.is_cancel:   INT unsigned → TINYINT (831340×0, 4×1)
     *   products.active:              INT → TINYINT (106×1)
     *   sms_logs.is_refund:           VARCHAR(191) → TINYINT (865148×'')
     */
    public function up(): void
    {
        // Clean sms_logs.is_refund: empty strings → 0, 'Yes' → 1
        DB::statement("UPDATE sms_logs SET is_refund = CASE WHEN is_refund = 'Yes' THEN '1' WHEN is_refund = '' THEN '0' ELSE is_refund END");

        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_refund')->default(false)->change();
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $table->boolean('is_refund')->default(false)->change();
            $table->boolean('is_adjustment')->default(false)->change();
            $table->boolean('is_cancel')->default(false)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('active')->default(true)->change();
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->boolean('is_refund')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('is_refund')->default(0)->change();
        });

        Schema::table('package_advances', function (Blueprint $table) {
            $table->unsignedInteger('is_refund')->default(0)->change();
            $table->unsignedInteger('is_adjustment')->default(0)->change();
            $table->unsignedInteger('is_cancel')->default(0)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('active')->default(1)->change();
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('is_refund', 191)->default('')->change();
        });
    }
};
