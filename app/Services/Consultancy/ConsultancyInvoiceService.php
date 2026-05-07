<?php

declare(strict_types=1);

namespace App\Services\Consultancy;

use App\Exceptions\AppointmentException;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\Widgets\DiscountWidget;
use App\Models\Activity;
use App\Models\Accounts;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\Discounts;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\LeadsServices;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\User;
use App\Jobs\IndexSingleAppointmentJob;
use App\Services\MetaConversionApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsultancyInvoiceService
{
    public function __construct(
        private readonly ConsultancyPriceCalculator $priceCalculator,
    ) {}

    /**
     * Get invoice details for a consultancy appointment.
     *
     * @return array<string, mixed>
     */
    public function getInvoiceDetails(int $appointmentId): array
    {
        $paidStatus = InvoiceStatuses::where('slug', 'paid')->first();

        $existingInvoice = Invoices::where([
            ['appointment_id', '=', $appointmentId],
            ['invoice_status_id', '=', $paidStatus?->id],
        ])->first();

        if ($existingInvoice) {
            return $this->buildPaidInvoiceResponse($appointmentId);
        }

        return $this->buildNewInvoiceResponse($appointmentId);
    }

    /**
     * Calculate consultancy price with discount.
     *
     * @return array<string, mixed>
     */
    public function calculatePrice(array $data): array
    {
        $location = Locations::findOrFail($data['location_id']);
        $discount = isset($data['discount_id']) ? Discounts::find($data['discount_id']) : null;
        $basePrice = (float) $data['price_for_calculation'];
        $taxTreatmentTypeId = isset($data['tax_treatment_type_id']) ? (int) $data['tax_treatment_type_id'] : null;
        $isExclusive = ($data['is_exclusive_consultancy'] ?? '0') === '1';

        // No discount - return base calculation
        if (!$discount) {
            $result = $this->priceCalculator->calculate($basePrice, $location, $taxTreatmentTypeId, $isExclusive);
            return [
                'status' => false,
                'discount_ava_check' => 'false',
                ...$result,
            ];
        }

        // Custom discount - return base calculation with custom flag
        if ($discount->slug === 'custom') {
            $result = $this->priceCalculator->calculate($basePrice, $location, $taxTreatmentTypeId, $isExclusive);
            return [
                'status' => false,
                'discount_ava_check' => 'true',
                ...$result,
            ];
        }

        // Standard discount
        $discountType = $discount->type === config('constants.Fixed')
            ? config('constants.Fixed')
            : config('constants.Percentage');

        $netAmount = $this->priceCalculator->applyDiscount($basePrice, $discount->type, (float) $discount->amount);
        $result = $this->priceCalculator->calculate($netAmount, $location, $taxTreatmentTypeId, $isExclusive);

        return [
            'status' => true,
            'discount_type' => $discountType,
            'discount_price' => $discount->amount,
            ...$result,
        ];
    }

    /**
     * Calculate custom discount price.
     *
     * @return array<string, mixed>
     */
    public function calculateCustomDiscount(array $data): array
    {
        $location = Locations::findOrFail($data['location_id']);
        $discount = Discounts::findOrFail($data['discount_id']);
        $basePrice = (float) $data['price'];
        $discountValue = (float) $data['discount_value'];
        $discountType = $data['discount_type'];
        $taxTreatmentTypeId = isset($data['tax_treatment_type_id']) ? (int) $data['tax_treatment_type_id'] : null;
        $isExclusive = ($data['is_exclusive_consultancy'] ?? '0') === '1';

        $fixedType = config('constants.Fixed');

        // Validate discount does not exceed maximum
        if ($discountType === $fixedType) {
            $discountInPercentage = ($discountValue / $basePrice) * 100;
            if ($discount->amount < $discountInPercentage) {
                return ['status' => false];
            }
        } else {
            if ($discount->amount < $discountValue) {
                return ['status' => false];
            }
        }

        $netAmount = $this->priceCalculator->applyDiscount($basePrice, $discountType, $discountValue);
        $result = $this->priceCalculator->calculate($netAmount, $location, $taxTreatmentTypeId, $isExclusive);

        return ['status' => true, ...$result];
    }

    /**
     * Check if a discount is custom type.
     */
    public function isCustomDiscount(int $discountId): bool
    {
        $discount = Discounts::find($discountId);

        return $discount?->slug === 'custom';
    }

    /**
     * Calculate final outstanding and settle amounts.
     *
     * @return array{status: true, outstanding: float, settle_amount: float}
     */
    public function calculateFinal(array $data): array
    {
        if ((int) $data['amount_type'] !== 0) {
            return [
                'status' => true,
                'outstanding' => 0,
                'settle_amount' => 0,
            ];
        }

        $cash = (float) ($data['cash'] ?? 0);
        $price = (float) ($data['price'] ?? 0);
        $balance = (float) ($data['balance'] ?? 0);

        if ($cash <= 0) {
            return [
                'status' => true,
                'outstanding' => (float) ($data['outstanding'] ?? 0),
                'settle_amount' => (float) ($data['settleamount'] ?? 0),
            ];
        }

        $outstanding = $price - $cash - $balance;
        $settleAmount = min($price - $cash, $balance);

        return [
            'status' => true,
            'outstanding' => $outstanding,
            'settle_amount' => $settleAmount,
        ];
    }

    /**
     * Save consultancy invoice with all related records.
     *
     * @return array{invoice_id: int}
     */
    public function saveInvoice(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $appointment = Appointments::findOrFail($data['appointment_id']);
            $user = Auth::user();
            $accountId = $user->account_id;

            $paymentModeId = $this->resolvePaymentModeId($data['payment_mode_id'] ?? '0');
            $invoiceStatus = InvoiceStatuses::where('slug', 'paid')->firstOrFail();
            $isExclusive = $this->resolveExclusiveFlag($data);

            // Create invoice
            $invoice = $this->createInvoice($appointment, $data, $invoiceStatus, $isExclusive, $user);

            // Create invoice detail
            $invoiceDetail = $this->createInvoiceDetail($appointment, $invoice, $data, $isExclusive);

            // Create package advance records
            $this->createPackageAdvances($appointment, $invoice, $invoiceDetail, $data, $paymentModeId, $user);

            // Update appointment status to arrived
            $this->updateAppointmentToArrived($appointment, $user);

            // Update lead status
            $this->updateLeadStatus($appointment, $invoice, $accountId);

            // Log activity
            $this->logInvoiceActivity($appointment, $invoice, $data, $user);

            // Dispatch Elasticsearch indexing
            IndexSingleAppointmentJob::dispatch([
                'account_id' => $accountId,
                'appointment_id' => $appointment->id,
            ]);

            return ['invoice_id' => $invoice->id];
        });
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function buildPaidInvoiceResponse(int $appointmentId): array
    {
        $paymentModes = PaymentModes::where('type', 'application')->pluck('name', 'id');
        $paymentModes->prepend('Select', '0');

        // Surface the existing paid invoice id so the SPA's preview
        // dialog can offer "Re-print invoice" / "Print consultation form"
        // without making a second round trip.
        $paidStatus = InvoiceStatuses::where('slug', 'paid')->first();
        $existingInvoice = Invoices::with('invoiceDetailService')
            ->where([
                ['appointment_id', '=', $appointmentId],
                ['invoice_status_id', '=', $paidStatus?->id],
            ])
            ->first();

        // The print page needs the same shape as the unpaid response so
        // it can render the printable layout from the saved data.
        // Pre-fix this returned all-null fields, which is why the print
        // tab opened post-save showed "—" for patient and 0 for totals.
        $appointment = Appointments::with([
            'service',
            'doctor',
            'location.city',
            'appointment_type',
            'patient',
        ])->find($appointmentId);

        $detail = $existingInvoice?->invoiceDetailService;
        $service = $appointment?->service;
        $location = $appointment?->location;
        $patient = $appointment?->patient_id ? User::find($appointment->patient_id) : null;
        $account = $appointment ? Accounts::find($appointment->account_id) : null;

        // Prefer the persisted detail values when available (they're the
        // exact numbers the customer was charged); fall back to the live
        // service price + location tax for older paid invoices that pre-
        // date the detail row.
        $taxRate = $detail?->tax_percentage !== null
            ? (float) $detail->tax_percentage
            : (float) ($location->tax_percentage ?? 0);
        $totalIncTax = $detail?->tax_including_price !== null
            ? (float) $detail->tax_including_price
            : (float) ($existingInvoice?->total_price ?? 0);
        $basePrice = $detail?->tax_exclusive_serviceprice !== null
            ? (float) $detail->tax_exclusive_serviceprice
            : (float) ($detail?->service_price ?? $service?->price ?? 0);

        return [
            'invoice_status' => true,
            'invoice_id' => $existingInvoice?->id,
            'price' => $basePrice,
            'appointment_type' => $appointment?->appointment_type,
            'service' => $service,
            'balance' => 0,
            'settle_amount' => 0,
            'outstanding' => 0,
            'tax' => $taxRate,
            'tax_amt' => $totalIncTax,
            'location_info' => $location,
            'discounts' => null,
            'cash' => $totalIncTax,
            'price_tax' => $basePrice,
            'patient' => $patient,
            'doctor' => $appointment?->doctor,
            'account' => $account,
            'payment_modes' => $paymentModes,
            'appointment_id' => $appointmentId,
        ];
    }

    private function buildNewInvoiceResponse(int $appointmentId): array
    {
        $appointment = Appointments::with(['service', 'doctor', 'location.city', 'appointment_type'])
            ->findOrFail($appointmentId);

        $location = $appointment->location;
        $appointmentType = $appointment->appointment_type;
        $service = $appointment->service;

        $discounts = DiscountWidget::Discount_data_consultancy($appointment, Auth::user()->account_id);

        $price = $tax = $priceTax = $taxAmt = $cash = $balance = 0;

        $consultancyTypeName = config('constants.Consultancy');
        if ($appointmentType?->name === $consultancyTypeName && $service) {
            $taxTreatmentTypeId = $service->tax_treatment_type_id;
            $taxExclusiveId = (int) config('constants.tax_is_exclusive');
            $taxBothId = (int) config('constants.tax_both');

            $isExclusive = in_array($taxTreatmentTypeId, [$taxBothId, $taxExclusiveId], true);

            $calculated = $this->priceCalculator->calculate(
                (float) $service->price,
                $location,
                $taxTreatmentTypeId,
                $isExclusive,
            );

            $price = $calculated['price'];
            $tax = $calculated['tax'];
            $taxAmt = $calculated['tax_amt'];
            $priceTax = $service->price;
        }

        $outstanding = max(0, $taxAmt - $cash - $balance);
        $settleAmount = min($price - $cash, $balance);

        $paymentModes = PaymentModes::where('type', 'application')->pluck('name', 'id');
        $paymentModes->prepend('Select', '0');

        $patient = $appointment->patient_id ? User::find($appointment->patient_id) : null;
        $account = Accounts::find($appointment->account_id);

        return [
            'invoice_status' => false,
            'price' => $price,
            'appointment_type' => $appointmentType,
            'service' => $service,
            'balance' => $balance,
            'settle_amount' => $settleAmount,
            'outstanding' => $outstanding,
            'tax' => $tax,
            'tax_amt' => $taxAmt,
            'location_info' => $location,
            'discounts' => $discounts,
            'cash' => $cash,
            'price_tax' => $priceTax,
            'patient' => $patient,
            'doctor' => $appointment->doctor,
            'account' => $account,
            'payment_modes' => $paymentModes,
            'appointment_id' => $appointmentId,
        ];
    }

    private function resolvePaymentModeId(mixed $paymentModeId): int
    {
        if ($paymentModeId === '0' || empty($paymentModeId)) {
            $payment = PaymentModes::first();
            return $payment ? $payment->id : 0;
        }

        return (int) $paymentModeId;
    }

    private function resolveExclusiveFlag(array $data): int
    {
        $taxTreatmentTypeId = $data['tax_treatment_type_id'] ?? null;

        $taxTreatmentId = $taxTreatmentTypeId !== null ? (int) $taxTreatmentTypeId : null;

        return match ($taxTreatmentId) {
            (int) config('constants.tax_both') => (int) ($data['is_exclusive'] ?? 0),
            (int) config('constants.tax_is_exclusive') => 1,
            default => 0,
        };
    }

    private function createInvoice(
        Appointments $appointment,
        array $data,
        InvoiceStatuses $invoiceStatus,
        int $isExclusive,
        User $user,
    ): Invoices {
        $now = Filters::getCurrentTimeStamp();

        return Invoices::CreateRecord([
            'total_price' => $data['price'] ?? 0,
            'account_id' => $user->account_id,
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $data['appointment_id'],
            'invoice_status_id' => $invoiceStatus->id,
            'created_by' => $user->id,
            'location_id' => $appointment->location_id,
            'doctor_id' => $appointment->doctor_id,
            'is_exclusive' => $isExclusive,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createInvoiceDetail(
        Appointments $appointment,
        Invoices $invoice,
        array $data,
        int $isExclusive,
    ): InvoiceDetails {
        $now = Filters::getCurrentTimeStamp();

        $detailData = [
            'tax_exclusive_serviceprice' => $data['amount_create'] ?? 0,
            'tax_percentage' => $appointment->location?->tax_percentage ?? 0,
            'tax_price' => $data['tax_create'] ?? 0,
            'tax_including_price' => $data['price'] ?? 0,
            'net_amount' => $data['price'] ?? 0,
            'is_exclusive' => $isExclusive,
            'qty' => '1',
            'service_price' => $appointment->service?->price ?? 0,
            'service_id' => $appointment->service_id ?? 0,
            'invoice_id' => $invoice->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Attach discount info if available
        if (!empty($data['discount_id'])) {
            $discount = Discounts::find($data['discount_id']);
            if ($discount) {
                $detailData['discount_id'] = $data['discount_id'];
                $detailData['discount_name'] = $discount->name;
                $detailData['discount_type'] = $data['discount_type'] ?? null;
                $detailData['discount_price'] = $data['discount_value'] ?? null;
            }
        }

        return InvoiceDetails::createRecord($detailData, $invoice);
    }

    private function createPackageAdvances(
        Appointments $appointment,
        Invoices $invoice,
        InvoiceDetails $invoiceDetail,
        array $data,
        int $paymentModeId,
        User $user,
    ): void {
        $now = Filters::getCurrentTimeStamp();
        $settlePaymentMode = PaymentModes::where('payment_type', config('constants.payment_type_settle'))->first();

        $basePackageData = [
            'patient_id' => $appointment->patient_id,
            'account_id' => $user->account_id,
            'appointment_type_id' => $appointment->appointment_type_id,
            'appointment_id' => $data['appointment_id'],
            'invoice_id' => $invoice->id,
            'location_id' => $appointment->location_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Cash inflow
        PackageAdvances::createRecord_forinvoice(array_merge($basePackageData, [
            'cash_flow' => 'in',
            'cash_amount' => $data['cash'] ?? 0,
            'payment_mode_id' => $paymentModeId,
        ]));

        // Cash outflow transactions (price and tax)
        $outTotal = ($data['cash'] ?? 0) + ($data['settle'] ?? 0);
        $taxPrice = $invoiceDetail->tax_price ?? 0;

        $outflows = [
            ['amount' => $outTotal - $taxPrice, 'is_tax' => false],
            ['amount' => $taxPrice, 'is_tax' => true],
        ];

        foreach ($outflows as $outflow) {
            $outData = array_merge($basePackageData, [
                'cash_flow' => 'out',
                'cash_amount' => $outflow['amount'],
                'payment_mode_id' => $settlePaymentMode?->id ?? 0,
            ]);

            if ($outflow['is_tax']) {
                $outData['is_tax'] = 1;
            }

            if ($invoiceDetail->package_id !== null) {
                $outData['package_id'] = $invoiceDetail->package_id;
            }

            PackageAdvances::createRecord_forinvoice($outData);
        }
    }

    private function updateAppointmentToArrived(Appointments $appointment, mixed $user): void
    {
        $arrivedStatus = AppointmentStatuses::where('is_arrived', 1)->first()
            ?? AppointmentStatuses::where('name', 'LIKE', '%Arrived%')->first();

        if (!$arrivedStatus) {
            return;
        }

        $consultancyTypeId = (int) config('constants.appointment_type_consultancy');

        if ($appointment->appointment_type_id !== $consultancyTypeId) {
            return;
        }

        $childStatus = AppointmentStatuses::where('parent_id', $arrivedStatus->id)
            ->where('active', 1)
            ->first();

        $statusId = $childStatus?->id ?? $arrivedStatus->id;

        $appointment->update([
            'base_appointment_status_id' => $arrivedStatus->id,
            'appointment_status_id' => $statusId,
            'updated_by' => $user->id,
            'arrived_at' => now(),
        ]);
    }

    private function updateLeadStatus(Appointments $appointment, Invoices $invoice, int $accountId): void
    {
        $arrivedLeadStatus = LeadStatuses::where([
            'account_id' => $accountId,
            'is_arrived' => 1,
        ])->first();

        if (!$arrivedLeadStatus) {
            return;
        }

        $leadRecord = Leads::where('patient_id', $appointment->patient_id)
            ->orderByDesc('id')
            ->first();

        // Update all leads for this patient
        Leads::where('patient_id', $appointment->patient_id)
            ->update(['lead_status_id' => $arrivedLeadStatus->id]);

        if ($leadRecord) {
            // Per-appointment idempotency for the Meta CAPI `arrived`
            // event — without this, paying the same invoice twice
            // (e.g. retry after a transient failure) re-sends the
            // event and inflates Meta's Arrived count. Activity log
            // still fires every time so the audit trail is unaffected.
            // Flag is only set when the Meta call actually succeeded
            // (sendMetaCapiEvent returns false on failure) so a
            // transient Meta error retries on the next call.
            if (! $appointment->meta_arrived_sent) {
                if ($this->sendMetaCapiEvent($leadRecord)) {
                    $appointment->update(['meta_arrived_sent' => 1]);
                }
            }

            // Log lead arrived activity
            $location = Locations::with('city')->find($appointment->location_id);
            $service = Services::find($appointment->service_id);
            ActivityLogger::logLeadArrived($leadRecord, $appointment, $invoice, $location, $service);
        }

        // Update lead services
        if ($appointment->lead_id) {
            LeadsServices::where([
                'lead_id' => $appointment->lead_id,
                'service_id' => $appointment->service_id,
            ])->update(['lead_status_id' => $arrivedLeadStatus->id]);
        }
    }

    /**
     * Returns `true` only when the Meta CAPI call succeeded — caller
     * uses this to gate the `meta_arrived_sent=1` flag write so a
     * transient Meta failure can retry on the next invoice payment
     * for the same appointment.
     */
    private function sendMetaCapiEvent(Leads $lead): bool
    {
        try {
            $metaService = new MetaConversionApiService();
            $metaService->sendLeadStatus(
                $lead->phone,
                'arrived',
                $lead->meta_lead_id,
                $lead->email,
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Meta CAPI arrived event failed: ' . $e->getMessage(), [
                'lead_id' => $lead->id,
            ]);
            return false;
        }
    }

    private function logInvoiceActivity(
        Appointments $appointment,
        Invoices $invoice,
        array $data,
        User $user,
    ): void {
        $patient = User::find($appointment->patient_id);
        $location = Locations::with('city')->find($appointment->location_id);
        $serviceName = $appointment->service?->name ?? 'Service';
        $locationName = ($location?->city?->name ?? '') . '-' . ($location?->name ?? '');
        $price = $data['price'] ?? 0;

        $description = '<span class="highlight">' . e($user->name) . '</span>'
            . ' created invoice <span class="highlight-green">Rs. ' . number_format((float) $price) . '</span>'
            . ' for <span class="highlight-orange">' . e($serviceName) . ' Consultation</span>'
            . ($locationName ? ' in <span class="highlight">' . e($locationName) . '</span>' : '')
            . ' on ' . date('M j, Y');

        $now = Filters::getCurrentTimeStamp();

        $activity = new Activity();
        $activity->action = 'received';
        $activity->activity_type = 'invoice_created';
        $activity->description = $description;
        $activity->patient = $patient?->name;
        $activity->patient_id = $appointment->patient_id;
        $activity->appointment_id = $appointment->id;
        $activity->appointment_type = $serviceName . ' Consultation';
        $activity->created_by = $user->id;
        $activity->invoice_id = $invoice->id;
        $activity->amount = $price;
        $activity->location = $location?->name;
        $activity->centre_id = $appointment->location_id;
        $activity->account_id = $user->account_id;
        $activity->created_at = $now;
        $activity->updated_at = $now;
        $activity->save();
    }
}
