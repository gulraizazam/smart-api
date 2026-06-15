<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentType;
use App\Exceptions\AppointmentException;
use App\Exceptions\TreatmentException;
use App\Exports\ExportConsultancies;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Requests\Consultancy\ScheduleConsultancyRequest;
use App\Http\Requests\Treatment\AvailableResourcesRequest;
use App\Http\Requests\Treatment\CheckPatientLastTreatmentRequest;
use App\Http\Requests\Treatment\RescheduleTreatmentRequest;
use App\Http\Requests\Treatment\StoreTreatmentRequest;
use App\Http\Requests\Treatment\UpdateTreatmentRequest;
use App\Http\Resources\Treatment\TreatmentEditResource;
use App\Http\Resources\Treatment\TreatmentResource;
use App\Models\Appointments;
use App\Models\Settings;
use App\Models\SMSTemplates;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\TreatmentUpdateService;
use App\Services\Treatment\TreatmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Models\Bundles;
use App\Models\InvoiceStatuses;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\AppointmentTypes;
use App\Models\User;
use App\Models\Accounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unified Treatment API Controller.
 *
 * Handles all treatment-related endpoints for both /api/treatments/* and /api/treatment/* routes.
 * Keeps controller thin: request → service → response.
 */
final class TreatmentController extends Controller
{
    public function __construct(
        private readonly TreatmentService $treatmentService,
        private readonly TreatmentUpdateService $updateService,
        private readonly AppointmentService $appointmentService,
    ) {}

    // ──────────────────────────────────────────────────
    //  Datatable (POST /api/treatments/datatable)
    // ──────────────────────────────────────────────────

