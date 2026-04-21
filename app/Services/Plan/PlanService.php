<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Enums\AppointmentType;
use App\Enums\CashFlow;
use App\Enums\PlanType;
use App\Exceptions\PlanException;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Helpers\Widgets\DiscountWidget;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AuditTrails;
use App\Models\BundleHasServices;
use App\Models\Bundles;
use App\Models\Discounts;
use App\Models\DoctorHasLocations;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\PackageVouchers;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\PlanInvoice;
use App\Models\RoleHasUsers;
use App\Models\ServiceBundle;
use App\Models\Services;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\StudentVerification;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Models\UserOperatorSettings;
use App\Models\UserVouchers;
use App\Services\Membership\StudentVerificationService;
use App\Services\MetaConversionApiService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PlanService
{
    use ParsesDateRange;

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly PlanDiscountService $discountService,
        private readonly PlanRefundService $refundService,
        private readonly PlanMembershipService $membershipService,
    ) {}

    // ──────────────────────────────────────────────────
    //  Datatable — Patient-scoped
    // ──────────────────────────────────────────────────

    /**
     * @return array{total: int, query: Builder, orderBy: string, order: string}
     */
    public function getDatatableData(array $filters, int $patientId): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;

        $whereConditions = $this->buildWhereConditions($filters, 'patient_packages', $userId, $accountId, $patientId);
        [$orderBy, $order] = $this->getOrderParams($filters);

        return [
            'total' => $this->buildCountQuery($whereConditions)->count(),
            'query' => $this->buildOptimizedResultQuery($whereConditions, $accountId),
            'orderBy' => $orderBy,
            'order' => $order,
        ];
    }

    // ──────────────────────────────────────────────────
    //  Datatable — Global (admin packages page)
    // ──────────────────────────────────────────────────

    /**
     * @return array{total: int, query: Builder, orderBy: string, order: string, filter_values: array}
     */
    public function getGlobalDatatableData(array $filters): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;

        $whereConditions = $this->buildGlobalWhereConditions($filters, 'packages', $userId, $accountId);
        [$orderBy, $order] = $this->getOrderParams($filters);

        $userCentres = ACL::getUserCentres();
        $locations = (object) Locations::whereIn('id', $userCentres)
            ->where('active', 1)
            ->where('name', '!=', 'All Centres')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        $countQuery = $this->buildCountQuery($whereConditions);
        $total = $countQuery->count();

        Log::debug('Global Plans Datatable Debug', [
            'filters' => $filters,
            'whereConditions' => $whereConditions,
            'userCentres' => $userCentres,
            'accountId' => $accountId,
            'total' => $total,
            'sql' => $countQuery->toSql(),
        ]);

        return [
            'total' => $total,
            'query' => $this->buildOptimizedResultQuery($whereConditions, $accountId),
            'orderBy' => $orderBy,
            'order' => $order,
            'filter_values' => ['locations' => $locations],
        ];
    }

    // ──────────────────────────────────────────────────
    //  Statistics
    // ──────────────────────────────────────────────────

    /**
     * @return array{total_plans: int, active_plans: int, total_amount: float, cash_received: float, refunded_plans: int}
     */
    public function getPatientStatistics(int|string $patientId): array
    {
        $stats = Packages::where('patient_id', $patientId)
            ->selectRaw('
                COUNT(*) as total_plans,
                SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_plans,
                SUM(total_price) as total_amount,
                SUM(CASE WHEN is_refund = 1 THEN 1 ELSE 0 END) as refunded_plans
            ')
            ->first();

        $cashReceived = PackageAdvances::whereHas('package', fn ($q) => $q->where('patient_id', $patientId))
            ->where('cash_flow', CashFlow::In->value)
            ->where('is_cancel', 0)
            ->sum('cash_amount');

        return [
            'total_plans' => (int) ($stats->total_plans ?? 0),
            'active_plans' => (int) ($stats->active_plans ?? 0),
            'total_amount' => (float) ($stats->total_amount ?? 0),
            'cash_received' => (float) $cashReceived,
            'refunded_plans' => (int) ($stats->refunded_plans ?? 0),
        ];
    }

    // ──────────────────────────────────────────────────
    //  Lookup Data (cached)
    // ──────────────────────────────────────────────────

    public function getLookupData(int|string $patientId): array
    {
        $cacheKey = "plan_lookup_patient_{$patientId}_".Auth::id();

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($patientId): array {
            $userCentres = ACL::getUserCentres();

            return [
                'locations' => Locations::whereIn('id', $userCentres)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray(),
                'packages' => Packages::where('patient_id', $patientId)
                    ->pluck('name', 'id')
                    ->toArray(),
                'statuses' => ['1' => 'Active', '0' => 'Inactive'],
            ];
        });
    }

    public function getGlobalLookupData(): array
    {
        $cacheKey = 'plan_global_lookup_'.Auth::id();

        return Cache::remember($cacheKey, self::CACHE_TTL, function (): array {
            $userCentres = ACL::getUserCentres();

            return [
                'locations' => Locations::whereIn('id', $userCentres)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray(),
                'statuses' => ['1' => 'Active', '0' => 'Inactive'],
            ];
        });
    }

    public function clearLookupCache(int|string $patientId): void
    {
        Cache::forget("plan_lookup_patient_{$patientId}_".Auth::id());
    }

    // ──────────────────────────────────────────────────
    //  Bulk Delete
    // ──────────────────────────────────────────────────

    /**
     * @param  array<int>  $ids
     * @return array{deleted: int, skipped: int, message: string}
     */
    public function handleBulkDelete(array $ids): array
    {
        $accountId = Auth::user()->account_id;
        $deleted = 0;
        $skipped = 0;

        $packages = Packages::whereIn('id', $ids)
            ->where('account_id', $accountId)
            ->get();

        foreach ($packages as $package) {
            if ($this->hasChildRecords($package->id)) {
                $skipped++;

                continue;
            }

            $package->delete();
            $deleted++;
        }

        $message = $deleted > 0
            ? "Successfully deleted {$deleted} record(s)."
                .($skipped > 0 ? " {$skipped} record(s) skipped due to dependencies." : '')
            : "No records were deleted. {$skipped} record(s) have dependencies.";

        return compact('deleted', 'skipped', 'message');
    }

    // ──────────────────────────────────────────────────
    //  Create Form Data
    // ──────────────────────────────────────────────────

    public function getCreateFormData(array $userCentres): array
    {
        $locations = Locations::whereIn('id', $userCentres)
            ->where('active', 1)
            ->with('city:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'city_id'])
            ->mapWithKeys(fn ($loc) => [$loc->id => ($loc->city?->name ?? '').'-'.$loc->name]);

        $paymentmodes = PaymentModes::where('type', 'application')->pluck('name', 'id');

        $customDiscountRange = Settings::where('slug', 'sys-discounts')->first();
        $range = $customDiscountRange ? explode(':', $customDiscountRange->data) : [0, 100];

        $discounts = Discounts::where('active', 1)->get(['id', 'name']);

        return [
            'locations' => $locations,
            // Round 4 Crypto-H1 — Str::random uses random_bytes() (CSPRNG).
            // The previous md5(time() + bounded random_int) was guessable
            // because both inputs had ~26 bits of entropy total — an attacker
            // who could pre-compute md5 values for the current second range
            // could collide with another user's pending plan and inject
            // services into it via the random_id-keyed PackageService rows.
            'random_id' => Str::random(32),
            'paymentmodes' => $paymentmodes,
            'range' => $range,
            'discount_type' => config('constants.amount_types'),
            'discounts' => $discounts,
        ];
    }

    public function getCreateFormDataForPatient(array $userCentres, int $patientId): array
    {
        $data = $this->getCreateFormData($userCentres);
        $data['patient_specific_data_loaded'] = true;

        $patientUser = DB::table('users')->where('id', $patientId)->first(['name']);
        $data['patient_name'] = $patientUser?->name ?? 'Unknown';

        try {
            $lastConsultation = DB::table('appointments')
                ->where('patient_id', $patientId)
                ->where('appointment_type_id', 1)
                ->whereIn('appointment_status_id', [2, 16])
                ->orderByDesc('created_at')
                ->first(['id', 'location_id']);

            if ($lastConsultation) {
                $data['last_consultation_location_id'] = $lastConsultation->location_id;
                $data['last_consultation_id'] = $lastConsultation->id;

                $location = Locations::with('city:id,name')
                    ->where('id', $lastConsultation->location_id)
                    ->first(['id', 'name', 'city_id']);

                $data['last_consultation_location_name'] = $location?->city
                    ? $location->city->name.'-'.$location->name
                    : ($location?->name ?? 'Unknown Location');

                $data['appointmentArray'] = $this->buildPatientAppointments($patientId, $lastConsultation->location_id);
                $data['patient_membership'] = $this->getPatientMembershipDisplay($patientId);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('Error in getCreateFormDataForPatient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);

            $data['patient_data_error'] = $e->getMessage();

            return $data;
        }
    }

    // ──────────────────────────────────────────────────
    //  Appointment Info
    // ──────────────────────────────────────────────────

    public function getAppointmentInfo(int|string $patientId, int|string $locationId): array
    {
        try {
            $arrivedStatus = DB::table('appointment_statuses')->where('is_arrived', 1)->first();
            $convertedStatus = DB::table('appointment_statuses')->where('is_converted', 1)->first();

            $validStatusIds = array_filter([
                $arrivedStatus?->id,
                $convertedStatus?->id,
            ]);

            $appointmentType = DB::table('appointment_types')->where('slug', 'consultancy')->first();

            $appointments = DB::table('appointments')
                ->join('services', 'appointments.service_id', '=', 'services.id')
                ->join('users', 'appointments.doctor_id', '=', 'users.id')
                ->where('appointments.patient_id', $patientId)
                ->where('appointments.appointment_type_id', $appointmentType?->id)
                ->where('appointments.location_id', $locationId)
                ->whereIn('appointments.appointment_status_id', $validStatusIds)
                ->whereNull('appointments.deleted_at')
                ->orderByDesc('appointments.scheduled_date')
                ->orderByDesc('appointments.scheduled_time')
                ->select(
                    'appointments.id',
                    'appointments.scheduled_date',
                    'appointments.scheduled_time',
                    'appointments.doctor_id',
                    'services.name as service_name',
                    'users.name as doctor_name',
                )
                ->get();

            $appointmentArray = [];
            foreach ($appointments as $apt) {
                $dateTime = $apt->scheduled_date.' '.$apt->scheduled_time;
                $appointmentArray[$apt->id] = [
                    'id' => $apt->id.'.A',
                    'name' => $apt->service_name.' - '
                        .Carbon::parse($dateTime)->format('F j,Y h:i A').' - '
                        .$apt->doctor_name,
                    'doctor_id' => $apt->doctor_id,
                ];
            }

            $membershipTypeName = $this->getMembershipForLocation($patientId);

            $doctorIds = DB::table('doctor_has_locations')
                ->where('is_allocated', 1)
                ->where('location_id', $locationId)
                ->pluck('user_id')
                ->toArray();

            $allDoctors = DB::table('users')->whereIn('id', $doctorIds)->pluck('name', 'id')->toArray();

            $selectedUserId = null;
            if (! empty($appointmentArray)) {
                $firstAppointment = reset($appointmentArray);
                if (array_key_exists($firstAppointment['doctor_id'], $allDoctors)) {
                    $selectedUserId = $firstAppointment['doctor_id'];
                }
            }

            $recentTreatmentDoctorIds = DB::table('appointments')
                ->where('patient_id', $patientId)
                ->where('location_id', $locationId)
                ->where('appointment_status_id', 2)
                ->where('appointment_type_id', 2)
                ->where('scheduled_date', '>=', now()->subDays(30)->format('Y-m-d'))
                ->pluck('doctor_id')
                ->unique()
                ->toArray();

            $userIdsToShow = array_unique(array_merge(
                $selectedUserId ? [$selectedUserId] : [],
                $recentTreatmentDoctorIds,
            ));

            $usersToShow = array_intersect_key($allDoctors, array_flip($userIdsToShow));

            return [
                'appointments' => $appointmentArray,
                'membership' => $membershipTypeName,
                'users' => $usersToShow,
                'selected_doctor_id' => $selectedUserId,
                'latest_consultation_id' => $appointments->first()?->id,
            ];
        } catch (\Throwable $e) {
            Log::error('Get Appointment Info Error: '.$e->getMessage());

            return [
                'appointments' => [],
                'membership' => 'No membership',
                'users' => [],
                'selected_doctor_id' => null,
            ];
        }
    }

    // ──────────────────────────────────────────────────
    //  Services by Location
    // ──────────────────────────────────────────────────

    public function getServicesByLocation(int|string $locationId, int|string $accountId): array
    {
        try {
            $serviceHasLocations = DB::table('service_has_locations')
                ->where('location_id', $locationId)
                ->pluck('service_id');

            if ($serviceHasLocations->isEmpty()) {
                return [];
            }

            // All services access (magic service_id=13)
            if ($serviceHasLocations->contains(13)) {
                return DB::table('services')
                    ->where('parent_id', '>', 0)
                    ->where('active', 1)
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at')
                    ->select('id', 'name', 'parent_id', 'active')
                    ->get()
                    ->toArray();
            }

            $assignedServices = DB::table('services')
                ->whereIn('id', $serviceHasLocations)
                ->whereNull('deleted_at')
                ->select('id', 'name', 'parent_id', 'active')
                ->get();

            $resultServices = collect();

            foreach ($assignedServices as $service) {
                if ($service->parent_id == 0) {
                    $children = DB::table('services')
                        ->where('parent_id', $service->id)
                        ->where('active', 1)
                        ->where('account_id', $accountId)
                        ->whereNull('deleted_at')
                        ->select('id', 'name', 'parent_id', 'active')
                        ->get();

                    $resultServices = $resultServices->merge($children);
                } else {
                    $resultServices->push($service);
                }
            }

            return $resultServices
                ->filter(fn ($svc) => $svc->parent_id > 0)
                ->unique('id')
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Get Services By Location Error: '.$e->getMessage());

            return [];
        }
    }

    public function getUserDefaultCenter(): array
    {
        $centers = ACL::getUserCentres();

        return count($centers) === 1
            ? ['status' => true, 'center' => $centers[0]]
            : ['status' => false, 'center' => null];
    }

    // ──────────────────────────────────────────────────
    //  Add Service to Package
    // ──────────────────────────────────────────────────

    /**
     * @throws PlanException
     */
    public function addServiceToPackage(array $data): array
    {
        DB::beginTransaction();

        try {
            $service = Services::find($data['bundle_id'])
                ?? throw PlanException::invalidOperation('Service not found');

            $location = Locations::find($data['location_id'])
                ?? throw PlanException::invalidOperation('Location not found');

            $discount = ! empty($data['discount_id'])
                ? Discounts::find($data['discount_id'])
                : null;

            $soldBy = $data['sold_by'] ?? null;
            $soldByName = $soldBy
                ? (User::find($soldBy)?->name ?? '-')
                : '-';

            $packageBundleData = $this->buildPackageBundleDataFromService($service, $discount, $location, $data);

            if ($discount?->discount_type === 'voucher') {
                $this->handleVoucherConsumption(
                    $discount,
                    $data['user_id'],
                    $data['random_id'],
                    $service,
                    $data['discount_price'] ?? 0,
                    $packageBundleData['id'],
                );
            }

            $serviceData = [[
                'service_price' => $service->price,
                'calculated_price' => $data['net_amount'] ?? $service->price,
                'service_id' => $service->id,
                'name' => $service->name,
                'is_consumed' => 0,
            ]];

            $allDataServices = $this->buildServiceDataWithTaxFromService($serviceData, $service, $location, $data);

            $previousServicesTotal = PackageService::where('random_id', $data['random_id'])->sum('tax_including_price');

            $total = $previousServicesTotal > 0
                ? $previousServicesTotal
                : number_format((float) $packageBundleData['tax_including_price'], 2, '.', '');

            $packageServices = PackageService::where('random_id', $data['random_id'])->get();
            $packageBundle = PackageBundles::where('random_id', $data['random_id'])->get();

            $discountData = $this->prepareDiscountData($discount, $packageBundleData, $data);

            DB::commit();

            return [
                'bundlesData' => $packageBundleData,
                'packageServicesData' => $allDataServices,
                'packageServices' => $packageServices,
                'packageBundle' => $packageBundle,
                'random_id' => $data['random_id'],
                'service_name' => $packageBundleData['service_name'],
                'service_price' => $packageBundleData['service_price'],
                'discount_name' => $discountData['discount_name'],
                'discount_type' => $discountData['discount_type'],
                'discount_price' => $discountData['discount_price'],
                'net_amount' => $packageBundleData['net_amount'],
                'total' => $total,
                'sold_by' => $soldBy,
                'sold_by_name' => $soldByName,
            ];
        } catch (PlanException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            // Round 4 Crypto-H3 — getTraceAsString() inlines argument values
            // (patient ids, CNICs, phone numbers passed down the call chain)
            // into the log line. Replace with file/line so debugging stays
            // possible but PII does not land in storage/logs/laravel.log.
            Log::error('Add Service To Package Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw PlanException::invalidOperation('Failed to add service to package: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────
    //  Save Plan Package
    // ──────────────────────────────────────────────────

    /**
     * @throws PlanException
     */
    public function savePlanPackage(array $data, mixed $request = null): array
    {
        $this->validateCustomDiscountLimits($data);

        DB::beginTransaction();

        try {
            $appointmentId = $this->handleAppointment($data)
                ?? throw PlanException::invalidOperation('Appointment ID is required');

            $package = $this->createPackageRecord($data, $appointmentId);

            $planType = PlanType::tryFrom($data['plan_type'] ?? 'plan') ?? PlanType::Plan;

            if ($planType === PlanType::Membership) {
                $this->processMembershipPlan($package, $data, $appointmentId, $request);
            } else {
                $this->storePackageBundlesOptimized($package, $data);
                $this->updatePlanName($package);

                if ($this->hasPayment($data)) {
                    $this->handlePackagePayment($package, $data, $appointmentId);
                }
            }

            DB::commit();

            return ['status' => true, 'package_id' => $package->id];
        } catch (PlanException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            // Round 4 Crypto-H3 — see comment on Add Service handler above.
            Log::error('Save Plan Package Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw PlanException::invalidOperation('Failed to save package: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────
    //  Update Plan Package
    // ──────────────────────────────────────────────────

    /**
     * @throws PlanException
     */
    public function updatePlanPackage(array $data): array
    {
        $this->validateCustomDiscountLimits($data);

        DB::beginTransaction();

        try {
            $package = Packages::where('random_id', $data['random_id'])->first()
                ?? throw PlanException::packageNotFound();

            $isSettled = PackageAdvances::where([
                ['cash_flow', '=', CashFlow::Out->value],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $package->id],
            ])->exists();

            if ($isSettled) {
                throw PlanException::alreadySettled();
            }

            $hasNewServices = ! empty($data['package_bundles']);
            $hasPayment = $this->hasPayment($data);

            $appointmentId = $this->handleAppointmentForUpdate($data, $package);

            if (! $appointmentId && ($hasNewServices || $hasPayment)) {
                throw PlanException::invalidOperation('Appointment ID is required');
            }

            if ($hasNewServices || $hasPayment) {
                $package->update([
                    'total_price' => str_replace(',', '', $data['total']),
                    'sessioncount' => '1',
                    'account_id' => Auth::user()->account_id,
                    'appointment_id' => $appointmentId,
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ]);
            }

            if ($hasNewServices) {
                $this->validateConsumptionOrder($package);
                $this->storePackageBundlesOptimized($package, $data);
            }

            if ($hasPayment) {
                $this->handlePackagePayment($package, $data, $appointmentId);
            }

            $this->updatePlanName($package);

            DB::commit();

            return ['status' => true, 'message' => 'Plan updated successfully.', 'package_id' => $package->id];
        } catch (PlanException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            // Round 4 Crypto-H3 — see comment on Add Service handler above.
            Log::error('Update Plan Package Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw PlanException::invalidOperation('Failed to update package: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────
    //  Display / Edit Data
    // ──────────────────────────────────────────────────

    /**
     * Hydrate `bundle` and `service` relations on package_bundle rows
     * with a sensible display service, so JS / Blade consumers always
     * find a name regardless of source_type value or broken references.
     *
     * Resolution order for the display service:
     *   1. source_type='bundle'         → keep `bundle` as-is (Bundles record)
     *   2. source_type='membership'     → keep `membershipType` as-is
     *   3. source_type='service_bundle' → clone serviceBundle.service and prefix name with "{qty}x "
     *   4. $pb->service populated       → use it (covers source_type='service' and legacy rows)
     *   5. $pb->bundle populated        → use its name (legacy rows where bundle still exists)
     *   6. first child package_service  → last-resort fallback for rows whose bundle_id
     *                                     no longer resolves (hard-deleted service, mis-set
     *                                     source_type, etc.)
     *
     * Both `bundle` and `service` relations are populated so every JS
     * consumer (some read .bundle.name, some read .service.name) works.
     * Relations that are already populated are never overwritten.
     */
    public function normalizeBundleDisplayRelations(Collection $packageBundles): void
    {
        foreach ($packageBundles as $pb) {
            if ($pb->source_type === 'bundle' && $pb->bundle) {
                continue;
            }
            if ($pb->source_type === 'membership' && $pb->membershipType) {
                continue;
            }

            $displayService = null;

            if ($pb->source_type === 'service_bundle' && $pb->serviceBundle?->service) {
                $src = $pb->serviceBundle->service;
                $displayService = $src->replicate();
                $displayService->id = $src->id;
                $displayService->name = $pb->qty.'x '.$src->name;
            } elseif ($pb->service) {
                $displayService = $pb->service;
            } elseif ($pb->bundle) {
                $displayService = new Services(['name' => $pb->bundle->name]);
                $displayService->id = $pb->bundle->id;
            } else {
                $firstChild = $pb->packageservice?->first();
                if ($firstChild?->service) {
                    $displayService = $firstChild->service;
                }
            }

            if (! $displayService) {
                continue;
            }

            if (! $pb->bundle) {
                $pb->setRelation('bundle', $displayService);
            }
            if (! $pb->service) {
                $pb->setRelation('service', $displayService);
            }
        }
    }

    /**
     * @throws PlanException
     */
    public function getEditFormData(int|string $packageId): array
    {
        try {
            $package = Packages::with('user', 'location')->find($packageId)
                ?? throw PlanException::notFound($packageId);

            // Cast to float — Eloquent `sum()` on a decimal column returns a
            // string, which would break the JS `total_price.toFixed(...)` call
            // on the plan edit screen.
            $totalPrice = (float) PackageBundles::where('package_id', $packageId)->sum('tax_including_price');

            $packageBundles = PackageBundles::with(['bundle', 'service', 'discount', 'membershipType', 'serviceBundle.service', 'packageservice.service', 'packageservice.soldBy'])
                ->where('package_id', $packageId)
                ->get();

            $this->normalizeBundleDisplayRelations($packageBundles);

            $packageServices = PackageService::with('service', 'soldBy')
                ->where('package_id', $packageId)
                ->get();

            $packageAdvances = PackageAdvances::with('paymentmode')
                ->where([
                    ['package_id', '=', $packageId],
                    ['is_cancel', '=', '0'],
                    ['is_adjustment', '=', '0'],
                ])
                ->get();

            $advancesSummary = DB::table('package_advances')
                ->where('package_id', $packageId)
                ->selectRaw("
                    SUM(CASE WHEN cash_flow = 'in' AND is_cancel = 0 AND is_setteled = 0 THEN cash_amount ELSE 0 END) as cash_in,
                    SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END) as cash_out,
                    SUM(CASE WHEN cash_flow = 'out' AND is_refund = 1 THEN cash_amount ELSE 0 END) as refunded,
                    SUM(CASE WHEN cash_flow = 'out' AND is_setteled = 1 THEN cash_amount ELSE 0 END) as setteled
                ")
                ->first();

            $grandTotal = $totalPrice - ($advancesSummary->cash_in ?? 0);
            $remainingAmount = number_format($grandTotal + ($advancesSummary->refunded ?? 0) + ($advancesSummary->setteled ?? 0));

            $userLocations = Locations::whereIn('id', function ($q) {
                $q->select('location_id')
                    ->from('user_has_locations')
                    ->where('user_id', Auth::id());
            })
                ->where('account_id', Auth::user()->account_id)
                ->where('slug', 'custom')
                ->get();

            $paymentModes = PaymentModes::where('type', 'application')->pluck('name', 'id');

            $customDiscountRange = Cache::remember('sys_discounts', self::CACHE_TTL, fn () => Settings::where('slug', 'sys-discounts')->first());
            $range = $customDiscountRange ? explode(':', $customDiscountRange->data) : [];

            $locationHasService = $this->getServicesByLocation($package->location_id, Auth::user()->account_id);

            $financeEditingDays = Cache::remember('sys_financeediting', self::CACHE_TTL, fn () => Settings::where('slug', 'sys-financeediting')->first());
            $endPreviousDate = Carbon::now()->subDays($financeEditingDays->data ?? 0)->toDateString();

            $appointmentInfo = $this->getAppointmentInfo($package->patient_id, $package->location_id);
            $membershipDisplay = $this->getMembershipDisplay($package->patient_id);
            $discounts = Discounts::where('active', 1)->get(['id', 'name']);

            $selectedAppointmentId = $package->appointment_id
                ? $package->appointment_id.'.A'
                : null;

            $studentDocuments = [];
            $studentVerification = StudentVerification::where('package_id', $packageId)->first();
            if ($studentVerification && ! empty($studentVerification->document_paths)) {
                $studentDocuments = $studentVerification->document_paths;
            }

            $isMembershipConsumed = PackageService::where('package_id', $packageId)
                ->where('is_consumed', 1)
                ->exists();

            return [
                'package' => $package,
                'locations' => $userLocations,
                'packagebundles' => $packageBundles,
                'packageservices' => $packageServices,
                'users' => $appointmentInfo['users'],
                'selectedUserId' => $appointmentInfo['selected_doctor_id'],
                'selectedAppointmentId' => $selectedAppointmentId,
                'packageadvances' => $packageAdvances,
                'paymentmodes' => $paymentModes,
                'grand_total' => $remainingAmount,
                'range' => $range,
                'locationhasservice' => $locationHasService,
                'total_price' => $totalPrice,
                'end_previous_date' => $endPreviousDate,
                'appointmentArray' => $appointmentInfo['appointments'],
                'discount_type' => config('constants.amount_types'),
                'discounts' => $discounts,
                'membership' => $membershipDisplay,
                'student_documents' => $studentDocuments,
                'is_membership_consumed' => $isMembershipConsumed,
            ];
        } catch (PlanException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Get Edit Form Data Error: '.$e->getMessage());
            throw PlanException::invalidOperation('Failed to load edit form data: '.$e->getMessage());
        }
    }

    /**
     * @throws PlanException
     */
    public function getDisplayData(int|string $packageId): array
    {
        try {
            $package = Packages::with('user', 'location')->find($packageId)
                ?? throw PlanException::notFound($packageId);

            $packageBundles = PackageBundles::with(['bundle', 'service', 'discount', 'membershipType', 'serviceBundle.service', 'packageservice.service', 'packageservice.soldBy'])
                ->where('package_id', $packageId)
                ->get();

            $this->normalizeBundleDisplayRelations($packageBundles);

            $packageServices = PackageService::with('service', 'soldBy')
                ->where('package_id', $packageId)
                ->get();

            // Cast to float — Eloquent `sum()` on a decimal column returns a
            // string, which would break the `round()` call below (PHP 8
            // rejects strings in numeric functions).
            $packageServicesPrice = (float) ($package->plan_type === PlanType::Membership
                ? PackageBundles::where('package_id', $packageId)->sum('tax_including_price')
                : PackageService::where('package_id', $packageId)->sum('price'));

            $packageAdvances = PackageAdvances::with('paymentmode')
                ->where([
                    ['package_id', '=', $packageId],
                    ['is_cancel', '=', '0'],
                    ['is_adjustment', '=', '0'],
                ])
                ->get();

            $packageAdvances = $this->processPackageAdvances($packageAdvances);

            $cashSummary = PackageAdvances::where('package_id', $packageId)
                ->selectRaw("
                    SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END) as cash_in,
                    SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END) as cash_out
                ")
                ->first();

            $services = Cache::remember('all_services', self::CACHE_TTL, fn () => Services::getServices());
            $discounts = Cache::remember('discounts_'.Auth::user()->account_id, self::CACHE_TTL, fn () => Discounts::getDiscount(Auth::user()->account_id));
            $paymentModes = PaymentModes::pluck('name', 'id');
            $membershipDisplay = $this->getMembershipDisplayForPackage($package->patient_id);

            $studentDocuments = [];
            $studentVerification = StudentVerification::where('package_id', $packageId)->first();
            if ($studentVerification && ! empty($studentVerification->document_paths)) {
                $studentDocuments = $studentVerification->document_paths;
            }

            return [
                'package' => $package,
                'packagebundles' => $packageBundles,
                'packageservices' => $packageServices,
                'packageadvances' => $packageAdvances,
                'services' => $services,
                'discount' => $discounts,
                'paymentmodes' => $paymentModes,
                'grand_total' => round($packageServicesPrice, 2),
                'membership' => $membershipDisplay,
                'student_documents' => $studentDocuments,
            ];
        } catch (PlanException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Get Display Data Error: '.$e->getMessage());
            throw PlanException::invalidOperation('Failed to load display data: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────
    //  Delete Plan
    // ──────────────────────────────────────────────────

    /**
     * @throws PlanException
     */
    public function deletePlan(int|string $packageId): array
    {
        $package = Packages::find($packageId)
            ?? throw PlanException::notFound($packageId);

        $childRecords = [];

        if (DB::table('invoice_details')->where('package_id', $packageId)->whereNull('deleted_at')->exists()) {
            $childRecords[] = 'Invoice Details';
        }

        if (DB::table('package_advances')->where('package_id', $packageId)->whereNull('deleted_at')->exists()) {
            $childRecords[] = 'Package Advances (Payments)';
        }

        if (! empty($childRecords)) {
            throw PlanException::hasChildRecords(implode(', ', $childRecords));
        }

        $package->delete();

        AuditTrails::deleteEventLogger(
            'packages',
            'delete',
            ['name', 'sessioncount', 'total_price', 'is_exclusive', 'patient_id', 'active', 'location_id', 'appointment_id', 'is_refund', 'created_at', 'updated_at', 'deleted_at'],
            $packageId,
        );

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    // ══════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════

    // ── Query Building ──────────────────────────────────

    private function buildCountQuery(array $where): Builder
    {
        $query = Packages::query();

        if (! empty($where)) {
            $query->where($where);
        }

        $query->whereIn('location_id', ACL::getUserCentres());

        if (! Gate::allows('view_inactive_plans')) {
            $query->where('active', 1);
        }

        return $query;
    }

    private function buildOptimizedResultQuery(array $where, int|string $accountId): Builder
    {
        $userCentres = ACL::getUserCentres();
        $canViewInactive = Gate::allows('view_inactive_plans');

        $scopedPackageIds = DB::table('packages')
            ->select('id')
            ->where('account_id', $accountId)
            ->whereIn('location_id', $userCentres)
            ->whereNull('deleted_at')
            ->when(! $canViewInactive, fn ($q) => $q->where('active', 1));

        if (! empty($where)) {
            foreach ($where as $condition) {
                if (is_array($condition) && count($condition) === 3) {
                    $col = str_replace('packages.', '', $condition[0]);
                    $scopedPackageIds->where($col, $condition[1], $condition[2]);
                }
            }
        }

        $scopedSql = $scopedPackageIds->toSql();
        $scopedBindings = $scopedPackageIds->getBindings();

        $query = Packages::query()
            ->select([
                'packages.*',
                DB::raw('CASE
                    WHEN packages.plan_type = "membership" THEN COALESCE(pb_agg.bundle_total, 0)
                    ELSE COALESCE(ps_agg.service_total, 0)
                END as total_price'),
                DB::raw('COALESCE(pa_agg.cash_receive, 0) as cash_receive'),
                DB::raw('COALESCE(pa_agg.settle_amount, 0) as settle_amount'),
                DB::raw('COALESCE(pa_agg.refund_amount_calculated, 0) as refund_amount_calculated'),
                DB::raw('COALESCE(ps_agg.session_count, 0) as session_count'),
                DB::raw('GREATEST(
                    COALESCE(pa_agg.max_updated, "1970-01-01"),
                    COALESCE(pb_agg.max_updated, "1970-01-01"),
                    COALESCE(ps_agg.max_updated, "1970-01-01")
                ) as latest_advance_updated_at'),
            ])
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(CASE WHEN cash_flow = "in" AND is_cancel = 0 THEN cash_amount ELSE 0 END) as cash_receive,
                    SUM(CASE WHEN cash_flow = "out" AND is_refund = 0 THEN cash_amount ELSE 0 END) as settle_amount,
                    SUM(CASE WHEN is_refund = 1 THEN cash_amount ELSE 0 END) as refund_amount_calculated,
                    MAX(updated_at) as max_updated
                FROM package_advances
                WHERE deleted_at IS NULL
                  AND package_id IN ('.$scopedSql.')
                GROUP BY package_id
            ) as pa_agg'), fn ($join) => $join->on('pa_agg.package_id', '=', 'packages.id'))
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(tax_including_price) as bundle_total,
                    MAX(updated_at) as max_updated
                FROM package_bundles
                WHERE package_id IN ('.$scopedSql.')
                GROUP BY package_id
            ) as pb_agg'), fn ($join) => $join->on('pb_agg.package_id', '=', 'packages.id'))
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(tax_including_price) as service_total,
                    COUNT(*) as session_count,
                    MAX(updated_at) as max_updated
                FROM package_services
                WHERE package_id IN ('.$scopedSql.')
                GROUP BY package_id
            ) as ps_agg'), fn ($join) => $join->on('ps_agg.package_id', '=', 'packages.id'))
            ->with([
                'user:id,name,account_id',
                'user.membership:id,patient_id,code,active,end_date,is_referral',
                'location:id,name,city_id',
                'location.city:id,name',
            ]);

        // Inject bindings (3x for the 3 subqueries)
        $query->addBinding(array_merge($scopedBindings, $scopedBindings, $scopedBindings), 'join');

        if (! empty($where)) {
            $query->where($where);
        }

        $query->whereIn('packages.location_id', $userCentres);

        if (! $canViewInactive) {
            $query->where('packages.active', 1);
        }

        return $query;
    }

    // ── Filter Building ─────────────────────────────────

    private function buildWhereConditions(array $filters, string $filename, int|string $userId, int|string $accountId, int|string $patientId): array
    {
        return $this->buildWhereConditionsBase($filters, $filename, $userId, $accountId, $patientId);
    }

    private function buildGlobalWhereConditions(array $filters, string $filename, int|string $userId, int|string $accountId): array
    {
        return $this->buildWhereConditionsBase($filters, $filename, $userId, $accountId, null);
    }

    private function buildWhereConditionsBase(array $filters, string $filename, int|string $userId, int|string $accountId, int|string|null $patientId): array
    {
        $where = [];
        $applyFilter = $this->shouldApplyFilter($filters);

        $where[] = ['packages.account_id', '=', $accountId];
        Filters::put($userId, $filename, 'account_id', $accountId);

        if ($patientId !== null) {
            $where[] = ['packages.patient_id', '=', $patientId];
            Filters::put($userId, $filename, 'patient_id', $patientId);
        } else {
            $this->addPatientFilter($where, $filters, $userId, $filename, $applyFilter);
        }

        $this->addFilterCondition($where, $filters, 'package_id', 'packages.id', $userId, $filename, $applyFilter);
        $this->addFilterCondition($where, $filters, 'location_id', 'packages.location_id', $userId, $filename, $applyFilter);
        $this->addStatusFilter($where, $filters, $userId, $filename, $applyFilter);
        $this->addDateRangeFilter($where, $filters, $userId, $filename, $applyFilter);

        $this->validateLocationFilter($where, $userId, $filename);

        return $where;
    }

    private function validateLocationFilter(array &$where, int|string $userId, string $filename): void
    {
        $userCentres = ACL::getUserCentres();

        foreach ($where as $idx => $condition) {
            if (! is_array($condition) || count($condition) !== 3) {
                continue;
            }
            if ($condition[0] !== 'packages.location_id') {
                continue;
            }
            if (! in_array((int) $condition[2], array_map('intval', $userCentres), true)) {
                unset($where[$idx]);
                Filters::forget($userId, $filename, 'location_id');
            }
        }

        $where = array_values($where);
    }

    private function addPatientFilter(array &$where, array $filters, int|string $userId, string $filename, bool $applyFilter): void
    {
        if ($this->hasFilter($filters, 'patient_id')) {
            $patientId = $filters['patient_id'];
            if (is_string($patientId) && str_starts_with($patientId, 'P-')) {
                $patientId = GeneralFunctions::patientSearch($patientId);
            }
            $where[] = ['packages.patient_id', '=', $patientId];
            Filters::put($userId, $filename, 'patient_id', $patientId);

            return;
        }

        if (array_key_exists('patient_id', $filters) && ! $this->hasFilter($filters, 'patient_id')) {
            return;
        }

        if ($this->hasFilter($filters, 'patient_name')) {
            $matched = $this->resolvePatientIdsByName($filters['patient_name']);
            if (! empty($matched)) {
                $patientId = $matched[0];
                $where[] = ['packages.patient_id', '=', $patientId];
                Filters::put($userId, $filename, 'patient_id', $patientId);
            } else {
                $where[] = ['packages.patient_id', '=', 0];
            }

            return;
        }

        if ($applyFilter) {
            Filters::forget($userId, $filename, 'patient_id');
        } elseif ($cached = Filters::get($userId, $filename, 'patient_id')) {
            $where[] = ['packages.patient_id', '=', $cached];
        }
    }

    private function resolvePatientIdsByName(string $patientName): array
    {
        $name = trim($patientName);
        if ($name === '') {
            return [];
        }

        $phone = null;
        if (str_contains($name, ' - ')) {
            [$namePart, $phonePart] = array_map('trim', explode(' - ', $name, 2));
            $name = $namePart;
            $phone = $phonePart !== '' ? $phonePart : null;
        }

        return Patients::query()
            ->where(function ($q) use ($name, $phone): void {
                $q->where('name', 'LIKE', '%'.$name.'%');
                if ($phone !== null) {
                    $q->orWhere('phone', 'LIKE', '%'.$phone.'%');
                }
            })
            ->limit(100)
            ->pluck('id')
            ->all();
    }

    private function addFilterCondition(array &$where, array $filters, string $filterKey, string $column, int|string $userId, string $filename, bool $applyFilter): void
    {
        if ($this->hasFilter($filters, $filterKey)) {
            $where[] = [$column, '=', $filters[$filterKey]];
            Filters::put($userId, $filename, $filterKey, $filters[$filterKey]);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, $filterKey);
        } elseif ($cached = Filters::get($userId, $filename, $filterKey)) {
            $where[] = [$column, '=', $cached];
        }
    }

    private function addStatusFilter(array &$where, array $filters, int|string $userId, string $filename, bool $applyFilter): void
    {
        if ($this->hasFilter($filters, 'status')) {
            $where[] = ['packages.active', '=', $filters['status']];
            Filters::put($userId, $filename, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'status');
        } else {
            $status = Filters::get($userId, $filename, 'status');
            if ($status === 0 || $status === 1 || $status === '0' || $status === '1') {
                $where[] = ['packages.active', '=', $status];
            }
        }
    }

    private function addDateRangeFilter(array &$where, array $filters, int|string $userId, string $filename, bool $applyFilter): void
    {
        if ($this->hasFilter($filters, 'created_at')) {
            if (str_contains($filters['created_at'], ' - ')) {
                [$fromDay, $toDay] = self::parseDateRangeAsCarbonDay($filters['created_at']);
                $where[] = ['packages.created_at', '>=', $fromDay];
                $where[] = ['packages.created_at', '<=', $toDay];
                Filters::put($userId, $filename, 'created_at', $filters['created_at']);
            }
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'created_at');
        }
    }

    /**
     * @return array{string, string}
     */
    private function getOrderParams(array $filters): array
    {
        $orderBy = 'packages.updated_at';
        $order = 'DESC';

        if (isset($filters['sort']['field'], $filters['sort']['sort'])) {
            $orderBy = $filters['sort']['field'];
            $order = strtoupper($filters['sort']['sort']);
        }

        if (! in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $fieldMap = [
            'id' => 'packages.id',
            'package_id' => 'packages.id',
            'created_at' => 'packages.created_at',
            'updated_at' => 'packages.updated_at',
            'packages.updated_at' => 'packages.updated_at',
            'latest_advance_updated_at' => 'latest_advance_updated_at',
        ];

        return [$fieldMap[$orderBy] ?? 'packages.updated_at', $order];
    }

    private function hasFilter(array $filters, string $key): bool
    {
        return isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== null;
    }

    private function shouldApplyFilter(array $filters): bool
    {
        $action = $filters['action'] ?? null;

        if (is_array($action) && ($action[0] ?? null) === 'filter_cancel') {
            return true;
        }

        return $action === 'filter';
    }

    // ── Tax Calculations ────────────────────────────────

    private function calculateTax(int|string $taxTreatmentType, float $netAmount, float $taxPercentage, bool $isExclusive): array
    {
        $taxBoth = Config::get('constants.tax_both');
        $taxExclusive = Config::get('constants.tax_is_exclusive');

        return match (true) {
            $taxTreatmentType == $taxBoth && $isExclusive,
            $taxTreatmentType == $taxExclusive => [
                'tax_exclusive_net_amount' => $netAmount,
                'tax_price' => ceil($netAmount * ($taxPercentage / 100)),
                'tax_including_price' => ceil($netAmount + ($netAmount * $taxPercentage / 100)),
            ],
            default => [
                'tax_including_price' => $netAmount,
                'tax_exclusive_net_amount' => ceil((100 * $netAmount) / ($taxPercentage + 100)),
                'tax_price' => ceil($netAmount - ceil((100 * $netAmount) / ($taxPercentage + 100))),
            ],
        };
    }

    private function calculateServiceTax(int|string $taxTreatmentType, float $price, float $taxPercentage, bool $isExclusive): array
    {
        $taxBoth = Config::get('constants.tax_both');
        $taxExclusive = Config::get('constants.tax_is_exclusive');

        $base = ['tax_percentage' => $taxPercentage];

        if (($taxTreatmentType == $taxBoth && $isExclusive) || $taxTreatmentType == $taxExclusive) {
            return $base + [
                'tax_exclusive_price' => $price,
                'tax_price' => ceil($price * ($taxPercentage / 100)),
                'tax_including_price' => ceil($price + ($price * $taxPercentage / 100)),
                'is_exclusive' => 1,
            ];
        }

        if ($taxTreatmentType == $taxBoth && ! $isExclusive) {
            $exclusivePrice = ceil((100 * $price) / ($taxPercentage + 100));

            return $base + [
                'tax_including_price' => $price,
                'tax_exclusive_price' => $exclusivePrice,
                'tax_price' => ceil($price - $exclusivePrice),
                'is_exclusive' => 0,
            ];
        }

        // Default: inclusive
        $exclusivePrice = ceil((100 * $price) / ($taxPercentage + 100));

        return $base + [
            'tax_including_price' => $price,
            'tax_exclusive_price' => $exclusivePrice,
            'tax_price' => ceil($price - $exclusivePrice),
            'is_exclusive' => 0,
        ];
    }

    // ── Package Bundle Builders ─────────────────────────

    private function buildPackageBundleDataFromService(Services $service, ?Discounts $discount, Locations $location, array $data): array
    {
        $packageBundleData = [
            'qty' => '1',
            'bundle_id' => $service->id,
            'service_price' => $service->price,
            'service_name' => $service->name,
            'net_amount' => $data['net_amount'],
        ];

        if ($discount) {
            $discountPrice = $data['discount_price'] ?? 0;
            $packageBundleData['discount_name'] = $discount->name;
            $packageBundleData['discount_price'] = min($discountPrice, $service->price);
            $packageBundleData['discount_type'] = $data['discount_type'] ?? null;
            $packageBundleData['discount_id'] = $discount->id;
        }

        $isExclusive = ($data['is_exclusive'] ?? '0') == '1';
        $packageBundleData['is_exclusive'] = $isExclusive;
        $packageBundleData['tax_percentage'] = $location->tax_percentage;

        $taxData = $this->calculateTax($service->tax_treatment_type_id, (float) $data['net_amount'], (float) $location->tax_percentage, $isExclusive);
        $packageBundleData = array_merge($packageBundleData, $taxData);

        $packageBundleData['id'] = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        return $packageBundleData;
    }

    private function buildServiceDataWithTaxFromService(array $calculatedServicePrices, Services $service, Locations $location, array $data): array
    {
        $allDataServices = [];

        foreach ($calculatedServicePrices as $detail) {
            $dataService = [
                'random_id' => $data['random_id'],
                'service_id' => $detail['service_id'],
                'name' => $detail['name'],
                'price' => $detail['calculated_price'],
                'orignal_price' => $detail['service_price'],
                'created_at' => Filters::getCurrentTimeStamp(),
                'updated_at' => Filters::getCurrentTimeStamp(),
                'is_consumed' => 0,
                'sold_by' => $data['sold_by'] ?? null,
            ];

            $isExclusive = ($data['is_exclusive'] ?? '0') == '1';
            $taxData = $this->calculateServiceTax(
                $service->tax_treatment_type_id,
                (float) $detail['calculated_price'],
                (float) $location->tax_percentage,
                $isExclusive,
            );

            $allDataServices[] = array_merge($dataService, $taxData);
        }

        return $allDataServices;
    }

    // ── Package Storage ─────────────────────────────────

    private function storePackageBundlesOptimized(Packages $package, array $data): void
    {
        if (empty($data['package_bundles'])) {
            return;
        }

        $firstBundle = reset($data['package_bundles']);
        $isSimpleIdFormat = ! is_array($firstBundle);

        if ($isSimpleIdFormat) {
            $packageBundleIds = $data['package_bundles'];
            PackageBundles::whereIn('id', $packageBundleIds)
                ->where('random_id', $data['random_id'])
                ->update(['package_id' => $package->id, 'is_allocate' => 1]);

            PackageService::whereIn('package_bundle_id', $packageBundleIds)
                ->where('random_id', $data['random_id'])
                ->update(['package_id' => $package->id]);

            return;
        }

        $locationInfo = Locations::find($data['location_id']);
        $planType = PlanType::tryFrom($data['plan_type'] ?? 'plan') ?? PlanType::Plan;

        // Separate service_bundle items from regular bundles/services
        $regularBundles = [];
        $serviceBundleItems = [];

        foreach ($data['package_bundles'] as $packageBundle) {
            if (is_array($packageBundle) && ($packageBundle['source_type'] ?? '') === 'service_bundle') {
                $serviceBundleItems[] = $packageBundle;
            } else {
                $regularBundles[] = $packageBundle;
            }
        }

        $allPackageServices = [];

        // Process regular bundles/services
        if (! empty($regularBundles)) {
            $regularData = array_merge($data, ['package_bundles' => $regularBundles]);
            $bundleIds = array_column($regularBundles, 'bundleId');

            if ($planType === PlanType::Plan) {
                $allPackageServices = $this->storePlanTypeServices($package, $regularData, $bundleIds, $locationInfo);
            } else {
                $allPackageServices = $this->storeBundleTypeServices($package, $regularData, $bundleIds, $locationInfo);
            }
        }

        // Process service bundle items
        if (! empty($serviceBundleItems)) {
            $sbServices = $this->storeServiceBundleTypeServices($package, $data, $serviceBundleItems, $locationInfo);
            $allPackageServices = array_merge($allPackageServices, $sbServices);
        }

        if (! empty($allPackageServices)) {
            PackageService::insert($allPackageServices);
        }
    }

    /**
     * Defense-in-depth: enforce custom-slug discount allocation limits
     * (max_percentage / max_fixed_amount) on plan create & update.
     *
     * @throws PlanException
     */
    private function validateCustomDiscountLimits(array $data): void
    {
        $bundles = $data['package_bundles'] ?? [];
        if (empty($bundles) || empty($data['location_id'])) {
            return;
        }

        $locationId = (int) $data['location_id'];
        $accountId = Auth::user()->account_id;

        foreach ($bundles as $idx => $pb) {
            $discountId = $pb['DiscountId'] ?? null;
            $serviceId = $pb['bundleId'] ?? null;
            if (empty($discountId) || $discountId === '0' || empty($serviceId)) {
                continue;
            }

            $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService(
                $locationId,
                (int) $serviceId,
                $accountId
            );
            $allocation = $allocations[$discountId] ?? null;
            if (! $allocation || ($allocation->slug ?? 'default') !== 'custom') {
                continue;
            }

            $service = Services::find($serviceId);
            if (! $service) {
                continue;
            }

            $allocationAmount = (float) ($allocation->amount ?? 0);
            $maxPercentage = $allocation->type === 'Percentage' ? $allocationAmount : 100.0;
            $maxFixedAmount = round(((float) $service->price) * ($allocationAmount / 100), 2);

            $discountValue = (float) str_replace(',', '', (string) ($pb['DiscountValue'] ?? '0'));
            $type = $pb['Type'] ?? null;
            $rowLabel = $service->name ?? 'service #'.$serviceId;

            if ($type === 'Percentage') {
                if ($discountValue > $maxPercentage) {
                    throw PlanException::validationFailed(
                        "Discount on {$rowLabel} exceeds allowed limit (max {$maxPercentage}%)."
                    );
                }
            } elseif ($type === 'Fixed') {
                if ($discountValue > $maxFixedAmount) {
                    throw PlanException::validationFailed(
                        "Discount on {$rowLabel} exceeds allowed limit (max ".number_format($maxFixedAmount, 2).').'
                    );
                }
            } else {
                // Type not provided — fall back to strictest check against both caps.
                if ($discountValue > $maxPercentage && $discountValue > $maxFixedAmount) {
                    throw PlanException::validationFailed(
                        "Discount on {$rowLabel} exceeds allowed allocation limit."
                    );
                }
            }
        }
    }

    private function storePlanTypeServices(Packages $package, array $data, array $bundleIds, Locations $locationInfo): array
    {
        $servicesData = Services::whereIn('id', $bundleIds)->get()->keyBy('id');
        $allPackageServices = [];

        foreach ($data['package_bundles'] as $packageBundle) {
            $serviceId = $packageBundle['bundleId'];
            $serviceData = $servicesData->get($serviceId);

            if (! $serviceData) {
                continue;
            }

            $discountId = $packageBundle['DiscountId'] ?? null;
            if ($discountId == '0' || $discountId == '') {
                $discountId = null;
            }

            $packageBundleData = [
                'random_id' => $package->random_id,
                'is_allocate' => 1,
                'qty' => 1,
                'discount_name' => $packageBundle['DiscountName'] ?? null,
                'discount_type' => $packageBundle['Type'] ?? null,
                'discount_price' => str_replace(',', '', $packageBundle['DiscountValue'] ?? '0'),
                'service_price' => str_replace(',', '', $packageBundle['RegularPrice']),
                'net_amount' => str_replace(',', '', $packageBundle['RegularPrice']),
                'discount_id' => $discountId,
                'config_group_id' => ! empty($packageBundle['config_group_id']) ? $packageBundle['config_group_id'] : null,
                'bundle_id' => $serviceId,
                'source_type' => 'service',
                'package_id' => $package->id,
                'tax_exclusive_net_amount' => str_replace(',', '', $packageBundle['Amount']),
                'tax_percentage' => $locationInfo->tax_percentage ?? 0,
                'tax_price' => str_replace(',', '', $packageBundle['Tax']),
                'tax_including_price' => str_replace(',', '', $packageBundle['Total']),
                'location_id' => $data['location_id'],
            ];

            $packageBundleRecord = PackageBundles::create($packageBundleData);

            // Write the voucher usage journal row. The balance itself
            // was already decremented by `reserveVoucherAmount` when
            // the user clicked "Add" on the plan form — this just
            // pins the usage to the now-persisted PackageBundles row
            // so reports (voucher-usage-by-patient, etc.) can join on
            // a real bundle id.
            if ($discountId) {
                $discountModel = Discounts::find($discountId);
                if ($discountModel && $discountModel->discount_type === 'voucher') {
                    $voucherAmountApplied = (float) str_replace(',', '', $packageBundle['DiscountValue'] ?? '0');
                    if ($voucherAmountApplied <= 0) {
                        $voucherAmountApplied = (float) str_replace(',', '', $packageBundle['RegularPrice'] ?? '0')
                            - (float) str_replace(',', '', $packageBundle['Amount'] ?? '0');
                    }
                    $this->discountService->writeVoucherJournalRow(
                        (int) $discountModel->id,
                        (int) $package->patient_id,
                        max(0.0, $voucherAmountApplied),
                        $packageBundleRecord,
                        $serviceData->id,
                    );
                }
            }

            $totalPrice = (float) str_replace(',', '', $packageBundle['Total']);
            $serviceTaxType = $serviceData->tax_treatment_type_id;
            $isExclusive = $serviceTaxType == Config::get('constants.tax_is_exclusive');

            $taxData = $this->calculateServiceTax(
                $serviceTaxType,
                $totalPrice,
                (float) $locationInfo->tax_percentage,
                $isExclusive,
            );

            $consumptionOrder = match ($packageBundle['row_type'] ?? '') {
                'buy' => 1,
                'get' => $totalPrice == 0 ? 3 : 2,
                default => 0,
            };

            $allPackageServices[] = array_merge([
                'random_id' => $data['random_id'],
                'package_id' => $package->id,
                'package_bundle_id' => $packageBundleRecord->id,
                'service_id' => $serviceData->id,
                'price' => $totalPrice,
                'orignal_price' => $serviceData->price,
                'actual_price' => $serviceData->price,
                'consumption_order' => $consumptionOrder,
                'created_at' => Filters::getCurrentTimeStamp(),
                'updated_at' => Filters::getCurrentTimeStamp(),
                'sold_by' => $packageBundle['sold_by'] ?? null,
            ], $taxData);
        }

        return $allPackageServices;
    }

    private function storeBundleTypeServices(Packages $package, array $data, array $bundleIds, Locations $locationInfo): array
    {
        $bundlesData = Bundles::whereIn('id', $bundleIds)->get()->keyBy('id');
        $allBundleServices = BundleHasServices::with('service')
            ->whereIn('bundle_id', $bundleIds)
            ->get()
            ->groupBy('bundle_id');

        $allPackageServices = [];

        foreach ($data['package_bundles'] as $packageBundle) {
            $bundleId = $packageBundle['bundleId'];
            $serviceData = $bundlesData->get($bundleId);

            if (! $serviceData) {
                continue;
            }

            $discountId = $packageBundle['DiscountId'] ?? null;
            if ($discountId == '0' || $discountId == '') {
                $discountId = null;
            }

            $packageBundleData = [
                'random_id' => $package->random_id,
                'is_allocate' => 1,
                'qty' => 1,
                'discount_name' => $packageBundle['DiscountName'] ?? null,
                'discount_type' => $packageBundle['Type'] ?? null,
                'discount_price' => str_replace(',', '', $packageBundle['DiscountValue'] ?? '0'),
                'service_price' => str_replace(',', '', $packageBundle['RegularPrice']),
                'net_amount' => str_replace(',', '', $packageBundle['RegularPrice']),
                'discount_id' => $discountId,
                'bundle_id' => $bundleId,
                'source_type' => 'bundle',
                'package_id' => $package->id,
                'tax_exclusive_net_amount' => str_replace(',', '', $packageBundle['Amount']),
                'tax_percentage' => $locationInfo->tax_percentage ?? 0,
                'tax_price' => str_replace(',', '', $packageBundle['Tax']),
                'tax_including_price' => str_replace(',', '', $packageBundle['Total']),
                'location_id' => $data['location_id'],
            ];

            $packageBundleRecord = PackageBundles::create($packageBundleData);

            $bundleServices = $allBundleServices->get($bundleId, collect());
            $calculableServices = $bundleServices->map(fn ($bs) => [
                'service_price' => $bs->calculated_price,
                'calculated_price' => $bs->calculated_price,
                'service_id' => $bs->service_id,
            ])->toArray();

            $calculatedServicesPrices = Bundles::calculatePrices(
                $calculableServices,
                str_replace(',', '', $packageBundle['RegularPrice']),
                str_replace(',', '', $packageBundle['Total']),
            );

            $serviceIds = array_column($calculatedServicesPrices, 'service_id');
            $servicesInfo = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

            $bundleServiceStartIndex = count($allPackageServices);

            foreach ($calculatedServicesPrices as $csp) {
                $svcInfo = $servicesInfo->get($csp['service_id']);
                $serviceTaxType = $svcInfo?->tax_treatment_type_id ?? $serviceData->tax_treatment_type_id;
                $isExclusive = $serviceTaxType == Config::get('constants.tax_is_exclusive');

                $taxData = $this->calculateServiceTax(
                    $serviceTaxType,
                    (float) $csp['calculated_price'],
                    (float) $locationInfo->tax_percentage,
                    $isExclusive,
                );

                $allPackageServices[] = array_merge([
                    'random_id' => $data['random_id'],
                    'package_id' => $package->id,
                    'package_bundle_id' => $packageBundleRecord->id,
                    'service_id' => $csp['service_id'],
                    'price' => $csp['calculated_price'],
                    'orignal_price' => $csp['service_price'],
                    'actual_price' => $svcInfo?->price,
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                    'sold_by' => $packageBundle['sold_by'] ?? null,
                ], $taxData);
            }

            // Last-session absorption
            $bundleTotal = (float) str_replace(',', '', $packageBundle['Total']);
            $bundleServiceCount = count($allPackageServices) - $bundleServiceStartIndex;

            if ($bundleServiceCount > 1) {
                $sumWithoutLast = 0;
                for ($i = $bundleServiceStartIndex; $i < count($allPackageServices) - 1; $i++) {
                    $sumWithoutLast += $allPackageServices[$i]['tax_including_price'];
                }
                $lastIdx = count($allPackageServices) - 1;
                $allPackageServices[$lastIdx]['tax_including_price'] = round($bundleTotal - $sumWithoutLast, 2);
                $allPackageServices[$lastIdx]['price'] = $allPackageServices[$lastIdx]['tax_including_price'];
            }
        }

        return $allPackageServices;
    }

    private function storeServiceBundleTypeServices(Packages $package, array $data, array $serviceBundleItems, Locations $locationInfo): array
    {
        $bundleIds = array_column($serviceBundleItems, 'bundleId');
        $serviceBundles = ServiceBundle::with('service')->whereIn('id', $bundleIds)->get()->keyBy('id');

        $allPackageServices = [];

        foreach ($serviceBundleItems as $item) {
            $bundleId = $item['bundleId'];
            $serviceBundle = $serviceBundles->get($bundleId);

            if (! $serviceBundle || ! $serviceBundle->service) {
                continue;
            }

            $service = $serviceBundle->service;
            $sessions = $serviceBundle->sessions;
            $totalPrice = (float) str_replace(',', '', $item['Total'] ?? (string) $serviceBundle->price);

            $packageBundleRecord = PackageBundles::create([
                'random_id' => $package->random_id,
                'is_allocate' => 1,
                'qty' => $sessions,
                'discount_name' => '-',
                'discount_type' => '-',
                'discount_price' => 0,
                'service_price' => $serviceBundle->price,
                'net_amount' => $totalPrice,
                'bundle_id' => $bundleId,
                'source_type' => 'service_bundle',
                'package_id' => $package->id,
                'tax_exclusive_net_amount' => str_replace(',', '', $item['Amount'] ?? (string) $totalPrice),
                'tax_percentage' => $locationInfo->tax_percentage ?? 0,
                'tax_price' => str_replace(',', '', $item['Tax'] ?? '0'),
                'tax_including_price' => $totalPrice,
                'location_id' => $data['location_id'],
            ]);

            $serviceTaxType = $service->tax_treatment_type_id;
            $isExclusive = ($serviceTaxType == Config::get('constants.tax_is_exclusive'));
            $perSessionPrice = round($totalPrice / $sessions, 2);

            $startIndex = count($allPackageServices);

            for ($i = 0; $i < $sessions; $i++) {
                $isLastSession = ($i === $sessions - 1);

                if ($isLastSession && $sessions > 1) {
                    $sumPrevious = round($perSessionPrice * ($sessions - 1), 2);
                    $sessionPrice = round($totalPrice - $sumPrevious, 2);
                } else {
                    $sessionPrice = $perSessionPrice;
                }

                $taxData = $this->calculateServiceTax(
                    $serviceTaxType,
                    $sessionPrice,
                    (float) ($locationInfo->tax_percentage ?? 0),
                    $isExclusive,
                );

                $allPackageServices[] = array_merge([
                    'random_id' => $data['random_id'],
                    'package_id' => $package->id,
                    'package_bundle_id' => $packageBundleRecord->id,
                    'service_id' => $service->id,
                    'price' => $sessionPrice,
                    'orignal_price' => $service->price,
                    'actual_price' => $service->price,
                    'consumption_order' => 0,
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                    'sold_by' => $item['sold_by'] ?? null,
                ], $taxData);
            }

            // Last-session absorption to ensure exact total
            $serviceCount = count($allPackageServices) - $startIndex;
            if ($serviceCount > 1) {
                $sumWithoutLast = 0;
                for ($j = $startIndex; $j < count($allPackageServices) - 1; $j++) {
                    $sumWithoutLast += $allPackageServices[$j]['tax_including_price'];
                }
                $lastIdx = count($allPackageServices) - 1;
                $allPackageServices[$lastIdx]['tax_including_price'] = round($totalPrice - $sumWithoutLast, 2);
                $allPackageServices[$lastIdx]['price'] = $allPackageServices[$lastIdx]['tax_including_price'];
            }
        }

        return $allPackageServices;
    }

    // ── Membership Processing ───────────────────────────

    private function processMembershipPlan(Packages $package, array $data, int|string $appointmentId, mixed $request): void
    {
        $total = (float) str_replace(',', '', $data['total'] ?? '0');
        $cashAmount = (float) ($data['cash_amount'] ?? 0);
        $isFullyPaid = ($total - $cashAmount) <= 0;

        $packageMemberships = $data['package_memberships'];
        if (is_string($packageMemberships)) {
            $packageMemberships = json_decode($packageMemberships, true);
        }

        $isStudentMembership = false;
        $hasStudentDocuments = false;

        if ($request && isset($packageMemberships[0]['membershipId'])) {
            $membershipTypeId = (int) $packageMemberships[0]['membershipId'];
            $studentVerificationService = app(StudentVerificationService::class);
            $isStudentCheck = $studentVerificationService->isStudentMembership($membershipTypeId);

            if ($isStudentCheck) {
                $isStudentMembership = true;
                $storedDocumentPaths = $data['pre_stored_document_paths'] ?? [];
                $hasStudentDocuments = ! empty($storedDocumentPaths);

                $shouldConsume = $isFullyPaid && $hasStudentDocuments;
                $this->storeMembershipData($package, $data, $shouldConsume);

                if ($hasStudentDocuments) {
                    $studentVerificationService->createVerificationRecord([
                        'patient_id' => $data['patient_id'],
                        'membership_id' => $packageMemberships[0]['membershipCodeId'] ?? null,
                        'membership_type_id' => $membershipTypeId,
                        'package_id' => $package->id,
                        'document_paths' => $storedDocumentPaths,
                    ]);
                }
            } else {
                $this->storeMembershipData($package, $data, $isFullyPaid);
            }
        } else {
            $this->storeMembershipData($package, $data, $isFullyPaid);
        }

        $this->updatePlanName($package);

        if ($this->hasPayment($data)) {
            $shouldUseFullPaymentFlow = $isStudentMembership
                ? ($isFullyPaid && $hasStudentDocuments)
                : $isFullyPaid;

            if ($shouldUseFullPaymentFlow) {
                $this->handlePackagePayment($package, $data, $appointmentId);
            } else {
                $this->handlePartialMembershipPayment($package, $data, $appointmentId);
            }
        }
    }

    private function storeMembershipData(Packages $package, array $data, bool $isFullyPaid = true): void
    {
        $packageMemberships = $data['package_memberships'];
        if (is_string($packageMemberships)) {
            $packageMemberships = json_decode($packageMemberships, true);
        }

        if (empty($packageMemberships) || ! is_array($packageMemberships)) {
            return;
        }

        $locationInfo = Locations::find($data['location_id']);

        foreach ($packageMemberships as $membership) {
            $packageBundleData = [
                'random_id' => $package->random_id,
                'is_allocate' => 1,
                'qty' => 1,
                'discount_name' => $membership['DiscountName'] ?? null,
                'discount_type' => $membership['Type'] ?? null,
                'discount_price' => $membership['DiscountValue'] ?? 0,
                'service_price' => str_replace(',', '', $membership['RegularPrice']),
                'net_amount' => str_replace(',', '', $membership['RegularPrice']),
                'discount_id' => null,
                'bundle_id' => null,
                'membership_type_id' => $membership['membershipId'] ?? null,
                'membership_code_id' => $membership['membershipCodeId'] ?? null,
                'package_id' => $package->id,
                'tax_exclusive_net_amount' => str_replace(',', '', $membership['Amount']),
                'tax_percentage' => $locationInfo->tax_percentage ?? 0,
                'tax_price' => $membership['Tax'] ?? 0,
                'tax_including_price' => str_replace(',', '', $membership['Total']),
                'location_id' => $data['location_id'],
            ];

            $packageBundle = PackageBundles::create($packageBundleData);

            $soldBy = $membership['sold_by'] ?? $data['sold_by'] ?? null;
            $consumedAt = $isFullyPaid ? Filters::getCurrentTimeStamp() : null;

            PackageService::create([
                'random_id' => $package->random_id,
                'package_id' => $package->id,
                'package_bundle_id' => $packageBundle->id,
                'service_id' => null,
                'is_consumed' => $isFullyPaid ? 1 : 0,
                'consumed_at' => $consumedAt,
                'price' => str_replace(',', '', $membership['RegularPrice']),
                'orignal_price' => str_replace(',', '', $membership['RegularPrice']),
                'actual_price' => str_replace(',', '', $membership['RegularPrice']),
                'is_exclusive' => 0,
                'tax_exclusive_price' => str_replace(',', '', $membership['Amount']),
                'tax_percentage' => $locationInfo->tax_percentage ?? 0,
                'tax_price' => $membership['Tax'] ?? 0,
                'tax_including_price' => str_replace(',', '', $membership['Total']),
                'sold_by' => $soldBy,
            ]);

            $membershipCodeId = $membership['membershipCodeId'] ?? null;
            if ($membershipCodeId) {
                $this->updateMembershipRecord((int) $membershipCodeId, $membership, $data, $isFullyPaid);
            }
        }
    }

    private function updateMembershipRecord(int|string $membershipCodeId, array $membership, array $data, bool $isFullyPaid): void
    {
        $membershipRecord = Membership::find($membershipCodeId);
        if (! $membershipRecord) {
            return;
        }

        if ($isFullyPaid) {
            $membershipType = MembershipType::find($membership['membershipId'] ?? $membershipRecord->membership_type_id);
            $durationDays = (int) ($membershipType->period ?? 365);

            $membershipRecord->update([
                'patient_id' => $data['patient_id'],
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays($durationDays)->toDateString(),
                'assigned_at' => now()->toDateString(),
                'updated_by' => Auth::id(),
            ]);
        } else {
            $membershipRecord->update([
                'patient_id' => $data['patient_id'],
                'updated_by' => Auth::id(),
            ]);
        }
    }

    // ── Appointment Handling ────────────────────────────

    private function handleAppointment(array $data): ?int
    {
        if (empty($data['appointment_id'])) {
            return null;
        }

        $tagAppoint = explode('.', (string) $data['appointment_id']);

        if (count($tagAppoint) >= 2 && $tagAppoint[1] === 'A') {
            return (int) $tagAppoint[0];
        }

        if (count($tagAppoint) >= 2) {
            $calc = new PlanAppointmentCalculation;
            $appointmentId = $calc->storeAppointment(
                $data['patient_id'],
                $data['location_id'],
                (object) $data,
                $tagAppoint[0],
                false,
            );
            $calc->saveinvoice($appointmentId);

            return $appointmentId;
        }

        return (int) $tagAppoint[0];
    }

    private function handleAppointmentForUpdate(array $data, Packages $package): ?int
    {
        if (! isset($data['appointment_id'])) {
            return null;
        }

        $tagAppoint = explode('.', (string) $data['appointment_id']);

        if (($tagAppoint[1] ?? '') === 'A') {
            return (int) $tagAppoint[0];
        }

        $calc = new PlanAppointmentCalculation;
        $appointmentDecision = Appointments::find($package->appointment_id);

        if ($appointmentDecision) {
            return $calc->updateAppointment($data['patient_id'], $data['location_id'], (object) $data, $tagAppoint[0], $package);
        }

        $appointmentId = $calc->storeAppointment($data['patient_id'], $data['location_id'], (object) $data, $tagAppoint[0], false);
        $calc->saveinvoice($appointmentId);

        return $appointmentId;
    }

    // ── Package Record Creation ─────────────────────────

    private function createPackageRecord(array $data, int|string $appointmentId): Packages
    {
        $totalPrice = str_replace(',', '', $data['total']);

        $package = Packages::create([
            'random_id' => $data['random_id'],
            'patient_id' => $data['patient_id'],
            'location_id' => $data['location_id'],
            'total_price' => $totalPrice,
            'sessioncount' => '1',
            'account_id' => Auth::user()->account_id,
            'is_exclusive' => $data['is_exclusive'] ?? 0,
            'plan_type' => $data['plan_type'] ?? 'plan',
            'appointment_id' => $appointmentId,
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ]);

        $package->update(['name' => sprintf('%05d', $package->id)]);

        return $package;
    }

    // ── Payment Processing ──────────────────────────────

    private function handlePackagePayment(Packages $package, array $data, int|string $appointmentId): void
    {
        $packageAdvance = PackageAdvances::createRecord([
            'cash_flow' => CashFlow::In->value,
            'cash_amount' => $data['cash_amount'],
            'account_id' => Auth::user()->account_id,
            'patient_id' => $data['patient_id'],
            'payment_mode_id' => $data['payment_mode_id'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'package_id' => $package->id,
            'location_id' => $data['location_id'],
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ], $package);

        $planType = PlanType::tryFrom($data['plan_type'] ?? 'plan');

        if ($planType === PlanType::Membership) {
            $this->createMembershipOutEntries($package, $data);
        }

        // Note: PlanInvoice is already created inside PackageAdvances::createRecord()
        $this->logPaymentActivity($package, $data, 'received payment');

        Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($package->id, $packageAdvance);
        $this->markAppointmentAsConvertedOptimized($appointmentId, $package->id, (float) $data['cash_amount']);
    }

    private function handlePartialMembershipPayment(Packages $package, array $data, int|string $appointmentId): void
    {
        $packageAdvance = PackageAdvances::createRecord([
            'cash_flow' => CashFlow::In->value,
            'cash_amount' => $data['cash_amount'],
            'account_id' => Auth::user()->account_id,
            'patient_id' => $data['patient_id'],
            'payment_mode_id' => $data['payment_mode_id'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'package_id' => $package->id,
            'location_id' => $data['location_id'],
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ], $package);

        // Note: PlanInvoice is already created inside PackageAdvances::createRecord()
        $this->logPaymentActivity($package, $data, 'received partial payment');

        Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($package->id, $packageAdvance);
        $this->markAppointmentAsConvertedOptimized($appointmentId, $package->id, (float) $data['cash_amount']);
    }

    private function createMembershipOutEntries(Packages $package, array $data): void
    {
        $packageBundles = PackageBundles::where('package_id', $package->id)->get();
        $taxExclusiveTotal = $packageBundles->sum('tax_exclusive_net_amount');
        $taxTotal = $packageBundles->sum('tax_price');

        $settlePaymentMode = PaymentModes::where('name', 'Settle Amount')->first();
        $settlePaymentModeId = $settlePaymentMode?->id;

        $baseData = [
            'cash_flow' => CashFlow::Out->value,
            'account_id' => Auth::user()->account_id,
            'patient_id' => $data['patient_id'],
            'payment_mode_id' => $settlePaymentModeId,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'package_id' => $package->id,
            'location_id' => $data['location_id'],
            'is_setteled' => 0,
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ];

        PackageAdvances::create(array_merge($baseData, [
            'cash_amount' => $taxExclusiveTotal,
            'is_tax' => 0,
        ]));

        if ($taxTotal > 0) {
            PackageAdvances::create(array_merge($baseData, [
                'cash_amount' => $taxTotal,
                'is_tax' => 1,
            ]));
        }
    }

    private function logPaymentActivity(Packages $package, array $data, string $action): void
    {
        $patient = User::find($data['patient_id']);
        $locationWithCity = Locations::with('city')->find($data['location_id']);
        $locationName = $locationWithCity
            ? (($locationWithCity->city?->name ?? '').'-'.$locationWithCity->name)
            : '';

        $creatorName = Auth::user()->name ?? 'System';
        $description = '<span class="highlight">'.e($creatorName).'</span> '.$action.' Rs. <span class="highlight-green">'.number_format((float) $data['cash_amount']).'</span> from <span class="highlight-orange">'.e($patient->name).'</span> for <span class="highlight-purple">Plan Id: '.$package->id.'</span> in <span class="highlight">'.e($locationName).'</span> ';

        Activity::create([
            'action' => 'received',
            'activity_type' => 'payment_received',
            'description' => $description,
            'patient' => $patient->name,
            'patient_id' => $patient->id,
            'appointment_type' => 'Plan',
            'created_by' => Auth::id(),
            'account_id' => Auth::user()->account_id,
            'plan_id' => $package->id,
            'amount' => $data['cash_amount'],
            'location' => $locationName,
            'centre_id' => $data['location_id'],
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ]);
    }

    // ── Plan Name Generation ────────────────────────────

    private function updatePlanName(Packages $package): void
    {
        if ($package->plan_type === PlanType::Membership) {
            $membershipNames = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('membership_types', 'package_bundles.membership_type_id', '=', 'membership_types.id')
                ->orderBy('package_bundles.id')
                ->limit(2)
                ->pluck('membership_types.name')
                ->toArray();

            if (! empty($membershipNames)) {
                $planName = implode(', ', $membershipNames);
                Packages::where('id', $package->id)->update(['plan_name' => $planName]);
                $package->plan_name = $planName;
            }

            return;
        }

        $totalBundleCount = PackageBundles::where('package_id', $package->id)->count();

        $names = PackageBundles::where('package_bundles.package_id', $package->id)
            ->leftJoin('services', function ($join) {
                $join->on('package_bundles.bundle_id', '=', 'services.id')
                    ->where('package_bundles.source_type', '=', 'service');
            })
            ->leftJoin('bundles', function ($join) {
                $join->on('package_bundles.bundle_id', '=', 'bundles.id')
                    ->where('package_bundles.source_type', '=', 'bundle');
            })
            ->leftJoin('service_bundles', function ($join) {
                $join->on('package_bundles.bundle_id', '=', 'service_bundles.id')
                    ->where('package_bundles.source_type', '=', 'service_bundle');
            })
            ->leftJoin('services as sb_services', 'service_bundles.service_id', '=', 'sb_services.id')
            ->orderBy('package_bundles.id')
            ->limit(2)
            ->selectRaw("COALESCE(services.name, bundles.name, CONCAT(package_bundles.qty, 'x ', sb_services.name)) as name")
            ->pluck('name')
            ->filter()
            ->values()
            ->toArray();

        $planName = ! empty($names) ? implode(', ', $names) : '-';

        if ($totalBundleCount > 2) {
            $planName .= '...';
        }

        Packages::where('id', $package->id)->update(['plan_name' => $planName]);
        $package->plan_name = $planName;
    }

    // ── Voucher Handling ────────────────────────────────

    private function handleVoucherConsumption(Discounts $discount, int|string $userId, string $randomId, Services $service, float $discountPrice, string $serviceId): void
    {
        $userVoucher = UserVouchers::where('voucher_id', $discount->id)
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if (! $userVoucher) {
            return;
        }

        $originalAmount = $userVoucher->amount;
        $amountLeft = max(0, $userVoucher->amount - $service->price);
        $actualConsumed = $originalAmount - $amountLeft;

        $userVoucher->update(['amount' => $amountLeft]);

        $amountForVoucher = $amountLeft <= 0 ? $discountPrice : $service->price;

        PackageVouchers::create([
            'package_random_id' => $randomId,
            'voucher_id' => $discount->id,
            'user_id' => $userId,
            'amount' => $amountForVoucher,
            'service_id' => $serviceId,
            'main_service_id' => $service->id,
        ]);

        if ($actualConsumed > 0) {
            // ActivityLogger::logVoucherConsumed() is typed to
            // App\Models\Patients. Fetching via User::find() returns a
            // User instance and TypeErrors at the logger call, matching
            // the bug pattern fixed in MembershipAssignmentService.
            $patient = Patients::find($userId);
            if ($patient) {
                ActivityLogger::logVoucherConsumed($actualConsumed, $patient, $discount, $amountLeft);
            }
        }
    }

    // ── Discount Data ───────────────────────────────────

    private function prepareDiscountData(?Discounts $discount, array $packageBundleData, array $data): array
    {
        if (empty($data['discount_id']) || $data['discount_id'] == '0') {
            return ['discount_name' => '-', 'discount_type' => '-', 'discount_price' => '0.00'];
        }

        return [
            'discount_name' => $packageBundleData['discount_name'] ?? '-',
            'discount_type' => $packageBundleData['discount_type'] ?? '-',
            'discount_price' => $packageBundleData['discount_price'] ?? '0.00',
        ];
    }

    // ── Appointment Conversion ──────────────────────────

    private function markAppointmentAsConvertedOptimized(int|string $appointmentId, int|string $packageId, float $paymentAmount): void
    {
        try {
            $appointment = Appointments::find($appointmentId);
            $package = Packages::find($packageId);

            if (! $appointment || ! $package) {
                return;
            }

            $accountId = $appointment->account_id;

            $statuses = Cache::remember("appointment_statuses_{$accountId}", self::CACHE_TTL, fn () => [
                'arrived' => AppointmentStatuses::where(['account_id' => $accountId, 'is_arrived' => 1])->first(),
                'converted' => AppointmentStatuses::where(['account_id' => $accountId, 'is_converted' => 1])->first(),
            ]);

            if (! $statuses['arrived'] || ! $statuses['converted']) {
                return;
            }

            $latestArrivedConsultation = Appointments::where([
                'patient_id' => $package->patient_id,
                'appointment_type_id' => AppointmentType::Consultancy->value,
                'base_appointment_status_id' => $statuses['arrived']->id,
            ])
                ->whereNull('deleted_at')
                ->orderByDesc('scheduled_date')
                ->orderByDesc('id')
                ->first();

            if (! $latestArrivedConsultation) {
                return;
            }

            $consultationInvoice = DB::table('invoices')
                ->where('appointment_id', $latestArrivedConsultation->id)
                ->whereNull('deleted_at')
                ->orderBy('created_at')
                ->first();

            if (! $consultationInvoice) {
                return;
            }

            $invoiceDate = Carbon::parse($consultationInvoice->created_at)->format('Y-m-d');

            $serviceAfterInvoice = DB::table('package_services')
                ->join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->join('packages', 'package_bundles.package_id', '=', 'packages.id')
                ->where('packages.patient_id', $package->patient_id)
                ->whereNull('packages.deleted_at')
                ->whereDate('package_services.created_at', '>=', $invoiceDate)
                ->exists();

            if (! $serviceAfterInvoice) {
                return;
            }

            $existingPaymentsCount = DB::table('package_advances')
                ->join('packages', 'package_advances.package_id', '=', 'packages.id')
                ->where('packages.patient_id', $package->patient_id)
                ->where('package_advances.cash_flow', CashFlow::In->value)
                ->where('package_advances.cash_amount', '>', 0)
                ->whereNull('package_advances.deleted_at')
                ->whereDate('package_advances.created_at', '>=', $invoiceDate)
                ->count();

            if ($existingPaymentsCount > 1) {
                return;
            }

            $latestArrivedConsultation->update([
                'base_appointment_status_id' => $statuses['converted']->id,
                'appointment_status_id' => $statuses['converted']->id,
                'converted_at' => now(),
            ]);

            $patient = Patients::find($package->patient_id);
            $location = Locations::with('city')->find($latestArrivedConsultation->location_id);
            $service = Services::find($latestArrivedConsultation->service_id);

            ActivityLogger::logAppointmentConverted($latestArrivedConsultation, $patient, $location, $service, $paymentAmount, $packageId);

            if ($latestArrivedConsultation->lead_id) {
                $this->updateLeadStatusToConverted($latestArrivedConsultation, $accountId, $location, $service, $paymentAmount);
            }

            $this->sendMetaConvertedEvent($latestArrivedConsultation, $packageId, $paymentAmount);
        } catch (\Throwable $e) {
            Log::error('Mark Appointment As Converted Error: '.$e->getMessage());
        }
    }

    private function updateLeadStatusToConverted(object $appointment, int|string $accountId, mixed $location, mixed $service, float $paymentAmount): void
    {
        $lead = Leads::find($appointment->lead_id);
        if (! $lead) {
            return;
        }

        $convertedLeadStatus = Cache::remember("converted_lead_status_{$accountId}", self::CACHE_TTL, fn () => DB::table('lead_statuses')->where(['account_id' => $accountId, 'is_converted' => 1])->first());

        if ($convertedLeadStatus) {
            $lead->update(['lead_status_id' => $convertedLeadStatus->id]);
            ActivityLogger::logLeadConverted($lead, $appointment, $location, $service, $paymentAmount);
        }
    }

    private function sendMetaConvertedEvent(object $appointment, int|string $packageId, float $paymentAmount): void
    {
        if (! $appointment->lead_id) {
            return;
        }

        $lead = Leads::find($appointment->lead_id);
        if (! $lead) {
            return;
        }

        $alreadySent = DB::table('appointments')
            ->where('lead_id', $lead->id)
            ->where('meta_purchase_sent', 1)
            ->exists();

        if ($alreadySent) {
            return;
        }

        try {
            $metaService = new MetaConversionApiService;
            $eventLeadId = $lead->meta_lead_id ?? 'apt_'.$appointment->id;

            $metaService->sendLeadStatus($lead->phone, 'converted', $eventLeadId, $lead->email, 'PKR', $paymentAmount);

            $appointment->update(['meta_purchase_sent' => 1]);
        } catch (\Throwable $e) {
            Log::error('Meta CAPI converted event failed: '.$e->getMessage());
        }
    }

    // ── Membership Display ──────────────────────────────

    private function getMembershipDisplay(int|string $patientId): string
    {
        $membership = Membership::with('membershiptype')
            ->where('patient_id', $patientId)
            ->where('active', 1)
            ->where('end_date', '>=', now()->format('Y-m-d'))
            ->orderByDesc('assigned_at')
            ->first();

        if (! $membership) {
            $membership = Membership::with('membershiptype')
                ->where('patient_id', $patientId)
                ->orderByDesc('assigned_at')
                ->first();
        }

        if (! $membership) {
            return 'No Membership';
        }

        $status = $this->determineMembershipStatus($membership);
        $expiryFormatted = $membership->end_date ? date('M d, Y', strtotime((string) $membership->end_date)) : '';

        if ($membership->is_referral == 1) {
            return "Ref: ({$membership->code})-{$status}".($expiryFormatted ? " (Exp: {$expiryFormatted})" : '');
        }

        $typeName = $membership->membershipType
            ? str_replace(' Membership', '', $membership->membershipType->name)
            : 'Gold';

        return "{$typeName} - {$membership->code} - {$status}".($expiryFormatted ? " (Exp: {$expiryFormatted})" : '');
    }

    private function getMembershipDisplayForPackage(int|string $patientId): string
    {
        $membership = Membership::with('membershiptype')
            ->where('patient_id', $patientId)
            ->orderByDesc('assigned_at')
            ->first();

        if (! $membership) {
            return 'No membership';
        }

        $status = $this->determineMembershipStatus($membership);

        return ($membership->membershipType?->name ?? 'Unknown')." - {$status}";
    }

    private function determineMembershipStatus(Membership $membership): string
    {
        if (empty($membership->start_date) || empty($membership->end_date)) {
            return 'Inactive';
        }

        if ($membership->end_date < now()->format('Y-m-d')) {
            return 'Expired';
        }

        return $membership->active == 1 ? 'Active' : 'Inactive';
    }

    private function getMembershipForLocation(int|string $patientId): string
    {
        $membership = DB::table('memberships')
            ->join('membership_types', 'memberships.membership_type_id', '=', 'membership_types.id')
            ->where('memberships.patient_id', $patientId)
            ->select('memberships.end_date', 'memberships.active', 'membership_types.name as type_name')
            ->orderByRaw('CASE WHEN memberships.end_date >= ? AND memberships.active = 1 THEN 0 ELSE 1 END', [now()->format('Y-m-d')])
            ->orderByDesc('memberships.assigned_at')
            ->first();

        if (! $membership) {
            return 'No membership';
        }

        $isExpired = $membership->end_date < now()->format('Y-m-d');
        $status = $isExpired ? ' - Expired' : ($membership->active == 1 ? ' - Active' : ' - Inactive');
        $typeName = str_replace(' Membership', '', $membership->type_name);

        return "{$typeName}{$status}";
    }

    private function getPatientMembershipDisplay(int|string $patientId): string
    {
        $patient = DB::table('users')
            ->leftJoin('user_memberships', 'users.id', '=', 'user_memberships.user_id')
            ->leftJoin('membership_types', 'user_memberships.membership_type_id', '=', 'membership_types.id')
            ->where('users.id', $patientId)
            ->select('user_memberships.id as membership_id', 'membership_types.name as membership_name', 'user_memberships.end_date', 'user_memberships.active')
            ->first();

        if (! $patient?->membership_id) {
            return 'No Membership';
        }

        $endDate = Carbon::parse($patient->end_date);
        $isExpired = $endDate->isPast();
        $status = $isExpired ? 'Expired' : ($patient->active == 1 ? 'Active' : 'Inactive');

        return "{$patient->membership_name} ({$status})";
    }

    // ── Package Advances Processing ─────────────────────

    private function processPackageAdvances(Collection $packageAdvances): array
    {
        if ($packageAdvances->isEmpty()) {
            return [];
        }

        return $packageAdvances->map(function ($advance) {
            if ($advance->cash_flow === CashFlow::Out->value && ! $advance->is_tax) {
                $advance->package_refund_price = number_format(
                    PackageAdvances::getAppointmentPackage(
                        (int) $advance->appointment_id,
                        (int) $advance->patient_id,
                        $advance->refund_note !== null ? (int) $advance->id : null,
                    ),
                );
            } elseif (! $advance->is_tax) {
                $advance->package_refund_price = number_format((float) $advance->cash_amount);
            } else {
                $advance->package_refund_price = '00.00';
            }

            $advance->created_at_formated = Carbon::parse($advance->created_at)->format('F j,Y H:i A');

            return $advance;
        })->toArray();
    }

    // ── Validation helpers ──────────────────────────────

    private function validateConsumptionOrder(Packages $package): void
    {
        $hasOutOfOrder = PackageService::where('package_services.package_id', $package->id)
            ->join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
            ->whereNotNull('package_bundles.config_group_id')
            ->where('package_services.is_consumed', '1')
            ->whereExists(function ($query) use ($package) {
                $query->select(DB::raw(1))
                    ->from('package_services as ps2')
                    ->join('package_bundles as pb2', 'ps2.package_bundle_id', '=', 'pb2.id')
                    ->whereColumn('pb2.config_group_id', 'package_bundles.config_group_id')
                    ->where('ps2.package_id', $package->id)
                    ->where('ps2.is_consumed', '0')
                    ->whereColumn('ps2.consumption_order', '<', 'package_services.consumption_order');
            })
            ->exists();

        if ($hasOutOfOrder) {
            throw PlanException::invalidOperation(
                'Cannot add new services. A configurable discount group has out-of-order consumption. Please consume the BUY services first or create a new plan.',
            );
        }
    }

    private function hasPayment(array $data): bool
    {
        return ! empty($data['cash_amount']) && $data['cash_amount'] != '0';
    }

    private function hasChildRecords(int|string $packageId): bool
    {
        return DB::table('invoice_details')->where('package_id', $packageId)->exists()
            || DB::table('package_advances')->where('package_id', $packageId)->exists();
    }

    // ── Patient Appointments Builder ────────────────────

    private function buildPatientAppointments(int|string $patientId, int|string $locationId): array
    {
        $appointments = DB::table('appointments')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->leftJoin('users as doctors', 'appointments.doctor_id', '=', 'doctors.id')
            ->where('appointments.patient_id', $patientId)
            ->where('appointments.location_id', $locationId)
            ->where('appointments.appointment_type_id', 1)
            ->whereIn('appointments.appointment_status_id', [2, 16])
            ->orderByDesc('appointments.created_at')
            ->select([
                'appointments.id',
                'appointments.created_at',
                'appointments.doctor_id',
                'services.name as service_name',
                'doctors.name as doctor_name',
            ])
            ->get();

        $result = [];
        foreach ($appointments as $apt) {
            if (! $apt->created_at) {
                continue;
            }

            $formattedDate = Carbon::parse($apt->created_at)->format('F d,Y h:i A');
            $serviceName = $apt->service_name ?? 'Consultation';

            $doctorName = $apt->doctor_name ?? '';
            if ($doctorName && ! str_starts_with($doctorName, 'Dr ') && ! str_starts_with($doctorName, 'Dr.')) {
                $doctorName = 'Dr '.$doctorName;
            }

            $displayName = $serviceName.' - '.$formattedDate;
            if ($doctorName) {
                $displayName .= ' - '.$doctorName;
            }

            $result[$apt->id] = [
                'id' => $apt->id.'.A',
                'name' => $displayName,
                'doctor_id' => $apt->doctor_id,
            ];
        }

        return $result;
    }

    // ──────────────────────────────────────────────────
    //  Discount Info
    // ──────────────────────────────────────────────────

    /**
     * Get discount info for a service/bundle.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}
     */
    public function getDiscountInfo(array $data): array
    {
        return $this->discountService->getDiscountInfo($data);
    }

    /**
     * Get custom discount info for a service/bundle.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}|false
     */
    public function getCustomDiscountInfo(array $data): array|false
    {
        return $this->discountService->getCustomDiscountInfo($data);
    }

    // ──────────────────────────────────────────────────
    //  Package Service Deletion
    // ──────────────────────────────────────────────────

    /**
     * Delete a package service (bundle) by ID.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}
     */
    public function deletePackageService(array $data): array
    {
        $id = $data['id'];
        $packageBundle = PackageBundles::find($id);

        // Block deletion if this bundle's own services are consumed
        $status = PackageService::where([
            ['package_bundle_id', '=', $id],
            ['is_consumed', '=', '1'],
        ])->first();

        if ($status) {
            return ['success' => false, 'message' => 'Unable to delete consumed service.', 'status_code' => 404, 'data' => ['del' => 1]];
        }

        // If this bundle belongs to a config group, block if ANY service in the group is consumed
        if ($packageBundle && $packageBundle->config_group_id) {
            $groupHasConsumed = PackageService::join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->where('package_bundles.config_group_id', $packageBundle->config_group_id)
                ->where('package_services.is_consumed', '1')
                ->exists();

            if ($groupHasConsumed) {
                return ['success' => false, 'message' => 'Cannot delete. A service in this configurable discount group has been consumed.', 'status_code' => 404, 'data' => ['del' => 1]];
            }
        }

        // All checks passed — proceed with deletion
        $packageService = PackageBundles::find($id);
        $findPackage = Packages::find($packageService->package_id);
        if ($findPackage) {
            $packageVoucher = PackageVouchers::where('package_random_id', $packageService->random_id)->where('main_service_id', $packageService->bundle_id)->first();
            if ($packageVoucher) {
                $packageVoucherAmount = $packageVoucher->amount;
                $findUserVoucher = UserVouchers::where('voucher_id', $packageVoucher->voucher_id)->where('user_id', $findPackage->patient_id)->first();
                if ($findUserVoucher) {
                    $findUserVoucher->update(['amount' => $findUserVoucher->amount + $packageVoucherAmount]);
                }
                $packageVoucher->delete();
            }
        }

        $packageTotal = $data['package_total'] ?? '';
        if ($packageTotal == '') {
            $packageTotal = 0;
        }
        $packageTotal = str_replace(',', '', (string) $packageTotal);

        $total = number_format(round(($packageTotal - $packageService->tax_including_price)));

        PackageService::where('package_bundle_id', '=', $id)->delete();
        PackageBundles::find($id)?->forcedelete();

        $oldTotal = PackageService::where('random_id', $packageService->random_id)->sum('tax_including_price');

        $updateStatus = $data['update_status'] ?? 0;
        if ($updateStatus == 1) {
            if ($packageService->package_id) {
                Packages::where('id', $packageService->package_id)->update(['total_price' => $total]);
            }
        }

        // Update plan_name after service removal
        if ($packageService->package_id) {
            $pkg = Packages::find($packageService->package_id);
            if ($pkg) {
                $this->updatePlanNameForPackage($pkg);
            }
        }

        return [
            'success' => true,
            'message' => 'Record found',
            'data' => [
                'total' => $total,
                'id' => $id,
                'old_total' => $oldTotal,
            ],
        ];
    }

    /**
     * Delete configurable package service by base_service_id.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}
     */
    public function deleteConfigurablePackageService(array $data): array
    {
        return $this->discountService->deleteConfigurablePackageService($data);
    }

    /**
     * Delete exclusive package service by random_id.
     *
     * @return array{success: bool, status: bool}
     */
    public function deleteExclusiveService(array $data): array
    {
        return $this->discountService->deleteExclusiveService($data);
    }

    // ──────────────────────────────────────────────────
    //  Bundle & Status
    // ──────────────────────────────────────────────────

    /**
     * Update bundle payment for a package.
     *
     * @return array{success: bool, message: string}
     *
     * @throws \Exception
     */
    public function updateBundlePayment(array $data): array
    {
        $package = Packages::findOrFail($data['package_id']);

        // Handle payment if provided
        if (! empty($data['payment_mode_id']) && ($data['cash_amount'] ?? 0) > 0) {
            // Only update appointment_id and updated_at when payment is added
            $package->appointment_id = $data['appointment_id'];
            $package->save();

            $packageAdvance = new PackageAdvances;
            $packageAdvance->package_id = $package->id;
            $packageAdvance->payment_mode_id = $data['payment_mode_id'];
            $packageAdvance->cash_amount = $data['cash_amount'];
            $packageAdvance->cash_flow = 'in';
            $packageAdvance->account_id = Auth::user()->account_id;
            $packageAdvance->created_by = Auth::id();
            $packageAdvance->save();

            // Always regenerate plan_name from services/bundles
            $this->updatePlanNameForPackage($package);
        }

        return ['success' => true, 'message' => 'Bundle plan updated successfully'];
    }

    /**
     * Toggle active/inactive status for a package.
     *
     * @return array{success: bool, message: string, status_code?: int}
     */
    public function toggleStatus(int $packageId, string $action): array
    {
        if ($action == '1') {
            $response = Packages::activeRecord($packageId);
        } else {
            $response = Packages::inactiveRecord($packageId);
        }

        if ($response['status']) {
            return ['success' => true, 'message' => $response['message']];
        }

        return ['success' => false, 'message' => $response['message'], 'status_code' => 400];
    }

    /**
     * Get bundle services (price) for zero-discount scenarios.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}
     */
    public function getBundleServices(int $bundleId): array
    {
        return $this->discountService->getBundleServices($bundleId);
    }

    // ──────────────────────────────────────────────────
    //  Plan Name Helper
    // ──────────────────────────────────────────────────

    /**
     * Update plan name for a package based on its bundles/memberships.
     */
    public function updatePlanNameForPackage(Packages $package): void
    {
        if ($package->plan_type === 'membership') {
            $membershipNames = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('membership_types', 'package_bundles.membership_type_id', '=', 'membership_types.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('membership_types.name')
                ->toArray();

            if (! empty($membershipNames)) {
                $planName = implode(', ', $membershipNames);
                Packages::where('id', $package->id)->update(['plan_name' => $planName]);
            }

            return;
        }

        $totalBundleCount = PackageBundles::where('package_id', $package->id)->count();

        if ($package->plan_type === 'plan') {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('services', 'package_bundles.bundle_id', '=', 'services.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('services.name')
                ->toArray();
        } else {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('bundles.name')
                ->toArray();
        }

        $planName = ! empty($names) ? implode(', ', $names) : '-';

        if ($package->plan_type === 'plan' && $totalBundleCount > 2) {
            $planName .= '...';
        }

        Packages::where('id', $package->id)->update(['plan_name' => $planName]);
    }

    // ──────────────────────────────────────────────────
    //  Bundle & Membership Service Methods
    // ──────────────────────────────────────────────────

    /**
     * Add a bundle service to a plan (create or edit mode).
     *
     * @param  array  $data  Keys: bundle_id, location_id, net_amount, random_id, sold_by
     * @return array Structured response data with servicesData
     */
    public function addBundleService(array $data): array
    {
        // Route to service bundle handler if source_type is 'service_bundle'
        if (($data['source_type'] ?? 'bundle') === 'service_bundle') {
            return $this->addServiceBundleToPlan($data);
        }

        $bundle = Bundles::find($data['bundle_id']);

        if (! $bundle) {
            throw new PlanException('Bundle not found');
        }

        $locationInfo = Locations::find($data['location_id']);

        if (! $locationInfo) {
            throw new PlanException('Location not found');
        }

        $taxPct = (float) ($locationInfo->tax_percentage ?? 0);
        $netAmount = (float) $data['net_amount'];

        // Build bundle data structure
        $bundleData = [
            'qty' => '1',
            'bundle_id' => $bundle->id,
            'service_price' => $bundle->price,
            'service_name' => $bundle->name,
            'net_amount' => $netAmount,
            'discount_name' => '-',
            'discount_type' => '-',
            'discount_price' => '0',
            'tax_percentage' => $taxPct,
        ];

        // Calculate tax based on bundle's tax treatment type
        if ($bundle->tax_treatment_type_id == Config::get('constants.tax_both')) {
            $bundleData['tax_exclusive_net_amount'] = $netAmount;
            $bundleData['tax_price'] = ceil($netAmount * ($taxPct / 100));
            $bundleData['tax_including_price'] = ceil($netAmount + $bundleData['tax_price']);
        } else {
            $bundleData['tax_including_price'] = $netAmount;
            $bundleData['tax_exclusive_net_amount'] = $taxPct > 0
                ? ceil((100 * $netAmount) / ($taxPct + 100))
                : $netAmount;
            $bundleData['tax_price'] = ceil($netAmount - $bundleData['tax_exclusive_net_amount']);
        }

        // Resolve the parent package (if editing an existing plan)
        $findPackage = Packages::where('random_id', $data['random_id'])->first();
        $isEditMode = $findPackage !== null;

        // Get bundle services and calculate proportional prices
        $bundleServices = BundleHasServices::with('service')
            ->where('bundle_id', $bundle->id)
            ->get();

        $calculableServices = $bundleServices->map(fn ($bs) => [
            'service_price' => $bs->calculated_price,
            'calculated_price' => $bs->calculated_price,
            'service_id' => $bs->service_id,
        ])->toArray();

        $calculatedServicesPrices = Bundles::calculatePrices(
            $calculableServices,
            $bundleData['tax_exclusive_net_amount'],
            $bundleData['tax_including_price']
        );

        $serviceIds = array_column($calculatedServicesPrices, 'service_id');
        $servicesInfo = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

        // In edit mode: persist to DB immediately since package already exists
        // In create mode: only return calculated data — persistence happens in savepackages via storeBundleTypeServices
        $packageBundleRecordId = $bundle->id;

        if ($isEditMode) {
            $packageBundleRecord = PackageBundles::create([
                'random_id' => $data['random_id'],
                'qty' => 1,
                'bundle_id' => $bundle->id,
                'source_type' => 'bundle',
                'discount_name' => '-',
                'discount_type' => '-',
                'discount_price' => 0,
                'service_price' => $bundle->price,
                'net_amount' => $netAmount,
                'is_exclusive' => 0,
                'tax_exclusive_net_amount' => $bundleData['tax_exclusive_net_amount'],
                'tax_percentage' => $taxPct,
                'tax_price' => $bundleData['tax_price'],
                'tax_including_price' => $bundleData['tax_including_price'],
                'location_id' => $data['location_id'],
                'package_id' => $findPackage->id,
                'is_allocate' => 1,
            ]);
            $packageBundleRecordId = $packageBundleRecord->id;
        }

        $soldBy = $data['sold_by'] ?? null;
        $packageServicesData = [];

        foreach ($calculatedServicesPrices as $calculatedService) {
            $serviceInfo = $servicesInfo->get($calculatedService['service_id']);

            if (! $serviceInfo) {
                continue;
            }

            $serviceTaxType = $serviceInfo->tax_treatment_type_id;
            $isExclusive = ($serviceTaxType == Config::get('constants.tax_is_exclusive'));

            if (($serviceTaxType == Config::get('constants.tax_both') && $isExclusive) ||
                $serviceTaxType == Config::get('constants.tax_is_exclusive')) {
                $taxExclusivePrice = $calculatedService['calculated_price'];
                $taxPrice = ceil($taxExclusivePrice * ($taxPct / 100));
                $taxIncludingPrice = ceil($taxExclusivePrice + $taxPrice);
            } else {
                $taxIncludingPrice = $calculatedService['calculated_price'];
                $taxExclusivePrice = $taxPct > 0
                    ? ceil((100 * $taxIncludingPrice) / ($taxPct + 100))
                    : $taxIncludingPrice;
                $taxPrice = ceil($taxIncludingPrice - $taxExclusivePrice);
            }

            // Only persist PackageService records in edit mode
            if ($isEditMode) {
                PackageService::create([
                    'random_id' => $data['random_id'],
                    'package_id' => $findPackage->id,
                    'package_bundle_id' => $packageBundleRecordId,
                    'service_id' => $calculatedService['service_id'],
                    'price' => $calculatedService['calculated_price'],
                    'orignal_price' => $calculatedService['service_price'],
                    'actual_price' => $serviceInfo->price,
                    'is_exclusive' => $isExclusive ? 1 : 0,
                    'tax_exclusive_price' => $taxExclusivePrice,
                    'tax_percentage' => $taxPct,
                    'tax_price' => $taxPrice,
                    'tax_including_price' => $taxIncludingPrice,
                    'sold_by' => $soldBy,
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ]);
            }

            $packageServicesData[] = [
                'name' => $serviceInfo->name,
                'service_id' => $calculatedService['service_id'],
                'tax_exclusive_price' => $taxExclusivePrice,
                'tax_price' => $taxPrice,
                'tax_including_price' => $taxIncludingPrice,
                'is_consumed' => 0,
                'actual_price' => $serviceInfo->price,
            ];
        }

        // Update plan name and total only in edit mode
        if ($isEditMode) {
            $newTotal = PackageBundles::where('package_id', $findPackage->id)->sum('tax_including_price');
            $findPackage->update(['total_price' => $newTotal]);
            $this->updatePlanNameForPackage($findPackage);
        }

        return [
            'servicesData' => [
                'service_name' => $bundle->name,
                'service_price' => $bundle->price,
                'discount_name' => '-',
                'discount_type' => '-',
                'discount_price' => '0',
                'sold_by' => $soldBy,
                'bundlesData' => array_merge($bundleData, [
                    'id' => $packageBundleRecordId,
                    'source_type' => 'bundle',
                ]),
                'packageServicesData' => $packageServicesData,
            ],
        ];
    }

    /**
     * Add a service bundle (N sessions of same service) to a plan.
     * Creates one PackageBundles row + N PackageService rows with last-session absorption pricing.
     *
     * @param  array  $data  Keys: bundle_id (service_bundles.id), location_id, net_amount, random_id, sold_by
     * @return array Structured response data with servicesData
     */
    private function addServiceBundleToPlan(array $data): array
    {
        $serviceBundle = ServiceBundle::with('service')->find($data['bundle_id']);

        if (! $serviceBundle) {
            throw new PlanException('Service bundle not found');
        }

        $service = $serviceBundle->service;

        if (! $service) {
            throw new PlanException('Service not found for this bundle');
        }

        $locationInfo = Locations::find($data['location_id']);

        if (! $locationInfo) {
            throw new PlanException('Location not found');
        }

        $taxPct = (float) ($locationInfo->tax_percentage ?? 0);
        $netAmount = (float) $data['net_amount'];
        $sessions = $serviceBundle->sessions;
        $bundleName = $sessions.'x '.$service->name;

        // Bundle-level tax calculation (inclusive — service bundles use inclusive pricing)
        $taxIncludingPrice = $netAmount;
        $taxExclusiveNetAmount = $taxPct > 0
            ? ceil((100 * $netAmount) / ($taxPct + 100))
            : $netAmount;
        $taxPrice = ceil($netAmount - $taxExclusiveNetAmount);

        $bundleData = [
            'qty' => $sessions,
            'bundle_id' => $serviceBundle->id,
            'service_price' => $serviceBundle->price,
            'service_name' => $bundleName,
            'net_amount' => $netAmount,
            'discount_name' => '-',
            'discount_type' => '-',
            'discount_price' => '0',
            'tax_percentage' => $taxPct,
            'tax_exclusive_net_amount' => $taxExclusiveNetAmount,
            'tax_price' => $taxPrice,
            'tax_including_price' => $taxIncludingPrice,
        ];

        // Resolve parent package (edit mode vs create mode)
        $findPackage = Packages::where('random_id', $data['random_id'])->first();
        $isEditMode = $findPackage !== null;
        $packageBundleRecordId = $serviceBundle->id;

        if ($isEditMode) {
            $packageBundleRecord = PackageBundles::create([
                'random_id' => $data['random_id'],
                'qty' => $sessions,
                'bundle_id' => $serviceBundle->id,
                'source_type' => 'service_bundle',
                'discount_name' => '-',
                'discount_type' => '-',
                'discount_price' => 0,
                'service_price' => $serviceBundle->price,
                'net_amount' => $netAmount,
                'is_exclusive' => 0,
                'tax_exclusive_net_amount' => $taxExclusiveNetAmount,
                'tax_percentage' => $taxPct,
                'tax_price' => $taxPrice,
                'tax_including_price' => $taxIncludingPrice,
                'location_id' => $data['location_id'],
                'package_id' => $findPackage->id,
                'is_allocate' => 1,
            ]);
            $packageBundleRecordId = $packageBundleRecord->id;
        }

        // Calculate per-session prices with last-session absorption
        $serviceTaxType = $service->tax_treatment_type_id;
        $isExclusive = ($serviceTaxType == Config::get('constants.tax_is_exclusive'));
        $perSessionPrice = round($taxIncludingPrice / $sessions, 2);

        $soldBy = $data['sold_by'] ?? null;
        $packageServicesData = [];

        for ($i = 0; $i < $sessions; $i++) {
            $isLastSession = ($i === $sessions - 1);

            if ($isLastSession && $sessions > 1) {
                // Last session absorbs rounding difference
                $sumPrevious = round($perSessionPrice * ($sessions - 1), 2);
                $sessionTaxIncluding = round($taxIncludingPrice - $sumPrevious, 2);
            } else {
                $sessionTaxIncluding = $perSessionPrice;
            }

            $taxData = $this->calculateServiceTax(
                $serviceTaxType,
                $sessionTaxIncluding,
                $taxPct,
                $isExclusive,
            );

            if ($isEditMode) {
                PackageService::create([
                    'random_id' => $data['random_id'],
                    'package_id' => $findPackage->id,
                    'package_bundle_id' => $packageBundleRecordId,
                    'service_id' => $service->id,
                    'price' => $sessionTaxIncluding,
                    'orignal_price' => $service->price,
                    'actual_price' => $service->price,
                    'is_exclusive' => $taxData['is_exclusive'] ?? 0,
                    'tax_exclusive_price' => $taxData['tax_exclusive_price'],
                    'tax_percentage' => $taxPct,
                    'tax_price' => $taxData['tax_price'],
                    'tax_including_price' => $taxData['tax_including_price'],
                    'consumption_order' => 0,
                    'sold_by' => $soldBy,
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ]);
            }

            $packageServicesData[] = [
                'name' => $service->name,
                'service_id' => $service->id,
                'tax_exclusive_price' => $taxData['tax_exclusive_price'],
                'tax_price' => $taxData['tax_price'],
                'tax_including_price' => $taxData['tax_including_price'],
                'is_consumed' => 0,
                'actual_price' => $service->price,
            ];
        }

        // Update plan total in edit mode
        if ($isEditMode) {
            $newTotal = PackageBundles::where('package_id', $findPackage->id)->sum('tax_including_price');
            $findPackage->update(['total_price' => $newTotal]);
            $this->updatePlanNameForPackage($findPackage);
        }

        return [
            'servicesData' => [
                'service_name' => $bundleName,
                'service_price' => $serviceBundle->price,
                'discount_name' => '-',
                'discount_type' => '-',
                'discount_price' => '0',
                'sold_by' => $soldBy,
                'bundlesData' => array_merge($bundleData, [
                    'id' => $packageBundleRecordId,
                    'source_type' => 'service_bundle',
                ]),
                'packageServicesData' => $packageServicesData,
            ],
        ];
    }

    /**
     * Add a membership service to a plan.
     *
     * @param  array  $data  Keys: membership_id, location_id, net_amount, sold_by
     * @return array Structured response data with servicesData
     */
    public function addMembershipService(array $data): array
    {
        $membershipTypeId = $data['membership_id'];
        $membershipType = MembershipType::find($membershipTypeId);

        if (! $membershipType) {
            throw new PlanException('Membership type not found');
        }

        $locationInfo = Locations::find($data['location_id']);
        $taxPercentage = $locationInfo->tax_percentage ?? 0;
        $netAmount = (float) $data['net_amount'];

        // Calculate tax (tax-inclusive by default for memberships)
        $taxIncludingPrice = $netAmount;
        $taxExclusivePrice = ceil((100 * $taxIncludingPrice) / ($taxPercentage + 100));
        $taxPrice = ceil($taxIncludingPrice - $taxExclusivePrice);

        $membershipsData = [
            'id' => $membershipType->id,
            'qty' => '1',
            'membership_type_id' => $membershipType->id,
            'service_price' => $membershipType->amount,
            'service_name' => $membershipType->name,
            'net_amount' => $netAmount,
            'tax_percentage' => $taxPercentage,
            'tax_exclusive_net_amount' => $taxExclusivePrice,
            'tax_price' => $taxPrice,
            'tax_including_price' => $taxIncludingPrice,
        ];

        return [
            'servicesData' => [
                'service_name' => $membershipType->name,
                'service_price' => $membershipType->amount,
                'discount_name' => '-',
                'discount_type' => '-',
                'discount_price' => '0',
                'sold_by' => $data['sold_by'] ?? null,
                'membershipsData' => $membershipsData,
                'packageServicesData' => [],
            ],
        ];
    }

    /**
     * Get active bundles for the authenticated user's account.
     *
     * @param  int  $locationId  Location ID (currently unused but kept for future filtering)
     * @return array Contains 'bundles' key with collection of bundles
     */
    public function getBundlesByLocation(int $locationId): array
    {
        return $this->discountService->getBundlesByLocation($locationId);
    }

    /**
     * Get membership types available for a location/patient, including renewal types for expired memberships.
     *
     * @param  int  $locationId  Location ID
     * @param  int|null  $patientId  Optional patient ID to check for expired membership renewals
     * @return array Contains 'memberships' and 'expired_membership_type_id' keys
     */
    public function getMembershipTypes(int $locationId, ?int $patientId = null): array
    {
        return $this->membershipService->getMembershipTypes($locationId, $patientId);
    }

    /**
     * Get membership type info (price and name).
     *
     * @param  int  $membershipTypeId  The membership type ID
     * @return array Contains 'net_amount' and 'membership_name' keys
     */
    public function getMembershipInfo(int $membershipTypeId): array
    {
        return $this->membershipService->getMembershipInfo($membershipTypeId);
    }

    /**
     * Search membership codes by keyword, optionally filtered by membership type.
     *
     * @param  string  $query  Search query string
     * @param  int|null  $membershipTypeId  Optional membership type ID to filter by
     * @return array Contains 'codes' key with matching membership codes
     */
    public function searchMembershipCodes(string $query, ?int $membershipTypeId = null): array
    {
        return $this->membershipService->searchMembershipCodes($query, $membershipTypeId);
    }

    /**
     * Calculate grand total for plan creation.
     *
     * @param  string  $total  The total amount (may contain commas)
     * @param  float  $cashAmount  The cash amount to subtract
     * @return array Contains 'grand_total' key with formatted result
     */
    public function calculateGrandTotal(string $total, float $cashAmount): array
    {
        $packageTotal = str_replace(',', '', $total);
        $grandTotal = number_format((float) $packageTotal - $cashAmount);

        return [
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Calculate grand total for plan update, accounting for refunds and settlements.
     *
     * @param  string  $randomId  The package random ID
     * @param  string  $total  The total amount (may contain commas)
     * @param  float  $cashAmount  The cash amount to subtract
     * @return array Contains 'grand_total' key with formatted result
     */
    public function calculateGrandTotalForUpdate(string $randomId, string $total, float $cashAmount): array
    {
        $package = Packages::where('random_id', '=', $randomId)->first();

        if (! $package) {
            throw new PlanException('Package not found');
        }

        $packageadvancesCashAmount = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');

        $refunded = PackageAdvances::where([
            'package_id' => $package->id,
            'cash_flow' => 'out',
            'is_refund' => 1,
        ])->sum('cash_amount');

        $setteled = PackageAdvances::where([
            'package_id' => $package->id,
            'cash_flow' => 'out',
            'is_setteled' => 1,
        ])->sum('cash_amount');

        $packageTotal = str_replace(',', '', $total);
        $totalWithRefunded = $packageTotal + $refunded + $setteled;
        $grandTotal = number_format(round(($totalWithRefunded - $packageadvancesCashAmount)) - $cashAmount);

        // Only update total_price without touching updated_at
        Packages::where('id', $package->id)->update(['total_price' => $total]);

        return [
            'grand_total' => $grandTotal,
        ];
    }

    // ──────────────────────────────────────────────────
    //  Payment Management
    // ──────────────────────────────────────────────────

    /**
     * Store or update a package advance payment.
     */
    public function storePayment(array $data): array
    {
        $packageTotalPrice = PackageBundles::where('package_id', '=', $data['package_id'])->sum('tax_including_price');
        $getPackageUseAmount = PackageAdvances::where([
            ['package_id', '=', $data['package_id']],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        $getPackageUnusedAmountExceptEdit = PackageAdvances::where([
            ['id', '!=', $data['package_advances_id']],
            ['package_id', '=', $data['package_id']],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount');
        $getPackageUnusedAmountWithEdit = $data['cash_amount'];
        $getPackageUnuseAmount = $getPackageUnusedAmountExceptEdit + $getPackageUnusedAmountWithEdit;
        $amountStatus = true;

        // Get old values before update
        $packageAdvanceBefore = PackageAdvances::find($data['package_advances_id']);
        $oldAmount = $packageAdvanceBefore?->cash_amount ?? 0;
        $oldDate = $packageAdvanceBefore?->created_at;

        $record = PackageAdvances::updateRecordFinanceedit(
            $data,
            Auth::user()->account_id,
            $amountStatus
        );

        if ($record) {
            // Sync plan_invoices table
            $planInvoice = PlanInvoice::where('package_advance_id', $data['package_advances_id'])->first();
            if ($planInvoice) {
                $planInvoice->update([
                    'total_price' => $data['cash_amount'],
                    'payment_mode_id' => $data['payment_mode_id'],
                    'created_at' => $data['created_at'].' '.Carbon::now()->toTimeString(),
                    'updated_at' => now(),
                ]);
            }

            // Log payment updated activity
            $package = Packages::find($data['package_id']);
            $patient = $package ? Patients::find($package->patient_id) : null;
            $location = $package ? Locations::with('city')->find($package->location_id) : null;
            $newAmount = (float) $data['cash_amount'];
            $newDate = $data['created_at'];

            $amountChanged = $oldAmount != $newAmount;
            $oldDateFormatted = $oldDate ? Carbon::parse($oldDate)->format('Y-m-d') : null;
            $dateChanged = $oldDateFormatted && $newDate && $oldDateFormatted != $newDate;

            if ($package && $patient && ($amountChanged || $dateChanged)) {
                ActivityLogger::logPaymentUpdated($oldAmount, $newAmount, $oldDateFormatted, $newDate, $amountChanged, $dateChanged, $package, $patient, $location);
            }

            return [
                'success' => true,
                'message' => 'Data Updated successfully.',
                'data' => ['amount_status' => $amountStatus],
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to update record.',
        ];
    }

    /**
     * Delete a package advance payment.
     */
    public function deletePayment(array $data): array
    {
        $packageadvanceinfo = PackageAdvances::withTrashed()->find($data['package_advance_id']);

        $getPackageUseAmount = PackageAdvances::where([
            ['package_id', '=', $packageadvanceinfo->package_id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        $getPackageUnusedAmountExceptEdit = PackageAdvances::where([
            ['id', '!=', $data['package_advance_id']],
            ['package_id', '=', $packageadvanceinfo->package_id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');

        if ($getPackageUseAmount <= $getPackageUnusedAmountExceptEdit) {
            $record = PackageAdvances::deletefinaceRecord((int) $data['package_advance_id']);
            $cashReceiveRemain = number_format(sanitize_money($data['cash_receveive_remain']) + $packageadvanceinfo->cash_amount);

            // Sync plan_invoices table - soft delete the corresponding plan_invoice
            $planInvoice = PlanInvoice::where('package_advance_id', $data['package_advance_id'])->first();
            if ($planInvoice) {
                $planInvoice->delete();
            }

            // Log payment deleted activity
            $package = Packages::find($packageadvanceinfo->package_id);
            $patient = $package ? Patients::find($package->patient_id) : null;
            $location = $package ? Locations::with('city')->find($package->location_id) : null;
            if ($package && $patient) {
                ActivityLogger::logPaymentDeleted($packageadvanceinfo->cash_amount, $package, $patient, $location);
            }

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
                'data' => [
                    'id' => $data['package_advance_id'],
                    'cash_receveive_remain' => $cashReceiveRemain,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'Unable to delete consume amount.',
        ];
    }

    // ──────────────────────────────────────────────────
    //  Sold By
    // ──────────────────────────────────────────────────

    /**
     * Get sold-by data for a package service or bundle.
     */
    public function getSoldByData(int $packageServiceId, ?int $bundleId, int $locationId, ?array $configBundleIds = null): array
    {
        if ($packageServiceId > 0) {
            $packageService = PackageService::find($packageServiceId);

            if (! $packageService) {
                return ['success' => false, 'message' => 'Package service not found'];
            }

            $package = Packages::find($packageService->package_id);
            $locationId = $locationId ?: $package->location_id;
            $currentSoldBy = $packageService->sold_by;
            $packageServices = collect([$packageService]);
        } elseif ($bundleId) {
            $packageBundle = PackageBundles::find($bundleId);

            if (! $packageBundle) {
                return ['success' => false, 'message' => 'Package bundle not found'];
            }

            $package = Packages::find($packageBundle->package_id);
            $locationId = $locationId ?: $package->location_id;

            $bundleIds = ! empty($configBundleIds) ? $configBundleIds : [$packageBundle->id];

            $packageServices = PackageService::whereIn('package_bundle_id', $bundleIds)->get();

            if ($packageServices->isEmpty()) {
                return ['success' => false, 'message' => 'No services found for this bundle'];
            }

            $currentSoldBy = $packageServices->first()->sold_by;
        } else {
            return ['success' => false, 'message' => 'Package service or bundle ID required'];
        }

        // Get all active doctors from the location
        $doctorsIds = DoctorHasLocations::where('is_allocated', 1)->where('location_id', $locationId)->pluck('user_id')->toArray();

        $allDoctors = User::whereIn('id', $doctorsIds)
            ->where('active', 1)
            ->pluck('name', 'id')
            ->toArray();

        // Get FDM users by getting the user_ids associated with the center (location_id)
        $findFDM = UserHasLocations::where('location_id', $locationId)->pluck('user_id')->toArray();

        $findRole = DB::table('roles')->where('name', 'FDM')->first();
        $fdmUserIds = [];
        if ($findRole) {
            $roleId = $findRole->id;
            $roleHasUser = RoleHasUsers::where('role_id', $roleId)->pluck('user_id')->toArray();
            $fdmUserIds = array_intersect($findFDM, $roleHasUser);
        }

        $selectedUserId = $currentSoldBy;
        $usersToShow = [];

        // Ensure the currently selected user (sold_by) is ALWAYS included, even if inactive
        if ($selectedUserId) {
            $currentSoldByUser = User::find($selectedUserId);
            if ($currentSoldByUser) {
                $usersToShow[$currentSoldByUser->id] = $currentSoldByUser->name;
            }
        }

        // Add all active doctors from the location
        foreach ($allDoctors as $doctorId => $doctorName) {
            if (! array_key_exists($doctorId, $usersToShow)) {
                $usersToShow[$doctorId] = $doctorName;
            }
        }

        // Add all active FDM users from the location
        if (! empty($fdmUserIds)) {
            $FDMUsers = User::whereIn('id', $fdmUserIds)
                ->where('active', 1)
                ->pluck('name', 'id')
                ->toArray();

            foreach ($FDMUsers as $fdmId => $fdmName) {
                if (! array_key_exists($fdmId, $usersToShow)) {
                    $usersToShow[$fdmId] = $fdmName;
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Record found',
            'data' => [
                'users' => $usersToShow,
                'current_sold_by' => $currentSoldBy,
                'package_services' => $packageServices->map(fn ($service) => [
                    'id' => $service->id,
                    'sold_by' => $service->sold_by,
                ]),
            ],
        ];
    }

    /**
     * Update sold by for package service(s).
     */
    public function updateSoldBy(array $data): array
    {
        // If package_services array is provided, update multiple services
        if (! empty($data['package_services']) && is_array($data['package_services'])) {
            foreach ($data['package_services'] as $serviceId) {
                $packageService = PackageService::find($serviceId);
                if ($packageService) {
                    $packageService->sold_by = $data['sold_by'];
                    $packageService->save();
                }
            }

            return ['success' => true, 'message' => 'Sold by updated successfully for all services'];
        }

        // Single service update
        if (! empty($data['package_service_id'])) {
            $packageService = PackageService::find($data['package_service_id']);

            if (! $packageService) {
                return ['success' => false, 'message' => 'Package service not found'];
            }

            $packageService->sold_by = $data['sold_by'];
            $packageService->save();

            return ['success' => true, 'message' => 'Sold by updated successfully'];
        }

        return ['success' => false, 'message' => 'Package service ID required'];
    }

    /**
     * Check if service is duplicate and return appropriate sold-by users.
     */
    public function checkDuplicateServiceForSoldBy(array $data): array
    {
        $bundleId = $data['bundle_id'];
        $packageId = $data['package_id'];
        $locationId = $data['location_id'];

        $package = Packages::where('random_id', $packageId)->first();

        if (! $package) {
            return ['success' => false, 'message' => 'Package not found'];
        }

        // Get all services in the current package for this bundle
        $existingServices = PackageService::join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
            ->where('package_services.package_id', $package->id)
            ->where('package_bundles.bundle_id', $bundleId)
            ->count();

        $isDuplicateService = $existingServices > 0;

        if ($isDuplicateService) {
            $package->load('appointment.doctor');

            $usersToShow = [];

            if ($package->appointment && $package->appointment->doctor_id) {
                $appointmentDoctor = User::find($package->appointment->doctor_id);

                if ($appointmentDoctor) {
                    $usersToShow[$appointmentDoctor->id] = $appointmentDoctor->name;
                }
            }

            return [
                'success' => true,
                'message' => 'Duplicate service detected',
                'data' => [
                    'users' => $usersToShow,
                    'is_duplicate' => true,
                ],
            ];
        }

        // If not duplicate, show doctors who have treated this patient in last 60 days
        $sixtyDaysAgo = now()->subDays(60);

        $recentTreatmentDoctorIds = Appointments::where('patient_id', $package->patient_id)
            ->where('location_id', $locationId)
            ->where('appointment_status_id', 2)
            ->where('appointment_type_id', 2)
            ->where('scheduled_date', '>=', $sixtyDaysAgo)
            ->pluck('doctor_id')
            ->unique()
            ->toArray();

        $doctorsIds = DoctorHasLocations::where('is_allocated', 1)
            ->where('location_id', $locationId)
            ->pluck('user_id')
            ->toArray();

        $allDoctors = User::whereIn('id', $doctorsIds)
            ->where('active', 1)
            ->pluck('name', 'id')
            ->toArray();

        $usersToShow = [];

        foreach ($recentTreatmentDoctorIds as $doctorId) {
            if (array_key_exists($doctorId, $allDoctors)) {
                $usersToShow[$doctorId] = $allDoctors[$doctorId];
            }
        }

        // If no recent history, return the appointment doctor
        if (empty($usersToShow)) {
            $package->load('appointment.doctor');

            if ($package->appointment && $package->appointment->doctor_id) {
                $appointmentDoctor = User::find($package->appointment->doctor_id);

                if ($appointmentDoctor) {
                    $usersToShow[$appointmentDoctor->id] = $appointmentDoctor->name;
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Service not duplicate',
            'data' => [
                'users' => $usersToShow,
                'is_duplicate' => false,
            ],
        ];
    }

    // ──────────────────────────────────────────────────
    //  SMS
    // ──────────────────────────────────────────────────

    /**
     * Resend an SMS from its log entry.
     */
    public function resendSms(int $smsLogId): array
    {
        $SMSLog = SMSLogs::findOrFail($smsLogId);

        $packageInfo = Packages::find($SMSLog->package_id);
        $setting = Settings::whereSlug('sys-current-sms-operator')->first();
        $operatorSettings = UserOperatorSettings::getRecord($packageInfo->account_id, $setting->data);

        if ($setting->data == 1) {
            $SMSObj = [
                'username' => $operatorSettings->username,
                'password' => $operatorSettings->password,
                'to' => $SMSLog->to,
                'text' => $SMSLog->text,
                'mask' => $operatorSettings->mask,
                'test_mode' => $operatorSettings->test_mode,
            ];
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = [
                'username' => $operatorSettings->username,
                'password' => $operatorSettings->password,
                'from' => $operatorSettings->mask,
                'to' => $SMSLog->to,
                'text' => $SMSLog->text,
                'test_mode' => $operatorSettings->test_mode,
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }

        if ($response['status']) {
            SMSLogs::find($smsLogId)?->update(['status' => 1]);

            return ['success' => true, 'message' => 'SMS sent successfully.'];
        }

        return ['success' => false, 'message' => 'SMS not sent.'];
    }

    // ──────────────────────────────────────────────────
    //  Refund
    // ──────────────────────────────────────────────────

    /**
     * Get refund form data for a package.
     */
    public function getRefundFormData(int $packageId): array
    {
        return $this->refundService->getRefundFormData($packageId);
    }

    /**
     * Process a refund update for a package.
     */
    public function processRefund(array $data): array
    {
        return $this->refundService->processRefund($data);
    }

    // ──────────────────────────────────────────────────
    //  Plan Row / Voucher Management
    // ──────────────────────────────────────────────────

    /**
     * Delete a plan row and restore any associated voucher.
     */
    public function deletePlanRow(array $data): array
    {
        $voucher = PackageVouchers::where('service_id', $data['id'])
            ->where('package_random_id', $data['random_id'])
            ->first();

        if ($voucher) {
            $checkUser = UserVouchers::where('voucher_id', $voucher->voucher_id)
                ->where('user_id', $voucher->user_id)
                ->first();
            if ($checkUser) {
                $newAmount = $checkUser->amount + $voucher->amount;
                $checkUser->amount = $newAmount;
                $checkUser->update();
            }
            $voucher->delete();
        }

        return [
            'success' => true,
            'message' => 'Record deleted successfully',
        ];
    }

    /**
     * Reset voucher package bundles and restore voucher amounts.
     */
    public function resetVoucherPackageBundles(array $data): array
    {
        return $this->discountService->resetVoucherPackageBundles($data);
    }

    // ──────────────────────────────────────────────────
    //  Extracted from PackagesController
    // ──────────────────────────────────────────────────

    /**
     * Get service info for packages (bundle-based).
     * Extracted from PackagesController::getserviceinfo().
     */
    public function getServiceInfoForPackage(array $data): array
    {
        return $this->discountService->getServiceInfoForPackage($data);
    }

    /**
     * Get service info for simple plans (non-bundle).
     * Extracted from PackagesController::getserviceinfo_for_plan().
     */
    public function getServiceInfoForPlan(array $data): array
    {
        return $this->discountService->getServiceInfoForPlan($data);
    }

    /** @internal dead body placeholder for getServiceInfoForPlan */
    /** @internal removed - extracted to PlanDiscountService */
    /**
     * Get discount info for simple plans (non-bundle).
     * Extracted from PackagesController::getdiscountinfo_for_plan().
     */
    public function getDiscountInfoForPlan(array $data): array
    {
        return $this->discountService->getDiscountInfoForPlan($data);
    }

    /** @internal dead placeholder - REMOVED */
    private function _DEAD_getDiscountInfoForPlan_FINAL(): void {}

    /**
     * Get custom discount info for simple plans (non-bundle).
     * Extracted from PackagesController::getdiscountinfocustom_for_plan().
     */
    public function getCustomDiscountInfoForPlan(array $data): array
    {
        return $this->discountService->getCustomDiscountInfoForPlan($data);
    }

    /** @internal dead placeholder - REMOVED */
    private function _DEAD_getCustomDiscountInfoForPlan(): void {}

    /**
     * Save service to plan - handles both simple and configurable discounts.
     * Extracted from PackagesController::savepackages_service_for_plan().
     */
    public function saveServiceForPlan(array $data): array
    {
        return $this->discountService->saveServiceForPlan($data);
    }

    public function reserveVoucherAmount(int $voucherId, int $patientId, float $amount): array
    {
        return $this->discountService->reserveVoucherAmount($voucherId, $patientId, $amount);
    }

    public function refundVoucherAmount(int $voucherId, int $patientId, float $amount): array
    {
        return $this->discountService->refundVoucherAmount($voucherId, $patientId, $amount);
    }

    /** @internal dead placeholder - REMOVED */
    private function _DEAD_saveServiceForPlan(): void
    {
        // body removed - see PlanDiscountService::saveServiceForPlan
    }

    /**
     * Save packages service information (bundle path).
     * Extracted from PackagesController::savepackages_service().
     */
    public function savePackagesService(array $data): array
    {
        return $this->discountService->savePackagesService($data);
    }
}
