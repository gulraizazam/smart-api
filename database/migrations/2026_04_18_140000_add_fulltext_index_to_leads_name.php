<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a FULLTEXT index on leads.name so name search can use
 * MATCH(name) AGAINST(? IN BOOLEAN MODE) with per-token prefix wildcards
 * instead of `LIKE %x%`. The old LIKE pattern forces a full table scan
 * (no index can serve a leading-wildcard predicate); at 500k+ rows this
 * is visibly slow for telecallers on a call. BOOLEAN mode gives us
 * word-aware prefix matching ("Kha*" hits "Khan Rashid" and "Ayesha Khan")
 * and uses the FT index for O(log n) lookups.
 *
 * Applied ALGORITHM=INPLACE on InnoDB 8 so there's no table rebuild and
 * DML keeps flowing. A FULLTEXT index on a single varchar(255) column
 * adds a few MB of index pages — cheap at this row count.
 *
 * Rollback: drop the index. No data touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        // Skip if somehow already present (re-run safety).
        $exists = collect(DB::select('SHOW INDEX FROM leads'))
            ->contains(fn ($r) => $r->Key_name === 'idx_leads_name_ft');

        if (! $exists) {
            // FULLTEXT creation requires LOCK=SHARED in InnoDB 8 (the index
            // build needs to snapshot the column). Reads continue; writes
            // block for the ~few seconds the build takes. At 500k rows this
            // is a brief window; run during low-traffic if you care.
            DB::statement('ALTER TABLE leads ADD FULLTEXT INDEX idx_leads_name_ft (name), ALGORITHM=INPLACE, LOCK=SHARED');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads')) {
            return;
        }

        $exists = collect(DB::select('SHOW INDEX FROM leads'))
            ->contains(fn ($r) => $r->Key_name === 'idx_leads_name_ft');

        if ($exists) {
            DB::statement('ALTER TABLE leads DROP INDEX idx_leads_name_ft');
        }
    }
};
