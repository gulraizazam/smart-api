<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Helpers\AppointmentHelper;
use App\Models\Appointments;
use App\Models\InvoiceStatuses;
use App\Models\Invoices;
use Illuminate\Support\Facades\Auth;

/**
 * Shared resolution of the two per-row flags the appointment list
 * resources expose — `has_paid_invoice` and `has_children`.
 *
 * Both ConsultancyResource and TreatmentResource surface these so the
 * SPA can lock the status dialog (paid invoice) and grey out the Delete
 * affordance (existing child rows) up-front. Computed naively that is a
 * 5-query-per-row N+1 (1 paid-invoice + 4 child-table EXISTS); on a
 * 25-row page that was the dominant cost of the list endpoints.
 *
 * {@see preload()} resolves both flags for an entire page in a fixed
 * handful of `whereIn` queries and stashes each result on its model;
 * the resolvers below then read the stashed attribute. Single-resource
 * paths (show / store / update / status / schedule) never preload, so
 * they fall back to the original per-row queries transparently.
 */
trait ResolvesAppointmentRowFlags
{
    /** Model attribute keys under which preload() stashes its results. */
    private static string $attrHasPaidInvoice = 'has_paid_invoice_precomputed';

    private static string $attrHasChildren = 'has_children_precomputed';

    /**
     * Memoised paid-invoice status id, keyed by account_id. The
     * `invoice_statuses` table is account-scoped so the slug='paid' row
     * has a different id per tenant; a single static would leak between
     * accounts when one FPM worker serves multiple tenants.
     *
     * @var array<int, int|null>
     */
    private static array $paidInvoiceStatusIdByAccount = [];

    /**
     * Batch-resolve `has_paid_invoice` / `has_children` for a page of
     * appointments and stash each result on its model so toArray() reads
     * the attribute instead of firing per-row EXISTS queries.
     *
     * @param  iterable<Appointments>  $appointments
     */
    public static function preload(iterable $appointments): void
    {
        $models = [];
        $ids = [];
        foreach ($appointments as $model) {
            if ($model instanceof Appointments && $model->id) {
                $models[] = $model;
                $ids[] = (int) $model->id;
            }
        }

        if (empty($models)) {
            return;
        }

        $accountId = (int) (Auth::user()?->account_id ?? 0);
        $paidSet = AppointmentHelper::paidInvoiceSet($ids, $accountId);
        $childSet = AppointmentHelper::childExistenceSet($ids, $accountId);

        foreach ($models as $model) {
            $id = (int) $model->id;
            $model->setAttribute(self::$attrHasPaidInvoice, isset($paidSet[$id]));
            $model->setAttribute(self::$attrHasChildren, isset($childSet[$id]));
        }
    }

    private function resolveHasPaidInvoice(): bool
    {
        // Page-level batch result when the list endpoint preloaded it;
        // otherwise a per-row query for single-resource paths.
        $attrs = $this->resource->getAttributes();
        if (array_key_exists(self::$attrHasPaidInvoice, $attrs)) {
            return (bool) $attrs[self::$attrHasPaidInvoice];
        }

        $accountId = (int) ($this->account_id ?? 0);
        if ($accountId <= 0) {
            return false;
        }
        $paidId = $this->paidInvoiceStatusIdFor($accountId);
        if (! $paidId) {
            return false;
        }

        return Invoices::where('appointment_id', $this->id)
            ->where('invoice_status_id', $paidId)
            ->exists();
    }

    private function resolveHasChildren(): bool
    {
        $attrs = $this->resource->getAttributes();
        if (array_key_exists(self::$attrHasChildren, $attrs)) {
            return (bool) $attrs[self::$attrHasChildren];
        }

        return AppointmentHelper::isChildExists(
            (int) $this->id,
            (int) (Auth::user()?->account_id ?? 0),
        );
    }

    private function paidInvoiceStatusIdFor(int $accountId): ?int
    {
        if (! array_key_exists($accountId, self::$paidInvoiceStatusIdByAccount)) {
            $id = (int) InvoiceStatuses::where('slug', '=', 'paid')
                ->where('account_id', $accountId)
                ->value('id');
            self::$paidInvoiceStatusIdByAccount[$accountId] = $id > 0 ? $id : null;
        }

        return self::$paidInvoiceStatusIdByAccount[$accountId];
    }
}
