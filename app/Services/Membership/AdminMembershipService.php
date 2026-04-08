<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Models\Appointments;
use App\Models\DoctorHasLocations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\PackageBundles;
use App\Models\Patients;
use App\Models\RoleHasUsers;
use App\Models\StudentVerification;
use App\Models\User;
use App\Models\UserHasLocations;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AdminMembershipService
{
    // ── Datatable Filters ───────────────────────────────

    public function buildFiltersWhere(Request $request, mixed $accountId, bool $applyFilter): array
    {
        $filters = getFilters($request->all());
        $where   = [];

        // Code filter
        if (hasFilter($filters, 'code')) {
            $where[] = ['memberships.code', 'like', '%' . $filters['code'] . '%'];
            Filters::put(Auth::user()->id, 'memberships', 'code', $filters['code']);
        } else {
            if ($applyFilter) {
                Filters::forget(Auth::user()->id, 'memberships', 'code');
            } elseif ($saved = Filters::get(Auth::user()->id, 'memberships', 'code')) {
                $where[] = ['memberships.code', 'like', '%' . $saved . '%'];
            }
        }

        // Membership type filter
        if (hasFilter($filters, 'membership_type_id')) {
            $where[] = ['memberships.membership_type_id', '=', $filters['membership_type_id']];
            Filters::put(Auth::user()->id, 'memberships', 'membership_type_id', $filters['membership_type_id']);
        } else {
            if ($applyFilter) {
                Filters::forget(Auth::user()->id, 'memberships', 'membership_type_id');
            } elseif ($saved = Filters::get(Auth::user()->id, 'memberships', 'membership_type_id')) {
                $where[] = ['memberships.membership_type_id', '=', $saved];
            }
        }

        // Created by filter
        if (hasFilter($filters, 'created_by')) {
            $where[] = ['memberships.created_by', '=', $filters['created_by']];
            Filters::put(Auth::user()->id, 'memberships', 'created_by', $filters['created_by']);
        } else {
            if ($applyFilter) {
                Filters::forget(Auth::user()->id, 'memberships', 'created_by');
            } elseif ($saved = Filters::get(Auth::user()->id, 'memberships', 'created_by')) {
                $where[] = ['memberships.created_by', '=', $saved];
            }
        }

        // Assigned filter
        if (hasFilter($filters, 'assigned')) {
            if ($filters['assigned'] == 1) {
                $where[] = ['memberships.patient_id', '<>', null];
            } elseif ($filters['assigned'] == 0) {
                $where[] = ['memberships.patient_id', '=', null];
            }
            Filters::put(Auth::user()->id, 'memberships', 'assigned', $filters['assigned']);
        } else {
            if ($applyFilter) {
                Filters::forget(Auth::user()->id, 'memberships', 'assigned');
            } elseif (($saved = Filters::get(Auth::user()->id, 'memberships', 'assigned')) !== null) {
                if ($saved == 1) {
                    $where[] = ['memberships.patient_id', '<>', null];
                } elseif ($saved == 0) {
                    $where[] = ['memberships.patient_id', '=', null];
                }
            }
        }

        // Status filter
        if (hasFilter($filters, 'status')) {
            $statusFilter = $filters['status'];
            if ($statusFilter === 'active') {
                $where[] = ['memberships.patient_id', '<>', null];
                $where[] = ['memberships.end_date', '>=', now()->format('Y-m-d')];
            } elseif ($statusFilter === 'inactive') {
                $where[] = ['memberships.patient_id', '=', null];
            } elseif ($statusFilter === 'expired') {
                $where[] = ['memberships.end_date', '<', now()->format('Y-m-d')];
            }
            Filters::put(Auth::user()->id, 'memberships', 'status', $filters['status']);
        } else {
            if ($applyFilter) {
                Filters::forget(Auth::user()->id, 'memberships', 'status');
            } elseif (($saved = Filters::get(Auth::user()->id, 'memberships', 'status')) !== null) {
                if ($saved === 'active') {
                    $where[] = ['memberships.patient_id', '<>', null];
                    $where[] = ['memberships.end_date', '>=', now()->format('Y-m-d')];
                } elseif ($saved === 'inactive') {
                    $where[] = ['memberships.patient_id', '=', null];
                } elseif ($saved === 'expired') {
                    $where[] = ['memberships.end_date', '<', now()->format('Y-m-d')];
                }
            }
        }

        // Location/sold_by filters — save to Filters only (actual filtering done in query builders)
        if (hasFilter($filters, 'location_id')) {
            Filters::put(Auth::user()->id, 'memberships', 'location_id', $filters['location_id']);
        } elseif ($applyFilter) {
            Filters::forget(Auth::user()->id, 'memberships', 'location_id');
        }

        if (hasFilter($filters, 'sold_by')) {
            Filters::put(Auth::user()->id, 'memberships', 'sold_by', $filters['sold_by']);
        } elseif ($applyFilter) {
            Filters::forget(Auth::user()->id, 'memberships', 'sold_by');
        }

        // Assigned at date range filter
        if (hasFilter($filters, 'assigned_at')) {
            $dateRange        = explode(' - ', $filters['assigned_at']);
            $startDateTime    = date('Y-m-d 00:00:00', strtotime($dateRange[0]));
            $endDateObj       = new \DateTime($dateRange[1]);
            $endDateObj->setTime(23, 59, 59);
            $where[] = ['memberships.assigned_at', '>=', $startDateTime];
            $where[] = ['memberships.assigned_at', '<=', $endDateObj->format('Y-m-d H:i:s')];
            Filters::put(Auth::user()->id, 'memberships', 'assigned_at', $filters['assigned_at']);
        } else {
            if ($applyFilter) {
                Filters::forget(Auth::user()->id, 'memberships', 'assigned_at');
            } elseif ($saved = Filters::get(Auth::user()->id, 'memberships', 'assigned_at')) {
                $dateRange     = explode(' - ', $saved);
                $startDateTime = date('Y-m-d 00:00:00', strtotime($dateRange[0]));
                $endDateObj    = new \DateTime($dateRange[1]);
                $endDateObj->setTime(23, 59, 59);
                $where[] = ['memberships.assigned_at', '>=', $startDateTime];
                $where[] = ['memberships.assigned_at', '<=', $endDateObj->format('Y-m-d H:i:s')];
            }
        }

        return $where;
    }

    /**
     * Returns null (no filter) or array of patient IDs (including empty array = no match).
     */
    public function getPatientIdFilter(Request $request, bool $applyFilter): ?array
    {
        $filters   = getFilters($request->all());
        $patientId = null;

        if (hasFilter($filters, 'patient_id')) {
            $patientId = $filters['patient_id'];
            Filters::put(Auth::user()->id, 'memberships', 'patient_id', $filters['patient_id']);
        } else {
            if ($applyFilter) {
                Filters::forget(Auth::user()->id, 'memberships', 'patient_id');
            } else {
                $patientId = Filters::get(Auth::user()->id, 'memberships', 'patient_id');
            }
        }

        if (empty($patientId)) {
            return null;
        }

        return [$patientId];
    }

    /**
     * Returns null (no filter) or array of patient IDs from appointment location.
     */
    public function getLocationFilter(Request $request, bool $applyFilter): ?array
    {
        $filters    = getFilters($request->all());
        $locationId = null;

        if (hasFilter($filters, 'location_id')) {
            $locationId = $filters['location_id'];
        } elseif (! $applyFilter) {
            $locationId = Filters::get(Auth::user()->id, 'memberships', 'location_id');
        }

        if (empty($locationId)) {
            return null;
        }

        return Appointments::where('location_id', $locationId)
            ->whereNotNull('patient_id')
            ->distinct()
            ->pluck('patient_id')
            ->toArray();
    }

    /**
     * Returns null (no filter) or array of membership IDs that match sold_by.
     */
    public function getSoldByFilter(Request $request, bool $applyFilter): ?array
    {
        $filters = getFilters($request->all());
        $soldBy  = null;

        if (hasFilter($filters, 'sold_by')) {
            $soldBy = $filters['sold_by'];
        } elseif (! $applyFilter) {
            $soldBy = Filters::get(Auth::user()->id, 'memberships', 'sold_by');
        }

        if (empty($soldBy)) {
            return null;
        }

        return DB::table('memberships')
            ->join('package_bundles', 'memberships.id', '=', 'package_bundles.membership_code_id')
            ->join('package_services', 'package_bundles.id', '=', 'package_services.package_bundle_id')
            ->where('package_services.sold_by', $soldBy)
            ->distinct()
            ->pluck('memberships.id')
            ->toArray();
    }

    /**
     * Total record count for the admin datatable.
     */
    public function getTotalRecords(Request $request, mixed $accountId, bool $applyFilter): int
    {
        $where       = $this->buildFiltersWhere($request, $accountId, $applyFilter);
        $userCentres = ACL::getUserCentres();
        $query       = DB::table('memberships');

        if (count($where)) {
            $query->where($where);
        }

        $patientIdFilter = $this->getPatientIdFilter($request, $applyFilter);
        if ($patientIdFilter !== null) {
            if (empty($patientIdFilter)) {
                $query->where('memberships.patient_id', '=', -1);
            } else {
                $query->whereIn('memberships.patient_id', $patientIdFilter);
            }
        }

        $locationFilter = $this->getLocationFilter($request, $applyFilter);
        if ($locationFilter !== null) {
            if (empty($locationFilter)) {
                $query->where('memberships.patient_id', '=', -1);
            } else {
                $query->whereIn('memberships.patient_id', $locationFilter);
            }
        }

        $soldByFilter = $this->getSoldByFilter($request, $applyFilter);
        if ($soldByFilter !== null) {
            if (empty($soldByFilter)) {
                $query->where('memberships.id', '=', -1);
            } else {
                $query->whereIn('memberships.id', $soldByFilter);
            }
        }

        if (! Gate::allows('view_inactive_centres')) {
            $query->where('memberships.active', 1);
        }

        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        if (! empty($userCentres)) {
            if ($isSuperAdmin) {
                $query->where(function ($q) use ($userCentres) {
                    $q->whereNull('memberships.patient_id')
                      ->orWhereExists(function ($sub) use ($userCentres) {
                          $sub->select(DB::raw(1))
                              ->from('appointments')
                              ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                              ->whereIn('appointments.location_id', $userCentres);
                      });
                });
            } else {
                $query->whereNotNull('memberships.patient_id')
                      ->whereExists(function ($sub) use ($userCentres) {
                          $sub->select(DB::raw(1))
                              ->from('appointments')
                              ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                              ->whereIn('appointments.location_id', $userCentres);
                      });
            }
        } elseif (! $isSuperAdmin) {
            $query->whereNotNull('memberships.patient_id');
        }

        return (int) $query->count();
    }

    /**
     * Paginated records for the admin datatable.
     */
    public function getRecords(Request $request, int $start, int $length, mixed $accountId, bool $applyFilter): mixed
    {
        $where       = $this->buildFiltersWhere($request, $accountId, $applyFilter);
        $userCentres = ACL::getUserCentres();
        $query       = Membership::with('membershiptype');

        if (count($where)) {
            $query->where($where);
        }

        $patientIdFilter = $this->getPatientIdFilter($request, $applyFilter);
        if ($patientIdFilter !== null) {
            if (empty($patientIdFilter)) {
                $query->where('memberships.patient_id', '=', -1);
            } else {
                $query->whereIn('memberships.patient_id', $patientIdFilter);
            }
        }

        $locationFilter = $this->getLocationFilter($request, $applyFilter);
        if ($locationFilter !== null) {
            if (empty($locationFilter)) {
                $query->where('memberships.patient_id', '=', -1);
            } else {
                $query->whereIn('memberships.patient_id', $locationFilter);
            }
        }

        $soldByFilter = $this->getSoldByFilter($request, $applyFilter);
        if ($soldByFilter !== null) {
            if (empty($soldByFilter)) {
                $query->where('memberships.id', '=', -1);
            } else {
                $query->whereIn('memberships.id', $soldByFilter);
            }
        }

        if (! Gate::allows('view_inactive_machine_types')) {
            $query->where('memberships.active', 1);
        }

        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        if (! empty($userCentres)) {
            if ($isSuperAdmin) {
                $query->where(function ($q) use ($userCentres) {
                    $q->whereNull('memberships.patient_id')
                      ->orWhereExists(function ($sub) use ($userCentres) {
                          $sub->select(DB::raw(1))
                              ->from('appointments')
                              ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                              ->whereIn('appointments.location_id', $userCentres);
                      });
                });
            } else {
                $query->whereNotNull('memberships.patient_id')
                      ->whereExists(function ($sub) use ($userCentres) {
                          $sub->select(DB::raw(1))
                              ->from('appointments')
                              ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                              ->whereIn('appointments.location_id', $userCentres);
                      });
            }
        } elseif (! $isSuperAdmin) {
            $query->whereNotNull('memberships.patient_id');
        }

        return $query->limit($length)
            ->offset($start)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // ── Status Toggle ───────────────────────────────────

    public function activate(int $id, int $status): bool
    {
        $membership = Membership::find($id);

        if (! $membership) {
            return false;
        }

        $checkMembershipType = MembershipType::find($membership->membership_type_id);

        if ($checkMembershipType?->active == 0) {
            return false;
        }

        return (bool) $membership->update(['active' => $status]);
    }

    public function deactivate(int $id): bool
    {
        $membership = Membership::find($id);

        if (! $membership) {
            return false;
        }

        return (bool) $membership->update(['active' => 0]);
    }

    // ── Cancel Membership ───────────────────────────────

    /**
     * Cancel a patient's membership, including referrals.
     * Returns ['success' => bool, 'message' => string].
     */
    public function cancelMembership(int $patientId): array
    {
        $membership = Membership::where('patient_id', $patientId)->first();

        if (! $membership) {
            return ['success' => false, 'message' => 'Membership not found'];
        }

        $isInactiveAndExpired = ($membership->end_date < now());

        if (! $isInactiveAndExpired) {
            $packages = DB::table('packages')
                ->where('patient_id', $patientId)
                ->whereNull('deleted_at')
                ->get();

            if ($packages->count() > 0) {
                $restrictedServiceNames = ['Gold Membership Card', 'Student Membership Card'];
                $packageIds = $packages->pluck('id')->toArray();

                $hasRestrictedService = DB::table('package_services')
                    ->join('services', 'package_services.service_id', '=', 'services.id')
                    ->whereIn('package_services.package_id', $packageIds)
                    ->whereIn('services.name', $restrictedServiceNames)
                    ->whereNull('services.deleted_at')
                    ->exists();

                if ($hasRestrictedService) {
                    return ['success' => false, 'message' => 'Membership applied on services, you can not cancel it'];
                }
            }
        }

        $membershipCode = $membership->code;
        $isReferral     = $membership->is_referral;
        $patient        = Patients::find($patientId);
        $membershipType = $membership->membershipType;

        $membership->update([
            'patient_id'  => null,
            'start_date'  => null,
            'end_date'    => null,
            'assigned_at' => null,
        ]);

        $cancelledReferrals = 0;

        if (! $isReferral) {
            $cancelledReferrals = Membership::where('parent_membership_code', $membershipCode)
                ->where('is_referral', 1)
                ->update([
                    'patient_id'  => null,
                    'start_date'  => null,
                    'end_date'    => null,
                    'assigned_at' => null,
                ]);
        }

        if ($patient) {
            ActivityLogger::logMembershipCancelled($patient, $membership, $membershipType);
        }

        $message = 'Membership cancelled successfully';
        if ($cancelledReferrals > 0) {
            $message .= ' along with ' . $cancelledReferrals . ' associated referral(s)';
        }

        return ['success' => true, 'message' => $message];
    }

    // ── Sold By Users ───────────────────────────────────

    public function getSoldByUsers(?int $locationId): mixed
    {
        $accountId = Auth::user()->account_id;

        if (empty($locationId)) {
            return User::where('account_id', $accountId)
                ->where('active', 1)
                ->whereIn('user_type_id', [config('constants.doctor_user_id'), config('constants.fdm_user_id')])
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $doctorIds       = DoctorHasLocations::where('location_id', $locationId)
            ->where('is_allocated', 1)
            ->pluck('user_id')
            ->toArray();

        $locationUserIds = UserHasLocations::where('location_id', $locationId)
            ->pluck('user_id')
            ->toArray();

        $fdmRole    = DB::table('roles')->where('name', 'FDM')->first();
        $fdmUserIds = [];
        if ($fdmRole) {
            $roleHasUsers = RoleHasUsers::where('role_id', $fdmRole->id)
                ->pluck('user_id')
                ->toArray();
            $fdmUserIds = array_intersect($locationUserIds, $roleHasUsers);
        }

        $allUserIds = array_unique(array_merge($doctorIds, $fdmUserIds));

        return User::whereIn('id', $allUserIds)
            ->where('active', 1)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    // ── Student Verification Details ────────────────────

    public function getStudentVerificationDetails(int $membershipId): array
    {
        $membership = Membership::with('membershipType')->findOrFail($membershipId);
        $patient    = User::findOrFail($membership->patient_id);

        $studentVerification = StudentVerification::where('membership_id', $membershipId)
            ->orWhere(function ($query) use ($membership) {
                $query->where('patient_id', $membership->patient_id)
                      ->where('membership_type_id', $membership->membership_type_id);
            })
            ->first();

        $documents = [];
        if ($studentVerification && ! empty($studentVerification->document_paths)) {
            $documents = $studentVerification->document_paths;
        }

        $membershipTypeDiscountIds = \App\Models\Discounts::where('customer_type_id', $membership->membership_type_id)
            ->pluck('id')
            ->toArray();

        $usedServices = collect();
        if (! empty($membershipTypeDiscountIds)) {
            $usedServices = PackageBundles::whereIn('discount_id', $membershipTypeDiscountIds)
                ->whereHas('package', fn ($q) => $q->where('patient_id', $patient->id))
                ->with(['bundle', 'package', 'discount', 'packageservice'])
                ->get();
        }

        $serviceUsage        = [];
        $totalDiscountAmount = 0;

        foreach ($usedServices as $service) {
            $serviceName  = $service->bundle?->name ?? 'Unknown Service';
            $discountSaved = $service->service_price - $service->tax_including_price;

            $packageService = $service->packageservice->first();
            $isConsumed     = $packageService ? (bool) $packageService->is_consumed : false;
            $consumedAt     = $packageService && $packageService->consumed_at
                ? Carbon::parse($packageService->consumed_at)->format('d/m/y')
                : null;

            $serviceUsage[] = [
                'service_name'   => $serviceName,
                'service_price'  => $service->service_price,
                'discount_amount' => $service->discount_price ?? 0,
                'discount_type'  => $service->discount_type,
                'net_amount'     => $service->tax_including_price,
                'plan_id'        => $service->package_id,
                'plan_date'      => $service->package ? $service->package->created_at->format('M d, Y') : null,
                'is_consumed'    => $isConsumed,
                'consumed_at'    => $consumedAt,
            ];

            if ($discountSaved > 0) {
                $totalDiscountAmount += $discountSaved;
            }
        }

        return [
            'membership' => [
                'id'         => $membership->id,
                'code'       => $membership->code,
                'type'       => $membership->membershipType->name ?? 'N/A',
                'start_date' => $membership->start_date,
                'end_date'   => $membership->end_date,
                'status'     => $membership->active ? 'Active' : 'Expired',
            ],
            'patient' => [
                'id'        => $patient->id,
                'unique_id' => 'C-' . $patient->id,
                'name'      => $patient->name,
                'email'     => $patient->email,
                'phone'     => $patient->phone,
            ],
            'verification' => $studentVerification ? [
                'id'           => $studentVerification->id,
                'status'       => $studentVerification->status,
                'submitted_at' => $studentVerification->submitted_at
                    ? $studentVerification->submitted_at->format('M d, Y h:i A')
                    : null,
                'reviewed_at'  => $studentVerification->reviewed_at
                    ? $studentVerification->reviewed_at->format('M d, Y h:i A')
                    : null,
            ] : null,
            'documents'     => $documents,
            'service_usage' => [
                'total_services'     => count($serviceUsage),
                'total_discount_saved' => $totalDiscountAmount,
                'services'           => $serviceUsage,
            ],
        ];
    }
}
