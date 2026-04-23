<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ACL;
use App\Http\Controllers\Admin\PackagesController as AdminPackagesController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PackageStatusRequest;
use App\Http\Requests\Admin\ResendPackageSMSRequest;
use App\Http\Resources\Package\PackageDetailResource;
use App\Http\Resources\Package\PackageResource;
use App\Http\Resources\Package\PackageSMSLogResource;
use App\Models\Packages;
use App\Models\SMSLogs;
use App\Services\Plan\PlanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Packages (Plans) REST API — read + lifecycle operations.
 *
 * Create/update are intentionally **not** exposed here. The legacy
 * save/update flow spans half a dozen staged calls (service picker,
 * bundles, memberships, voucher reservation, grand-total calculation,
 * cash collection) and the final commit writes to Packages +
 * PackageBundles + PackageServices + Appointments + Invoices +
 * InvoiceDetails + PackageAdvances atomically. Porting that safely
 * requires extracting a dedicated `SavePlanAction`/`UpdatePlanAction`
 * first; wiring a single POST here without that prep would risk
 * half-written plans and broken cash balances.
 *
 * This controller covers: list, show, plans (per-patient), status,
 * destroy, SMS logs, resend SMS.
 */
class PackagesController extends Controller
{
    public function __construct(
        private readonly AdminPackagesController $adminController,
        private readonly PlanService $planService,
    ) {}

