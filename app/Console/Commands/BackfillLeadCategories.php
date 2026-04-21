<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AppointmentType;
use App\Models\Appointments;
use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\Services;
use App\Services\Lead\BackfillLeadCategoryAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retroactive companion to the runtime backfill in
 * AppointmentsController (booking) and PlanService::markAppointmentAsConvertedOptimized
 * (conversion). Walks already-booked and already-converted leads and
 * normalises their `leads_services` active row to match the
 * service/category the booking or package actually used.
 *
 * Intentionally independent of the `lead_category_backfill` feature
 * flag — the flag guards runtime side-effects; this command is an
 * explicit, auditable admin operation. Run with `--dry-run` first to
 * inspect what would change.
 */
final class BackfillLeadCategories extends Command
{
    protected $signature = 'leads:backfill-categories
        {--account= : Scope to a single account_id (default: all accounts)}
        {--chunk=500 : Chunk size when streaming leads}
        {--dry-run : Report what would change without writing}
        {--skip-booked : Skip the booked-leads pass}
        {--skip-converted : Skip the converted-leads pass}';

    protected $description = 'Retroactively align each lead\'s active leads_services category with the service they were booked/converted against.';

    public function handle(BackfillLeadCategoryAction $action): int
    {
        $accountId = $this->option('account') !== null ? (int) $this->option('account') : null;
        $chunkSize = max(50, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line($dryRun ? '— DRY RUN: no writes will be made —' : '— APPLYING changes —');

        $stats = [
            'booked_scanned' => 0,
            'booked_updated' => 0,
            'converted_scanned' => 0,
            'converted_updated' => 0,
            'skipped_no_appointment' => 0,
            'skipped_no_package' => 0,
            'skipped_no_service' => 0,
        ];

        if (! $this->option('skip-booked')) {
            $this->handleBooked($action, $accountId, $chunkSize, $dryRun, $stats);
        }

        if (! $this->option('skip-converted')) {
            $this->handleConverted($action, $accountId, $chunkSize, $dryRun, $stats);
        }

        $this->newLine();
        $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k): array => [$k, (int) $v])->values()->all());

        return self::SUCCESS;
    }

    private function handleBooked(
        BackfillLeadCategoryAction $action,
        ?int $accountId,
        int $chunkSize,
        bool $dryRun,
        array &$stats,
    ): void {
        $bookedStatusIds = LeadStatuses::query()
            ->where('is_booked', 1)
            ->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId))
            ->pluck('id');

        if ($bookedStatusIds->isEmpty()) {
            $this->warn('No booked lead-statuses found; skipping booked pass.');

            return;
        }

        $query = Leads::query()
            ->whereIn('lead_status_id', $bookedStatusIds)
            ->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId))
            ->orderBy('id');

        $this->info("Booked pass: streaming leads in chunks of {$chunkSize}…");

        $query->chunkById($chunkSize, function ($leads) use ($action, $dryRun, &$stats): void {
            foreach ($leads as $lead) {
                $stats['booked_scanned']++;

                // Most recent consultation appointment wins — that's the
                // one the booking flow originally logged against.
                $appointment = Appointments::query()
                    ->where('lead_id', $lead->id)
                    ->where('appointment_type_id', AppointmentType::Consultancy->value)
                    ->orderByDesc('id')
                    ->first(['id', 'service_id']);

                if (! $appointment || ! $appointment->service_id) {
                    $stats['skipped_no_appointment']++;

                    continue;
                }

                $service = Services::find($appointment->service_id);
                if (! $service) {
                    $stats['skipped_no_service']++;

                    continue;
                }

                if ($dryRun) {
                    $stats['booked_updated']++;

                    continue;
                }

                $action->execute(
                    lead: $lead,
                    service: $service,
                    consultancyId: (int) $appointment->id,
                    leadStatusId: (int) $lead->lead_status_id,
                );

                $stats['booked_updated']++;
            }
        });
    }

    private function handleConverted(
        BackfillLeadCategoryAction $action,
        ?int $accountId,
        int $chunkSize,
        bool $dryRun,
        array &$stats,
    ): void {
        $convertedStatusIds = LeadStatuses::query()
            ->where('is_converted', 1)
            ->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId))
            ->pluck('id');

        if ($convertedStatusIds->isEmpty()) {
            $this->warn('No converted lead-statuses found; skipping converted pass.');

            return;
        }

        $query = Leads::query()
            ->whereIn('lead_status_id', $convertedStatusIds)
            ->whereNotNull('patient_id')
            ->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId))
            ->orderBy('id');

        $this->info("Converted pass: streaming leads in chunks of {$chunkSize}…");

        $query->chunkById($chunkSize, function ($leads) use ($action, $dryRun, &$stats): void {
            foreach ($leads as $lead) {
                $stats['converted_scanned']++;

                // Pick the most recent package belonging to this lead's
                // patient; then the first package_services row on it —
                // same source of truth the runtime code uses.
                $package = DB::table('packages')
                    ->where('patient_id', $lead->patient_id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first(['id']);

                if (! $package) {
                    $stats['skipped_no_package']++;

                    continue;
                }

                $primaryServiceId = DB::table('package_services')
                    ->join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                    ->where('package_bundles.package_id', $package->id)
                    ->whereNull('package_services.deleted_at')
                    ->orderBy('package_services.created_at')
                    ->orderBy('package_services.id')
                    ->value('package_services.service_id');

                if (! $primaryServiceId) {
                    $stats['skipped_no_service']++;

                    continue;
                }

                $service = Services::find($primaryServiceId);
                if (! $service) {
                    $stats['skipped_no_service']++;

                    continue;
                }

                if ($dryRun) {
                    $stats['converted_updated']++;

                    continue;
                }

                // Link the backfill to the most recent consultation appointment
                // for this lead if we have one — gives reporting a clean join
                // from leads_services.consultancy_id to the originating row.
                $consultancyId = Appointments::query()
                    ->where('lead_id', $lead->id)
                    ->where('appointment_type_id', AppointmentType::Consultancy->value)
                    ->orderByDesc('id')
                    ->value('id');

                $action->execute(
                    lead: $lead,
                    service: $service,
                    consultancyId: $consultancyId !== null ? (int) $consultancyId : null,
                    leadStatusId: (int) $lead->lead_status_id,
                );

                $stats['converted_updated']++;
            }
        });
    }
}
