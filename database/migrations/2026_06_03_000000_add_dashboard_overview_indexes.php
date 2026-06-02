<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Management Dashboard Overview perf — equality-first composite indexes for
 * the hot aggregation queries (AppointmentCountsQuery, SalesLedgerQuery,
 * RevenueInvoiceQuery) that run on every Overview load (twice, for the MoM
 * deltas). The existing indexes are date-leading (e.g. idx_appt_date_type_account)
 * or single-column and miss the (account_id, [location_id], type/status,
 * date-range) access pattern these queries actually use.
 *
 * Purely ADDITIVE: indexes change query speed only, never results or schema
 * semantics — safe for crm2 on the shared DB. Owes a prod replay at the next
 * migration (local-vs-prod DB rule). On prod MariaDB builds these online
 * (ALGORITHM=INPLACE); still run off-peak as the tables are large.
 */
return new class extends Migration
{
    /** @var list<array{0:string,1:list<string>,2:string}> [table, columns, index name] */
    private array $indexes = [
        ['appointments', ['account_id', 'appointment_type_id', 'scheduled_date'], 'idx_appt_acct_type_date'],
        ['appointments', ['account_id', 'location_id', 'appointment_type_id', 'scheduled_date'], 'idx_appt_acct_loc_type_date'],
        ['package_advances', ['account_id', 'location_id', 'created_at'], 'idx_pa_acct_loc_created'],
        ['invoices', ['account_id', 'invoice_status_id', 'location_id', 'created_at'], 'idx_inv_acct_status_loc_created'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if ($this->indexExists($table, $name)) {
                continue;
            }
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $columns, $name]) {
            if (! $this->indexExists($table, $name)) {
                continue;
            }
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