    /**
     * GET /api/packages
     *
     * Paginated account-scoped list. Filters: `search` (patient search
     * string or package id), `patient_id`, `location_id`, `status`,
     * `created_from`, `created_to`, `per_page`.
     *
     * Scoped by `ACL::getUserCentres()` just like the legacy datatable.
     * `view_inactive_plans` controls whether deactivated plans are
     * returned.
     *
     * Permission: `managePlans` (policy).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('managePlans', Packages::class)) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:50'],
                'patient_id' => ['nullable', 'integer'],
                'location_id' => ['nullable', 'integer'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'created_from' => ['nullable', 'date'],
                'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Packages::query()
                ->with(['user:id,name,phone', 'location:id,name'])
                ->where('account_id', $accountId)
                ->whereIn('location_id', ACL::getUserCentres());

            if (! Gate::allows('view_inactive_plans')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('patient_id')) {
                $query->where('patient_id', (int) $request->integer('patient_id'));
            }

            if ($request->filled('location_id')) {
                $query->where('location_id', (int) $request->integer('location_id'));
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->string('search'));
                if ($search !== '') {
                    $query->where(function ($q) use ($search): void {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('plan_name', 'like', '%'.$search.'%');

                        $numeric = ltrim($search, '0');
                        if ($numeric !== '' && ctype_digit($numeric)) {
                            $q->orWhere('id', (int) $numeric);
                        }
                    });
                }
            }

            if ($request->filled('created_from')) {
                $query->where('created_at', '>=', Carbon::parse($request->input('created_from'))->startOfDay());
            }

            if ($request->filled('created_to')) {
                $query->where('created_at', '<=', Carbon::parse($request->input('created_to'))->endOfDay());
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('updated_at')->paginate($perPage);

            return $this->successResponse(
                'Packages retrieved successfully.',
                [
                    'items' => PackageResource::collection($paginated->items()),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\PackagesController@index');
        }
    }

    /**
     * GET /api/packages/{package}
     *
     * Full display payload — delegates to `PlanService::getDisplayData()`
     * which returns package + bundles + services + advances + cash
     * summary + membership. Shape is preserved verbatim so downstream
     * consumers stay stable (see `PackageDetailResource` note).
     *
     * Permission: `managePlans` (policy).
     */
    public function show(Packages $package): JsonResponse
    {
        try {
            if (! Gate::allows('managePlans', Packages::class)) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($package)) {
                return $this->errorResponse('Package not found.', 404);
            }

            $data = $this->planService->getDisplayData((int) $package->id);

            return $this->successResponse(
                'Package retrieved successfully.',
                new PackageDetailResource($data),
            );
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Api\\PackagesController@show');
        }
    }

    /**
     * GET /api/packages/patient/{patientId}
     *
     * Per-patient plan list. Mirrors the legacy
     * `planDatatable/{id}` endpoint — used by the patient profile screen.
     *
     * Permission: `managePlans` (policy).
     */
    public function forPatient(int $patientId, Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('managePlans', Packages::class)) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = Packages::query()
                ->with(['user:id,name,phone', 'location:id,name'])
                ->where('account_id', $accountId)
                ->where('patient_id', $patientId)
                ->whereIn('location_id', ACL::getUserCentres());

            if (! Gate::allows('view_inactive_plans')) {
                $query->where('active', 1);
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('updated_at')->paginate($perPage);

            return $this->successResponse(
                'Patient packages retrieved successfully.',
                [
                    'items' => PackageResource::collection($paginated->items()),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\PackagesController@forPatient');
        }
    }

    /**
     * PATCH /api/packages/{package}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). The Policy
     * `inactivatePlan` gates both directions (matches legacy behaviour —
     * there's no separate `activatePlan` ability).
     */
    public function status(PackageStatusRequest $request, Packages $package): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($package)) {
                return $this->errorResponse('Package not found.', 404);
            }

            $statusValue = (int) $request->validated()['status'];
            $action = $statusValue === 1 ? 'activate' : 'inactivate';

            $result = $this->planService->toggleStatus((int) $package->id, $action);

            if (empty($result['success'])) {
                return $this->errorResponse(
                    (string) ($result['message'] ?? 'Status change failed.'),
                    (int) ($result['status_code'] ?? 400),
                );
            }

            $package->refresh()->load(['user:id,name,phone', 'location:id,name']);

            return $this->successResponse(
                (string) $result['message'],
                new PackageResource($package),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Api\\PackagesController@status');
        }
    }

    /**
     * DELETE /api/packages/{package}
     *
     * Soft-delete. Returns **409** when the plan has linked
     * invoice_details or package_advances — mirrors
     * `Packages::isChildExists`.
     *
     * Permission: `destroyPlan` (policy).
     */
    public function destroy(Packages $package): JsonResponse
    {
        try {
            if (! Gate::allows('destroyPlan', Packages::class)) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($package)) {
                return $this->errorResponse('Package not found.', 404);
            }

            $result = $this->planService->deletePlan((int) $package->id);

            if (empty($result['status'])) {
                $message = strtolower((string) ($result['message'] ?? ''));
                $status = str_contains($message, 'child') ? 409 : 400;

                return $this->errorResponse(
                    (string) ($result['message'] ?? 'Delete failed.'),
                    $status,
                );
            }

            return $this->successResponse((string) $result['message']);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Api\\PackagesController@destroy');
        }
    }

    /**
     * GET /api/packages/{package}/sms-logs
     *
     * Returns SMS history for this package, newest first.
     */
    public function smsLogs(Packages $package): JsonResponse
    {
        try {
            if (! Gate::allows('managePlans', Packages::class)) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($package)) {
                return $this->errorResponse('Package not found.', 404);
            }

            $logs = SMSLogs::where('package_id', $package->id)
                ->orderByDesc('created_at')
                ->get();

            return $this->successResponse(
                'Package SMS logs retrieved successfully.',
                PackageSMSLogResource::collection($logs),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\PackagesController@smsLogs');
        }
    }

    /**
     * POST /api/packages/{package}/resend-sms
     *
     * Re-sends a previously-logged SMS for this package. Body: `{ id }`.
     * Delegates to the legacy admin `sendLogSMS` so the Telenor/Jazz
     * operator routing stays in one place.
     */
    public function resendSMS(ResendPackageSMSRequest $request, Packages $package): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($package)) {
                return $this->errorResponse('Package not found.', 404);
            }

            $logId = (int) $request->validated()['id'];

            $log = SMSLogs::where('id', $logId)
                ->where('package_id', $package->id)
                ->first();

            if (! $log) {
                return $this->errorResponse('SMS log not found for this package.', 404);
            }

            $proxied = new Request(['id' => $logId]);
            $response = $this->adminController->sendLogSMS($proxied);

            $payload = $response->getData(true);
            $ok = isset($payload['status']) && (int) $payload['status'] === 1;

            return $ok
                ? $this->successResponse('SMS has been re-sent successfully.')
                : $this->errorResponse('SMS could not be re-sent. Please try again.', 502);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\PackagesController@resendSMS');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(Packages $package): bool
    {
        return (int) $package->account_id === (int) Auth::user()->account_id;
    }
}
