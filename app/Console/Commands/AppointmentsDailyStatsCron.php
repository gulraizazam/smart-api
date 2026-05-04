<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Accounts;
use App\Models\AppointmentTypes;
use App\Models\Locations;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Snapshots consultation appointments + status into
 * `appointments_daily_stats`, keyed on (appointment_id, scheduled_date).
 *
 * Captures today + tomorrow because rescheduling can shift an
 * appointment between the two during the day; the 23:50 PKT run
 * preserves today's final state and tomorrow's in-progress state.
 *
 * Consumers:
 *   - `Admin\Reports\Finance\FinanceArrivalReportController` (Blade)
 *   - `DashboardChartService::getCentreWiseArrival` (Blade)
 *   - API endpoints `/api/dashboard/{centre|csr|call}-wise-arrival`
 *     (read-side wired but not currently consumed by the SPA — kept
 *     warm for future SPA features that will read this snapshot
 *     instead of re-scanning the appointments table)
 *
 * Note: the modern Management Dashboard's `ArrivalRateMetric` queries
 * the `appointments` table directly and explicitly bypasses this
 * snapshot (because it captures consultations only — treatments would
 * be invisible). Future SPA arrival-by-status charts that don't need
 * treatments can read this table for cheap dashboard hits.
 */
final class AppointmentsDailyStatsCron extends Command
{
    protected $signature = 'appointments:daily-stats';

    protected $description = 'Snapshot today + tomorrow consultation status into appointments_daily_stats (legacy Blade reports).';

    public function handle(): int
    {
        try {
            $today = Carbon::now()->toDateString();
            $tomorrow = Carbon::now()->addDay()->toDateString();
            $cronDate = $today;

            $accounts = Accounts::query()
                ->whereNull('deleted_at')
                ->where('suspended', 0)
                ->pluck('id')
                ->all();

            $totalUpserted = 0;

            foreach ($accounts as $accountId) {
                $consultancyTypeId = AppointmentTypes::query()
                    ->where('account_id', $accountId)
                    ->where('slug', 'consultancy')
                    ->value('id');

                if (! $consultancyTypeId) {
                    Log::info('appointments:daily-stats skipped account (no consultancy type)', [
                        'account_id' => $accountId,
                    ]);
                    continue;
                }

                $locationIds = Locations::query()
                    ->where('account_id', $accountId)
                    ->where('active', 1)
                    ->pluck('id')
                    ->all();

                if ($locationIds === []) {
                    continue;
                }

                $rows = DB::table('appointments')
                    ->whereIn('location_id', $locationIds)
                    ->where('appointment_type_id', $consultancyTypeId)
                    ->whereBetween('scheduled_date', [$today, $tomorrow])
                    ->whereNull('deleted_at')
                    ->select(
                        'id as appointment_id',
                        'location_id as centre_id',
                        'created_by as user_id',
                        'base_appointment_status_id as appointment_status_id',
                        'scheduled_date',
                    )
                    ->get();

                if ($rows->isEmpty()) {
                    continue;
                }

                $now = Carbon::now();
                $payload = $rows->map(fn ($r) => [
                    'appointment_id' => (int) $r->appointment_id,
                    'centre_id' => (int) $r->centre_id,
                    'user_id' => (int) $r->user_id,
                    'appointment_status_id' => (int) $r->appointment_status_id,
                    'scheduled_date' => $r->scheduled_date,
                    'cron_current_date' => $cronDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                // Single round-trip per account. Dedup key is
                // (appointment_id, scheduled_date) — the table currently
                // lacks a unique index on that pair, so concurrent runs
                // can still race; today the schedule fires once daily so
                // it's not a live problem. Flag for a future migration
                // if this ever gets parallelised.
                foreach (array_chunk($payload, 1000) as $chunk) {
                    DB::table('appointments_daily_stats')->upsert(
                        $chunk,
                        ['appointment_id', 'scheduled_date'],
                        ['centre_id', 'user_id', 'appointment_status_id', 'cron_current_date', 'updated_at'],
                    );
                }

                $totalUpserted += count($payload);
            }

            $this->info("appointments:daily-stats upserted {$totalUpserted} row(s) across "
                . count($accounts) . ' account(s).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('appointments:daily-stats failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
