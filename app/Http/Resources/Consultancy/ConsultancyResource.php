<?php

declare(strict_types=1);

namespace App\Http\Resources\Consultancy;

use App\Enums\ConsultancyType;
use App\Helpers\AppointmentHelper;
use App\Models\InvoiceStatuses;
use App\Models\Invoices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ConsultancyResource extends JsonResource
{
    /**
     * Memoised paid-invoice status id, keyed by account_id. The
     * `invoice_statuses` table is account-scoped so the slug='paid' row
     * has a different id per tenant; a single static would leak between
     * accounts when the same FPM worker handles requests for multiple
     * tenants. The map keeps each account's lookup isolated.
     *
     * @var array<int, int|null>
     */
    private static array $paidInvoiceStatusIdByAccount = [];

    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('consultations.list.view_contact');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->when($canViewContact, fn () => $this->patient?->phone),
            'patient_id' => $this->patient_id,
            'lead_id' => $this->lead_id,
            'consultancy_type' => $this->consultancy_type,
            'consultancy_type_label' => ConsultancyType::tryFrom($this->consultancy_type)?->label() ?? '',
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ]),
            'doctor' => $this->whenLoaded('doctor', fn () => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ]),
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'name' => $this->location->name,
                'city' => $this->location->city?->name,
            ]),
            'appointment_status' => $this->whenLoaded('appointment_status', fn () => [
                'id' => $this->appointment_status->id,
                'name' => $this->appointment_status->name,
                // Flag fields so the SPA can distinguish system-managed
                // statuses without doing a substring match on the name.
                // Used by the row-level Schedule affordance and the
                // status dialog's lock state.
                'is_arrived'     => (bool) ($this->appointment_status->is_arrived ?? false),
                'is_converted'   => (bool) ($this->appointment_status->is_converted ?? false),
                'is_unscheduled' => (bool) ($this->appointment_status->is_unscheduled ?? false),
                'is_cancelled'   => (bool) ($this->appointment_status->is_cancelled ?? false),
            ]),
            'appointment_type' => $this->whenLoaded('appointment_type', fn () => [
                'id' => $this->appointment_type->id,
                'name' => $this->appointment_type->name,
            ]),
            'scheduled_date' => $this->scheduled_date
                ? Carbon::parse($this->scheduled_date)->format('Y-m-d')
                : null,
            'scheduled_time' => $this->scheduled_time
                ? Carbon::parse($this->scheduled_time)->format('H:i:s')
                : null,
            'scheduled_at' => $this->scheduled_date
                ? Carbon::parse($this->scheduled_date)->format('M j, Y')
                    . ' at ' . Carbon::parse($this->scheduled_time)->format('h:i A')
                : null,
            'arrived_at' => $this->arrived_at,
            'converted_at' => $this->converted_at,
            // Surfaced so the SPA's status dialog can lock the dropdown
            // up-front instead of letting the user pick a value the
            // backend would 422 on. Mirrors AppointmentService's own
            // paid-invoice lock at updateAppointmentStatus.
            'has_paid_invoice' => $this->resolveHasPaidInvoice(),
            // Same for the row-level Delete affordance — backend rejects
            // delete when invoices/advances/measurements/images exist
            // (AppointmentHelper::isChildExists). Surfacing the flag lets
            // the SPA grey the icon out before the click.
            'has_children' => AppointmentHelper::isChildExists(
                (int) $this->id,
                (int) (Auth::user()?->account_id ?? 0),
            ),
            'patient' => $this->whenLoaded('patient', function () use ($canViewContact): array {
                $patient = [
                    'id' => $this->patient->id,
                    'name' => $this->patient->name,
                ];

                if ($canViewContact) {
                    $patient['phone'] = $this->patient->phone;
                }

                return $patient;
            }),
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
            ]),
            'created_by' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            // Surface the two remaining audit-user fields the SPA's row
            // expander shows alongside Created By. `converted_by` is what
            // the legacy admin labels "Rescheduled By" — same column,
            // historical naming kept for the export filter compatibility.
            'updated_by' => $this->whenLoaded('user_updated_by', fn () => $this->user_updated_by ? [
                'id' => $this->user_updated_by->id,
                'name' => $this->user_updated_by->name,
            ] : null),
            'rescheduled_by' => $this->whenLoaded('user_converted_by', fn () => $this->user_converted_by ? [
                'id' => $this->user_converted_by->id,
                'name' => $this->user_converted_by->name,
            ] : null),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveHasPaidInvoice(): bool
    {
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
