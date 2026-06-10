<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The discount allocation dialog historically tagged every simple (fixed /
 * percentage) allocation with slug='custom'. That routed it through the
 * operator-entered, percentage-oriented discount-validation path, which
 * rejected fixed rupee amounts when applied on a plan ("limit exceed").
 *
 * Simple allocations are meant to auto-apply their per-centre pivot amount,
 * which is the slug='default' path. Configurable allocations use
 * slug='configurable' and are left untouched. This is display/behaviour only —
 * `slug` is a crm3-added column the legacy crm2 app does not read, so the
 * change is additive vs the shared DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('discount_has_locations')
            ->where('slug', 'custom')
            ->update(['slug' => 'default']);
    }

    public function down(): void
    {
        // Intentionally irreversible: the original 'custom' tagging was the bug,
        // and once flipped these rows are indistinguishable from pre-existing
        // 'default' allocations, so a precise restore isn't possible. Restoring
        // 'custom' would re-break fixed allocations.
    }
};
