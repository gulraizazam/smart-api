<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Collapse duplicate `leads_services` rows down to one per unique
 * (lead_id, service_id, child_service_id) tuple. Two causes of duplicates
 * are being addressed together:
 *
 *   1. Phantom rows from the old LeadService::updateExistingLead() path
 *      which double-called createLeadService on the new-phone branch —
 *      fixed in a previous migration. These look like two rows with the
 *      exact same timestamp for the same service.
 *
 *   2. Legitimate re-inquiries for the same category that the legacy
 *      code kept appending as new rows. Product decision: a re-inquiry
 *      for the same (category, service) doesn't warrant a new row; the
 *      going-forward createLeadService now upserts instead of inserting.
 *      Historical rows are collapsed here to match that contract.
 *
 * Strategy: keep MAX(id) per unique tuple (the newest row carries the
 * most recent status/updated_at), delete the rest. NULL-safe comparison
 * on child_service_id via the <=> operator so nullable tuples group
 * correctly.
 *
 * Non-reversible — rows are deleted, not archived. Same data is
 * effectively preserved via the surviving MAX(id) row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads_services')) {
            return;
        }

        $before = (int) DB::table('leads_services')->count();

        /*
         * Build the "keep list" once, then delete everything outside it.
         * Done in a single DELETE JOIN for efficiency on a large table.
         * MariaDB 11 both support the <=> NULL-safe operator.
         */
        DB::statement('
            DELETE ls FROM leads_services ls
            INNER JOIN (
                SELECT lead_id, service_id, child_service_id, MAX(id) AS keep_id
                FROM leads_services
                GROUP BY lead_id, service_id, child_service_id
                HAVING COUNT(*) > 1
            ) dup
              ON ls.lead_id = dup.lead_id
             AND (ls.service_id <=> dup.service_id)
             AND (ls.child_service_id <=> dup.child_service_id)
             AND ls.id <> dup.keep_id
        ');

        $after = (int) DB::table('leads_services')->count();
        $removed = $before - $after;

        // Log into the app log so the cleanup volume is recorded with the
        // deploy, not just the migrations table.
        Log::info('leads_services.dedupe', [
            'event' => 'leads_services.dedupe.completed',
            'before' => $before,
            'after' => $after,
            'removed' => $removed,
        ]);
    }

    public function down(): void
    {
        // Irreversible — the collapsed rows were true duplicates and
        // carry no information not already represented by the surviving
        // MAX(id) row. Rolling back would require a pre-migration backup,
        // which is out of this file's scope.
    }
};
