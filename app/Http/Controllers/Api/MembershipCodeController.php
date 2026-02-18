<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MembershipCodeException;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\GenerateCodesRequest;
use App\Services\Membership\MembershipCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MembershipCodeController extends Controller
{
    protected MembershipCodeService $codeService;
    protected $success;
    protected $error;
    protected $unauthorized;

    public function __construct(MembershipCodeService $codeService)
    {
        $this->codeService = $codeService;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Generate membership codes in bulk
     *
     * @param GenerateCodesRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateCodes(GenerateCodesRequest $request)
    {
        if (!Gate::allows('memberships_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to generate membership codes.', false);
        }

        try {
            $result = $this->codeService->generateCodeRange(
                $request->membership_type_id,
                $request->start_code,
                $request->end_code
            );

            return ApiHelper::apiResponse(
                $this->success,
                "Successfully generated {$result['count']} membership codes from {$result['start_code']} to {$result['end_code']}.",
                true,
                $result
            );
        } catch (MembershipCodeException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            Log::error('Unexpected error generating codes', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return ApiHelper::apiResponse($this->error, 'An unexpected error occurred. Please try again.', false);
        }
    }

    /**
     * Preview code generation (calculate how many codes will be generated)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function previewCodes(Request $request)
    {
        try {
            $request->validate([
                'start_code' => 'required|string',
                'end_code' => 'required|string',
            ]);

            $startCode = $request->start_code;
            $endCode = $request->end_code;

            $startPrefix = preg_replace('/\d+$/', '', $startCode);
            $endPrefix = preg_replace('/\d+$/', '', $endCode);

            if ($startPrefix !== $endPrefix) {
                return ApiHelper::apiResponse($this->error, 'Start and end codes must have the same prefix.', false);
            }

            $startNumber = (int) preg_replace('/^\D+/', '', $startCode);
            $endNumber = (int) preg_replace('/^\D+/', '', $endCode);

            if ($startNumber >= $endNumber) {
                return ApiHelper::apiResponse($this->error, 'Start code number must be less than end code number.', false);
            }

            if (strlen($startCode) !== strlen($endCode)) {
                return ApiHelper::apiResponse($this->error, 'Start and end codes must have the same length.', false);
            }

            $count = $endNumber - $startNumber + 1;

            if ($count > 10000) {
                return ApiHelper::apiResponse($this->error, 'Cannot generate more than 10,000 codes at once.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Preview calculated successfully.', true, [
                'count' => $count,
                'prefix' => $startPrefix,
                'start_number' => $startNumber,
                'end_number' => $endNumber,
                'sample_codes' => [
                    $startCode,
                    $startPrefix . str_pad($startNumber + 1, strlen(preg_replace('/^\D+/', '', $startCode)), '0', STR_PAD_LEFT),
                    $startPrefix . str_pad($startNumber + 2, strlen(preg_replace('/^\D+/', '', $startCode)), '0', STR_PAD_LEFT),
                    '...',
                    $endCode,
                ]
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    /**
     * Get available codes for a membership type
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableCodes(Request $request)
    {
        try {
            $request->validate([
                'membership_type_id' => 'required|exists:membership_types,id',
                'limit' => 'nullable|integer|min:1|max:500',
            ]);

            $codes = $this->codeService->getAvailableCodes(
                $request->membership_type_id,
                $request->limit ?? 100
            );

            return ApiHelper::apiResponse($this->success, 'Available codes retrieved successfully.', true, [
                'codes' => $codes
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    /**
     * Search membership codes
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchCodes(Request $request)
    {
        try {
            $request->validate([
                'search' => 'required|string|min:1',
                'membership_type_id' => 'nullable|exists:membership_types,id',
                'available_only' => 'nullable|boolean',
            ]);

            $codes = $this->codeService->searchCodes(
                $request->search,
                $request->membership_type_id,
                $request->available_only ?? false
            );

            return ApiHelper::apiResponse($this->success, 'Search completed successfully.', true, [
                'codes' => $codes,
                'count' => $codes->count()
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }
}