    public function datatable(Request $request, ?int $patientId = null): JsonResponse
    {
        try {
            // Gate the legacy admin-v1 datatable. Without this, any
            // authenticated user could enumerate every treatment.
            if (!Gate::allows('treatments.list.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $patientId = $patientId ?: $request->integer('patient_id') ?: null;

            $data = $this->treatmentService->getDatatableData($request->all(), $patientId);

            return response()->json($data);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode(), $e->getErrorData());
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Store (POST /api/treatments/store)
    // ──────────────────────────────────────────────────

    public function store(StoreTreatmentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->treatmentService->store($request->validated());

            return $this->successResponse($result['message'], ['id' => $result['id']]);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Update (PUT /api/treatment/{id})
    // ──────────────────────────────────────────────────

    public function update(UpdateTreatmentRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $treatment = $this->updateService->updateTreatment($id, $request->validated());

            return $this->successResponse(
                'Treatment updated successfully.',
                new TreatmentResource($treatment),
            );
        } catch (TreatmentException|AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error updating treatment: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Drag-Drop Reschedule (POST /api/treatments/drag-drop-reschedule)
    // ──────────────────────────────────────────────────

    public function dragDropReschedule(RescheduleTreatmentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.reschedule')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->treatmentService->dragDropReschedule($request->validated());

            return $this->successResponse($result['message'], ['id' => $result['id']]);
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Check Patient Last Treatment (GET /api/treatments/check-patient-last-treatment)
    // ──────────────────────────────────────────────────

    public function checkPatientLastTreatment(CheckPatientLastTreatmentRequest $request): JsonResponse
    {
        try {
            // Lookup used during treatment creation to pre-fill recent
            // treatment info. Gate on the create perm — without it the
            // endpoint leaked treatment data to any authed caller.
            if (!Gate::allows('treatments.create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->treatmentService->checkPatientLastTreatment($request->validated());

            return $this->successResponse('Patient treatment history retrieved.', $data);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Error checking patient treatment history.',
                500,
            );
        }
    }

    // ──────────────────────────────────────────────────
    //  Edit Data (GET /api/treatments/{id}/edit)
    // ──────────────────────────────────────────────────

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->treatmentService->getEditData($id);

            return $this->successResponse('Data found.', new TreatmentEditResource($data));
        } catch (TreatmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Clear Cache (POST /api/treatments/clear-cache)
    // ──────────────────────────────────────────────────

    public function clearCache(): JsonResponse
    {
        try {
            // Cache flush is a privileged operation — bouncing the
            // treatment-list cache for everyone. Gate on list view at
            // minimum so randoms can't trigger thundering-herd recompute.
            if (!Gate::allows('treatments.list.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $this->treatmentService->clearCache();

            return $this->successResponse('Cache cleared successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Secondary API — List (GET /api/treatment/)
    // ──────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.list.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters = $request->only([
                // Unified patient search — SPA sends `q`; the service
                // delegates to PatientSearchService::applyPatientFilter
                // (the canonical classifier shared with Plans / Patients /
                // Vouchers / Invoices / Consultancy listings).
                'q',
                'patient_id', 'phone', 'location_id', 'doctor_id', 'service_id',
                // `service_parent_id` lets the SPA's tree picker filter
                // by a service category — expands to every active
                // child service of the parent server-side.
                'service_parent_id',
                'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to',
                'created_date_from', 'created_date_to', 'scheduled',
                'created_by', 'updated_by', 'rescheduled_by',
            ]);

            // Sort wire format mirrors consultancy: ?sort[field]=...&sort[sort]=...
            // Whitelist enforced server-side; unknown fields fall back to default.
            $filters['sort'] = $this->resolveSort($request);

            $query = $this->treatmentService->getTreatmentList($filters);

            if ($request->input('paginate') === 'false') {
                $rows = $query->get();
                // Batch-resolve the per-row `has_paid_invoice` /
                // `has_children` flags so the resource doesn't fire
                // EXISTS queries per row (the list's N+1 hot spot).
                TreatmentResource::preload($rows);

                return response()->json([
                    'success' => true,
                    'message' => 'Treatments retrieved successfully.',
                    'data'    => TreatmentResource::collection($rows),
                ]);
            }

            $perPage = max(1, min((int) $request->get('per_page', 15), 100));
            $paginator = $query->paginate($perPage)->appends($request->query());

            // Same batch preload for the paginated path — collapses the
            // page's per-row EXISTS queries into a fixed handful.
            TreatmentResource::preload($paginator->getCollection());

            // Hand-rolled pagination envelope so the SPA's Paginated<T>
            // shape (data + meta + links siblings) lines up with what
            // its consultations module already expects.
            return response()->json([
                'success' => true,
                'message' => 'Treatments retrieved successfully.',
                'data'    => TreatmentResource::collection($paginator->items()),
                'meta'    => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                    'from'         => $paginator->firstItem(),
                    'to'           => $paginator->lastItem(),
                ],
                'links'   => [
                    'first' => $paginator->url(1),
                    'last'  => $paginator->url($paginator->lastPage()),
                    'prev'  => $paginator->previousPageUrl(),
                    'next'  => $paginator->nextPageUrl(),
                ],
            ]);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching treatments: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Show / Destroy / Status / Schedule / WhatsApp / Export
    //  (mirror of the consultancy controller's methods so the SPA
    //  can drive the same dialogs against treatments)
    // ──────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.detail.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $treatment = $this->requireTreatment($id);

            return $this->successResponse(
                'Treatment retrieved successfully.',
                new TreatmentResource($treatment),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.delete')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $this->requireTreatment($id);
            $this->appointmentService->deleteAppointment($id);

            return $this->successResponse('Treatment deleted successfully.');
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.update_status')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $this->requireTreatment($id);
            $treatment = $this->appointmentService->updateAppointmentStatus($id, $request->validated());

            return $this->successResponse(
                'Treatment status updated successfully.',
                new TreatmentResource($treatment),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function schedule(ScheduleConsultancyRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.reschedule')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $this->requireTreatment($id);
            $treatment = $this->appointmentService->scheduleAppointment($id, $request->toServiceData());

            return $this->successResponse(
                'Treatment scheduled successfully.',
                new TreatmentResource($treatment),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function whatsappData(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.copy_whatsapp')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $appointment = Appointments::with([
                'patient', 'doctor', 'location', 'service', 'appointment_status',
            ])->where('account_id', Auth::user()->account_id)->find($id);

            if (!$appointment || $appointment->appointment_type_id !== AppointmentType::Treatment->value) {
                return $this->errorResponse('Treatment not found.', 404);
            }

            $rawPhone = $appointment->patient?->phone;
            if (!$rawPhone) {
                return $this->errorResponse('Customer WhatsApp number not found.', 422);
            }

            // Same PK normalisation as the consultancy endpoint.
            $whatsapp = preg_replace('/[^0-9]/', '', (string) $rawPhone);
            if (str_starts_with($whatsapp, '0')) {
                $whatsapp = '92' . substr($whatsapp, 1);
            } elseif (strlen($whatsapp) === 10 && !str_starts_with($whatsapp, '92')) {
                $whatsapp = '92' . $whatsapp;
            }

            $template = SMSTemplates::getBySlug('treatment_whatsapp', Auth::user()->account_id);
            if (!$template) {
                return $this->errorResponse(
                    'WhatsApp template not configured. Create an SMS template with slug "treatment_whatsapp".',
                    422,
                );
            }

            $appointmentTime = 'N/A';
            if ($appointment->scheduled_date && $appointment->scheduled_time) {
                try {
                    $appointmentTime = Carbon::parse($appointment->scheduled_time)->format('h:i A');
                } catch (\Throwable $e) {
                    $appointmentTime = (string) $appointment->scheduled_time;
                }
            }

            $tokens = [
                'patient_name'      => $appointment->patient?->name ?? 'N/A',
                'appointment_time'  => $appointmentTime,
                'patient_id'        => (string) ($appointment->patient?->id ?? 'N/A'),
                'appointment_id'    => (string) $appointment->id,
                'doctor_name'       => $appointment->doctor?->name ?? 'N/A',
                'location_name'     => $appointment->location?->name ?? 'N/A',
                'centre_google_map' => $appointment->location?->google_map ?? 'N/A',
                'service_name'      => $appointment->service?->name ?? 'N/A',
                'scheduled_date'    => $appointment->scheduled_date
                    ? $appointment->scheduled_date->format('Y-m-d')
                    : 'N/A',
                'scheduled_time'    => $appointment->scheduled_time
                    ? (string) $appointment->scheduled_time
                    : 'N/A',
                'status'            => $appointment->appointment_status?->name ?? 'N/A',
            ];

            $message = $template->content;
            foreach ($tokens as $key => $value) {
                $message = str_replace('##' . $key . '##', $value, $message);
                $message = str_replace('#' . $key . '#', $value, $message);
            }

            return $this->successResponse('WhatsApp data retrieved.', [
                'whatsapp' => $whatsapp,
                'message'  => $message,
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'TreatmentController');
        }
    }

    /**
     * Treatment invoice payload for the SPA. Mirrors the data shape
     * the legacy `AppointmentInvoiceController@invoice` Blade view
     * computes, but returns JSON rather than rendering HTML so the
     * React invoice dialog can drive it directly.
     *
     * Treatment-specific fields (vs the consultancy invoice endpoint):
     *   • `packages` — list of patient packages at this centre that
     *     still have unconsumed sessions for this service. The
     *     operator picks one to consume from, or none → cash flow.
     *   • `service_in_plan` — whether the service exists in any of
     *     the patient's packages at this centre (consumed or not).
     *   • `amount_create` / `tax_create` — pre-computed tax breakdown
     *     used by the legacy save flow.
     */
    public function invoice(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.invoice.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $appointment = $this->requireTreatment($id);
            $appointment->load(['service', 'doctor', 'location.city', 'appointment_type', 'patient']);

            $paidStatus = InvoiceStatuses::where('slug', 'paid')->first();
            $existingInvoice = Invoices::where([
                ['appointment_id', '=', $id],
                ['invoice_status_id', '=', $paidStatus?->id],
            ])->first();

            $paymentModes = PaymentModes::where('type', 'application')->pluck('name', 'id');
            $paymentModes->prepend('Select', '0');

            $patient = $appointment->patient_id ? User::find($appointment->patient_id) : null;
            $account = Accounts::find($appointment->account_id);
            $location = $appointment->location;
            $service = $appointment->service;

            // Already-paid branch — return the saved invoice id and a
            // skeleton payload so the SPA can render the "already
            // invoiced" state with reprint links.
            if ($existingInvoice) {
                // Pull the line-item pricing breakdown off invoice_details so
                // the printable can show the patient's regular vs. paid amount
                // (otherwise complimentary invoices print as a row of zeros
                // and read like a generic consultation receipt — no signal
                // that a discount was actually applied).
                $invoiceDetail = DB::table('invoice_details')
                    ->where('invoice_id', '=', $existingInvoice->id)
                    ->first();
                $invoicePriceTax = $invoiceDetail ? (float) $invoiceDetail->tax_exclusive_serviceprice : null;
                $invoiceTaxRate  = $invoiceDetail ? (float) $invoiceDetail->tax_percentage : null;
                $invoiceTaxAmt   = $invoiceDetail ? (float) $invoiceDetail->tax_including_price : (float) $existingInvoice->total_price;

                return $this->successResponse('Invoice details.', [
                    'invoice_status' => true,
                    'invoice_id'     => $existingInvoice->id,
                    'appointment_id' => $id,
                    'price'          => $invoiceDetail ? (float) $invoiceDetail->service_price : null,
                    'tax'            => $invoiceTaxRate,
                    'tax_amt'        => $invoiceTaxAmt,
                    'settle_amount'  => null,
                    'outstanding'    => null,
                    'cash'           => null,
                    'price_tax'      => $invoicePriceTax,
                    'amount_create'  => null,
                    'tax_create'     => null,
                    // Per-row discount breakdown so the printable can render
                    // "Service Price / Discount / Total" instead of a row of
                    // zeros for complimentary services. Null when no
                    // invoice_details row exists (legacy invoices).
                    'invoice_line' => $invoiceDetail ? [
                        'service_price' => (float) $invoiceDetail->service_price,
                        'discount_name' => $invoiceDetail->discount_name ?: null,
                        'discount_type' => $invoiceDetail->discount_type ?: null,
                        'discount_price' => (float) $invoiceDetail->discount_price,
                        'net_amount' => (float) $invoiceDetail->net_amount,
                        'tax_exclusive_serviceprice' => (float) $invoiceDetail->tax_exclusive_serviceprice,
                        'tax_percentage' => (float) $invoiceDetail->tax_percentage,
                        'tax_price' => (float) $invoiceDetail->tax_price,
                        'tax_including_price' => (float) $invoiceDetail->tax_including_price,
                    ] : null,
                    'packages'       => [],
                    'service_in_plan' => false,
                    'service'        => $service ? [
                        'id'   => $service->id,
                        'name' => $service->name,
                        'price' => $service->price ?? null,
                        'tax_treatment_type_id' => $service->tax_treatment_type_id ?? null,
                    ] : null,
                    'appointment_type' => $appointment->appointment_type ? [
                        'id'   => $appointment->appointment_type->id,
                        'name' => $appointment->appointment_type->name,
                    ] : null,
                    'location_info' => $location ? [
                        'id' => $location->id,
                        'name' => $location->name,
                        'tax_percentage' => $location->tax_percentage ?? null,
                        'address' => $location->address ?? null,
                        'fdo_phone' => $location->fdo_phone ?? null,
                        'ntn' => $location->ntn ?? null,
                        'stn' => $location->stn ?? null,
                        'city' => $location->city ? [
                            'id' => $location->city->id,
                            'name' => $location->city->name,
                        ] : null,
                    ] : null,
                    'discounts'    => [],
                    'payment_modes' => $paymentModes,
                    'patient' => $patient ? [
                        'id' => $patient->id,
                        'name' => $patient->name,
                        'phone' => Gate::allows('treatments.list.view_contact') ? $patient->phone : null,
                    ] : null,
                    'doctor' => $appointment->doctor ? [
                        'id' => $appointment->doctor->id,
                        'name' => $appointment->doctor->name,
                    ] : null,
                    'account' => $account ? [
                        'id' => $account->id,
                        'name' => $account->name ?? null,
                        'email' => $account->email ?? null,
                    ] : null,
                ]);
            }

            // Unpaid branch — compute price + tax exactly the way the
            // legacy Blade controller does (tax-exclusive vs inclusive
            // services have different math) so the SPA's preview lines
            // up with the stored numbers when the invoice is saved.
            $price = 0;
            $amountCreate = 0;
            $taxCreate = 0;
            $serviceInPlan = false;
            $packages = [];

            $serviceTypeName = Config::get('constants.Service');
            $isTreatment = $appointment->appointment_type?->name === $serviceTypeName;

            if ($isTreatment && $service && $location) {
                // Has the patient any active package at this centre that
                // contains this service? (consumed or not — drives the
                // `service_in_plan` flag).
                $serviceInPlan = DB::table('packages')
                    ->leftJoin('package_services', 'packages.id', '=', 'package_services.package_id')
                    ->where([
                        'packages.active'              => '1',
                        'packages.patient_id'          => $appointment->patient_id,
                        'package_services.service_id'  => $appointment->service_id,
                        'packages.location_id'         => $appointment->location_id,
                    ])->exists();

                // Packages with **unconsumed** sessions for this service —
                // the operator picks one of these to consume from.
                $packages = DB::table('packages')
                    ->leftJoin('package_services', 'packages.id', '=', 'package_services.package_id')
                    ->where([
                        'packages.active'                  => '1',
                        'packages.patient_id'              => $appointment->patient_id,
                        'package_services.service_id'     => $appointment->service_id,
                        'package_services.is_consumed'    => '0',
                        'packages.location_id'             => $appointment->location_id,
                    ])
                    ->select('packages.id', 'packages.name')
                    ->groupBy('packages.id', 'packages.name')
                    ->orderByDesc('packages.id')
                    ->get()
                    ->map(fn ($p) => ['id' => (int) $p->id, 'name' => (string) $p->name])
                    ->all();

                // Pricing only matters when there's no package available —
                // otherwise the operator pays via the package and the
                // amount comes from `package_service.actual_price`.
                if (empty($packages)) {
                    $servicePrice = (float) ($service->price ?? 0);

                    // Plan-aware pricing override: if the service is
                    // attached to a plan (even if all sessions used),
                    // the actual_price from package_services wins.
                    if ($serviceInPlan) {
                        $packageService = DB::table('package_services')
                            ->join('packages', 'package_services.package_id', '=', 'packages.id')
                            ->where([
                                'packages.active'             => '1',
                                'packages.patient_id'         => $appointment->patient_id,
                                'package_services.service_id' => $appointment->service_id,
                                'packages.location_id'        => $appointment->location_id,
                            ])
                            ->select('package_services.actual_price')
                            ->first();
                        if ($packageService && $packageService->actual_price !== null) {
                            $servicePrice = (float) $packageService->actual_price;
                        }
                    }

                    $taxPercent = (float) ($location->tax_percentage ?? 0);
                    $taxBoth = (int) Config::get('constants.tax_both');
                    $taxExclusive = (int) Config::get('constants.tax_is_exclusive');
                    $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);

                    if (in_array($orgTaxTreatment, [$taxBoth, $taxExclusive], true)) {
                        // Service price is tax-exclusive → add tax on top.
                        $amountCreate = $servicePrice;
                        $taxCreate    = ceil($servicePrice * ($taxPercent / 100));
                        $price        = ceil($amountCreate + (($amountCreate * $taxPercent) / 100));
                    } else {
                        // Tax-inclusive → back out the net.
                        $price        = $servicePrice;
                        $amountCreate = ceil((100 * $price) / ($taxPercent + 100));
                        $taxCreate    = ceil($price - $amountCreate);
                    }
                }
            }

            // Discount list comes from the same widget the consultancy
            // path uses — keeps the picker consistent across surfaces.
            $discounts = \App\Helpers\Widgets\DiscountWidget::Discount_data_consultancy(
                $appointment,
                Auth::user()->account_id,
            );

            return $this->successResponse('Invoice details.', [
                'invoice_status' => false,
                'invoice_id'     => null,
                'appointment_id' => $id,
                'price'          => $price,
                'tax'            => $location->tax_percentage ?? 0,
                'tax_amt'        => $price,
                'settle_amount'  => 0,
                'outstanding'    => $price,
                'cash'           => 0,
                'price_tax'      => $price,
                // Pre-computed tax breakdown the save endpoint expects.
                'amount_create'  => $amountCreate,
                'tax_create'     => $taxCreate,
                // Treatment-only.
                'packages'       => $packages,
                'service_in_plan' => $serviceInPlan,
                'service'        => $service ? [
                    'id'   => $service->id,
                    'name' => $service->name,
                    'price' => $service->price ?? null,
                    'tax_treatment_type_id' => $service->tax_treatment_type_id ?? null,
                ] : null,
                'appointment_type' => $appointment->appointment_type ? [
                    'id'   => $appointment->appointment_type->id,
                    'name' => $appointment->appointment_type->name,
                ] : null,
                'location_info' => $location ? [
                    'id' => $location->id,
                    'name' => $location->name,
                    'tax_percentage' => $location->tax_percentage ?? null,
                    'address' => $location->address ?? null,
                    'fdo_phone' => $location->fdo_phone ?? null,
                    'ntn' => $location->ntn ?? null,
                    'stn' => $location->stn ?? null,
                    'city' => $location->city ? [
                        'id' => $location->city->id,
                        'name' => $location->city->name,
                    ] : null,
                ] : null,
                'discounts'    => $discounts,
                'payment_modes' => $paymentModes,
                'patient' => $patient ? [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'phone' => Gate::allows('treatments.list.view_contact') ? $patient->phone : null,
                ] : null,
                'doctor' => $appointment->doctor ? [
                    'id' => $appointment->doctor->id,
                    'name' => $appointment->doctor->name,
                ] : null,
                'account' => $account ? [
                    'id' => $account->id,
                    'name' => $account->name ?? null,
                    'email' => $account->email ?? null,
                ] : null,
            ]);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            Log::error('Treatment invoice load failed: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    /**
     * Plan-info payload for the SPA's bundle table — JSON parallel to
     * the legacy `getplansinformation` web route. Returns the bundles
     * + services for a chosen plan plus the consumption-lock data
     * (`total_plan_payments`, `total_consumed_value`, `is_plan_fully_paid`,
     * `config_group_services`) the React invoice modal needs to grey
     * out unbought sessions.
     */
    public function invoicePlanInfo(int $id, int $packageId): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.invoice.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $appointment = $this->requireTreatment($id);
            $serviceId = (int) $appointment->service_id;
            $package = Packages::select('id')->find($packageId);

            if (!$serviceId || !$package) {
                return $this->successResponse('Plan info.', [
                    'package_bundles'      => [],
                    'package_services'     => [],
                    'total_plan_payments'  => 0,
                    'total_consumed_value' => 0,
                    'is_plan_fully_paid'   => false,
                    'config_group_services' => [],
                ]);
            }

            // Bundle-type bundles: package_bundles.bundle_id → bundles.id
            $bundleIds = Bundles::join('bundle_has_services', 'bundles.id', '=', 'bundle_has_services.bundle_id')
                ->where('bundle_has_services.service_id', $serviceId)
                ->pluck('bundles.id')
                ->toArray();

            $bundleTypeBundles = collect();
            if (!empty($bundleIds)) {
                $bundleTypeBundles = PackageBundles::join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
                    ->where('package_bundles.package_id', $package->id)
                    ->whereIn('package_bundles.bundle_id', $bundleIds)
                    ->select('package_bundles.*', 'package_bundles.discount_name as discountname', 'bundles.name as bundlename')
                    ->get();
            }

            // Plan-type bundles: package_bundles.bundle_id stores a service_id directly.
            $bundleTypeBundleIds = $bundleTypeBundles->pluck('id')->toArray();
            $planTypeBundles = PackageBundles::leftJoin('services', 'package_bundles.bundle_id', '=', 'services.id')
                ->where('package_bundles.package_id', $package->id)
                ->whereNotIn('package_bundles.id', $bundleTypeBundleIds)
                ->whereHas('packageservice', function ($q) use ($serviceId) {
                    $q->where('service_id', $serviceId);
                })
                ->select('package_bundles.*', 'package_bundles.discount_name as discountname', 'services.name as bundlename')
                ->get();

            $packageBundles = $bundleTypeBundles->merge($planTypeBundles);

            // Package services for this service. `required_to_consume` is the
            // MINIMUM cumulative payment this session needs before it can be
            // consumed — the same rule the backend enforces in
            // AppointmentInvoiceController::saveinvoice: the REGULAR price for
            // a bundle/package line (GREATEST(orignal, sold), floored at sold),
            // else the sold price. The consume dialog reads this so its
            // pre-check matches what actually happens on consume (previously it
            // used the sold price, so Bundles looked consumable below regular).
            $packageServices = PackageService::join('services', 'package_services.service_id', '=', 'services.id')
                ->leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->where('package_services.package_id', $package->id)
                ->where('package_services.service_id', $serviceId)
                ->select('package_services.*', 'services.name as servicename', 'package_bundles.config_group_id')
                ->selectRaw("CASE WHEN package_bundles.source_type IN ('bundle', 'service_bundle') THEN GREATEST(COALESCE(package_services.orignal_price, 0), package_services.tax_including_price) ELSE package_services.tax_including_price END AS required_to_consume")
                ->get();

            $totalPlanPayments = (float) PackageAdvances::where('package_id', $package->id)
                ->where('cash_flow', 'in')
                ->sum('cash_amount');

            $totalConsumedValue = (float) PackageService::where('package_id', $package->id)
                ->where('is_consumed', 1)
                ->sum('tax_including_price');

            // Cumulative REGULAR requirement of already-consumed sessions —
            // mirrors saveinvoice's `consumedRequired`. The dialog's gate adds
            // this to the current session's `required_to_consume` (capped at the
            // sold plan total) to decide if more payment is needed.
            $totalConsumedRequired = (float) PackageService::leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->where('package_services.package_id', $package->id)
                ->where('package_services.is_consumed', 1)
                ->selectRaw("COALESCE(SUM(CASE WHEN package_bundles.source_type IN ('bundle', 'service_bundle') THEN GREATEST(COALESCE(package_services.orignal_price, 0), package_services.tax_including_price) ELSE package_services.tax_including_price END), 0) AS req")
                ->value('req');

            // All services in the same config groups, used for the
            // consumption-order lock check on the client.
            $configGroupIds = $packageServices->pluck('config_group_id')->filter()->unique()->values();
            $configGroupServices = [];
            if ($configGroupIds->isNotEmpty()) {
                $configGroupServices = PackageService::join('services', 'package_services.service_id', '=', 'services.id')
                    ->leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                    ->where('package_services.package_id', $package->id)
                    ->whereIn('package_bundles.config_group_id', $configGroupIds)
                    ->select(
                        'package_services.id',
                        'package_services.is_consumed',
                        'package_services.consumption_order',
                        'package_services.tax_including_price',
                        'package_bundles.config_group_id',
                        'services.name as servicename',
                    )
                    ->get()
                    ->groupBy('config_group_id')
                    ->toArray();
            }

            // 1-rupee tolerance for rounding (e.g. 3 × 6996.67 ≈ 20990.01 vs 20990 paid).
            $totalPlanValue = (float) PackageService::where('package_id', $package->id)
                ->sum('tax_including_price');
            $isPlanFullyPaid = $totalPlanPayments >= ($totalPlanValue - 1);

            return $this->successResponse('Plan info.', [
                'package_bundles'       => $packageBundles,
                'package_services'      => $packageServices,
                'total_plan_payments'   => $totalPlanPayments,
                'total_consumed_value'  => $totalConsumedValue,
                'total_consumed_required' => $totalConsumedRequired,
                'total_plan_value'      => $totalPlanValue,
                'is_plan_fully_paid'    => $isPlanFullyPaid,
                'config_group_services' => $configGroupServices,
            ]);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            Log::error('invoicePlanInfo failed: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    /**
     * Per-service price + outstanding/settle/remaining payload —
     * JSON parallel to the legacy `getpackageprice` web route. Driven
     * by the React modal once the operator picks a service row from
     * the bundle table.
     *
     * Inputs (`package_id`, `package_service_id`) are query params,
     * appointment id comes from the URL segment.
     */
    public function invoicePackagePrice(int $id, Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.invoice.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $appointment = $this->requireTreatment($id);
            $packageId = (int) $request->input('package_id', 0);
            $packageServiceId = (int) $request->input('package_service_id', 0);

            if (!$packageId || !$packageServiceId) {
                return $this->errorResponse('Missing required parameters.', 422);
            }

            $patientId = (int) $appointment->patient_id;

            // Single-query balance: in − out for this patient/package.
            $balanceData = PackageAdvances::where('patient_id', $patientId)
                ->where('package_id', $packageId)
                ->selectRaw("
                    SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END) as total_out
                ")
                ->first();

            $balance = (int) ceil(((float) ($balanceData->total_in ?? 0)) - ((float) ($balanceData->total_out ?? 0)));

            // Reserved money — a paid BUY whose discounted/free GET was consumed
            // ahead of it — is NOT available to settle a different service. Hold
            // it back so the dialog shows the TRUE outstanding instead of "0",
            // mirroring the saveinvoice reservation gate (ConsumptionReservation).
            // Zero unless the plan was consumed out of order, so ordinary plans
            // are unaffected.
            $reservedBalance = (int) ceil(
                app(\App\Services\Plan\ConsumptionReservation::class)->reservedRefundAmount($packageId)
            );
            $balance = (int) max(0, $balance - $reservedBalance);

            $packageService = PackageService::find($packageServiceId);
            if (!$packageService) {
                return $this->errorResponse('Package service not found.', 404);
            }

            $packageBundle = PackageBundles::find($packageService->package_bundle_id);
            if (!$packageBundle) {
                return $this->errorResponse('Package bundle not found.', 404);
            }

            // Bundle is only relevant when its `type === 'multiple'`
            // (the multi-service bundle math). Single bundles fall
            // through to the per-service pricing.
            $bundle = Bundles::where('id', $packageBundle->bundle_id)
                ->where('type', 'multiple')
                ->first();

            $service = Services::find($packageService->service_id);
            if (!$service) {
                return $this->errorResponse('Service not found.', 404);
            }

            // Bundle access rule: if the patient hasn't paid enough
            // for either the bundle or the service, the bundle math
            // doesn't unlock and we fall through to the per-service
            // remainder mode.
            $packageAccess = 1;
            if ($bundle && $balance < $bundle->price && $balance < $service->price) {
                $packageAccess = 0;
            }

            $totalPackageCost = (float) $packageBundle->tax_including_price;
            $consumedFromBundle = (float) PackageService::where('package_bundle_id', $packageBundle->id)
                ->where('is_consumed', 1)
                ->sum('tax_including_price');
            $remainingToPay = max(0, $totalPackageCost - $consumedFromBundle - $balance);

            $cash = 0;
            $calcOutstanding = function ($bundle, $service, $balance, $cash, $remainingToPay, $fallback = 0) {
                if ($bundle) {
                    if ($balance >= $service->price) {
                        return 0;
                    }
                    $naive = (int) $service->price - (int) $balance - $cash;
                    return min($naive, max(0, $remainingToPay));
                }
                return (int) $fallback - $cash - (int) $balance;
            };

            if ($packageAccess === 1) {
                $price = (float) $packageService->tax_including_price;
                $outstanding = $calcOutstanding($bundle, $service, $balance, $cash, $remainingToPay, $packageService->tax_including_price);
                $remaining = 0;
                $settleAmount = min($price - $cash, $balance);
            } else {
                if ($packageService->price > ($packageBundle->net_amount - $balance)) {
                    $price = (float) $packageService->price;
                    $outstanding = $calcOutstanding($bundle, $service, $balance, $cash, $remainingToPay);
                    $settleAmount = min((int) ($packageBundle->net_amount - $balance) - $cash, $balance);
                } else {
                    $price = (float) $packageService->price;
                    $outstanding = $calcOutstanding($bundle, $service, $balance, $cash, $remainingToPay);
                    $settleAmount = min($price - $cash, $balance);
                }
                $remaining = (float) $packageService->tax_including_price;
            }

            return $this->successResponse('Package price.', [
                'amount'        => (float) $packageService->tax_exclusive_price,
                'tax_price'     => (float) $packageService->tax_price,
                'service_price' => (float) $price,
                'outstanding'   => max(0, (float) $outstanding),
                'settle_amount' => round((float) $settleAmount, 2),
                'balance'       => round((float) $balance, 2),
                'remaining'     => (float) $remaining,
            ]);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            Log::error('invoicePackagePrice failed: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function export(Request $request): BinaryFileResponse
    {
        if (!Gate::allows('treatments.export')) {
            abort(403, 'You are not authorised to export treatments.');
        }

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        // Force appointment_type=treatment so this endpoint can never
        // bleed consultancy rows. The legacy ExportConsultancies
        // exporter is type-agnostic — appointment_type filter selects
        // which rows it actually emits.
        $treatmentTypeId = AppointmentType::Treatment->value;

        $request->merge([
            'appointmenttype'              => $treatmentTypeId,
            'filter_date_from'             => $request->input('scheduled_date_from'),
            'filter_date_to'               => $request->input('scheduled_date_to'),
            'filter_created_from_id'       => $request->input('created_date_from'),
            'filter_created_to_id'         => $request->input('created_date_to'),
            'filter_doctor_id'             => $request->input('doctor_id'),
            'filter_center_id'             => $request->input('location_id'),
            'filter_service_id'            => $request->input('service_id'),
            'filter_status_id'             => $request->input('appointment_status_id'),
            'filter_patient_id'            => $request->input('patient_id'),
            'filter_created_by_id'         => $request->input('created_by'),
            'filter_updated_by_id'         => $request->input('updated_by'),
            'filter_rescheduled_by_id'     => $request->input('rescheduled_by'),
            'filter_phone'                 => $request->input('phone'),
        ]);

        return Excel::download(new ExportConsultancies(10000, 0, $request), 'treatments.xlsx');
    }

    /**
     * Resolve a safe sort tuple from the request — matches the
     * consultancy controller's resolveSort().
     *
     * @return array{field: string, direction: string}
     */
    private function resolveSort(Request $request): array
    {
        $allowed = [
            'name'                  => 'appointments.name',
            'scheduled_date'        => 'appointments.scheduled_date',
            'created_at'            => 'appointments.created_at',
            'updated_at'            => 'appointments.updated_at',
            'appointment_status_id' => 'appointments.appointment_status_id',
            'location_id'           => 'appointments.location_id',
            'doctor_id'             => 'appointments.doctor_id',
            'service_id'            => 'appointments.service_id',
        ];

        $field = $request->input('sort.field');
        $direction = strtolower((string) $request->input('sort.sort', 'desc'));

        if (!is_string($field) || !array_key_exists($field, $allowed)) {
            return ['field' => 'appointments.created_at', 'direction' => 'desc'];
        }

        if ($direction !== 'asc' && $direction !== 'desc') {
            $direction = 'desc';
        }

        return ['field' => $allowed[$field], 'direction' => $direction];
    }

    /**
     * Load a row by id and 404 if it isn't a treatment. Centralises
     * the type-guard so the various write actions can't accidentally
     * mutate a consultancy via a treatment endpoint.
     */
    private function requireTreatment(int $id): Appointments
    {
        $appointment = $this->appointmentService->getAppointmentById($id);

        if (!$appointment || $appointment->appointment_type_id !== AppointmentType::Treatment->value) {
            throw AppointmentException::notFound();
        }

        return $appointment;
    }

    // ──────────────────────────────────────────────────
    //  Scheduled / Non-Scheduled / Statistics
    // ──────────────────────────────────────────────────

    public function scheduled(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.list.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to']);
            $treatments = $this->treatmentService->getScheduledTreatments($filters);

            return $this->successResponse('Scheduled treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching scheduled treatments: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function nonScheduled(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.list.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id']);
            $treatments = $this->treatmentService->getNonScheduledTreatments($filters);

            return $this->successResponse('Non-scheduled treatments retrieved successfully.', $treatments);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching non-scheduled treatments: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.list.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $filters    = $request->only(['location_id', 'doctor_id', 'service_id', 'appointment_status_id', 'scheduled_date_from', 'scheduled_date_to']);
            $statistics = $this->treatmentService->getTreatmentStatistics($filters);

            return $this->successResponse('Treatment statistics retrieved successfully.', $statistics);
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            Log::error('Error fetching treatment statistics: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Resources & Services
    // ──────────────────────────────────────────────────

    public function availableResources(AvailableResourcesRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.list.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $resources = $this->treatmentService->getAvailableResources(
                $request->integer('location_id'),
                $request->integer('service_id') ?: null,
            );

            return $this->successResponse('Available resources retrieved successfully.', $resources);
        } catch (\Exception $e) {
            Log::error('Error fetching available resources: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    public function servicesByLocation(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('treatments.list.view')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $request->validate(['location_id' => 'required|exists:locations,id']);

            $services = $this->treatmentService->getServicesByLocation(
                $request->integer('location_id'),
            );

            return $this->successResponse('Services retrieved successfully.', $services);
        } catch (\Exception $e) {
            Log::error('Error fetching services by location: ' . $e->getMessage());
            return $this->handleException($e, 'TreatmentController');
        }
    }

    // ──────────────────────────────────────────────────
    //  Standardized response helpers
    // ──────────────────────────────────────────────────

    protected function successResponse(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    protected function errorResponse(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $code);
    }
}
