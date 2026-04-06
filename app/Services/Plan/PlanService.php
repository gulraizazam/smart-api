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
use App\Models\DoctorHasLocations;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\PlanInvoice;
use App\Models\RoleHasUsers;
use App\Models\SMSLogs;
use App\Models\StudentVerification;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Models\UserOperatorSettings;
use App\Models\UserVouchers;
use App\Helpers\JazzSMSAPI;
use App\Helpers\TelenorSMSAPI;
use App\Services\Membership\StudentVerificationService;
use App\Services\MetaConversionApiService;
use App\Models\BaseDiscountService;
use App\Models\GetDiscountService;
use App\Models\DiscountHasLocations;
use App\Helpers\Widgets\DiscountWidget;
use App\Helpers\Widgets\LocationsWidget;
use Illuminate\Support\Collection as SupportCollection;
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
        if (empty($data['discount_id'])) {
            return ['success' => false, 'message' => 'No Record Found', 'status_code' => 404];
        }

        $discountIsVoucher = false;
        $serviceId = $data['service_id'];
        $patientId = $data['patient_id'] ?? null;
        $serviceData = Bundles::find($serviceId);

        $discountId = $data['discount_id'];
        $discountData = Discounts::find($discountId);

        if ($discountData->slug == 'custom') {
            return [
                'success' => true,
                'message' => 'custom',
                'data' => ['custom_checked' => 1],
            ];
        }

        $discountType = '';
        $discountPrice = 0;
        $netAmount = $serviceData->price;

        if ($discountData->type == Config::get('constants.Fixed') && $discountData->discount_type != 'voucher') {
            $discountType = Config::get('constants.Fixed');
            $discountPrice = $discountData->amount;
            $netAmount = ($serviceData->price) - ($discountData->amount);
        } elseif ($discountData->type == Config::get('constants.Percentage') && $discountData->discount_type != 'voucher') {
            $discountType = Config::get('constants.Percentage');
            $discountPrice = $discountData->amount;
            $discountPriceCal = $serviceData->price * (($discountPrice) / 100);
            $netAmount = ($serviceData->price) - ($discountPriceCal);
        } elseif ($discountData->type == 'Configurable' && $discountData->discount_type != 'voucher') {
            $discountType = 'Configurable';
            $discountPrice = $discountData->amount;
            $discountPriceCal = $serviceData->price * (($discountPrice) / 100);
            $netAmount = ($serviceData->price) - ($discountPriceCal);
        } elseif ($discountData->discount_type == 'voucher') {
            $patientVoucher = UserVouchers::where('user_id', $patientId)->where('voucher_id', $discountId)->first();
            if ($patientVoucher) {
                $discountType = Config::get('constants.Fixed');
                $discountPrice = $patientVoucher->amount;
                $discountIsVoucher = true;
                $netAmount = ($serviceData->price) - ($discountPrice);
                if ($netAmount < 0) {
                    $netAmount = 0;
                }
            } else {
                $discountType = '';
                $discountPrice = 0;
                $discountIsVoucher = false;
                $netAmount = $serviceData->price;
            }
        }

        return [
            'success' => true,
            'message' => 'Record Found',
            'data' => [
                'discount_type' => $netAmount < 0 ? '' : $discountType,
                'discount_price' => $discountPrice,
                'net_amount' => $netAmount < 0 ? $serviceData->price : $netAmount,
                'custom_checked' => 0,
                'discount_is_voucher' => $discountIsVoucher,
            ],
        ];
    }

    /**
     * Get custom discount info for a service/bundle.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}|false
     */
    public function getCustomDiscountInfo(array $data): array|false
    {
        $status = true;
        $serviceId = $data['service_id'];
        $serviceData = Bundles::find($serviceId);
        $discountId = $data['discount_id'];
        $discountData = Discounts::find($discountId);
        $discountValue = $data['discount_value'] ?? 0;
        $discountTypeInput = $data['discount_type'] ?? null;
        $patientId = $data['patient_id'] ?? null;

        if ($discountData->slug == 'custom') {
            // discount_id stays as is
        } else {
            if ($discountData->discount_type == 'voucher') {
                $voucherRecord = UserVouchers::where('user_id', $patientId)->where('voucher_id', $discountId)->first();
                $discountValue = $voucherRecord ? $voucherRecord->amount : 0;
            } else {
                $discountValue = $discountData->amount;
            }
        }

        $discountPrice = 0;
        $netAmount = $serviceData->price;

        if ($discountData->type == 'Fixed' && $discountData->discount_type != 'voucher') {
            if ($discountTypeInput == Config::get('constants.Fixed')) {
                if ($discountValue > $discountData->amount || $discountValue > $serviceData->price) {
                    return false;
                }
                $discountPrice = $discountValue;
                $netAmount = ($serviceData->price) - ($discountPrice);
            } else {
                $discountPrice = $discountValue;
                $discountPriceCal = ($discountData->amount / $serviceData->price) * 100;
                if ($discountValue > $discountPriceCal) {
                    $status = false;
                }
                $amountAfterPer = ($discountValue / 100) * $serviceData->price;
                $netAmount = $serviceData->price - $amountAfterPer;
            }
        } elseif ($discountData->type == 'Fixed' && $discountData->discount_type == 'voucher') {
            $voucherRecord = UserVouchers::where('user_id', $patientId)->where('voucher_id', $discountId)->first();
            if ($voucherRecord) {
                $discountPrice = $voucherRecord->amount;
                $netAmount = ($serviceData->price) - ($discountPrice);
                if ($netAmount < 0) {
                    $netAmount = 0;
                }
            } else {
                $discountPrice = 0;
                $netAmount = $serviceData->price;
            }
        } elseif ($discountData->type == 'Percentage' && $discountData->discount_type == 'voucher') {
            $voucherRecord = UserVouchers::where('user_id', $patientId)->where('voucher_id', $discountId)->first();
            if ($voucherRecord) {
                $discountPrice = $voucherRecord->amount;
                $discountPriceInPercentage = ($discountPrice / 100) * $serviceData->price;
                $netAmount = ($serviceData->price) - ($discountPriceInPercentage);
                if ($netAmount < 0) {
                    $netAmount = 0;
                }
            } else {
                $discountPrice = 0;
                $netAmount = $serviceData->price;
            }
        } else {
            if ($discountTypeInput == Config::get('constants.Fixed')) {
                $discountPrice = $discountValue;
                $discountPriceInPercentage = ($discountPrice / $serviceData->price) * 100;
                if ($discountData->discount_type != 'voucher' && $discountPriceInPercentage > $discountData->amount) {
                    return false;
                }
                $netAmount = ($serviceData->price) - ($discountValue);
            } else {
                if ($discountData->discount_type != 'voucher' && $discountValue > $discountData->amount) {
                    return false;
                }
                $discountPrice = $discountValue;
                $discountPriceInPercentage = ($discountValue / 100) * $serviceData->price;
                $netAmount = ($serviceData->price) - ($discountPriceInPercentage);
            }
        }

        if ($status) {
            return [
                'success' => true,
                'message' => 'Net Amount',
                'data' => ['net_amount' => $netAmount < 0 ? 0 : $netAmount],
            ];
        }

        return ['success' => false, 'message' => 'Net Amount', 'status_code' => 404];
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
        PackageBundles::find($id)->forcedelete();

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
        $id = $data['id'];

        // Block if ANY service in the config group is consumed
        $hasConsumedInGroup = PackageService::where([
            ['base_service_id', '=', $id],
            ['is_consumed', '=', '1'],
        ])->exists();

        if ($hasConsumedInGroup) {
            return ['success' => false, 'message' => 'Cannot delete. A service in this configurable discount group has been consumed.', 'status_code' => 404, 'data' => ['del' => 1]];
        }

        $packageService = PackageBundles::where('base_service_id', $id)->first();

        $packageTotal = $data['package_total'] ?? '';
        if ($packageTotal == '') {
            $packageTotal = 0;
        }
        $packageTotal = str_replace(',', '', (string) $packageTotal);

        $total = $packageTotal - $packageService->tax_including_price;

        PackageService::where('base_service_id', '=', $id)->delete();
        PackageBundles::where('base_service_id', $id)->forcedelete();

        $updateStatus = $data['update_status'] ?? 0;
        if ($updateStatus == 1) {
            if ($packageService->package_id) {
                Packages::where('id', $packageService->package_id)->update(['total_price' => $total]);
            }
        }

        // Update plan_name after configurable service removal
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
            ],
        ];
    }

    /**
     * Delete exclusive package service by random_id.
     *
     * @return array{success: bool, status: bool}
     */
    public function deleteExclusiveService(array $data): array
    {
        if (!empty($data['random_id'])) {
            PackageService::where('random_id', '=', $data['random_id'])->forcedelete();
            PackageBundles::where('random_id', '=', $data['random_id'])->forcedelete();

            return ['success' => true, 'status' => true];
        }

        return ['success' => false, 'status' => false];
    }

    // ──────────────────────────────────────────────────
    //  Bundle & Status
    // ──────────────────────────────────────────────────

    /**
     * Update bundle payment for a package.
     *
     * @return array{success: bool, message: string}
     * @throws \Exception
     */
    public function updateBundlePayment(array $data): array
    {
        $package = Packages::findOrFail($data['package_id']);

        // Handle payment if provided
        if (!empty($data['payment_mode_id']) && ($data['cash_amount'] ?? 0) > 0) {
            // Only update appointment_id and updated_at when payment is added
            $package->appointment_id = $data['appointment_id'];
            $package->save();

            $packageAdvance = new PackageAdvances();
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
        $serviceData = Bundles::where('id', '=', $bundleId)->first();

        if ($serviceData) {
            return [
                'success' => true,
                'message' => 'Records found',
                'data' => ['net_amount' => $serviceData->price],
            ];
        }

        return ['success' => false, 'message' => 'No record found', 'status_code' => 404];
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

            if (!empty($membershipNames)) {
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

        $planName = !empty($names) ? implode(', ', $names) : '-';

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
     * @param array $data Keys: bundle_id, location_id, net_amount, random_id, sold_by
     * @return array Structured response data with servicesData
     */
    public function addBundleService(array $data): array
    {
        $bundle = Bundles::find($data['bundle_id']);

        if (!$bundle) {
            throw new PlanException('Bundle not found');
        }

        $locationInfo = Locations::find($data['location_id']);

        if (!$locationInfo) {
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
            'tax_percenatage' => $taxPct,
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
            'service_price'    => $bs->calculated_price,
            'calculated_price' => $bs->calculated_price,
            'service_id'       => $bs->service_id,
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
                'random_id'              => $data['random_id'],
                'qty'                    => 1,
                'bundle_id'              => $bundle->id,
                'source_type'            => 'bundle',
                'discount_name'          => '-',
                'discount_type'          => '-',
                'discount_price'         => 0,
                'service_price'          => $bundle->price,
                'net_amount'             => $netAmount,
                'is_exclusive'           => 0,
                'tax_exclusive_net_amount' => $bundleData['tax_exclusive_net_amount'],
                'tax_percenatage'        => $taxPct,
                'tax_price'              => $bundleData['tax_price'],
                'tax_including_price'    => $bundleData['tax_including_price'],
                'location_id'            => $data['location_id'],
                'package_id'             => $findPackage->id,
                'is_allocate'            => 1,
            ]);
            $packageBundleRecordId = $packageBundleRecord->id;
        }

        $soldBy = $data['sold_by'] ?? null;
        $packageServicesData = [];

        foreach ($calculatedServicesPrices as $calculatedService) {
            $serviceInfo = $servicesInfo->get($calculatedService['service_id']);

            if (!$serviceInfo) {
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
                    'random_id'          => $data['random_id'],
                    'package_id'         => $findPackage->id,
                    'package_bundle_id'  => $packageBundleRecordId,
                    'service_id'         => $calculatedService['service_id'],
                    'price'              => $calculatedService['calculated_price'],
                    'orignal_price'      => $calculatedService['service_price'],
                    'actual_price'       => $serviceInfo->price,
                    'is_exclusive'       => $isExclusive ? 1 : 0,
                    'tax_exclusive_price' => $taxExclusivePrice,
                    'tax_percenatage'    => $taxPct,
                    'tax_price'          => $taxPrice,
                    'tax_including_price' => $taxIncludingPrice,
                    'sold_by'            => $soldBy,
                    'created_at'         => Filters::getCurrentTimeStamp(),
                    'updated_at'         => Filters::getCurrentTimeStamp(),
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
                ]),
                'packageServicesData' => $packageServicesData,
            ],
        ];
    }

    /**
     * Add a membership service to a plan.
     *
     * @param array $data Keys: membership_id, location_id, net_amount, sold_by
     * @return array Structured response data with servicesData
     */
    public function addMembershipService(array $data): array
    {
        $membershipTypeId = $data['membership_id'];
        $membershipType = MembershipType::find($membershipTypeId);

        if (!$membershipType) {
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
            'tax_percenatage' => $taxPercentage,
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
     * @param int $locationId Location ID (currently unused but kept for future filtering)
     * @return array Contains 'bundles' key with collection of bundles
     */
    public function getBundlesByLocation(int $locationId): array
    {
        $accountId = Auth::user()->account_id;

        $bundles = Bundles::where('account_id', $accountId)
            ->where('active', 1)
            ->whereDate('start', '<=', now())
            ->whereDate('end', '>=', now())
            ->select('id', 'name', 'price')
            ->orderBy('name', 'asc')
            ->get();

        if ($bundles->isEmpty()) {
            return ['bundles' => []];
        }

        return ['bundles' => $bundles];
    }

    /**
     * Get membership types available for a location/patient, including renewal types for expired memberships.
     *
     * @param int $locationId Location ID
     * @param int|null $patientId Optional patient ID to check for expired membership renewals
     * @return array Contains 'memberships' and 'expired_membership_type_id' keys
     */
    public function getMembershipTypes(int $locationId, ?int $patientId = null): array
    {
        $expiredMembershipTypeId = null;

        // Check if patient's latest membership is expired and get its type
        if ($patientId) {
            $latestMembership = Membership::where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestMembership && $latestMembership->end_date < now()->format('Y-m-d')) {
                $expiredType = MembershipType::find($latestMembership->membership_type_id);
                if ($expiredType) {
                    $expiredMembershipTypeId = $expiredType->parent_id ?? $expiredType->id;
                }
            }
        }

        // Get all parent membership types (always show these)
        $parentMemberships = MembershipType::where('active', 1)
            ->whereNull('parent_id')
            ->select('id', 'name', 'amount as price', 'parent_id')
            ->orderBy('name', 'asc')
            ->get();

        $memberships = $parentMemberships;

        // If patient has an expired membership, add ONLY the renewal for that specific type
        if ($expiredMembershipTypeId) {
            $renewalMembership = MembershipType::where('active', 1)
                ->where('parent_id', $expiredMembershipTypeId)
                ->select('id', 'name', 'amount as price', 'parent_id')
                ->first();

            if ($renewalMembership) {
                $memberships = $parentMemberships->push($renewalMembership)->sortBy('name')->values();
            }
        }

        if ($memberships->isEmpty()) {
            return ['memberships' => [], 'expired_membership_type_id' => null];
        }

        return [
            'memberships' => $memberships,
            'expired_membership_type_id' => $expiredMembershipTypeId,
        ];
    }

    /**
     * Get membership type info (price and name).
     *
     * @param int $membershipTypeId The membership type ID
     * @return array Contains 'net_amount' and 'membership_name' keys
     */
    public function getMembershipInfo(int $membershipTypeId): array
    {
        $membership = MembershipType::where('id', $membershipTypeId)
            ->where('active', 1)
            ->first();

        if (!$membership) {
            throw new PlanException('Membership not found');
        }

        return [
            'net_amount' => (float) $membership->amount,
            'membership_name' => $membership->name,
        ];
    }

    /**
     * Search membership codes by keyword, optionally filtered by membership type.
     *
     * @param string $query Search query string
     * @param int|null $membershipTypeId Optional membership type ID to filter by
     * @return array Contains 'codes' key with matching membership codes
     */
    public function searchMembershipCodes(string $query, ?int $membershipTypeId = null): array
    {
        if (strlen($query) < 2) {
            return ['codes' => []];
        }

        $dbQuery = Membership::where('code', 'like', '%' . $query . '%')
            ->where('active', 1)
            ->whereNull('patient_id');

        if ($membershipTypeId) {
            $membershipType = MembershipType::find($membershipTypeId);

            if ($membershipType && $membershipType->parent_id) {
                $dbQuery->where(function ($q) use ($membershipTypeId, $membershipType) {
                    $q->where('membership_type_id', $membershipTypeId)
                      ->orWhere('membership_type_id', $membershipType->parent_id);
                });
            } else {
                $dbQuery->where('membership_type_id', $membershipTypeId);
            }
        }

        $codes = $dbQuery->select('id', 'code', 'patient_id', 'membership_type_id')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'is_assigned' => !empty($item->patient_id),
                    'patient_id' => $item->patient_id,
                    'membership_type_id' => $item->membership_type_id,
                ];
            });

        return ['codes' => $codes];
    }

    /**
     * Calculate grand total for plan creation.
     *
     * @param string $total The total amount (may contain commas)
     * @param float $cashAmount The cash amount to subtract
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
     * @param string $randomId The package random ID
     * @param string $total The total amount (may contain commas)
     * @param float $cashAmount The cash amount to subtract
     * @return array Contains 'grand_total' key with formatted result
     */
    public function calculateGrandTotalForUpdate(string $randomId, string $total, float $cashAmount): array
    {
        $package = Packages::where('random_id', '=', $randomId)->first();

        if (!$package) {
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
        $oldAmount = $packageAdvanceBefore ? $packageAdvanceBefore->cash_amount : 0;
        $oldDate = $packageAdvanceBefore ? $packageAdvanceBefore->created_at : null;

        $record = PackageAdvances::updateRecordFinanceedit(
            (object) $data,
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
                    'created_at' => $data['created_at'] . ' ' . Carbon::now()->toTimeString(),
                    'updated_at' => now(),
                ]);
            }

            // Log payment updated activity
            $package = Packages::find($data['package_id']);
            $patient = $package ? User::find($package->patient_id) : null;
            $location = $package ? Locations::with('city')->find($package->location_id) : null;
            $newAmount = $data['cash_amount'];
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
            $record = PackageAdvances::deletefinaceRecord((object) $data);
            $cashReceiveRemain = number_format(filter_var($data['cash_receveive_remain'], FILTER_SANITIZE_NUMBER_INT) + $packageadvanceinfo->cash_amount);

            // Sync plan_invoices table - soft delete the corresponding plan_invoice
            $planInvoice = PlanInvoice::where('package_advance_id', $data['package_advance_id'])->first();
            if ($planInvoice) {
                $planInvoice->delete();
            }

            // Log payment deleted activity
            $package = Packages::find($packageadvanceinfo->package_id);
            $patient = $package ? User::find($package->patient_id) : null;
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

            if (!$packageService) {
                return ['success' => false, 'message' => 'Package service not found'];
            }

            $package = Packages::find($packageService->package_id);
            $locationId = $locationId ?: $package->location_id;
            $currentSoldBy = $packageService->sold_by;
            $packageServices = collect([$packageService]);
        } elseif ($bundleId) {
            $packageBundle = PackageBundles::find($bundleId);

            if (!$packageBundle) {
                return ['success' => false, 'message' => 'Package bundle not found'];
            }

            $package = Packages::find($packageBundle->package_id);
            $locationId = $locationId ?: $package->location_id;

            $bundleIds = !empty($configBundleIds) ? $configBundleIds : [$packageBundle->id];

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
            if (!array_key_exists($doctorId, $usersToShow)) {
                $usersToShow[$doctorId] = $doctorName;
            }
        }

        // Add all active FDM users from the location
        if (!empty($fdmUserIds)) {
            $FDMUsers = User::whereIn('id', $fdmUserIds)
                ->where('active', 1)
                ->pluck('name', 'id')
                ->toArray();

            foreach ($FDMUsers as $fdmId => $fdmName) {
                if (!array_key_exists($fdmId, $usersToShow)) {
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
                'package_services' => $packageServices->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'sold_by' => $service->sold_by,
                    ];
                }),
            ],
        ];
    }

    /**
     * Update sold by for package service(s).
     */
    public function updateSoldBy(array $data): array
    {
        // If package_services array is provided, update multiple services
        if (!empty($data['package_services']) && is_array($data['package_services'])) {
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
        if (!empty($data['package_service_id'])) {
            $packageService = PackageService::find($data['package_service_id']);

            if (!$packageService) {
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

        if (!$package) {
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
            SMSLogs::find($smsLogId)->update(['status' => 1]);
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
        $returnTaxAmount = '';

        $packageInformation = Packages::find($packageId);
        $patient = User::whereId($packageInformation->patient_id)->first();

        /* calculation for back date refund entry */
        $packageAdvanceLastIn = PackageAdvances::where([
            ['cash_flow', '=', 'in'],
            ['is_setteled', '=', '0'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $packageInformation->id],
        ])->orderBy('created_at', 'desc')->first();

        $dateBackend = date('Y-m-d', strtotime($packageAdvanceLastIn->created_at));
        $bundleInformation = PackageBundles::where('package_id', '=', $packageId)->first();
        $taxPercentage = $bundleInformation->tax_percenatage ?? '';
        $isAdjustmentAmount = 0;

        $packageIsRefundedAmount = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');

        $packageIsSetteled = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_setteled', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');

        $amountToRefund = $packageIsRefundedAmount + $packageIsSetteled;

        /* Document charges */
        $documentationcharges = Settings::where('slug', '=', 'sys-documentationcharges')->first();

        $packageCashReceive = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');

        $packageRefundedAmount = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_refund', '=', '1'],
            ['cash_amount', '>', '0'],
        ])->latest()->first();

        $latestPackageRefundedAmount = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_refund', '=', '1'],
        ])->latest()->first();

        $packageSetteledAmount = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '1'],
        ])->sum('cash_amount');

        $refundableAmount = 0;
        $cosumeAmountTax = 0;

        if ($packageCashReceive) {
            $packageServiceOriginalPriceConsumed = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('price');

            $cosumeAmountTax = 0;

            $refund1 = $packageServiceOriginalPriceConsumed + $cosumeAmountTax + $documentationcharges->data;
            $refundableAmount = ceil(($packageCashReceive - $refund1) - $amountToRefund);
        }

        if ($refundableAmount > 0) {
            $packageServicePriceConsumedTax = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('tax_including_price');

            $packageServicePriceConsumedWithoutTax = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('tax_exclusive_price');

            $givenTaxAmount = $packageServicePriceConsumedTax - $packageServicePriceConsumedWithoutTax;

            $returnTaxAmount = ($cosumeAmountTax - $givenTaxAmount);
            $calAdjustmentFinal = $packageServicePriceConsumedTax + ($packageCashReceive - $refund1);
            $isAdjustmentAmount = ceil(($packageCashReceive - $calAdjustmentFinal) - $returnTaxAmount);
            $returnTaxAmount = ceil($returnTaxAmount);
        }

        if ($refundableAmount < 0) {
            $refundableAmount = 0;
        }

        $packageIsAdjustmentAmount = PackageAdvances::where([
            'package_id' => $packageId,
            'cash_flow' => 'out',
            'is_adjustment' => '1',
        ])->sum('cash_amount');

        $document = $packageIsAdjustmentAmount == 0;

        $paymentmodes = PaymentModes::where('name', '!=', 'Settle Amount')->get()->pluck('name', 'id');

        return [
            'success' => true,
            'message' => 'Record found',
            'data' => [
                'id' => $packageId,
                'refundable_amount' => $refundableAmount,
                'cash_amount' => $packageCashReceive,
                'is_adjustment_amount' => $isAdjustmentAmount,
                'documentationcharges' => $documentationcharges,
                'document' => $document,
                'return_tax_amount' => $returnTaxAmount,
                'date_backend' => $dateBackend,
                'paymentmodes' => $paymentmodes,
                'refunded_amount' => $packageRefundedAmount->cash_amount,
                'record_id' => $packageRefundedAmount->id,
                'package_setteled_amount' => $packageSetteledAmount,
                'patient_name' => $patient->name,
                'patient_id' => $patient->id,
                'plan' => $packageInformation->name,
                'created_date' => $latestPackageRefundedAmount && $latestPackageRefundedAmount->created_at ? Carbon::parse($latestPackageRefundedAmount->created_at)->format('Y-m-d') : date('Y-m-d'),
                'refund_note' => $latestPackageRefundedAmount->refund_note ?? '',
                'payment_method_id' => $latestPackageRefundedAmount->payment_mode_id ?? 1,
            ],
        ];
    }

    /**
     * Process a refund update for a package.
     */
    public function processRefund(array $data): array
    {
        $latestRefund = PackageAdvances::where([
            ['package_id', '=', $data['package_id']],
            ['is_refund', '=', 1],
            ['cash_amount', '>', 0],
            ['is_tax', '=', 0],
        ])->latest()->first();

        // Check if case was previously settled (for activity logging)
        $wasPreviouslySettled = PackageAdvances::where([
            ['package_id', '=', $data['package_id']],
            ['cash_flow', '=', 'out'],
            ['is_setteled', '=', 1],
        ])->exists();

        if ($data['case_setteled'] == '1') {
            $packageCashReceive = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'in'],
                ['is_cancel', '=', '0'],
            ])->sum('cash_amount');

            $packageIsRefundedAmount = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '1'],
                ['is_tax', '=', '0'],
                ['is_setteled', '=', '0'],
            ])->sum('cash_amount');

            $packageIsConsumedAmount = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '0'],
                ['is_tax', '=', '0'],
                ['is_setteled', '=', '0'],
                ['is_adjustment', '=', '0'],
            ])->sum('cash_amount');

            $packageIsConsumedTaxAmount = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '0'],
                ['is_tax', '=', '1'],
                ['is_setteled', '=', '0'],
            ])->sum('cash_amount');

            $consumedAmountWithTax = $packageIsConsumedAmount + $packageIsConsumedTaxAmount;

            $packageIsRefundedAmount = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '1'],
                ['is_tax', '=', '0'],
            ])->sum('cash_amount');

            $amountAfterRefund = $consumedAmountWithTax + $packageIsRefundedAmount;
            $amountLeft = $packageCashReceive - $amountAfterRefund;
            $packageinformation = Packages::find($data['package_id']);
            $findDoc = Appointments::where('id', $packageinformation->appointment_id)->first();

            if ($amountLeft > 0) {
                $dataAdjustment = [
                    'cash_flow' => 'out',
                    'cash_amount' => $amountLeft,
                    'is_adjustment' => '0',
                    'is_setteled' => 1,
                    'patient_id' => $packageinformation->patient_id,
                    'payment_mode_id' => $data['payment_mode_id'],
                    'account_id' => Auth::user()->account_id,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'package_id' => $data['package_id'],
                    'location_id' => $packageinformation->location_id,
                    'appointment_id' => $packageinformation->appointment_id,
                    'created_at' => $data['created_at'] . ' ' . Carbon::now()->toTimeString(),
                    'updated_at' => $data['created_at'] . ' ' . Carbon::now()->toTimeString(),
                ];

                PackageAdvances::create($dataAdjustment);
                $services = Services::where('name', 'Refund Settelment')->first();

                $dataInvoice = [
                    'total_price' => $amountLeft,
                    'account_id' => Auth::user()->account_id,
                    'patient_id' => $packageinformation->patient_id,
                    'appointment_id' => $packageinformation->appointment_id,
                    'invoice_status_id' => 3,
                    'created_by' => Auth::id(),
                    'location_id' => $packageinformation->location_id,
                    'doctor_id' => $findDoc->doctor_id,
                    'active' => 1,
                    'is_exclusive' => 0,
                    'is_settlement' => 1,
                    'package_id' => $data['package_id'],
                ];
                $createInvoice = Invoices::create($dataInvoice);

                $dataInvoiceDetail = [
                    'qty' => 1,
                    'service_id' => $services->id,
                    'package_id' => $data['package_id'],
                    'invoice_id' => $createInvoice->id,
                    'is_settlement' => 1,
                ];
                InvoiceDetails::create($dataInvoiceDetail);
            } else {
                $latestRefund->where('id', $data['record_id'])->update(['is_setteled' => 1]);
            }
        } else {
            // Handle unchecked case - remove settlement status
            $latestRefund->where('id', $data['record_id'])->update(['is_setteled' => 0]);

            // Delete settlement records for this package
            PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_setteled', '=', 1],
            ])->delete();

            $findInvoice = Invoices::where('package_id', $data['package_id'])->where('is_settlement', 1)->first();
            if ($findInvoice) {
                $findInvoiceDetails = InvoiceDetails::where('invoice_id', $findInvoice->id)->where('is_settlement', 1)->first();
                if ($findInvoiceDetails) {
                    $findInvoiceDetails->delete();
                }
                $findInvoice->delete();
            }
        }

        $latestRefund->where('id', $data['record_id'])->update([
            'created_at' => $data['created_at'] . ' ' . Carbon::now()->toTimeString(),
            'cash_amount' => $data['refund_amount'],
            'payment_mode_id' => $data['payment_mode_id'],
            'refund_note' => $data['refund_note'],
        ]);

        // Log refund update activity
        $packageInfo = Packages::find($data['package_id']);
        $patient = User::find($packageInfo->patient_id);
        $location = Locations::find($packageInfo->location_id);

        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $locationName = $location->name ?? '';
        $refundAmount = $data['refund_amount'];
        $refundDate = $data['created_at'] ? date('M j, Y', strtotime($data['created_at'])) : date('M j, Y');
        $caseSetteled = $data['case_setteled'] == '1';

        $description = '<span class="highlight">' . $creatorName . '</span> updated refund <span class="highlight-green">Rs. ' . number_format($refundAmount) . '</span> for <span class="highlight-orange">' . $patientName . '</span> in <span class="highlight-purple">Plan #' . sprintf('%05d', $data['package_id']) . '</span>' . ($locationName ? ' at <span class="highlight">' . $locationName . '</span>' : '') . ' on <span class="highlight-purple">' . $refundDate . '</span>';

        if ($caseSetteled) {
            $description .= ' - <span class="highlight-green">Case Settled</span>';
        } elseif ($wasPreviouslySettled && !$caseSetteled) {
            $description .= ' - <span class="highlight-orange">Case Unsettled</span>';
        }

        $activity = new Activity();
        $activity->timestamps = false;
        $activity->action = 'refund_updated';
        $activity->activity_type = 'refund_updated';
        $activity->description = $description;
        $activity->patient = $patientName;
        $activity->patient_id = $patient->id ?? null;
        $activity->appointment_type = 'Plan';
        $activity->created_by = Auth::id();
        $activity->planId = $data['package_id'];
        $activity->amount = $refundAmount;
        $activity->location = $locationName;
        $activity->centre_id = $packageInfo->location_id;
        $activity->account_id = Auth::user()->account_id;
        $activity->created_at = Filters::getCurrentTimeStamp();
        $activity->updated_at = Filters::getCurrentTimeStamp();
        $activity->save();

        return ['success' => true, 'message' => 'Record updated', 'data' => []];
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
        $servicesIds = $data['package_bundles'];
        $randomId = $data['random_id'];

        $vouchers = PackageVouchers::where('package_random_id', $randomId)
            ->whereIn('service_id', $servicesIds)
            ->get();

        $voucherAmounts = [];
        foreach ($vouchers as $voucher) {
            $key = $voucher->user_id . '_' . $voucher->voucher_id;
            $voucherAmounts[$key]['user_id'] = $voucher->user_id;
            $voucherAmounts[$key]['voucher_id'] = $voucher->voucher_id;
            $voucherAmounts[$key]['amount'] = ($voucherAmounts[$key]['amount'] ?? 0) + $voucher->amount;
        }

        // Update user vouchers and log activity
        foreach ($voucherAmounts as $item) {
            UserVouchers::where('user_id', $item['user_id'])
                ->where('voucher_id', $item['voucher_id'])
                ->increment('amount', $item['amount']);

            $patient = User::find($item['user_id']);
            $voucherModel = Discounts::find($item['voucher_id']);
            if ($patient && $voucherModel) {
                ActivityLogger::logVoucherRefunded($item['amount'], $patient, $voucherModel);
            }
        }

        // Delete package vouchers
        PackageVouchers::where('package_random_id', $randomId)
            ->whereIn('service_id', $servicesIds)
            ->delete();

        return ['success' => true, 'message' => 'Vouchers reset successfully'];
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
        $discounts = SupportCollection::make();
        $today = Carbon::now()->toDateString();

        $userRoleIds = Auth::user()->user_roles()->pluck('role_id')->toArray();
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        $patientActiveMembership = null;
        $patientMembershipTypeId = null;

        if ($data['patient_id'] ?? null) {
            $patientActiveMembership = Membership::where('patient_id', $data['patient_id'])
                ->where('active', 1)
                ->whereDate('end_date', '>=', $today)
                ->orderBy('assigned_at', 'desc')
                ->first();

            if ($patientActiveMembership) {
                $patientMembershipTypeId = $patientActiveMembership->membership_type_id;
            }
        }

        $bundle = Bundles::find($data['bundle_id'] ?? null);

        if ($bundle && $bundle->type == 'single') {

            $bundleService = BundleHasServices::where([
                'bundle_id' => $bundle->id,
            ])->first();

            $service_id = $bundleService->service_id;

            $location_id = $data['location_id'];

            $discountIds = DiscountWidget::loadPlanDsicountByLocationService($location_id, $service_id, Auth::User()->account_id);

            $generalDiscountsQuery = Discounts::whereIn('id', $discountIds)
                ->where('discount_type', '!=', 'voucher')
                ->where('active', '=', '1')
                ->whereDate('start', '<=', $today)
                ->whereDate('end', '>=', $today);

            if (!$isSuperAdmin) {
                $generalDiscountsQuery->whereHas('roles', function($query) use ($userRoleIds) {
                    $query->whereIn('role_id', $userRoleIds);
                });
            }

            if ($patientActiveMembership && $patientMembershipTypeId) {
                $generalDiscountsQuery->where(function($query) use ($patientMembershipTypeId) {
                    $query->where('customer_type_id', $patientMembershipTypeId)
                          ->orWhereNull('customer_type_id');
                });
            } else {
                $generalDiscountsQuery->whereNull('customer_type_id');
            }

            $generalDiscounts = $generalDiscountsQuery->get();

            $voucherDiscounts = SupportCollection::make();
            $checkUserVouchers = UserVouchers::where('user_id', $data['patient_id'])
                ->pluck('voucher_id')
                ->toArray();

            if ($checkUserVouchers) {
                $voucherDiscountsQuery = Discounts::whereIn('id', $discountIds)
                    ->whereIn('id', $checkUserVouchers)
                    ->where('discount_type', '=', 'voucher');

                $voucherDiscounts = $voucherDiscountsQuery->get();
            }

            $discounts = $generalDiscounts->merge($voucherDiscounts);

        } else {

            if ($bundle && $bundle->apply_discount == '1') {
                $bundleServices = BundleHasServices::where([
                    'bundle_id' => $bundle->id,
                ])->get();
                $discountIds = [];
                foreach ($bundleServices as $bundleService) {
                    $service_id = $bundleService->service_id;
                    $location_id = $data['location_id'];
                    $discountIds[] = DiscountWidget::loadPlanDsicountByLocationService($location_id, $service_id, Auth::User()->account_id);
                }
                $uniq_array = [];
                foreach ($discountIds as $discountId) {
                    foreach ($discountId as $singledata) {
                        if (!in_array($singledata, $uniq_array)) {
                            $uniq_array[] = $singledata;
                        }
                    }
                }
                $generalDiscountsQuery = Discounts::whereIn('id', $uniq_array)
                    ->where('discount_type', '!=', 'voucher')
                    ->where('active', '=', '1')
                    ->whereDate('start', '<=', $today)
                    ->whereDate('end', '>=', $today);

                if (!$isSuperAdmin) {
                    $generalDiscountsQuery->whereHas('roles', function($query) use ($userRoleIds) {
                        $query->whereIn('role_id', $userRoleIds);
                    });
                }

                if ($patientActiveMembership && !empty($membershipDiscountIds)) {
                    $generalDiscountsQuery->where(function($query) use ($membershipDiscountIds, $allMembershipLinkedDiscountIds) {
                        $query->whereIn('id', $membershipDiscountIds)
                              ->orWhereNotIn('id', $allMembershipLinkedDiscountIds);
                    });
                } elseif (!empty($allMembershipLinkedDiscountIds)) {
                    $generalDiscountsQuery->whereNotIn('id', $allMembershipLinkedDiscountIds);
                }

                $generalDiscounts = $generalDiscountsQuery->get();

                $voucherDiscounts = SupportCollection::make();
                $checkUserVouchers = UserVouchers::where('user_id', $data['patient_id'])
                    ->pluck('voucher_id')
                    ->toArray();

                if ($checkUserVouchers) {
                    $voucherDiscountsQuery = Discounts::whereIn('id', $uniq_array)
                        ->whereIn('id', $checkUserVouchers)
                        ->where('discount_type', '=', 'voucher');

                    $voucherDiscounts = $voucherDiscountsQuery->get();
                }

                $discounts = $generalDiscounts->merge($voucherDiscounts);

            }
        }

        $temp_discounts = [];

        foreach ($discounts as $key => $discount) {

            if ($discount->slug == 'birthday') {
                $pre_days = $discount->pre_days;
                $post_days = $discount->post_days;

                $today_1 = Carbon::today();
                $today_2 = Carbon::today();
                $today_3 = Carbon::today();

                $predate = $today_1->subDay($pre_days)->format('Y-m-d');
                $postdate = $today_2->addDay($post_days)->format('Y-m-d');

                $patient_info = User::find($data['patient_id']);

                if ($patient_info->dob) {

                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year . '-' . 'm-d');

                    if (($patientbirthday >= $predate) && ($patientbirthday <= $postdate)) {
                    } else {
                        $discounts->forget($key);
                    }
                } else {
                    $discounts->forget($key);
                }
            }
        }

        $Discount_array = [];

        if (count($discounts) > 0) {
            $service_data = Bundles::where('id', '=', $data['bundle_id'])->first();
            if ($service_data) {
                foreach ($discounts as $discount) {
                    if ($discount->slug != 'custom') {
                        if ($discount->type == Config::get('constants.Fixed')) {

                            $discount_type = $discount->type;
                            $discount_price = $discount->amount;
                            $net_amount = ($service_data->price) - ($discount_price);
                            $Discount_array[$discount->id] = [
                                'id' => $discount->id,
                                'discount_type' => $discount_type,
                                'discount_price' => $discount_price,
                                'net_amount' => $net_amount,
                            ];
                        } else {
                            $discount_type = $discount->type;
                            $discount_price = $discount->amount;
                            $discount_price_cal = $service_data->price * (($discount_price) / 100);
                            $net_amount = ($service_data->price) - ($discount_price_cal);
                            $Discount_array[$discount->id] = [
                                'id' => $discount->id,
                                'discount_type' => $discount_type,
                                'discount_price' => $discount_price,
                                'net_amount' => $net_amount,
                            ];
                        }
                    }
                }

                $select_discount = [];
                $lowest = false;
                if (count($Discount_array) > 0) {
                    foreach ($Discount_array as $value) {
                        if ($lowest === false || $value['net_amount'] < $lowest) {
                            $lowest = $value['net_amount'];
                            $select_discount = $value;
                        }
                    }
                    $discounts = $discounts->toArray();
                    $service_data = Bundles::where('id', '=', $data['bundle_id'])->first();

                    $net_amount = $service_data->price;
                    if ($service_data->type == 'single') {
                        $bundleService = BundleHasServices::where('bundle_id', $service_data->id)->first();
                        if ($bundleService) {
                            $actualService = Services::find($bundleService->service_id);
                            if ($actualService) {
                                $net_amount = $actualService->price;
                            }
                        }
                    }

                    return [
                        'success' => true,
                        'message' => 'Records found.',
                        'data' => [
                            'discounts' => $discounts,
                            'checked_custom' => '0',
                            'dis_price_info' => $select_discount,
                            'net_amount' => $net_amount,
                        ],
                    ];
                } else {
                    $discounts = $discounts->toArray();
                    $service_data = Bundles::where('id', '=', $data['bundle_id'])->first();

                    $net_amount = $service_data->price;
                    if ($service_data->type == 'single') {
                        $bundleService = BundleHasServices::where('bundle_id', $service_data->id)->first();
                        if ($bundleService) {
                            $actualService = Services::find($bundleService->service_id);
                            if ($actualService) {
                                $net_amount = $actualService->price;
                            }
                        }
                    }

                    return [
                        'success' => true,
                        'message' => 'Records found.',
                        'data' => [
                            'discounts' => $discounts,
                            'checked_custom' => '1',
                            'net_amount' => $net_amount,
                        ],
                    ];
                }
            }

        }

        $net_amount = isset($bundle) ? $bundle->price : 0;
        if ($bundle && $bundle->type == 'single') {
            $bundleService = BundleHasServices::where('bundle_id', $bundle->id)->first();
            if ($bundleService) {
                $actualService = Services::find($bundleService->service_id);
                if ($actualService) {
                    $net_amount = $actualService->price;
                }
            }
        }

        return [
            'success' => false,
            'message' => 'Records found.',
            'status_code' => 404,
            'data' => [
                'net_amount' => $net_amount,
            ],
        ];
    }

    /**
     * Get service info for simple plans (non-bundle).
     * Extracted from PackagesController::getserviceinfo_for_plan().
     */
    public function getServiceInfoForPlan(array $data): array
    {
        $discounts = SupportCollection::make();
        $today = Carbon::now()->toDateString();
        $location_information = Locations::find($data['location_id']);

        $userRoleIds = Auth::user()->user_roles()->pluck('role_id')->toArray();
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        $patientActiveMembership = null;
        $patientMembershipTypeId = null;

        if ($data['patient_id'] ?? null) {
            $patientActiveMembership = Membership::where('patient_id', $data['patient_id'])
                ->where('active', 1)
                ->whereDate('end_date', '>=', $today)
                ->orderBy('assigned_at', 'desc')
                ->first();

            if ($patientActiveMembership) {
                $patientMembershipTypeId = $patientActiveMembership->membership_type_id;
            }
        }

        $service = Services::find($data['service_id'] ?? null);

        if (!$service) {
            return [
                'success' => false,
                'message' => 'Service not found.',
                'status_code' => 500,
            ];
        }

        $service_id = $service->id;
        $location_id = $data['location_id'];

        $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::User()->account_id);
        $discountIds = array_keys($allocations);

        $generalDiscountsQuery = Discounts::whereIn('id', $discountIds)
            ->where('discount_type', '!=', 'voucher')
            ->where('active', '=', '1')
            ->whereDate('start', '<=', $today)
            ->whereDate('end', '>=', $today);

        if (!$isSuperAdmin) {
            $generalDiscountsQuery->whereHas('roles', function($query) use ($userRoleIds) {
                $query->whereIn('role_id', $userRoleIds);
            });
        }

        if ($patientActiveMembership && $patientMembershipTypeId) {
            $generalDiscountsQuery->where(function($query) use ($patientMembershipTypeId) {
                $query->where('customer_type_id', $patientMembershipTypeId)
                      ->orWhereNull('customer_type_id');
            });
        } else {
            $generalDiscountsQuery->whereNull('customer_type_id');
        }

        $generalDiscounts = $generalDiscountsQuery->get();

        $voucherDiscounts = SupportCollection::make();
        $checkUserVouchers = UserVouchers::where('user_id', $data['patient_id'])
            ->pluck('voucher_id')
            ->toArray();

        if ($checkUserVouchers) {
            $voucherDiscountsQuery = Discounts::whereIn('id', $discountIds)
                ->whereIn('id', $checkUserVouchers)
                ->where('discount_type', '=', 'voucher');

            $voucherDiscounts = $voucherDiscountsQuery->get();
        }

        $discounts = $generalDiscounts->merge($voucherDiscounts);

        foreach ($discounts as $key => $discount) {
            if ($discount->slug == 'birthday') {
                $pre_days = $discount->pre_days;
                $post_days = $discount->post_days;

                $today_1 = Carbon::today();
                $today_2 = Carbon::today();
                $today_3 = Carbon::today();

                $predate = $today_1->subDay($pre_days)->format('Y-m-d');
                $postdate = $today_2->addDay($post_days)->format('Y-m-d');

                $patient_info = User::find($data['patient_id']);

                if ($patient_info->dob) {
                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year . '-' . 'm-d');

                    if (($patientbirthday >= $predate) && ($patientbirthday <= $postdate)) {
                        // Birthday is valid
                    } else {
                        $discounts->forget($key);
                    }
                } else {
                    $discounts->forget($key);
                }
            }
        }

        // Also load configurable discounts allocated to this location (slug='configurable')
        $configurableDiscountIds = DiscountHasLocations::where('location_id', $location_id)
            ->where('slug', 'configurable')
            ->pluck('discount_id')
            ->toArray();

        $configurableDiscounts = SupportCollection::make();
        if (!empty($configurableDiscountIds)) {
            $configurableQuery = Discounts::whereIn('id', $configurableDiscountIds)
                ->where('type', 'Configurable')
                ->where('active', '=', '1')
                ->whereDate('start', '<=', $today)
                ->whereDate('end', '>=', $today);

            if (!$isSuperAdmin) {
                $configurableQuery->whereHas('roles', function($query) use ($userRoleIds) {
                    $query->whereIn('role_id', $userRoleIds);
                });
            }

            if ($patientActiveMembership && $patientMembershipTypeId) {
                $configurableQuery->where(function($query) use ($patientMembershipTypeId) {
                    $query->where('customer_type_id', $patientMembershipTypeId)
                          ->orWhereNull('customer_type_id');
                });
            } else {
                $configurableQuery->whereNull('customer_type_id');
            }

            $searchServices = Services::where('account_id', Auth::User()->account_id)
                ->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id')->toArray();
            $serviceParentIds = LocationsWidget::findServiceParents($service_id, $searchServices);
            $serviceParentIds = array_merge($serviceParentIds ?? [], [$service_id]);

            $configurableDiscounts = $configurableQuery->get()->filter(function($discount) use ($service_id, $serviceParentIds) {
                $directMatch = BaseDiscountService::where('discount_id', $discount->id)
                    ->where('service_id', $service_id)
                    ->where(function($q) { $q->where('is_category', 0)->orWhereNull('is_category'); })
                    ->first();
                if ($directMatch) return true;

                $categoryMatch = BaseDiscountService::where('discount_id', $discount->id)
                    ->where('is_category', 1)
                    ->whereIn('service_id', $serviceParentIds)
                    ->first();
                return $categoryMatch !== null;
            });
        }

        $discounts = $discounts->merge($configurableDiscounts);

        $Discount_array = [];

        if (count($discounts) > 0) {
            foreach ($discounts as $discount) {
                if ($discount->type === 'Configurable') {
                    $Discount_array[$discount->id] = [
                        'id' => $discount->id,
                        'discount_type' => 'Configurable',
                        'discount_price' => 0,
                        'net_amount' => $service->price,
                        'slug' => 'configurable',
                    ];
                    continue;
                }

                $allocation = $allocations[$discount->id] ?? null;

                if (!$allocation || !$allocation->type || $allocation->amount === null) {
                    continue;
                }

                if ($allocation->slug == 'custom') {
                    continue;
                }

                $effective_type = $allocation->type;
                $effective_amount = $allocation->amount;

                if ($effective_type == Config::get('constants.Fixed')) {
                    $discount_type = $effective_type;
                    $discount_price = $effective_amount;
                    $net_amount = ($service->price) - ($discount_price);
                    $Discount_array[$discount->id] = [
                        'id' => $discount->id,
                        'discount_type' => $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount,
                        'slug' => $allocation->slug,
                    ];
                } else {
                    $discount_type = $effective_type;
                    $discount_price = $effective_amount;
                    $discount_price_cal = $service->price * (($discount_price) / 100);
                    $net_amount = ($service->price) - ($discount_price_cal);
                    $Discount_array[$discount->id] = [
                        'id' => $discount->id,
                        'discount_type' => $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount,
                        'slug' => $allocation->slug,
                    ];
                }
            }

            $select_discount = [];
            $lowest = false;
            if (count($Discount_array) > 0) {
                foreach ($Discount_array as $value) {
                    if ($value['discount_type'] === 'Configurable') {
                        continue;
                    }
                    if ($lowest === false || $value['net_amount'] < $lowest) {
                        $lowest = $value['net_amount'];
                        $select_discount = $value;
                    }
                }
                $discounts = $discounts->toArray();

                return [
                    'success' => true,
                    'message' => 'Records found.',
                    'data' => [
                        'discounts' => $discounts,
                        'checked_custom' => '0',
                        'dis_price_info' => $select_discount,
                        'net_amount' => $service->price,
                        'tax_treatment_type_id' => $service->tax_treatment_type_id,
                        'location_tax_percentage' => $location_information->tax_percentage ?? 0,
                        'service_name' => $service->name,
                    ],
                ];
            } else {
                $discounts = $discounts->toArray();

                return [
                    'success' => true,
                    'message' => 'Records found.',
                    'data' => [
                        'discounts' => $discounts,
                        'checked_custom' => '1',
                        'net_amount' => $service->price,
                        'tax_treatment_type_id' => $service->tax_treatment_type_id,
                        'location_tax_percentage' => $location_information->tax_percentage ?? 0,
                        'service_name' => $service->name,
                    ],
                ];
            }
        }

        $location_information = Locations::find($data['location_id']);
        return [
            'success' => false,
            'message' => 'Records found.',
            'status_code' => 404,
            'data' => [
                'net_amount' => $service->price,
                'tax_treatment_type_id' => $service->tax_treatment_type_id,
                'location_tax_percentage' => $location_information->tax_percentage ?? 0,
                'service_name' => $service->name,
            ],
        ];
    }

    /**
     * Get discount info for simple plans (non-bundle).
     * Extracted from PackagesController::getdiscountinfo_for_plan().
     */
    public function getDiscountInfoForPlan(array $data): array
    {
        if ($data['discount_id'] ?? null) {
            $discount_is_voucher = false;
            $service_id = $data['service_id'];
            $patient_id = $data['patient_id'];
            $location_id = $data['location_id'];
            $service_data = Services::find($service_id);

            if (!$service_data) {
                return [
                    'success' => false,
                    'message' => 'Service not found',
                    'status_code' => 500,
                ];
            }

            $discount_id = $data['discount_id'];
            $discount_data = Discounts::find($discount_id);

            $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::User()->account_id);
            $allocation = $allocations[$discount_id] ?? null;

            $effective_type = $allocation ? $allocation->type : null;
            $effective_amount = $allocation ? $allocation->amount : null;
            $allocation_slug = $allocation ? $allocation->slug : 'default';

            if ($allocation_slug == 'custom') {
                $max_percentage = $effective_type == 'Percentage' ? $effective_amount : 100;
                $max_fixed_amount = $service_data->price * ($effective_amount / 100);

                return [
                    'success' => true,
                    'message' => 'custom',
                    'data' => [
                        'custom_checked' => 1,
                        'allocation_type' => $effective_type,
                        'allocation_amount' => $effective_amount,
                        'service_price' => $service_data->price,
                        'max_percentage' => $max_percentage,
                        'max_fixed_amount' => round($max_fixed_amount, 2),
                    ],
                ];
            } else {
                // Handle configurable discounts - return preview of all services to be added
                if ($discount_data->type === 'Configurable') {
                    $baseServices = BaseDiscountService::where('discount_id', $discount_id)->get();
                    $getServices = GetDiscountService::where('discount_id', $discount_id)->get();

                    $isCategoryMode = $baseServices->isNotEmpty() && $baseServices->first()->is_category == 1;

                    $preview_rows = [];
                    $loc = Locations::find($location_id);
                    $locTaxPct = $loc->tax_percentage ?? 0;

                    if ($isCategoryMode) {
                        $sessionCount = (int) ($baseServices->first()->sessions ?? $baseServices->count());
                        for ($i = 0; $i < $sessionCount; $i++) {
                            $preview_rows[] = [
                                'service_id'    => $service_data->id,
                                'service_name'  => $service_data->name,
                                'service_price' => $service_data->price,
                                'net_amount'    => $service_data->price,
                                'discount_type' => '-',
                                'discount_price'=> 0,
                                'row_type'      => 'buy',
                                'tax_treatment_type_id' => $service_data->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    } else {
                        $serviceCache = [];
                        foreach ($baseServices as $bs) {
                            if (!isset($serviceCache[$bs->service_id])) {
                                $serviceCache[$bs->service_id] = Services::find($bs->service_id);
                            }
                            $svc = $serviceCache[$bs->service_id];
                            if (!$svc) continue;
                            $preview_rows[] = [
                                'service_id'    => $svc->id,
                                'service_name'  => $svc->name,
                                'service_price' => $svc->price,
                                'net_amount'    => $svc->price,
                                'discount_type' => '-',
                                'discount_price'=> 0,
                                'row_type'      => 'buy',
                                'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    }

                    if ($isCategoryMode) {
                        $getGroups = [];
                        foreach ($getServices as $gs) {
                            $key = $gs->discount_type . '_' . $gs->discount_amount . '_' . ($gs->same_service ? 'same' : $gs->service_id);
                            if (!isset($getGroups[$key])) {
                                $getGroups[$key] = ['record' => $gs, 'count' => 0];
                            }
                            $getGroups[$key]['count']++;
                        }
                        foreach ($getGroups as $group) {
                            $gs = $group['record'];
                            $svc = $gs->same_service ? $service_data : Services::find($gs->service_id);
                            if (!$svc) continue;
                            for ($i = 0; $i < $group['count']; $i++) {
                                if ($gs->discount_type === 'complimentory') {
                                    $net = 0;
                                    $disc_label = 'Complimentary';
                                    $disc_price = $svc->price;
                                } else {
                                    $disc_price = round($svc->price * ($gs->discount_amount / 100), 2);
                                    $net = $svc->price - $disc_price;
                                    $disc_label = $gs->discount_amount . '% Off';
                                }
                                $preview_rows[] = [
                                    'service_id'    => $svc->id,
                                    'service_name'  => $svc->name,
                                    'service_price' => $svc->price,
                                    'net_amount'    => $net,
                                    'discount_type' => $disc_label,
                                    'discount_price'=> $disc_price,
                                    'row_type'      => 'get',
                                    'gs_discount_type'   => $gs->discount_type,
                                    'gs_discount_amount' => $gs->discount_amount,
                                    'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                    'location_tax_percentage' => $locTaxPct,
                                ];
                            }
                        }
                    } else {
                        foreach ($getServices as $gs) {
                            $svc = $gs->same_service ? $service_data : Services::find($gs->service_id);
                            if (!$svc) continue;
                            if ($gs->discount_type === 'complimentory') {
                                $net = 0;
                                $disc_label = 'Complimentary';
                                $disc_price = $svc->price;
                            } else {
                                $disc_price = round($svc->price * ($gs->discount_amount / 100), 2);
                                $net = $svc->price - $disc_price;
                                $disc_label = $gs->discount_amount . '% Off';
                            }
                            $preview_rows[] = [
                                'service_id'    => $svc->id,
                                'service_name'  => $svc->name,
                                'service_price' => $svc->price,
                                'net_amount'    => $net,
                                'discount_type' => $disc_label,
                                'discount_price'=> $disc_price,
                                'row_type'      => 'get',
                                'gs_discount_type'   => $gs->discount_type,
                                'gs_discount_amount' => $gs->discount_amount,
                                'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    }

                    $total_net = array_sum(array_column($preview_rows, 'net_amount'));

                    return [
                        'success' => true,
                        'message' => 'Configurable',
                        'data' => [
                            'is_configurable'  => true,
                            'discount_type'    => 'Configurable',
                            'discount_price'   => 0,
                            'net_amount'       => $service_data->price,
                            'total_net_amount' => $total_net,
                            'custom_checked'   => 0,
                            'slug'             => 'configurable',
                            'preview_rows'     => $preview_rows,
                            'service_name'     => $service_data->name,
                            'tax_treatment_type_id' => $service_data->tax_treatment_type_id,
                            'location_tax_percentage' => $locTaxPct,
                        ],
                    ];
                }

                // Initialize default values
                $discount_type = '';
                $discount_price = 0;
                $net_amount = $service_data->price;

                if ($effective_type == Config::get('constants.Fixed') && $discount_data->discount_type !="voucher") {
                    $discount_type = Config::get('constants.Fixed');
                    $discount_price = $effective_amount;
                    $net_amount = ($service_data->price) - ($effective_amount);
                } else if ($effective_type == Config::get('constants.Percentage') && $discount_data->discount_type !="voucher") {
                    $discount_type = Config::get('constants.Percentage');
                    $discount_price = $effective_amount;
                    $discount_price_cal = $service_data->price * (($discount_price) / 100);
                    $net_amount = ($service_data->price) - ($discount_price_cal);
                } else if ($discount_data->discount_type == "voucher") {
                    $patientVoucher = UserVouchers::where("user_id", $patient_id)->where("voucher_id", $discount_id)->first();
                    if ($patientVoucher) {
                        $discount_type = Config::get('constants.Fixed');
                        $discount_price = $patientVoucher->amount;
                        $discount_is_voucher = true;
                        $net_amount = ($service_data->price) - ($discount_price);
                        if($net_amount < 0){
                            $net_amount = 0;
                        }
                    } else {
                        $discount_type = "";
                        $discount_price = 0;
                        $discount_is_voucher = false;
                        $net_amount = $service_data->price;
                    }
                }
                $loc = Locations::find($location_id);
                return [
                    'success' => true,
                    'message' => 'Record Found',
                    'data' => [
                        'discount_type' => $net_amount < 0 ? '' : $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount < 0 ? $service_data->price : $net_amount,
                        'custom_checked' => 0,
                        'discount_is_voucher' => $discount_is_voucher,
                        'slug' => $allocation_slug,
                        'service_name' => $service_data->name,
                        'service_price' => $service_data->price,
                        'tax_treatment_type_id' => $service_data->tax_treatment_type_id,
                        'location_tax_percentage' => $loc->tax_percentage ?? 0,
                    ],
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'No Record Found',
            'status_code' => 404,
        ];
    }

    /**
     * Get custom discount info for simple plans (non-bundle).
     * Extracted from PackagesController::getdiscountinfocustom_for_plan().
     */
    public function getCustomDiscountInfoForPlan(array $data): array
    {
        $status = true;
        $service_id = $data['service_id'];
        $location_id = $data['location_id'];
        $service_data = Services::find($service_id);

        if (!$service_data) {
            return [
                'success' => false,
                'message' => 'Service not found',
                'status_code' => 500,
            ];
        }

        $discount_id = $data['discount_id'];
        $discount_data = Discounts::find($discount_id);

        $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::User()->account_id);
        $allocation = $allocations[$discount_id] ?? null;

        $effective_type = $allocation ? $allocation->type : null;
        $effective_amount = $allocation ? $allocation->amount : null;
        $allocation_slug = $allocation ? $allocation->slug : 'default';

        $discount_value = $data['discount_value'] ?? 0;
        $discount_type_input = $data['discount_type'] ?? null;
        $patient_id = $data['patient_id'] ?? null;

        if ($allocation_slug == 'custom') {
            $discount_id = $data['discount_id'];
        } else {
            if($discount_data->discount_type == "voucher"){
                $discountValue = UserVouchers::where("user_id", $patient_id)->where("voucher_id", $discount_id)->first();
                if ($discountValue) {
                    $discount_value = $discountValue->amount;
                } else {
                    $discount_value = 0;
                }
            } else {
                $discount_value = $effective_amount;
            }
        }

        $net_amount = $service_data->price;
        $discount_price = 0;

        if ($effective_type == 'Fixed' && $discount_data->discount_type != 'voucher') {
            if ($discount_type_input == Config::get('constants.Fixed')) {
                if ($discount_value > $effective_amount || $discount_value > $service_data->price) {
                    $status = false;
                }
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $discount_value;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
            } else {
                $discount_type = Config::get('constants.Percentage');
                $discount_price = $discount_value;
                $discount_price_cal = ($effective_amount / $service_data->price) * 100;
                if ($discount_value > $discount_price_cal) {
                    $status = false;
                }
                $amount_after_per = ($discount_value / 100) * $service_data->price;
                $net_amount = $service_data->price - $amount_after_per;
            }
        } else if($effective_type == 'Fixed' && $discount_data->discount_type == 'voucher'){
            $discountValue = UserVouchers::where("user_id", $patient_id)->where("voucher_id", $discount_id)->first();
            if($discountValue){
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $discountValue->amount;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
                if($net_amount < 0){
                    $net_amount = 0;
                }
            } else {
                $discount_price = 0;
                $net_amount = ($service_data->price) - ($discount_price);
            }
        } else if ($effective_type == 'Percentage' && $discount_data->discount_type == 'voucher') {
            $discountValue = UserVouchers::where("user_id", $patient_id)->where("voucher_id", $discount_id)->first();
            if ($discountValue) {
                $discount_price = $discountValue->amount;
                $discount_price_in_percentage = ($discount_price / 100) * $service_data->price;
                $net_amount = ($service_data->price) - ($discount_price_in_percentage);
                if ($net_amount < 0) {
                    $net_amount = 0;
                }
            } else {
                $discount_price = 0;
                $net_amount = $service_data->price;
            }
        } else {
            if ($discount_type_input == Config::get('constants.Fixed')) {
                $discount_price = $discount_value;
                if ($service_data->price > 0) {
                    $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                    if ($discount_data->discount_type != 'voucher' && $discount_price_in_percentage > $effective_amount) {
                        $status = false;
                    }
                }
                $net_amount = ($service_data->price) - ($discount_value);
            } else {
                if ($discount_data->discount_type != 'voucher' && $discount_value > $effective_amount) {
                    $status = false;
                }
                $discount_price = $discount_value;
                $discount_price_in_percentage = ($discount_value / 100) * $service_data->price;
                $net_amount = ($service_data->price) - ($discount_price_in_percentage);
            }
        }

        if ($status == true) {
            return [
                'success' => true,
                'message' => 'Net Amount',
                'data' => [
                    'net_amount' => $net_amount < 0 ? 0 : $net_amount,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid discount value',
            'status_code' => 404,
        ];
    }

    /**
     * Save service to plan - handles both simple and configurable discounts.
     * Extracted from PackagesController::savepackages_service_for_plan().
     */
    public function saveServiceForPlan(array $data): array
    {
        Log::info('=== saveServiceForPlan CALLED ===', [
            'service_id_from_request' => $data['service_id'] ?? null,
            'discount_id' => $data['discount_id'] ?? null,
            'random_id' => $data['random_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
        ]);

        $location_information = Locations::find($data['location_id'] ?? null);
        if (!$location_information) {
            return [
                'success' => false,
                'message' => 'Location not found.',
                'status_code' => 500,
            ];
        }

        $service_data = Services::find($data['service_id'] ?? null);
        if (!$service_data) {
            return [
                'success' => false,
                'message' => 'Service not found.',
                'status_code' => 500,
            ];
        }

        Log::info('saveServiceForPlan: service found', [
            'service_id' => $service_data->id,
            'service_name' => $service_data->name,
            'service_table' => 'services',
        ]);

        // Check if plan is already settled
        $find_package = Packages::where('random_id', $data['random_id'])->first();
        if ($find_package) {
            $check_is_setteled = PackageAdvances::where([
                ['cash_flow', '=', 'out'],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $find_package->id],
            ])->first();
            if ($check_is_setteled) {
                return [
                    'success' => false,
                    'message' => 'Plan is already settled. You cannot add further treatment.',
                    'status_code' => 500,
                ];
            }
        }

        $discount_data = ($data['discount_id'] ?? null) ? Discounts::find($data['discount_id']) : null;

        // --- CONFIGURABLE DISCOUNT PATH ---
        if ($discount_data && $discount_data->type === 'Configurable') {
            $baseServices  = BaseDiscountService::where('discount_id', $discount_data->id)->get();
            $getServices   = GetDiscountService::where('discount_id', $discount_data->id)->get();
            $isCategoryMode = $baseServices->isNotEmpty() && $baseServices->first()->is_category == 1;
            $selectedService = Services::find($data['service_id']);
            $mergedServices = $baseServices->merge($getServices);

            $myarray = [];
            $running_total = str_replace(',', '', $data['package_total'] ?? '0');
            if ($running_total === '') $running_total = 0;

            foreach ($mergedServices as $ds) {
                $is_buy_row = ($ds instanceof BaseDiscountService || !isset($ds->discount_type));
                if (($is_buy_row && $isCategoryMode) || (!$is_buy_row && $ds->same_service)) {
                    $svc = $selectedService;
                } else {
                    $svc = Services::find($ds->service_id);
                }
                if (!$svc) continue;

                if ($is_buy_row) {
                    $row_net_amount  = $svc->price;
                    $row_disc_type   = '-';
                    $row_disc_price  = 0;
                } elseif ($ds->discount_type === 'complimentory') {
                    $row_net_amount  = 0;
                    $row_disc_type   = 'Complimentary';
                    $row_disc_price  = $svc->price;
                } else {
                    $disc_amt        = round($svc->price * ($ds->discount_amount / 100), 2);
                    $row_net_amount  = $svc->price - $disc_amt;
                    $row_disc_type   = 'Percentage';
                    $row_disc_price  = $ds->discount_amount;
                }

                $bundle_data = $data;
                $bundle_data['bundle_id']     = $svc->id;
                $bundle_data['service_price'] = $svc->price;
                $bundle_data['net_amount']    = $row_net_amount;
                $bundle_data['discount_name'] = $discount_data->name;
                $bundle_data['discount_type'] = $row_disc_type;
                $bundle_data['discount_price']= $row_disc_price;
                $bundle_data['qty']           = '1';

                if (($data['is_exclusive'] ?? '') == '' || ($data['is_exclusive'] ?? null) === null) {
                    $bundle_data['is_exclusive'] = 1;
                }

                $tax_pct = $location_information->tax_percentage ?? 0;
                if ($svc->tax_treatment_type_id == Config::get('constants.tax_is_exclusive') ||
                    ($svc->tax_treatment_type_id == Config::get('constants.tax_both') && ($bundle_data['is_exclusive'] ?? 1) == 1)) {
                    $bundle_data['tax_exclusive_net_amount'] = $row_net_amount;
                    $bundle_data['tax_percenatage']          = $tax_pct;
                    $bundle_data['tax_price']                = ceil($row_net_amount * ($tax_pct / 100));
                    $bundle_data['tax_including_price']      = ceil($row_net_amount + $bundle_data['tax_price']);
                    $bundle_data['is_exclusive']             = 1;
                } else {
                    $bundle_data['tax_including_price']      = $row_net_amount;
                    $bundle_data['tax_percenatage']          = $tax_pct;
                    $bundle_data['tax_exclusive_net_amount'] = $tax_pct > 0 ? ceil((100 * $row_net_amount) / ($tax_pct + 100)) : $row_net_amount;
                    $bundle_data['tax_price']                = ceil($row_net_amount - $bundle_data['tax_exclusive_net_amount']);
                    $bundle_data['is_exclusive']             = 0;
                }

                if (!($data['discount_id'] ?? null)) {
                    $bundle_data['discount_id'] = null;
                }

                $bundle_data['created_at'] = Filters::getCurrentTimeStamp();
                $bundle_data['updated_at'] = Filters::getCurrentTimeStamp();

                $packagebundle = PackageBundles::createPackagebundle($bundle_data);

                $data_service = [
                    'random_id'          => $data['random_id'],
                    'package_bundle_id'  => $packagebundle->id,
                    'service_id'         => $svc->id,
                    'price'              => $row_net_amount,
                    'orignal_price'      => $svc->price,
                    'tax_including_price'=> $bundle_data['tax_including_price'],
                    'tax_percenatage'    => $bundle_data['tax_percenatage'],
                    'tax_exclusive_price'=> $bundle_data['tax_exclusive_net_amount'],
                    'tax_price'          => $bundle_data['tax_price'],
                    'is_exclusive'       => $bundle_data['is_exclusive'],
                    'created_at'         => Filters::getCurrentTimeStamp(),
                    'updated_at'         => Filters::getCurrentTimeStamp(),
                ];
                PackageService::createPackageService($data_service);

                $running_total = (float) str_replace(',', '', (string) $running_total) + (float) $packagebundle->tax_including_price;

                $package_service_detail = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
                    ->select('package_services.*', 'services.name')
                    ->where('package_services.package_bundle_id', '=', $packagebundle->id)
                    ->get();

                $myarray[] = [
                    'record'        => PackageBundles::find($packagebundle->id),
                    'record_detail' => $package_service_detail,
                    'random_id'     => $data['random_id'],
                    'service_name'  => $svc->name,
                    'service_price' => $svc->price,
                    'discount_name' => $discount_data->name,
                    'discount_type' => $row_disc_type,
                    'discount_price'=> $row_disc_price,
                    'net_amount'    => $row_net_amount,
                    'total'         => number_format($running_total),
                ];
            }

            $pkg = Packages::where('random_id', $data['random_id'])->first();
            if ($pkg) {
                $grand_total = (float) PackageBundles::where('package_id', $pkg->id)->sum('tax_including_price');
                $this->updatePlanNameForPackage($pkg);
            } else {
                $grand_total = (float) PackageBundles::where('random_id', $data['random_id'])->sum('tax_including_price');
            }
            if (!empty($myarray)) {
                $myarray[0]['grand_total'] = $grand_total;
            }

            return [
                'success' => true,
                'message' => 'Record found',
                'data' => [
                    'is_configurable' => true,
                    'rows'            => $myarray,
                    'grand_total'     => $grand_total,
                ],
            ];
        }

        // --- SIMPLE DISCOUNT PATH ---
        $total = str_replace(',', '', $data['package_total'] ?? '0');
        if ($total === '') $total = 0;

        $is_exclusive = $data['is_exclusive'] ?? '';
        if ($is_exclusive == '' || $is_exclusive === null) {
            $is_exclusive = 1;
        }

        $bundle_data = $data;
        $bundle_data['bundle_id']     = $service_data->id;
        $bundle_data['service_price'] = $service_data->price;
        $bundle_data['qty']           = '1';
        $bundle_data['is_exclusive']  = $is_exclusive;

        if ($discount_data) {
            $bundle_data['discount_name'] = $discount_data->name;
        }

        $tax_pct = $location_information->tax_percentage ?? 0;
        $net_amount = $data['net_amount'] ?? $service_data->price;
        if ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive') ||
            ($service_data->tax_treatment_type_id == Config::get('constants.tax_both') && $is_exclusive == '1')) {
            $bundle_data['tax_exclusive_net_amount'] = $net_amount;
            $bundle_data['tax_percenatage']          = $tax_pct;
            $bundle_data['tax_price']                = ceil($net_amount * ($tax_pct / 100));
            $bundle_data['tax_including_price']      = ceil($net_amount + $bundle_data['tax_price']);
            $bundle_data['is_exclusive']             = 1;
        } else {
            $bundle_data['tax_including_price']      = $net_amount;
            $bundle_data['tax_percenatage']          = $tax_pct;
            $bundle_data['tax_exclusive_net_amount'] = $tax_pct > 0 ? ceil((100 * $net_amount) / ($tax_pct + 100)) : $net_amount;
            $bundle_data['tax_price']                = ceil($net_amount - $bundle_data['tax_exclusive_net_amount']);
            $bundle_data['is_exclusive']             = 0;
        }

        if (!($data['discount_id'] ?? null)) {
            $bundle_data['discount_id'] = null;
        }

        $bundle_data['created_at'] = Filters::getCurrentTimeStamp();
        $bundle_data['updated_at'] = Filters::getCurrentTimeStamp();

        $packagebundle = PackageBundles::createPackagebundle($bundle_data);

        Log::info('saveServiceForPlan: PackageBundle created', [
            'packagebundle_id' => $packagebundle->id,
            'bundle_id_stored' => $packagebundle->bundle_id,
            'service_data_id' => $service_data->id,
            'service_data_name' => $service_data->name,
        ]);

        $data_service = [
            'random_id'          => $data['random_id'],
            'package_bundle_id'  => $packagebundle->id,
            'service_id'         => $service_data->id,
            'price'              => $net_amount,
            'orignal_price'      => $service_data->price,
            'tax_including_price'=> $bundle_data['tax_including_price'],
            'tax_percenatage'    => $bundle_data['tax_percenatage'],
            'tax_exclusive_price'=> $bundle_data['tax_exclusive_net_amount'],
            'tax_price'          => $bundle_data['tax_price'],
            'is_exclusive'       => $bundle_data['is_exclusive'],
            'created_at'         => Filters::getCurrentTimeStamp(),
            'updated_at'         => Filters::getCurrentTimeStamp(),
        ];
        PackageService::createPackageService($data_service);

        $total = number_format((float) $total + (float) $packagebundle->tax_including_price);

        $discount_name  = '-';
        $discount_type  = '-';
        $discount_price = '0.00';
        if ($data['discount_id'] ?? null) {
            $discount_name  = $packagebundle->discount_name ?? $discount_data->name ?? '-';
            $discount_type  = $packagebundle->discount_type ?? '-';
            $discount_price = $packagebundle->discount_price ?? '0.00';
        }

        $package_service = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
            ->select('package_services.*', 'services.name')
            ->where('package_services.package_bundle_id', '=', $packagebundle->id)
            ->get();

        $myarray = [
            'record'        => PackageBundles::find($packagebundle->id),
            'record_detail' => $package_service,
            'random_id'     => $data['random_id'],
            'service_name'  => $service_data->name,
            'service_price' => $service_data->price,
            'discount_name' => $discount_name,
            'discount_type' => $discount_type,
            'discount_price'=> $discount_price,
            'net_amount'    => $packagebundle->net_amount,
            'total'         => $total,
        ];

        $pkgForName = Packages::where('random_id', $data['random_id'])->first();
        if ($pkgForName) {
            $this->updatePlanNameForPackage($pkgForName);
        }

        return [
            'success' => true,
            'message' => 'Record found',
            'data' => [
                'is_configurable' => false,
                'myarray'         => $myarray,
            ],
        ];
    }

    /**
     * Save packages service information (bundle path).
     * Extracted from PackagesController::savepackages_service().
     */
    public function savePackagesService(array $data): array
    {
        Log::info('=== savePackagesService (BUNDLE PATH) CALLED ===', [
            'bundle_id_from_request' => $data['bundle_id'] ?? null,
            'discount_id' => $data['discount_id'] ?? null,
            'random_id' => $data['random_id'] ?? null,
        ]);

        $status = true;
        $service_data = Bundles::find($data['bundle_id'] ?? null);
        Log::info('savePackagesService: Bundles::find result', [
            'found' => $service_data ? true : false,
            'name' => $service_data->name ?? 'NULL',
            'id' => $service_data->id ?? 'NULL',
        ]);
        $find_package = Packages::where('random_id', $data['random_id'])->first();
        if ($find_package) {
            $check_is_setteled = PackageAdvances::where([
                ['cash_flow', '=', 'out'],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $find_package->id],
            ])->first();
            if ($check_is_setteled) {
                return [
                    'success' => false,
                    'message' => 'Plan is already settled. you can not add further treatment in this plan.',
                    'status_code' => 404,
                    'data' => ['setteled' => 1],
                ];
            }
        }
        $find_discount = Discounts::find($data['discount_id'] ?? null);

        if ($find_discount && $find_discount->type == "Configurable") {
            if (($data['is_exclusive'] ?? '') == '') {
                $data['is_exclusive'] = 1;
            }
            if ($status == true) {
                $location_information = Locations::find($data['location_id']);
                $discount_info = Discounts::find($data['discount_id']);
                $base_services = BaseDiscountService::where('discount_id', $data['discount_id'])->get();
                $discounted_services = GetDiscountService::where('discount_id', $data['discount_id'])->get();
                $isCategoryMode = $base_services->isNotEmpty() && $base_services->first()->is_category == 1;
                $selectedService = Services::find($data['service_id'] ?? ($data['bundle_id'] ?? null));
                $merged_services = $base_services->merge($discounted_services);
                $myarray = [];
                foreach ($merged_services as $ds) {
                    $isBuyRow = $ds instanceof BaseDiscountService || !isset($ds->discount_type);
                    if (($isBuyRow && $isCategoryMode) || (!$isBuyRow && $ds->same_service)) {
                        $service_data1 = $selectedService;
                    } else {
                        $service_data1 = Services::find($ds->service_id);
                    }
                    if (!$service_data1) continue;

                    $data['qty'] = '1';
                    $data['bundle_id'] = $service_data1->id;
                    $data['service_price'] = $service_data1->price;
                    if ($discount_info) {
                        $data['discount_name'] = $discount_info->name;
                    }
                    if ($service_data1->tax_treatment_type_id == Config::get('constants.tax_both')) {
                        if ($data['is_exclusive'] == '1') {
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == "complimentory" ? 0 : $data['net_amount'];
                            $data['tax_percenatage'] = $ds->discount_type == "complimentory" ? 0 : $location_information->tax_percentage;
                            $data['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                            $data['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));
                            $data['is_exclusive'] = 1;
                        } else {
                            $data['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : $data['net_amount'];
                            $data['tax_percenatage'] = $ds->discount_type == "complimentory" ? 0 : $location_information->tax_percentage;
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                            $data['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        }
                    } elseif ($service_data1->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                        $data['tax_exclusive_net_amount'] = $ds->discount_type == "complimentory" ? 0 : $data['net_amount'];
                        $data['tax_percenatage'] = $ds->discount_type == "complimentory" ? 0 : $location_information->tax_percentage;
                        $data['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                        $data['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));

                        $data['is_exclusive'] = 1;
                    } else {

                        if ($ds->discount_type == "complimentory") {
                            $data['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : $data['net_amount'];
                            $data['tax_percenatage'] = $ds->discount_type == "complimentory" ? 0 : $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                            $data['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        } elseif ($ds->discount_type == "custom") {
                            $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;

                            $data['tax_including_price'] = $service_data1->price - $amount_after_discount;
                            $data['discount_type'] = $ds->discount_type;
                            $data['discount_price'] = $ds->discount_amount;
                            $data['tax_percenatage'] = $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                            $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        } else {

                            $data['tax_including_price'] = $data['net_amount'];
                            $data['tax_percenatage'] = $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                            $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        }
                    }
                    if ($data['discount_id'] == '0' || $data['discount_id'] == '') {
                        $data['discount_id'] = null;
                    }
                    $data['created_at'] = Filters::getCurrentTimeStamp();
                    $data['updated_at'] = Filters::getCurrentTimeStamp();
                    $packagesbundly = PackageBundles::createPackagebundle($data);

                    $calculated_services = [[
                        'service_price' => $service_data1->price,
                        'calculated_price' => $data['net_amount'] ?? $service_data1->price,
                        'service_id' => $service_data1->id,
                    ]];

                    foreach ($calculated_services as $detail) {
                        if ($ds->discount_type == "complimentory") {
                            $data_service['random_id'] = $data['random_id'];
                            $data_service['package_bundle_id'] = $packagesbundly->id;
                            $data_service['service_id'] = $detail['service_id'];
                            $data_service['price'] =  0;
                            $data_service['orignal_price'] = 0;
                        } elseif ($ds->discount_type == "custom") {

                            $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;
                            $data_service['random_id'] = $data['random_id'];
                            $data_service['package_bundle_id'] = $packagesbundly->id;
                            $data_service['service_id'] = $detail['service_id'];
                            $data_service['price'] = $service_data1->price - $amount_after_discount;
                            $data_service['orignal_price'] = $service_data1->price;
                        } else {
                            $data_service['random_id'] = $data['random_id'];
                            $data_service['package_bundle_id'] = $packagesbundly->id;
                            $data_service['service_id'] = $detail['service_id'];
                            $data_service['price'] = $detail['calculated_price'];
                            $data_service['orignal_price'] = $detail['service_price'];
                        }

                        if ($service_data1->tax_treatment_type_id == Config::get('constants.tax_both')) {
                            if ($data['is_exclusive'] == '1') {
                                $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : $detail['calculated_price'];
                                $data_service['tax_percenatage'] = $location_information->tax_percentage;
                                $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                                $data_service['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));
                                $data_service['is_exclusive'] = 1;
                            } else {
                                $data_service['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : $detail['calculated_price'];
                                $data_service['tax_percenatage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                                $data_service['is_exclusive'] = 0;
                            }
                        } elseif ($service_data1->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                            $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : $detail['calculated_price'];
                            $data_service['tax_percenatage'] = $location_information->tax_percentage;
                            $data_service['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                            $data_service['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                            $data_service['is_exclusive'] = 1;
                        } else {
                            if ($ds->discount_type == "complimentory") {
                                $data_service['tax_including_price'] = 0;
                                $data_service['tax_percenatage'] = 0;
                                $data_service['tax_exclusive_price'] = 0;
                                $data_service['tax_price'] = 0;

                                $data_service['is_exclusive'] = 0;
                            } else if ($ds->discount_type == "custom") {
                                $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;
                                $data_service['tax_including_price'] = $service_data1->price - $amount_after_discount;
                                $data_service['tax_percenatage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);
                                $data_service['is_exclusive'] = 0;
                            } else {
                                $data_service['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : $detail['calculated_price'];
                                $data_service['tax_percenatage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);
                                $data_service['is_exclusive'] = 0;
                            }
                        }
                        $data_service['created_at'] = Filters::getCurrentTimeStamp();
                        $data_service['updated_at'] = Filters::getCurrentTimeStamp();
                        $packageservice = PackageService::createPackageService($data_service);
                    }
                    $total = str_replace(',', '', $data['package_total'] ?? '');


                    if ($total == '') {
                        $total = 0;
                    }

                    $total = number_format((float) $total + (float) $packagesbundly->tax_including_price);
                    $net_amount = $packagesbundly->net_amount;
                    $service_name = $service_data1->name;
                    $service_price = $packagesbundly->service_price;

                    if ($data['discount_id'] == '0' || $data['discount_id'] == null) {
                        $discount_name = '-';
                        $discount_type = '-';
                        $discount_price = '0.00';
                    } else {
                        $discount_name = $packagesbundly->discount_name;
                        $discount_type = $packagesbundly->discount_type;
                        $discount_price = $packagesbundly->discount_price;
                    }
                    $package_service = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
                        ->select('package_services.*', 'services.name')
                        ->where('package_services.package_bundle_id', '=', $packagesbundly->id)
                        ->get();
                    $package_bundles = PackageBundles::find($packagesbundly->id);
                    $myarray[] = [
                        'record' => $package_bundles,
                        'record_detail' => $package_service,
                        'random_id' => $data['random_id'],
                        'service_name' => $service_name,
                        'service_price' => $service_price,
                        'discount_name' => $discount_name,
                        'discount_type' => $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount,
                        'total' =>  str_replace(',', '', $total),
                    ];
                }

                $grand_total = str_replace(',', '', $data['package_total'] ?? '');
                if ($grand_total == '') {
                    $grand_total = 0;
                }
                $package_id = Packages::where('random_id', $data['random_id'])->first();
                if ($package_id) {
                    $sum_services_price = PackageBundles::where('package_id', $package_id->id)->sum('tax_including_price');

                    $grand_total =  (float) $sum_services_price;
                    $myarray[0]['grand_total'] =  $grand_total;
                } else {
                    $sum_services_price = PackageBundles::where('random_id', $data['random_id'])->sum('tax_including_price');
                    $grand_total =  (float) $sum_services_price;
                    $myarray[0]['grand_total'] =  $grand_total;
                }

                return [
                    'success' => true,
                    'message' => 'Record found',
                    'data' => [
                        'myarray' => $myarray[0] ?? $myarray,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'No Record found',
                'status_code' => 404,
            ];
        } else {

            $total = str_replace(',', '', $data['package_total'] ?? '');
            if ($total == '') {
                $total = 0;
            }
            if (($data['is_exclusive'] ?? '') == '') {
                $data['is_exclusive'] = 1;
            }
            if ($status == true) {
                $location_information = Locations::find($data['location_id']);

                $discount_info = Discounts::find($data['discount_id'] ?? null);

                $data['qty'] = '1';
                $data['bundle_id'] = $service_data->id;
                $data['service_price'] = $service_data->price;

                if ($discount_info) {
                    $data['discount_name'] = $discount_info->name;
                }
                if ($service_data->tax_treatment_type_id == Config::get('constants.tax_both')) {
                    if ($data['is_exclusive'] == '1') {
                        $data['tax_exclusive_net_amount'] = $data['net_amount'];
                        $data['tax_percenatage'] = $location_information->tax_percentage;
                        $data['tax_price'] = ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                        $data['tax_including_price'] = ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));

                        $data['is_exclusive'] = 1;
                    } else {
                        $data['tax_including_price'] = $data['net_amount'];
                        $data['tax_percenatage'] = $location_information->tax_percentage;
                        $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                        $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                        $data['is_exclusive'] = 0;
                    }
                } elseif ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                    $data['tax_exclusive_net_amount'] = $data['net_amount'];
                    $data['tax_percenatage'] = $location_information->tax_percentage;
                    $data['tax_price'] = ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                    $data['tax_including_price'] = ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));

                    $data['is_exclusive'] = 1;
                } else {
                    $data['tax_including_price'] = $data['net_amount'];
                    $data['tax_percenatage'] = $location_information?->tax_percentage ?? '00.00';
                    $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                    $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                    $data['is_exclusive'] = 0;
                }
                if (($data['discount_id'] ?? '') == '0' || ($data['discount_id'] ?? '') == '') {
                    $data['discount_id'] = null;
                }
                $data['created_at'] = Filters::getCurrentTimeStamp();
                $data['updated_at'] = Filters::getCurrentTimeStamp();

                $packagesbundly = PackageBundles::createPackagebundle($data);

                $bundle_details = BundleHasServices::where('bundle_id', '=', $packagesbundly->bundle_id)->get();
                $calculable_servcies = [];

                foreach ($bundle_details as $detail) {
                    $calculable_servcies[] = [
                        'service_price' => $detail->calculated_price,
                        'calculated_price' => $detail->calculated_price,
                        'service_id' => $detail->service_id,
                    ];
                }
                $calculated_services = Bundles::calculatePrices($calculable_servcies, $data['service_price'], $data['net_amount']);

                foreach ($calculated_services as $detail) {

                    $data_service['random_id'] = $data['random_id'];
                    $data_service['package_bundle_id'] = $packagesbundly->id;
                    $data_service['service_id'] = $detail['service_id'];
                    $data_service['price'] = $detail['calculated_price'];
                    $data_service['orignal_price'] = $detail['service_price'];

                    if ($service_data->tax_treatment_type_id == Config::get('constants.tax_both')) {
                        if ($data['is_exclusive'] == '1') {
                            $data_service['tax_exclusive_price'] = $detail['calculated_price'];
                            $data_service['tax_percenatage'] = $location_information->tax_percentage;
                            $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                            $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                            $data_service['is_exclusive'] = 1;
                        } else {
                            $data_service['tax_including_price'] = $detail['calculated_price'];
                            $data_service['tax_percenatage'] = $location_information->tax_percentage;
                            $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                            $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                            $data_service['is_exclusive'] = 0;
                        }
                    } elseif ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                        $data_service['tax_exclusive_price'] = $detail['calculated_price'];
                        $data_service['tax_percenatage'] = $location_information->tax_percentage;
                        $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                        $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                        $data_service['is_exclusive'] = 1;
                    } else {
                        $data_service['tax_including_price'] = $detail['calculated_price'];
                        $data_service['tax_percenatage'] = $location_information->tax_percentage;
                        $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                        $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                        $data_service['is_exclusive'] = 0;
                    }
                    $data_service['created_at'] = Filters::getCurrentTimeStamp();
                    $data_service['updated_at'] = Filters::getCurrentTimeStamp();

                    $packageservice = PackageService::createPackageService($data_service);
                }
                $total = number_format((float) $total + (float) $packagesbundly->tax_including_price);

                $net_amount = $packagesbundly->net_amount;
                $service_name = $packagesbundly->bundle->name;
                $service_price = $packagesbundly->service_price;

                if (($data['discount_id'] ?? '') == '0' || ($data['discount_id'] ?? null) == null) {
                    $discount_name = '-';
                    $discount_type = '-';
                    $discount_price = '0.00';
                } else {
                    $discount_name = $packagesbundly->discount_name;
                    $discount_type = $packagesbundly->discount_type;
                    $discount_price = $packagesbundly->discount_price;
                }
                $package_service = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
                    ->select('package_services.*', 'services.name')
                    ->where('package_services.package_bundle_id', '=', $packagesbundly->id)
                    ->get();
                $package_bundles = PackageBundles::find($packagesbundly->id);
                $myarray = [
                    'record' => $package_bundles,
                    'record_detail' => $package_service,
                    'random_id' => $data['random_id'],
                    'service_name' => $service_name,
                    'service_price' => $service_price,
                    'discount_name' => $discount_name,
                    'discount_type' => $discount_type,
                    'discount_price' => $discount_price,
                    'net_amount' => $net_amount,
                    'total' => $total,
                ];

                return [
                    'success' => true,
                    'message' => 'Record found',
                    'data' => [
                        'myarray' => $myarray,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'No Record found',
                'status_code' => 404,
            ];
        }
    }
}
