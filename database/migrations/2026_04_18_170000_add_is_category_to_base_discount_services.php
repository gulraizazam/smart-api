<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the `is_category` flag to `base_discount_services`.
 *
 * The `BaseDiscountService` model has `is_category` in its `$fillable`
 * array and `DiscountService::buildBaseServices()` writes it on every
 * insert, but the column never existed on this schema. Every
 * Configurable discount create was fataling with
 * `SQLSTATE[42S22] Unknown column 'is_category'` — the service layer
 * was authored against a column that hadn't shipped.
 *
 * The flag distinguishes "buy any service from this category" (1)
 * from "buy this specific service" (0) in the configurable
 * Buy/Get semantics. Defaults to 0 (specific-service) so pre-existing
 * rows behave as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('base_discount_services', function (Blueprint $table): void {
            $table->unsignedTinyInteger('is_category')
                ->default(0)
                ->after('sessions');
        });
    }

    public function down(): void
    {
        Schema::table('base_discount_services', function (Blueprint $table): void {
            $table->dropColumn('is_category');
        });
    }
};
