<?php

declare(strict_types=1);
namespace App\Services\Membership;

use App\Exceptions\MembershipCodeException;
use App\Models\Membership;
use App\Models\MembershipType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipCodeService
{
    public function __construct(
        protected readonly MembershipService $membershipService,
    ) {}

    /**
     * Generate membership codes in a range
     *
     * @param int $membershipTypeId
     * @param string $startCode
     * @param string $endCode
     * @return array
     * @throws MembershipCodeException
     */
    public function generateCodeRange(int $membershipTypeId, string $startCode, string $endCode): array
    {
        DB::beginTransaction();
        try {
            $membershipType = MembershipType::find($membershipTypeId);
            if (!$membershipType) {
                throw new MembershipCodeException("Membership type not found.");
            }

            if (!$membershipType->active) {
                throw new MembershipCodeException("Cannot generate codes for inactive membership type.");
            }

            $this->validateCodeFormat($startCode, $endCode);

            $codes = $this->extractCodesFromRange($startCode, $endCode);
            
            if (count($codes) > 10000) {
                throw new MembershipCodeException("Cannot generate more than 10,000 codes at once.");
            }

            $existingCodes = $this->checkExistingCodes($codes);
            if (!empty($existingCodes)) {
                throw new MembershipCodeException(
                    "The following codes already exist: " . implode(', ', array_slice($existingCodes, 0, 5)) . 
                    (count($existingCodes) > 5 ? " and " . (count($existingCodes) - 5) . " more" : "")
                );
            }

            $createdCodes = [];
            $createdBy = Auth::id();

            foreach ($codes as $code) {
                $membership = Membership::create([
                    'code' => $code,
                    'membership_type_id' => $membershipTypeId,
                    'active' => 1,
                    'created_by' => $createdBy,
                ]);
                $createdCodes[] = $membership->code;
            }

            $this->membershipService->clearMembershipTypeCache($membershipTypeId);

            DB::commit();

            Log::info('Membership codes generated', [
                'membership_type_id' => $membershipTypeId,
                'start_code' => $startCode,
                'end_code' => $endCode,
                'count' => count($createdCodes),
                'created_by' => $createdBy
            ]);

            return [
                'success' => true,
                'count' => count($createdCodes),
                'start_code' => $startCode,
                'end_code' => $endCode,
                'codes' => $createdCodes
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate membership codes', [
                'membership_type_id' => $membershipTypeId,
                'start_code' => $startCode,
                'end_code' => $endCode,
                'error' => $e->getMessage()
            ]);
            throw new MembershipCodeException("Failed to generate codes: " . $e->getMessage());
        }
    }

    /**
     * Validate code format
     *
     * @param string $startCode
     * @param string $endCode
     * @return void
     * @throws MembershipCodeException
     */
    protected function validateCodeFormat(string $startCode, string $endCode): void
    {
        $startPrefix = preg_replace('/\d+$/', '', $startCode);
        $endPrefix = preg_replace('/\d+$/', '', $endCode);

        if ($startPrefix !== $endPrefix) {
            throw new MembershipCodeException("Start and end codes must have the same prefix.");
        }

        $startNumber = (int) preg_replace('/^\D+/', '', $startCode);
        $endNumber = (int) preg_replace('/^\D+/', '', $endCode);

        if ($startNumber >= $endNumber) {
            throw new MembershipCodeException("Start code number must be less than end code number.");
        }

        if (strlen($startCode) !== strlen($endCode)) {
            throw new MembershipCodeException("Start and end codes must have the same length.");
        }
    }

    /**
     * Extract codes from range
     *
     * @param string $startCode
     * @param string $endCode
     * @return array
     */
    protected function extractCodesFromRange(string $startCode, string $endCode): array
    {
        $prefix = preg_replace('/\d+$/', '', $startCode);
        $startNumber = (int) preg_replace('/^\D+/', '', $startCode);
        $endNumber = (int) preg_replace('/^\D+/', '', $endCode);
        
        $numberLength = strlen(preg_replace('/^\D+/', '', $startCode));
        
        $codes = [];
        for ($i = $startNumber; $i <= $endNumber; $i++) {
            // Cast to string for PHP 8.1+ under strict_types — str_pad
            // refuses int args even though it'd happily coerce them in
            // older PHP. Not optional under this file's declare(strict_types=1).
            $codes[] = $prefix . str_pad((string) $i, $numberLength, '0', STR_PAD_LEFT);
        }

        return $codes;
    }

    /**
     * Check if codes already exist
     *
     * @param array $codes
     * @return array
     */
    protected function checkExistingCodes(array $codes): array
    {
        return Membership::whereIn('code', $codes)->pluck('code')->toArray();
    }

    /**
     * Import codes from array
     *
     * @param int $membershipTypeId
     * @param array $codes
     * @return array
     * @throws MembershipCodeException
     */
    public function importCodes(int $membershipTypeId, array $codes): array
    {
        DB::beginTransaction();
        try {
            $membershipType = MembershipType::find($membershipTypeId);
            if (!$membershipType) {
                throw new MembershipCodeException("Membership type not found.");
            }

            if (!$membershipType->active) {
                throw new MembershipCodeException("Cannot import codes for inactive membership type.");
            }

            $codes = array_unique(array_filter($codes));

            if (count($codes) > 10000) {
                throw new MembershipCodeException("Cannot import more than 10,000 codes at once.");
            }

            $existingCodes = $this->checkExistingCodes($codes);
            $newCodes = array_diff($codes, $existingCodes);

            $createdCodes = [];
            $createdBy = Auth::id();

            foreach ($newCodes as $code) {
                $membership = Membership::create([
                    'code' => trim($code),
                    'membership_type_id' => $membershipTypeId,
                    'active' => 1,
                    'created_by' => $createdBy,
                ]);
                $createdCodes[] = $membership->code;
            }

            $this->membershipService->clearMembershipTypeCache($membershipTypeId);

            DB::commit();

            Log::info('Membership codes imported', [
                'membership_type_id' => $membershipTypeId,
                'total_codes' => count($codes),
                'created_count' => count($createdCodes),
                'skipped_count' => count($existingCodes),
                'created_by' => $createdBy
            ]);

            return [
                'success' => true,
                'total' => count($codes),
                'created' => count($createdCodes),
                'skipped' => count($existingCodes),
                'existing_codes' => $existingCodes,
                'created_codes' => $createdCodes
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to import membership codes', [
                'membership_type_id' => $membershipTypeId,
                'error' => $e->getMessage()
            ]);
            throw new MembershipCodeException("Failed to import codes: " . $e->getMessage());
        }
    }

    /**
     * Dry-run preview for the bulk-codes UI: validates the type + range,
     * expands the range, and reports which codes already exist (would be
     * skipped) and which would be created. Does NOT write anything — pair
     * with `bulkCreateRangeSkippingExisting` for the actual create step.
     *
     * Capped at 10,000 codes per call to match the create path.
     */
    public function previewCodeRange(int $membershipTypeId, string $startCode, string $endCode): array
    {
        $membershipType = MembershipType::find($membershipTypeId);
        if (!$membershipType) {
            throw new MembershipCodeException("Membership type not found.");
        }

        if (!$membershipType->active) {
            throw new MembershipCodeException("Cannot generate codes for inactive membership type.");
        }

        $this->validateCodeFormat($startCode, $endCode);
        $codes = $this->extractCodesFromRange($startCode, $endCode);

        if (count($codes) > 10000) {
            throw new MembershipCodeException("Cannot preview more than 10,000 codes at once.");
        }

        $existingCodes = $this->checkExistingCodes($codes);
        $newCodes = array_values(array_diff($codes, $existingCodes));

        return [
            'membership_type_id' => $membershipTypeId,
            'membership_type_name' => $membershipType->name,
            'start_code' => $startCode,
            'end_code' => $endCode,
            'total' => count($codes),
            'existing_count' => count($existingCodes),
            'to_create_count' => count($newCodes),
            'codes' => $codes,
            'existing_codes' => array_values($existingCodes),
            'to_create_codes' => $newCodes,
        ];
    }

    /**
     * Generate every code in the [start, end] range that does NOT already
     * exist; existing ones are silently skipped (versus
     * `generateCodeRange`, which aborts on the first duplicate). Created
     * codes are stamped active + unassigned, matching the bulk-codes UX
     * the SPA exposes.
     */
    public function bulkCreateRangeSkippingExisting(int $membershipTypeId, string $startCode, string $endCode): array
    {
        $membershipType = MembershipType::find($membershipTypeId);
        if (!$membershipType) {
            throw new MembershipCodeException("Membership type not found.");
        }

        if (!$membershipType->active) {
            throw new MembershipCodeException("Cannot generate codes for inactive membership type.");
        }

        $this->validateCodeFormat($startCode, $endCode);
        $codes = $this->extractCodesFromRange($startCode, $endCode);

        if (count($codes) > 10000) {
            throw new MembershipCodeException("Cannot generate more than 10,000 codes at once.");
        }

        // Reuse importCodes' skip-existing semantics — it owns the
        // transaction, the create loop, the cache bust, and the audit log,
        // so we don't reimplement any of it here.
        return $this->importCodes($membershipTypeId, $codes);
    }

    /**
     * Get available codes for membership type
     *
     * @param int $membershipTypeId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableCodes(int $membershipTypeId, int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return Membership::where('membership_type_id', $membershipTypeId)
            ->whereNull('patient_id')
            ->where('active', 1)
            ->orderBy('code')
            ->limit($limit)
            ->get();
    }

    /**
     * Search codes
     *
     * @param string $search
     * @param int|null $membershipTypeId
     * @param bool $availableOnly
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchCodes(string $search, ?int $membershipTypeId = null, bool $availableOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = Membership::with(['membershipType', 'patient'])
            ->where('code', 'like', "%{$search}%");

        if ($membershipTypeId) {
            $query->where('membership_type_id', $membershipTypeId);
        }

        if ($availableOnly) {
            $query->whereNull('patient_id')->where('active', 1);
        }

        return $query->orderBy('code')->limit(50)->get();
    }
}
