<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Models\Leads;
use App\Models\LeadsServices;
use App\Models\Services;
use Illuminate\Support\Facades\DB;

/**
 * Backfills a lead's active `leads_services` row to reflect the category
 * of a newly booked appointment or newly converted package.
 *
 * Category definition: a service's `parent_id` if set, otherwise the
 * service itself — same normalisation the Leads list display uses
 * (`LeadResource::resolveServiceNames`). Two services belong to the same
 * category iff their category IDs match.
 *
 * Soft semantics (preserves history):
 *   • Current active pivot rows (status=1) that don't match the new
 *     category are demoted to status=0 — the original inquiry stays
 *     queryable for reporting, it just stops being the headline.
 *   • A pivot row for the new service is found-or-created and set to
 *     status=1. If a row already existed (even demoted), it is
 *     reactivated rather than duplicated.
 *
 * Fast-path: if the current active row already shares the new category,
 * we refresh its metadata (consultancy_id, lead_status_id) without
 * churning status — same-category bookings shouldn't touch history.
 *
 * Wrapped in `DB::transaction()` so the "demote old + activate new"
 * pair is atomic; a partial failure would leave the lead with zero or
 * two active rows, which is exactly what the single-active-row invariant
 * is supposed to guarantee.
 */
final class BackfillLeadCategoryAction
{
    public function execute(
        Leads $lead,
        Services $service,
        ?int $consultancyId = null,
        ?int $leadStatusId = null,
    ): void {
        $newCategoryId = $this->categoryOf($service);

        DB::transaction(function () use ($lead, $service, $consultancyId, $leadStatusId, $newCategoryId): void {
            // Eager-load `service.parent_id` so category resolution doesn't
            // fan out N queries for leads with long history.
            $activeRows = LeadsServices::with('service:id,parent_id')
                ->where('lead_id', $lead->id)
                ->where('status', 1)
                ->get();

            // `instanceof` (not `!== null`) so static analysis narrows the
            // Model|null relation to the concrete Services type before we
            // hand it to categoryOf().
            $matchingActive = $activeRows->first(
                fn (LeadsServices $row): bool => $row->service instanceof Services
                    && $this->categoryOf($row->service) === $newCategoryId,
            );

            if ($matchingActive !== null) {
                // Same category already active — just refresh the pointer to
                // the appointment/package and the lead-status, no row churn.
                $updates = array_filter([
                    'consultancy_id' => $consultancyId,
                    'lead_status_id' => $leadStatusId,
                ], fn ($v): bool => $v !== null);

                if ($updates !== []) {
                    $matchingActive->update($updates);
                }

                return;
            }

            // Category differs — demote every active row (history-preserving)
            // and activate a row for the new service.
            if ($activeRows->isNotEmpty()) {
                LeadsServices::where('lead_id', $lead->id)
                    ->where('status', 1)
                    ->update(['status' => 0]);
            }

            $existing = LeadsServices::where('lead_id', $lead->id)
                ->where('service_id', $service->id)
                ->first();

            $payload = array_filter([
                'status' => 1,
                'consultancy_id' => $consultancyId,
                'lead_status_id' => $leadStatusId,
            ], fn ($v): bool => $v !== null);

            if ($existing !== null) {
                $existing->update($payload + ['status' => 1]);

                return;
            }

            LeadsServices::create([
                'lead_id' => $lead->id,
                'service_id' => $service->id,
            ] + $payload + ['status' => 1]);
        });
    }

    private function categoryOf(Services $service): int
    {
        // parent_id=0 is treated as "root" alongside NULL — the codebase
        // uses both sentinels interchangeably (see Services::scopeRootServices).
        return ($service->parent_id !== null && $service->parent_id !== 0)
            ? (int) $service->parent_id
            : (int) $service->id;
    }
}
