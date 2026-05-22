<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Exceptions\MembershipCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\GenerateCodesRequest;
use App\Services\Membership\MembershipCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class MembershipCodeController extends Controller
{
    public function __construct(
        protected readonly MembershipCodeService $codeService,
    ) {}

    /**
     * Generate membership codes in bulk
     *
     * @param GenerateCodesRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateCodes(GenerateCodesRequest $request): \Illuminate\Http\JsonResponse
    {
        if (!Gate::allows('memberships_create')) {
            return $this->errorResponse('You are not authorized to generate membership codes.', 403);
        }

        try {
            $result = $this->codeService->generateCodeRange(
                $request->membership_type_id,
                $request->start_code,
                $request->end_code
            );

            return $this->successResponse("Successfully generated {$result['count']} membership codes from {$result['start_code']} to {$result['end_code']}.", $result
            );
        } catch (MembershipCodeException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error generating codes', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return $this->errorResponse('An unexpected error occurred. Please try again.', 500);
        }
    }

    /**
     * Preview code generation (calculate how many codes will be generated)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function previewCodes(Request $request): \Illuminate\Http\JsonResponse
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
                return $this->errorResponse('Start and end codes must have the same prefix.', 500);
            }

            $startNumber = (int) preg_replace('/^\D+/', '', $startCode);
            $endNumber = (int) preg_replace('/^\D+/', '', $endCode);

            if ($startNumber >= $endNumber) {
                return $this->errorResponse('Start code number must be less than end code number.', 500);
            }

            if (strlen($startCode) !== strlen($endCode)) {
                return $this->errorResponse('Start and end codes must have the same length.', 500);
            }

            $count = $endNumber - $startNumber + 1;

            if ($count > 10000) {
                return $this->errorResponse('Cannot generate more than 10,000 codes at once.', 500);
            }

            return $this->successResponse('Preview calculated successfully.', [
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
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Get available codes for a membership type
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableCodes(Request $request): \Illuminate\Http\JsonResponse
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

            return $this->successResponse('Available codes retrieved successfully.', [
                'codes' => $codes
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Search membership codes
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchCodes(Request $request): \Illuminate\Http\JsonResponse
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

            return $this->successResponse('Search completed successfully.', [
                'codes' => $codes,
                'count' => $codes->count()
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }
}
