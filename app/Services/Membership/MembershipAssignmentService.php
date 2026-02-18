<?php

namespace App\Services\Membership;

use App\Exceptions\MembershipException;
use App\Helpers\ActivityLogger;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipAssignmentService
{
    protected MembershipService $membershipService;

    public function __construct(MembershipService $membershipService)
    {
        $this->membershipService = $membershipService;
    }

    /**
     * Assign membership to patient
     *
     * @param string $code
     * @param int $patientId
     * @param array $options
     * @return Membership
     * @throws MembershipException
     */
    public function assignMembership(string $code, int $patientId, array $options = []): Membership
    {
        DB::beginTransaction();
        try {
            $membership = Membership::where('code', $code)->first();
            if (!$membership) {
                throw new MembershipException("Membership code '{$code}' not found.");
            }

            if ($membership->patient_id) {
                throw new MembershipException("Membership code '{$code}' is already assigned to another patient.");
            }

            if (!$membership->active) {
                throw new MembershipException("Membership code '{$code}' is inactive.");
            }

            $membershipType = $membership->membershipType;
            if (!$membershipType || !$membershipType->active) {
                throw new MembershipException("Membership type is inactive.");
            }

            $patient = User::find($patientId);
            if (!$patient) {
                throw new MembershipException("Patient not found.");
            }

            $startDate = $options['start_date'] ?? Carbon::today()->format('Y-m-d');
            $endDate = $options['end_date'] ?? Carbon::parse($startDate)->addMonths($membershipType->period)->format('Y-m-d');

            if (Carbon::parse($startDate) > Carbon::parse($endDate)) {
                throw new MembershipException("Start date must be before end date.");
            }

            if ($this->membershipService->hasOverlappingMembership($patientId, $startDate, $endDate)) {
                throw new MembershipException("Patient already has an active membership during this period.");
            }

            $membership->update([
                'patient_id' => $patientId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'assigned_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            $this->membershipService->clearPatientMembershipCache($patientId);
            $this->membershipService->clearMembershipTypeCache($membershipType->id);

            ActivityLogger::logMembershipAssigned($patient, $membership, $membershipType);

            DB::commit();

            Log::info('Membership assigned to patient', [
                'membership_id' => $membership->id,
                'code' => $code,
                'patient_id' => $patientId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'assigned_by' => Auth::id()
            ]);

            return $membership->fresh(['membershipType', 'patient']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign membership', [
                'code' => $code,
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to assign membership: " . $e->getMessage());
        }
    }

    /**
     * Cancel membership assignment
     *
     * @param int $patientId
     * @param bool $forceCancel
     * @return array
     * @throws MembershipException
     */
    public function cancelMembership(int $patientId, bool $forceCancel = false): array
    {
        DB::beginTransaction();
        try {
            $membership = Membership::where('patient_id', $patientId)->first();

            if (!$membership) {
                throw new MembershipException("No membership found for this patient.");
            }

            $isInactiveAndExpired = ($membership->end_date < now());

            if (!$forceCancel && !$isInactiveAndExpired) {
                $hasAppliedServices = $this->checkMembershipUsage($patientId);
                if ($hasAppliedServices) {
                    throw new MembershipException("Membership has been applied on services and cannot be cancelled.");
                }
            }

            $membershipCode = $membership->code;
            $isReferral = $membership->is_referral;
            
            $patient = Patients::find($patientId);
            $membershipType = $membership->membershipType;

            $membership->delete();

            $cancelledReferrals = 0;

            if (!$isReferral) {
                $cancelledReferrals = Membership::where('parent_membership_code', $membershipCode)
                    ->where('is_referral', 1)
                    ->delete();
            }

            $this->membershipService->clearPatientMembershipCache($patientId);
            if ($membershipType) {
                $this->membershipService->clearMembershipTypeCache($membershipType->id);
            }

            if ($patient) {
                ActivityLogger::logMembershipCancelled($patient, $membership, $membershipType);
            }

            DB::commit();

            Log::info('Membership cancelled', [
                'patient_id' => $patientId,
                'code' => $membershipCode,
                'cancelled_referrals' => $cancelledReferrals,
                'cancelled_by' => Auth::id()
            ]);

            return [
                'success' => true,
                'message' => 'Membership cancelled successfully',
                'cancelled_referrals' => $cancelledReferrals
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel membership', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to cancel membership: " . $e->getMessage());
        }
    }

    /**
     * Check if membership has been used in services
     *
     * @param int $patientId
     * @return bool
     */
    protected function checkMembershipUsage(int $patientId): bool
    {
        $packages = DB::table('packages')
            ->where('patient_id', $patientId)
            ->whereNull('deleted_at')
            ->get();

        if ($packages->count() === 0) {
            return false;
        }

        $restrictedServiceNames = ['Gold Membership Card', 'Student Membership Card'];

        foreach ($packages as $package) {
            $hasRestrictedService = DB::table('package_services')
                ->join('services', 'package_services.service_id', '=', 'services.id')
                ->where('package_services.package_id', $package->id)
                ->whereIn('services.name', $restrictedServiceNames)
                ->whereNull('services.deleted_at')
                ->exists();

            if ($hasRestrictedService) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transfer membership to another patient
     *
     * @param string $code
     * @param int $fromPatientId
     * @param int $toPatientId
     * @return Membership
     * @throws MembershipException
     */
    public function transferMembership(string $code, int $fromPatientId, int $toPatientId): Membership
    {
        DB::beginTransaction();
        try {
            $membership = Membership::where('code', $code)
                ->where('patient_id', $fromPatientId)
                ->first();

            if (!$membership) {
                throw new MembershipException("Membership not found or not assigned to the specified patient.");
            }

            if ($this->checkMembershipUsage($fromPatientId)) {
                throw new MembershipException("Cannot transfer membership that has been used for services.");
            }

            $toPatient = User::find($toPatientId);
            if (!$toPatient) {
                throw new MembershipException("Target patient not found.");
            }

            if ($this->membershipService->hasOverlappingMembership(
                $toPatientId,
                $membership->start_date,
                $membership->end_date,
                $membership->id
            )) {
                throw new MembershipException("Target patient already has an active membership during this period.");
            }

            $fromPatient = User::find($fromPatientId);

            $membership->update([
                'patient_id' => $toPatientId,
                'updated_by' => Auth::id(),
            ]);

            $this->membershipService->clearPatientMembershipCache($fromPatientId);
            $this->membershipService->clearPatientMembershipCache($toPatientId);

            DB::commit();

            Log::info('Membership transferred', [
                'membership_id' => $membership->id,
                'code' => $code,
                'from_patient_id' => $fromPatientId,
                'to_patient_id' => $toPatientId,
                'transferred_by' => Auth::id()
            ]);

            return $membership->fresh(['membershipType', 'patient']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to transfer membership', [
                'code' => $code,
                'from_patient_id' => $fromPatientId,
                'to_patient_id' => $toPatientId,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to transfer membership: " . $e->getMessage());
        }
    }

    /**
     * Add referral membership
     *
     * @param int $patientId
     * @param string $parentMembershipCode
     * @return Membership
     * @throws MembershipException
     */
    public function addReferral(int $patientId, string $parentMembershipCode): Membership
    {
        DB::beginTransaction();
        try {
            $parentMembership = Membership::where('code', $parentMembershipCode)
                ->whereNotNull('patient_id')
                ->first();

            if (!$parentMembership) {
                throw new MembershipException("Parent membership code not found or not assigned.");
            }

            if ($parentMembership->is_referral) {
                throw new MembershipException("Cannot create referral from a referral membership.");
            }

            $patient = User::find($patientId);
            if (!$patient) {
                throw new MembershipException("Patient not found.");
            }

            $existingMembership = $this->membershipService->getPatientActiveMembership($patientId);
            if ($existingMembership) {
                throw new MembershipException("Patient already has an active membership.");
            }

            $referralCode = $this->generateReferralCode($parentMembershipCode);

            $membership = Membership::create([
                'code' => $referralCode,
                'membership_type_id' => $parentMembership->membership_type_id,
                'patient_id' => $patientId,
                'start_date' => $parentMembership->start_date,
                'end_date' => $parentMembership->end_date,
                'is_referral' => 1,
                'parent_membership_code' => $parentMembershipCode,
                'assigned_at' => now(),
                'active' => 1,
                'created_by' => Auth::id(),
            ]);

            $this->membershipService->clearPatientMembershipCache($patientId);

            DB::commit();

            Log::info('Referral membership created', [
                'membership_id' => $membership->id,
                'code' => $referralCode,
                'patient_id' => $patientId,
                'parent_code' => $parentMembershipCode,
                'created_by' => Auth::id()
            ]);

            return $membership->fresh(['membershipType', 'patient']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add referral membership', [
                'patient_id' => $patientId,
                'parent_code' => $parentMembershipCode,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to add referral: " . $e->getMessage());
        }
    }

    /**
     * Generate unique referral code
     *
     * @param string $parentCode
     * @return string
     */
    protected function generateReferralCode(string $parentCode): string
    {
        $baseCode = $parentCode . '-REF';
        $counter = 1;
        $referralCode = $baseCode . $counter;

        while (Membership::where('code', $referralCode)->exists()) {
            $counter++;
            $referralCode = $baseCode . $counter;
        }

        return $referralCode;
    }
}
