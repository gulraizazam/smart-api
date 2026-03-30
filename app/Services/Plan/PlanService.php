<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Enums\CashFlow;
use App\Enums\PlanType;
use App\Exceptions\PlanException;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\Bundles;
use App\Models\BundleHasServices;
use App\Models\Discounts;
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
use App\Models\Services;
use App\Models\Settings;
use App\Models\AuditTrails;
use App\Models\StudentVerification;
use App\Models\User;
use App\Models\UserVouchers;
use App\Services\Membership\StudentVerificationService;
use App\Services\MetaConversionApiService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class PlanService
{
    private const CACHE_TTL = 3600;

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
            'total'   => $this->buildCountQuery($whereConditions)->count(),
            'query'   => $this->buildOptimizedResultQuery($whereConditions, $accountId),
            'orderBy' => $orderBy,
            'order'   => $order,
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
        $locations = Locations::whereIn('id', $userCentres)
            ->where('active', 1)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        return [
            'total'         => $this->buildCountQuery($whereConditions)->count(),
            'query'         => $this->buildOptimizedResultQuery($whereConditions, $accountId),
            'orderBy'       => $orderBy,
            'order'         => $order,
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
            'total_plans'    => (int) ($stats->total_plans ?? 0),
            'active_plans'   => (int) ($stats->active_plans ?? 0),
            'total_amount'   => (float) ($stats->total_amount ?? 0),
            'cash_received'  => (float) $cashReceived,
            'refunded_plans' => (int) ($stats->refunded_plans ?? 0),
        ];
    }

    // ──────────────────────────────────────────────────
    //  Lookup Data (cached)
    // ──────────────────────────────────────────────────

    public function getLookupData(int|string $patientId): array
    {
        $cacheKey = "plan_lookup_patient_{$patientId}_" . Auth::id();

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($patientId): array {
            $userCentres = ACL::getUserCentres();

            return [
                'locations' => Locations::whereIn('id', $userCentres)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray(),
                'packages'  => Packages::where('patient_id', $patientId)
                    ->pluck('name', 'id')
                    ->toArray(),
                'statuses'  => ['1' => 'Active', '0' => 'Inactive'],
            ];
        });
    }

    public function getGlobalLookupData(): array
    {
        $cacheKey = 'plan_global_lookup_' . Auth::id();

        return Cache::remember($cacheKey, self::CACHE_TTL, function (): array {
            $userCentres = ACL::getUserCentres();

            return [
                'locations' => Locations::whereIn('id', $userCentres)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray(),
                'statuses'  => ['1' => 'Active', '0' => 'Inactive'],
            ];
        });
    }

    public function clearLookupCache(int|string $patientId): void
    {
        Cache::forget("plan_lookup_patient_{$patientId}_" . Auth::id());
    }

    // ──────────────────────────────────────────────────
    //  Bulk Delete
    // ──────────────────────────────────────────────────

    /**
     * @param array<int> $ids
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
                . ($skipped > 0 ? " {$skipped} record(s) skipped due to dependencies." : '')
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
            ->mapWithKeys(fn ($loc) => [$loc->id => ($loc->city?->name ?? '') . '-' . $loc->name]);

        $paymentmodes = PaymentModes::where('type', 'application')->pluck('name', 'id');

        $customDiscountRange = Settings::where('slug', 'sys-discounts')->first();
        $range = $customDiscountRange ? explode(':', $customDiscountRange->data) : [0, 100];

        $discounts = Discounts::where('active', 1)->get(['id', 'name']);

        return [
            'locations'     => $locations,
            'random_id'     => md5(time() . random_int(1, 9999) . random_int(78599, 99999)),
            'paymentmodes'  => $paymentmodes,
            'range'         => $range,
            'discount_type' => config('constants.amount_types'),
            'discounts'     => $discounts,
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
                    ? $location->city->name . '-' . $location->name
                    : ($location?->name ?? 'Unknown Location');

                $data['appointmentArray'] = $this->buildPatientAppointments($patientId, $lastConsultation->location_id);
                $data['patient_membership'] = $this->getPatientMembershipDisplay($patientId);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('Error in getCreateFormDataForPatient', [
                'patient_id' => $patientId,
                'error'      => $e->getMessage(),
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
                $dateTime = $apt->scheduled_date . ' ' . $apt->scheduled_time;
                $appointmentArray[$apt->id] = [
                    'id'        => $apt->id . '.A',
                    'name'      => $apt->service_name . ' - '
                        . Carbon::parse($dateTime)->format('F j,Y h:i A') . ' - '
                        . $apt->doctor_name,
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
            if (!empty($appointmentArray)) {
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
                'appointments'           => $appointmentArray,
                'membership'             => $membershipTypeName,
                'users'                  => $usersToShow,
                'selected_doctor_id'     => $selectedUserId,
                'latest_consultation_id' => $appointments->first()?->id,
            ];
        } catch (\Throwable $e) {
            Log::error('Get Appointment Info Error: ' . $e->getMessage());

            return [
                'appointments'       => [],
                'membership'         => 'No membership',
                'users'              => [],
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
            Log::error('Get Services By Location Error: ' . $e->getMessage());
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

            $discount = !empty($data['discount_id'])
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
                'service_price'    => $service->price,
                'calculated_price' => $data['net_amount'] ?? $service->price,
                'service_id'       => $service->id,
                'name'             => $service->name,
                'is_consumed'      => 0,
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
                'bundlesData'         => $packageBundleData,
                'packageServicesData' => $allDataServices,
                'packageServices'     => $packageServices,
                'packageBundle'       => $packageBundle,
                'random_id'           => $data['random_id'],
                'service_name'        => $packageBundleData['service_name'],
                'service_price'       => $packageBundleData['service_price'],
                'discount_name'       => $discountData['discount_name'],
                'discount_type'       => $discountData['discount_type'],
                'discount_price'      => $discountData['discount_price'],
                'net_amount'          => $packageBundleData['net_amount'],
                'total'               => $total,
                'sold_by'             => $soldBy,
                'sold_by_name'        => $soldByName,
            ];
        } catch (PlanException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Add Service To Package Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw PlanException::invalidOperation('Failed to add service to package: ' . $e->getMessage());
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
            Log::error('Save Plan Package Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw PlanException::invalidOperation('Failed to save package: ' . $e->getMessage());
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

            $hasNewServices = !empty($data['package_bundles']);
            $hasPayment = $this->hasPayment($data);

            $appointmentId = $this->handleAppointmentForUpdate($data, $package);

            if (!$appointmentId && ($hasNewServices || $hasPayment)) {
                throw PlanException::invalidOperation('Appointment ID is required');
            }

            if ($hasNewServices || $hasPayment) {
                $package->update([
                    'total_price'   => str_replace(',', '', $data['total']),
                    'sessioncount'  => '1',
                    'account_id'    => Auth::user()->account_id,
                    'appointment_id' => $appointmentId,
                    'updated_at'    => Filters::getCurrentTimeStamp(),
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

            return ['status' => true, 'message' => 'updated successfully', 'package_id' => $package->id];
        } catch (PlanException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Update Plan Package Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw PlanException::invalidOperation('Failed to update package: ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────
    //  Display / Edit Data
    // ──────────────────────────────────────────────────

    /**
     * @throws PlanException
     */
    public function getEditFormData(int|string $packageId): array
    {
        try {
            $package = Packages::with('user', 'location')->find($packageId)
                ?? throw PlanException::notFound($packageId);

            $totalPrice = PackageBundles::where('package_id', $packageId)->sum('tax_including_price');

            $packageBundles = PackageBundles::with(['bundle', 'service', 'discount', 'membershipType', 'packageservice.soldBy'])
                ->where('package_id', $packageId)
                ->get();

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
                ? $package->appointment_id . '.A'
                : null;

            $studentDocuments = [];
            $studentVerification = StudentVerification::where('package_id', $packageId)->first();
            if ($studentVerification && !empty($studentVerification->document_paths)) {
                $studentDocuments = $studentVerification->document_paths;
            }

            $isMembershipConsumed = PackageService::where('package_id', $packageId)
                ->where('is_consumed', 1)
                ->exists();

            return [
                'package'                 => $package,
                'locations'               => $userLocations,
                'packagebundles'          => $packageBundles,
                'packageservices'         => $packageServices,
                'users'                   => $appointmentInfo['users'],
                'selectedUserId'          => $appointmentInfo['selected_doctor_id'],
                'selectedAppointmentId'   => $selectedAppointmentId,
                'packageadvances'         => $packageAdvances,
                'paymentmodes'            => $paymentModes,
                'grand_total'             => $remainingAmount,
                'range'                   => $range,
                'locationhasservice'      => $locationHasService,
                'total_price'             => $totalPrice,
                'end_previous_date'       => $endPreviousDate,
                'appointmentArray'        => $appointmentInfo['appointments'],
                'discount_type'           => config('constants.amount_types'),
                'discounts'               => $discounts,
                'membership'              => $membershipDisplay,
                'student_documents'       => $studentDocuments,
                'is_membership_consumed'  => $isMembershipConsumed,
            ];
        } catch (PlanException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Get Edit Form Data Error: ' . $e->getMessage());
            throw PlanException::invalidOperation('Failed to load edit form data: ' . $e->getMessage());
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

            $packageBundles = PackageBundles::with(['bundle', 'service', 'discount', 'membershipType', 'packageservice.soldBy'])
                ->where('package_id', $packageId)
                ->get();

            // Normalize bundle relationship based on source_type
            $packageBundles->each(function ($pb) {
                if ($pb->source_type === 'service' && $pb->service) {
                    $pb->setRelation('bundle', $pb->service);
                } elseif (!$pb->source_type && $pb->service && !$pb->membership_type_id) {
                    $children = $pb->packageservice;
                    if ($children?->count() === 1 && $children->first()->service_id == $pb->bundle_id) {
                        $pb->setRelation('bundle', $pb->service);
                    }
                }
            });

            $packageServices = PackageService::with('service', 'soldBy')
                ->where('package_id', $packageId)
                ->get();

            $packageServicesPrice = $package->plan_type === PlanType::Membership
                ? PackageBundles::where('package_id', $packageId)->sum('tax_including_price')
                : PackageService::where('package_id', $packageId)->sum('price');

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
            $discounts = Cache::remember('discounts_' . Auth::user()->account_id, self::CACHE_TTL, fn () => Discounts::getDiscount(Auth::user()->account_id));
            $paymentModes = PaymentModes::pluck('name', 'id');
            $membershipDisplay = $this->getMembershipDisplayForPackage($package->patient_id);

            $studentDocuments = [];
            $studentVerification = StudentVerification::where('package_id', $packageId)->first();
            if ($studentVerification && !empty($studentVerification->document_paths)) {
                $studentDocuments = $studentVerification->document_paths;
            }

            return [
                'package'           => $package,
                'packagebundles'    => $packageBundles,
                'packageservices'   => $packageServices,
                'packageadvances'   => $packageAdvances,
                'services'          => $services,
                'discount'          => $discounts,
                'paymentmodes'      => $paymentModes,
                'grand_total'       => round($packageServicesPrice, 2),
                'membership'        => $membershipDisplay,
                'student_documents' => $studentDocuments,
            ];
        } catch (PlanException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Get Display Data Error: ' . $e->getMessage());
            throw PlanException::invalidOperation('Failed to load display data: ' . $e->getMessage());
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

        if (!empty($childRecords)) {
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

        if (!empty($where)) {
            $query->where($where);
        }

        $query->whereIn('location_id', ACL::getUserCentres());

        if (!Gate::allows('view_inactive_plans')) {
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
            ->when(!$canViewInactive, fn ($q) => $q->where('active', 1));

        if (!empty($where)) {
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
                  AND package_id IN (' . $scopedSql . ')
                GROUP BY package_id
            ) as pa_agg'), fn ($join) => $join->on('pa_agg.package_id', '=', 'packages.id'))
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(tax_including_price) as bundle_total,
                    MAX(updated_at) as max_updated
                FROM package_bundles
                WHERE package_id IN (' . $scopedSql . ')
                GROUP BY package_id
            ) as pb_agg'), fn ($join) => $join->on('pb_agg.package_id', '=', 'packages.id'))
            ->leftJoin(DB::raw('(
                SELECT package_id,
                    SUM(tax_including_price) as service_total,
                    COUNT(*) as session_count,
                    MAX(updated_at) as max_updated
                FROM package_services
                WHERE package_id IN (' . $scopedSql . ')
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

        if (!empty($where)) {
            $query->where($where);
        }

        $query->whereIn('packages.location_id', $userCentres);

        if (!$canViewInactive) {
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

        return $where;
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
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'patient_id');
        } elseif ($cached = Filters::get($userId, $filename, 'patient_id')) {
            $where[] = ['packages.patient_id', '=', $cached];
        }
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
            $dateRange = explode(' - ', $filters['created_at']);
            if (count($dateRange) === 2) {
                $where[] = ['packages.created_at', '>=', Carbon::parse($dateRange[0])->startOfDay()];
                $where[] = ['packages.created_at', '<=', Carbon::parse($dateRange[1])->endOfDay()];
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

        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        $fieldMap = [
            'id'                        => 'packages.id',
            'package_id'                => 'packages.id',
            'created_at'                => 'packages.created_at',
            'updated_at'                => 'packages.updated_at',
            'packages.updated_at'       => 'packages.updated_at',
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
                'tax_price'                => ceil($netAmount * ($taxPercentage / 100)),
                'tax_including_price'      => ceil($netAmount + ($netAmount * $taxPercentage / 100)),
            ],
            default => [
                'tax_including_price'      => $netAmount,
                'tax_exclusive_net_amount' => ceil((100 * $netAmount) / ($taxPercentage + 100)),
                'tax_price'                => ceil($netAmount - ceil((100 * $netAmount) / ($taxPercentage + 100))),
            ],
        };
    }

    private function calculateServiceTax(int|string $taxTreatmentType, float $price, float $taxPercentage, bool $isExclusive): array
    {
        $taxBoth = Config::get('constants.tax_both');
        $taxExclusive = Config::get('constants.tax_is_exclusive');

        $base = ['tax_percenatage' => $taxPercentage];

        if (($taxTreatmentType == $taxBoth && $isExclusive) || $taxTreatmentType == $taxExclusive) {
            return $base + [
                'tax_exclusive_price' => $price,
                'tax_price'           => ceil($price * ($taxPercentage / 100)),
                'tax_including_price' => ceil($price + ($price * $taxPercentage / 100)),
                'is_exclusive'        => 1,
            ];
        }

        if ($taxTreatmentType == $taxBoth && !$isExclusive) {
            $exclusivePrice = ceil((100 * $price) / ($taxPercentage + 100));
            return $base + [
                'tax_including_price' => $price,
                'tax_exclusive_price' => $exclusivePrice,
                'tax_price'           => ceil($price - $exclusivePrice),
                'is_exclusive'        => 0,
            ];
        }

        // Default: inclusive
        $exclusivePrice = ceil((100 * $price) / ($taxPercentage + 100));
        return $base + [
            'tax_including_price' => $price,
            'tax_exclusive_price' => $exclusivePrice,
            'tax_price'           => ceil($price - $exclusivePrice),
            'is_exclusive'        => 0,
        ];
    }

    // ── Package Bundle Builders ─────────────────────────

    private function buildPackageBundleDataFromService(Services $service, ?Discounts $discount, Locations $location, array $data): array
    {
        $packageBundleData = [
            'qty'          => '1',
            'bundle_id'    => $service->id,
            'service_price' => $service->price,
            'service_name' => $service->name,
            'net_amount'   => $data['net_amount'],
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
        $packageBundleData['tax_percenatage'] = $location->tax_percentage;

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
                'random_id'     => $data['random_id'],
                'service_id'    => $detail['service_id'],
                'name'          => $detail['name'],
                'price'         => $detail['calculated_price'],
                'orignal_price' => $detail['service_price'],
                'created_at'    => Filters::getCurrentTimeStamp(),
                'updated_at'    => Filters::getCurrentTimeStamp(),
                'is_consumed'   => 0,
                'sold_by'       => $data['sold_by'] ?? null,
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
        $isSimpleIdFormat = !is_array($firstBundle);

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
        $bundleIds = array_column($data['package_bundles'], 'bundleId');
        $planType = PlanType::tryFrom($data['plan_type'] ?? 'plan') ?? PlanType::Plan;

        $allPackageServices = [];

        if ($planType === PlanType::Plan) {
            $allPackageServices = $this->storePlanTypeServices($package, $data, $bundleIds, $locationInfo);
        } else {
            $allPackageServices = $this->storeBundleTypeServices($package, $data, $bundleIds, $locationInfo);
        }

        if (!empty($allPackageServices)) {
            PackageService::insert($allPackageServices);
        }
    }

    private function storePlanTypeServices(Packages $package, array $data, array $bundleIds, Locations $locationInfo): array
    {
        $servicesData = Services::whereIn('id', $bundleIds)->get()->keyBy('id');
        $allPackageServices = [];

        foreach ($data['package_bundles'] as $packageBundle) {
            $serviceId = $packageBundle['bundleId'];
            $serviceData = $servicesData->get($serviceId);

            if (!$serviceData) {
                continue;
            }

            $discountId = $packageBundle['DiscountId'] ?? null;
            if ($discountId == '0' || $discountId == '') {
                $discountId = null;
            }

            $packageBundleData = [
                'random_id'              => $package->random_id,
                'is_allocate'            => 1,
                'qty'                    => 1,
                'discount_name'          => $packageBundle['DiscountName'] ?? null,
                'discount_type'          => $packageBundle['Type'] ?? null,
                'discount_price'         => str_replace(',', '', $packageBundle['DiscountValue'] ?? '0'),
                'service_price'          => str_replace(',', '', $packageBundle['RegularPrice']),
                'net_amount'             => str_replace(',', '', $packageBundle['RegularPrice']),
                'discount_id'            => $discountId,
                'config_group_id'        => !empty($packageBundle['config_group_id']) ? $packageBundle['config_group_id'] : null,
                'bundle_id'              => $serviceId,
                'source_type'            => 'service',
                'package_id'             => $package->id,
                'tax_exclusive_net_amount' => str_replace(',', '', $packageBundle['Amount']),
                'tax_percenatage'        => $locationInfo->tax_percentage ?? 0,
                'tax_price'              => str_replace(',', '', $packageBundle['Tax']),
                'tax_including_price'    => str_replace(',', '', $packageBundle['Total']),
                'location_id'            => $data['location_id'],
            ];

            $packageBundleRecord = PackageBundles::create($packageBundleData);

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
                'random_id'          => $data['random_id'],
                'package_id'         => $package->id,
                'package_bundle_id'  => $packageBundleRecord->id,
                'service_id'         => $serviceData->id,
                'price'              => $totalPrice,
                'orignal_price'      => $serviceData->price,
                'actual_price'       => $serviceData->price,
                'consumption_order'  => $consumptionOrder,
                'created_at'         => Filters::getCurrentTimeStamp(),
                'updated_at'         => Filters::getCurrentTimeStamp(),
                'sold_by'            => $packageBundle['sold_by'] ?? null,
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

            if (!$serviceData) {
                continue;
            }

            $discountId = $packageBundle['DiscountId'] ?? null;
            if ($discountId == '0' || $discountId == '') {
                $discountId = null;
            }

            $packageBundleData = [
                'random_id'              => $package->random_id,
                'is_allocate'            => 1,
                'qty'                    => 1,
                'discount_name'          => $packageBundle['DiscountName'] ?? null,
                'discount_type'          => $packageBundle['Type'] ?? null,
                'discount_price'         => str_replace(',', '', $packageBundle['DiscountValue'] ?? '0'),
                'service_price'          => str_replace(',', '', $packageBundle['RegularPrice']),
                'net_amount'             => str_replace(',', '', $packageBundle['RegularPrice']),
                'discount_id'            => $discountId,
                'bundle_id'              => $bundleId,
                'source_type'            => 'bundle',
                'package_id'             => $package->id,
                'tax_exclusive_net_amount' => str_replace(',', '', $packageBundle['Amount']),
                'tax_percenatage'        => $locationInfo->tax_percentage ?? 0,
                'tax_price'              => str_replace(',', '', $packageBundle['Tax']),
                'tax_including_price'    => str_replace(',', '', $packageBundle['Total']),
                'location_id'            => $data['location_id'],
            ];

            $packageBundleRecord = PackageBundles::create($packageBundleData);

            $bundleServices = $allBundleServices->get($bundleId, collect());
            $calculableServices = $bundleServices->map(fn ($bs) => [
                'service_price'    => $bs->calculated_price,
                'calculated_price' => $bs->calculated_price,
                'service_id'       => $bs->service_id,
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
                    'random_id'         => $data['random_id'],
                    'package_id'        => $package->id,
                    'package_bundle_id' => $packageBundleRecord->id,
                    'service_id'        => $csp['service_id'],
                    'price'             => $csp['calculated_price'],
                    'orignal_price'     => $csp['service_price'],
                    'actual_price'      => $svcInfo?->price,
                    'created_at'        => Filters::getCurrentTimeStamp(),
                    'updated_at'        => Filters::getCurrentTimeStamp(),
                    'sold_by'           => $packageBundle['sold_by'] ?? null,
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
                $hasStudentDocuments = !empty($storedDocumentPaths);

                $shouldConsume = $isFullyPaid && $hasStudentDocuments;
                $this->storeMembershipData($package, $data, $shouldConsume);

                if ($hasStudentDocuments) {
                    $studentVerificationService->createVerificationRecord([
                        'patient_id'         => $data['patient_id'],
                        'membership_id'      => $packageMemberships[0]['membershipCodeId'] ?? null,
                        'membership_type_id' => $membershipTypeId,
                        'package_id'         => $package->id,
                        'document_paths'     => $storedDocumentPaths,
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

        if (empty($packageMemberships) || !is_array($packageMemberships)) {
            return;
        }

        $locationInfo = Locations::find($data['location_id']);

        foreach ($packageMemberships as $membership) {
            $packageBundleData = [
                'random_id'              => $package->random_id,
                'is_allocate'            => 1,
                'qty'                    => 1,
                'discount_name'          => $membership['DiscountName'] ?? null,
                'discount_type'          => $membership['Type'] ?? null,
                'discount_price'         => $membership['DiscountValue'] ?? 0,
                'service_price'          => str_replace(',', '', $membership['RegularPrice']),
                'net_amount'             => str_replace(',', '', $membership['RegularPrice']),
                'discount_id'            => null,
                'bundle_id'              => null,
                'membership_type_id'     => $membership['membershipId'] ?? null,
                'membership_code_id'     => $membership['membershipCodeId'] ?? null,
                'package_id'             => $package->id,
                'tax_exclusive_net_amount' => str_replace(',', '', $membership['Amount']),
                'tax_percenatage'        => $locationInfo->tax_percentage ?? 0,
                'tax_price'              => $membership['Tax'] ?? 0,
                'tax_including_price'    => str_replace(',', '', $membership['Total']),
                'location_id'            => $data['location_id'],
            ];

            $packageBundle = PackageBundles::create($packageBundleData);

            $soldBy = $membership['sold_by'] ?? $data['sold_by'] ?? null;
            $consumedAt = $isFullyPaid ? Filters::getCurrentTimeStamp() : null;

            PackageService::create([
                'random_id'          => $package->random_id,
                'package_id'         => $package->id,
                'package_bundle_id'  => $packageBundle->id,
                'service_id'         => null,
                'is_consumed'        => $isFullyPaid ? 1 : 0,
                'consumed_at'        => $consumedAt,
                'price'              => str_replace(',', '', $membership['RegularPrice']),
                'orignal_price'      => str_replace(',', '', $membership['RegularPrice']),
                'actual_price'       => str_replace(',', '', $membership['RegularPrice']),
                'is_exclusive'       => 0,
                'tax_exclusive_price' => str_replace(',', '', $membership['Amount']),
                'tax_percenatage'    => $locationInfo->tax_percentage ?? 0,
                'tax_price'          => $membership['Tax'] ?? 0,
                'tax_including_price' => str_replace(',', '', $membership['Total']),
                'sold_by'            => $soldBy,
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
        if (!$membershipRecord) {
            return;
        }

        if ($isFullyPaid) {
            $membershipType = MembershipType::find($membership['membershipId'] ?? $membershipRecord->membership_type_id);
            $durationDays = (int) ($membershipType->period ?? 365);

            $membershipRecord->update([
                'patient_id'  => $data['patient_id'],
                'start_date'  => now()->toDateString(),
                'end_date'    => now()->addDays($durationDays)->toDateString(),
                'assigned_at' => now()->toDateString(),
                'updated_by'  => Auth::id(),
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
            $calc = new PlanAppointmentCalculation();
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
        if (!isset($data['appointment_id'])) {
            return null;
        }

        $tagAppoint = explode('.', (string) $data['appointment_id']);

        if (($tagAppoint[1] ?? '') === 'A') {
            return (int) $tagAppoint[0];
        }

        $calc = new PlanAppointmentCalculation();
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
            'random_id'      => $data['random_id'],
            'patient_id'     => $data['patient_id'],
            'location_id'    => $data['location_id'],
            'total_price'    => $totalPrice,
            'sessioncount'   => '1',
            'account_id'     => Auth::user()->account_id,
            'is_exclusive'   => $data['is_exclusive'] ?? 0,
            'plan_type'      => $data['plan_type'] ?? 'plan',
            'appointment_id' => $appointmentId,
            'created_at'     => Filters::getCurrentTimeStamp(),
            'updated_at'     => Filters::getCurrentTimeStamp(),
        ]);

        $package->update(['name' => sprintf('%05d', $package->id)]);

        return $package;
    }

    // ── Payment Processing ──────────────────────────────

    private function handlePackagePayment(Packages $package, array $data, int|string $appointmentId): void
    {
        $packageAdvance = PackageAdvances::createRecord([
            'cash_flow'       => CashFlow::In->value,
            'cash_amount'     => $data['cash_amount'],
            'account_id'      => Auth::user()->account_id,
            'patient_id'      => $data['patient_id'],
            'payment_mode_id' => $data['payment_mode_id'],
            'created_by'      => Auth::id(),
            'updated_by'      => Auth::id(),
            'package_id'      => $package->id,
            'location_id'     => $data['location_id'],
            'created_at'      => Filters::getCurrentTimeStamp(),
            'updated_at'      => Filters::getCurrentTimeStamp(),
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
            'cash_flow'       => CashFlow::In->value,
            'cash_amount'     => $data['cash_amount'],
            'account_id'      => Auth::user()->account_id,
            'patient_id'      => $data['patient_id'],
            'payment_mode_id' => $data['payment_mode_id'],
            'created_by'      => Auth::id(),
            'updated_by'      => Auth::id(),
            'package_id'      => $package->id,
            'location_id'     => $data['location_id'],
            'created_at'      => Filters::getCurrentTimeStamp(),
            'updated_at'      => Filters::getCurrentTimeStamp(),
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
            'cash_flow'       => CashFlow::Out->value,
            'account_id'      => Auth::user()->account_id,
            'patient_id'      => $data['patient_id'],
            'payment_mode_id' => $settlePaymentModeId,
            'created_by'      => Auth::id(),
            'updated_by'      => Auth::id(),
            'package_id'      => $package->id,
            'location_id'     => $data['location_id'],
            'is_setteled'     => 0,
            'created_at'      => Filters::getCurrentTimeStamp(),
            'updated_at'      => Filters::getCurrentTimeStamp(),
        ];

        PackageAdvances::create(array_merge($baseData, [
            'cash_amount' => $taxExclusiveTotal,
            'is_tax'      => 0,
        ]));

        if ($taxTotal > 0) {
            PackageAdvances::create(array_merge($baseData, [
                'cash_amount' => $taxTotal,
                'is_tax'      => 1,
            ]));
        }
    }

    private function logPaymentActivity(Packages $package, array $data, string $action): void
    {
        $patient = User::find($data['patient_id']);
        $locationWithCity = Locations::with('city')->find($data['location_id']);
        $locationName = $locationWithCity
            ? (($locationWithCity->city?->name ?? '') . '-' . $locationWithCity->name)
            : '';

        $creatorName = Auth::user()->name ?? 'System';
        $description = '<span class="highlight">' . e($creatorName) . '</span> ' . $action . ' Rs. <span class="highlight-green">' . number_format((float) $data['cash_amount']) . '</span> from <span class="highlight-orange">' . e($patient->name) . '</span> for <span class="highlight-purple">Plan Id: ' . $package->id . '</span> in <span class="highlight">' . e($locationName) . '</span> ';

        Activity::create([
            'action'           => 'received',
            'activity_type'    => 'payment_received',
            'description'      => $description,
            'patient'          => $patient->name,
            'patient_id'       => $patient->id,
            'appointment_type' => 'Plan',
            'created_by'       => Auth::id(),
            'account_id'       => Auth::user()->account_id,
            'planId'           => $package->id,
            'amount'           => $data['cash_amount'],
            'location'         => $locationName,
            'centre_id'        => $data['location_id'],
            'created_at'       => Filters::getCurrentTimeStamp(),
            'updated_at'       => Filters::getCurrentTimeStamp(),
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

            if (!empty($membershipNames)) {
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
            ->orderBy('package_bundles.id')
            ->limit(2)
            ->selectRaw('COALESCE(services.name, bundles.name) as name')
            ->pluck('name')
            ->filter()
            ->values()
            ->toArray();

        $planName = !empty($names) ? implode(', ', $names) : '-';

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

        if (!$userVoucher) {
            return;
        }

        $originalAmount = $userVoucher->amount;
        $amountLeft = max(0, $userVoucher->amount - $service->price);
        $actualConsumed = $originalAmount - $amountLeft;

        $userVoucher->update(['amount' => $amountLeft]);

        $amountForVoucher = $amountLeft <= 0 ? $discountPrice : $service->price;

        PackageVouchers::create([
            'package_random_id' => $randomId,
            'voucher_id'        => $discount->id,
            'user_id'           => $userId,
            'amount'            => $amountForVoucher,
            'service_id'        => $serviceId,
            'main_service_id'   => $service->id,
        ]);

        if ($actualConsumed > 0) {
            $patient = User::find($userId);
            ActivityLogger::logVoucherConsumed($actualConsumed, $patient, $discount, $amountLeft);
        }
    }

    // ── Discount Data ───────────────────────────────────

    private function prepareDiscountData(?Discounts $discount, array $packageBundleData, array $data): array
    {
        if (empty($data['discount_id']) || $data['discount_id'] == '0') {
            return ['discount_name' => '-', 'discount_type' => '-', 'discount_price' => '0.00'];
        }

        return [
            'discount_name'  => $packageBundleData['discount_name'] ?? '-',
            'discount_type'  => $packageBundleData['discount_type'] ?? '-',
            'discount_price' => $packageBundleData['discount_price'] ?? '0.00',
        ];
    }

    // ── Appointment Conversion ──────────────────────────

    private function markAppointmentAsConvertedOptimized(int|string $appointmentId, int|string $packageId, float $paymentAmount): void
    {
        try {
            $appointment = Appointments::find($appointmentId);
            $package = Packages::find($packageId);

            if (!$appointment || !$package) {
                return;
            }

            $accountId = $appointment->account_id;

            $statuses = Cache::remember("appointment_statuses_{$accountId}", self::CACHE_TTL, fn () => [
                'arrived'   => AppointmentStatuses::where(['account_id' => $accountId, 'is_arrived' => 1])->first(),
                'converted' => AppointmentStatuses::where(['account_id' => $accountId, 'is_converted' => 1])->first(),
            ]);

            if (!$statuses['arrived'] || !$statuses['converted']) {
                return;
            }

            $latestArrivedConsultation = Appointments::where([
                'patient_id'                => $package->patient_id,
                'appointment_type_id'       => 1,
                'base_appointment_status_id' => $statuses['arrived']->id,
            ])
                ->whereNull('deleted_at')
                ->orderByDesc('scheduled_date')
                ->orderByDesc('id')
                ->first();

            if (!$latestArrivedConsultation) {
                return;
            }

            $consultationInvoice = DB::table('invoices')
                ->where('appointment_id', $latestArrivedConsultation->id)
                ->whereNull('deleted_at')
                ->orderBy('created_at')
                ->first();

            if (!$consultationInvoice) {
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

            if (!$serviceAfterInvoice) {
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
                'appointment_status_id'      => $statuses['converted']->id,
                'converted_at'               => now(),
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
            Log::error('Mark Appointment As Converted Error: ' . $e->getMessage());
        }
    }

    private function updateLeadStatusToConverted(object $appointment, int|string $accountId, mixed $location, mixed $service, float $paymentAmount): void
    {
        $lead = Leads::find($appointment->lead_id);
        if (!$lead) {
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
        if (!$appointment->lead_id) {
            return;
        }

        $lead = Leads::find($appointment->lead_id);
        if (!$lead) {
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
            $metaService = new MetaConversionApiService();
            $eventLeadId = $lead->meta_lead_id ?? 'apt_' . $appointment->id;

            $metaService->sendLeadStatus($lead->phone, 'converted', $eventLeadId, $lead->email, 'PKR', $paymentAmount);

            $appointment->update(['meta_purchase_sent' => 1]);
        } catch (\Throwable $e) {
            Log::error('Meta CAPI converted event failed: ' . $e->getMessage());
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

        if (!$membership) {
            $membership = Membership::with('membershiptype')
                ->where('patient_id', $patientId)
                ->orderByDesc('assigned_at')
                ->first();
        }

        if (!$membership) {
            return 'No Membership';
        }

        $status = $this->determineMembershipStatus($membership);
        $expiryFormatted = $membership->end_date ? date('M d, Y', strtotime($membership->end_date)) : '';

        if ($membership->is_referral == 1) {
            return "Ref: ({$membership->code})-{$status}" . ($expiryFormatted ? " (Exp: {$expiryFormatted})" : '');
        }

        $typeName = $membership->membershipType
            ? str_replace(' Membership', '', $membership->membershipType->name)
            : 'Gold';

        return "{$typeName} - {$membership->code} - {$status}" . ($expiryFormatted ? " (Exp: {$expiryFormatted})" : '');
    }

    private function getMembershipDisplayForPackage(int|string $patientId): string
    {
        $membership = Membership::with('membershiptype')
            ->where('patient_id', $patientId)
            ->orderByDesc('assigned_at')
            ->first();

        if (!$membership) {
            return 'No membership';
        }

        $status = $this->determineMembershipStatus($membership);

        return ($membership->membershipType?->name ?? 'Unknown') . " - {$status}";
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
            ->orderByRaw("CASE WHEN memberships.end_date >= ? AND memberships.active = 1 THEN 0 ELSE 1 END", [now()->format('Y-m-d')])
            ->orderByDesc('memberships.assigned_at')
            ->first();

        if (!$membership) {
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

        if (!$patient?->membership_id) {
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
            if ($advance->cash_flow === CashFlow::Out->value && !$advance->is_tax) {
                $advance->package_refund_price = number_format(
                    PackageAdvances::getAppointmentPackage(
                        (int) $advance->appointment_id,
                        (int) $advance->patient_id,
                        $advance->refund_note !== null ? (int) $advance->id : null,
                    ),
                );
            } elseif (!$advance->is_tax) {
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
        return !empty($data['cash_amount']) && $data['cash_amount'] != '0';
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
            if (!$apt->created_at) {
                continue;
            }

            $formattedDate = Carbon::parse($apt->created_at)->format('F d,Y h:i A');
            $serviceName = $apt->service_name ?? 'Consultation';

            $doctorName = $apt->doctor_name ?? '';
            if ($doctorName && !str_starts_with($doctorName, 'Dr ') && !str_starts_with($doctorName, 'Dr.')) {
                $doctorName = 'Dr ' . $doctorName;
            }

            $displayName = $serviceName . ' - ' . $formattedDate;
            if ($doctorName) {
                $displayName .= ' - ' . $doctorName;
            }

            $result[$apt->id] = [
                'id'        => $apt->id . '.A',
                'name'      => $displayName,
                'doctor_id' => $apt->doctor_id,
            ];
        }

        return $result;
    }
}
