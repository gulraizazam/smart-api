<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Conversion\WrongConversionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SPA-facing read + reset API for the wrong-conversions safety net.
 *
 * The auto-revert pipeline (`ConversionStateService::revertIfNeeded`)
 * fires on the canonical refund / payment-delete / plan-delete paths,
 * but cannot catch:
 *   - Historical "wrong" conversions that pre-date the trigger deploy
 *     (auto-revert only fires on NEW writes)
 *   - Direct DB edits (tinker, DB CLI, ad-hoc fixes)
 *   - Validation criteria beyond net cash (missing invoice / no service
 *     after invoice — see `WrongConversionService::isValidConversion`)
 *   - Trigger paths that silently fail (the SOT swallows exceptions to
 *     avoid rolling back the parent transaction)
 *
 * Finance uses this endpoint to scrub the list manually.
 *
 * Replaces the legacy `Admin\WrongConversionsController` (Blade) — that
 * controller and its view die with the Blade retirement.
 */
class WrongConversionsController extends Controller
{
    public function __construct(
        private readonly WrongConversionService $wrongConversionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $date = $request->query('date', Carbon::yesterday()->format('Y-m-d'));

            // Guard: invalid date string would make the underlying
            // `whereDate('converted_at', $date)` either error or silently
            // match nothing. Validate up-front and return 422 so the SPA
            // can surface the issue.
            try {
                $parsed = Carbon::parse((string) $date)->format('Y-m-d');
            } catch (\Throwable) {
                return $this->errorResponse('Invalid date', 422, ['date' => ['Date must be a valid Y-m-d string.']]);
            }

            $data = $this->wrongConversionService->getIndexData($parsed);

            if (! empty($data['error'])) {
                return $this->errorResponse($data['error'], 500);
            }

            return $this->successResponse('Wrong conversions loaded', [
                'date' => $data['date'],
                'total_converted' => $data['totalConverted'] ?? 0,
                'valid_count' => $data['validCount'] ?? 0,
                'invalid_count' => $data['invalidCount'] ?? 0,
                'arrived_status_id' => $data['arrivedStatusId'] ?? null,
                'appointments' => $data['appointments']->map(static fn ($a): array => [
                    'id' => (int) $a->id,
                    'patient' => [
                        'id' => $a->patient->id ?? null,
                        'name' => $a->patient->name ?? null,
                        'phone' => $a->patient->phone ?? null,
                    ],
                    'service' => [
                        'id' => $a->service->id ?? null,
                        'name' => $a->service->name ?? null,
                    ],
                    'location' => [
                        'id' => $a->location->id ?? null,
                        'name' => $a->location->name ?? null,
                    ],
                    'doctor' => [
                        'id' => $a->doctor->id ?? null,
                        'name' => $a->doctor->name ?? null,
                    ],
                    'scheduled_date' => $a->scheduled_date,
                    'converted_at' => $a->converted_at,
                ])->values()->all(),
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'WrongConversions.index');
        }
    }

    public function reset(int $id): JsonResponse
    {
        try {
            $result = $this->wrongConversionService->resetAppointment($id);
            if (! ($result['success'] ?? false)) {
                return $this->errorResponse($result['error'] ?? 'Reset failed', 422);
            }

            return $this->successResponse($result['message'] ?? 'Appointment reset', [
                'appointment_id' => $id,
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'WrongConversions.reset');
        }
    }

    public function resetAll(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ids' => ['required', 'array', 'min:1'],
                'ids.*' => ['integer'],
            ]);

            $result = $this->wrongConversionService->resetAllAppointments($validated['ids']);
            if (! ($result['success'] ?? false)) {
                return $this->errorResponse($result['error'] ?? 'Bulk reset failed', 422);
            }

            return $this->successResponse($result['message'] ?? 'Appointments reset', [
                'reset_ids' => $validated['ids'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'WrongConversions.resetAll');
        }
    }
}
