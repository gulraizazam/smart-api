<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop dead tables with 0 rows and no application code.
     *
     * rooms            — no model, no controller, no routes; Resources model queries resources table not rooms
     * taxes            — no model; app uses tax_treatment_type instead
     * purchases        — orphan model never called; replaced by VendorTransaction
     * purchase_details — child of purchases, also never called; inventory uses product_details
     * refunds          — Refunds model points to package_advances table; this table is never touched
     */
    public function up(): void
    {
        Schema::dropIfExists('purchase_details');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('refunds');
    }

    public function down(): void
    {
        // Tables can be recreated from the original migrations if needed:
        // 2021_12_30_131359_create_rooms_table.php
        // 2021_12_30_125114_create_taxes_table.php
        // 2022_11_29_120804_create_purchases_table.php
        // 2022_11_29_121416_create_purchase_details_table.php
        // 2021_12_30_130225_create_refunds_table.php
    }
};
