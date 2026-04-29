<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Filter dropdown payload for the General Sales report family. Centralises
 * the lookups (locations, parent services, active doctors) so the SPA
 * doesn't have to fan out to multiple legacy endpoints.
 */
class GeneralSalesFiltersApiController extends Controller
{
    private const DOCTOR_USER_TYPE_ID = 5;

    public function __invoke(): JsonResponse
    {
        try {
            // Trace point so we can see in the log whether the controller
            // is being reached at all when the SPA shows a 5xx.
            Log::info('GeneralSalesFiltersApiController::__invoke entered', [
                'user_id' => Auth::id(),
            ]);
            $accountId = Auth::user()->account_id;

            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();

            $services = Services::parentsOnly()
                ->isActive()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])
                ->all();

            $doctors = User::getAllRecords($accountId)
                ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                ->where('active', 1)
                ->values()
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->all();

            // Active users for the optional `user_id` filter on revenue
            // detail. SoftDeletes global scope already excludes trashed
            // rows, so no `whereNull('deleted_at')` here — adding it
            // depends on driver collation and was the suspected cause of
            // the 500 we were seeing.
            $users = User::getAllRecords($accountId)
                ->where('active', 1)
                ->values()
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->sortBy('name')
                ->values()
                ->all();

            $genders = [
                ['id' => 'male', 'name' => 'Male'],
                ['id' => 'female', 'name' => 'Female'],
                ['id' => 'unknown', 'name' => 'Unknown'],
            ];

            return $this->successResponse('OK', [
                'locations' => $locations,
                'services' => $services,
                'doctors' => $doctors,
                'users' => $users,
                'genders' => $genders,
            ]);
        } catch (\Throwable $e) {
            // Capture full trace + class so we can pinpoint which lookup failed.
            Log::error('GeneralSalesFiltersApi failed: ' . $e->getMessage(), [
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Doctors narrowed to those allocated-and-active for the given centres.
     * Mirrors the same shape as DoctorRatingsApiController::doctorsForCentres
     * so the SPA's "centre change → reload doctors" pattern is portable
     * across report screens.
     *
     * Empty `centre_ids` returns the full account-wide active doctor list.
     */
    public function doctorsForCentres(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'centre_ids' => 'nullable|array',
                'centre_ids.*' => 'integer|exists:locations,id',
            ]);

            $accountId = Auth::user()->account_id;
            $centreIds = (array) $request->input('centre_ids', []);
            $centreIds = array_values(array_filter(array_map('intval', $centreIds), fn ($id) => $id > 0));

            if (empty($centreIds)) {
                $doctors = User::getAllRecords($accountId)
                    ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                    ->where('active', 1)
                    ->values()
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();

                return $this->successResponse('OK', ['doctors' => $doctors]);
            }

            $allocated = DoctorHasLocations::where('is_allocated', 1)
                ->whereIn('location_id', $centreIds)
                ->distinct()
                ->pluck('user_id')
                ->all();

            $doctors = empty($allocated)
                ? []
                : User::whereIn('id', $allocated)
                    ->where('account_id', $accountId)
                    ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();

            return $this->successResponse('OK', ['doctors' => $doctors]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('GeneralSalesFiltersApi doctorsForCentres failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }
}
