<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track which input mode the operator used when setting a bundle's
 * price — either typing the net price directly or entering a percent
 * off the services total.
 *
 * Without this column the package edit form had to *infer* the mode
 * by comparing `price` to `services_price`. That heuristic always
 * defaulted to "Discount %" whenever `price < services_price`, even
 * for bundles where the operator originally typed a net price. When
 * the operator reopened such a bundle the form silently presented a
 * computed percentage they hadn't entered, which read as "the system
 * lost what I typed". Persisting the mode lets the form open in the
 * same shape it was saved in.
 *
 * Nullable: existing rows have no historical record of input mode.
 * The frontend treats NULL as "default to net price" — the safer
 * assumption, since net is the raw stored value either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            // 'discount' | 'net' — varchar 8 is enough headroom; nullable
            // for legacy rows that pre-date this column.
            $table->string('pricing_mode', 8)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn('pricing_mode');
        });
    }
};
