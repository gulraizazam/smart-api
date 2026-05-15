<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\BulkReplaceExceptionsRequest;
use App\Http\Requests\Schedule\StoreWorkingDayExceptionRequest;
use App\Http\Requests\Schedule\UpdateBusinessWorkingDaysRequest;
use App\Http\Requests\Schedule\UpdateWorkingDayExceptionRequest;
use App\Http\Resources\Schedule\WorkingDayExceptionResource;
use App\Models\Settings;
use App\Models\WorkingDayException;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BusinessWorkingDaysController extends Controller
{
    private const SETTING_SLUG = 'business_working_days';

    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private const DEFAULTS = [
        'monday' => true,
        'tuesday' => true,
        'wednesday' => true,
        'thursday' => true,
        'friday' => true,
        'saturday' => true,
        'sunday' => false,
    ];

    /**
     * GET /api/business-working-days
     *
     * Current 7-day weekly pattern + any per-date exceptions. Falls back to
     * Mon–Sat working / Sun closed when nothing has been saved yet (matches
     * the legacy defaults).
     */
    public function show(): JsonResponse
    {
        try {
            if (! Gate::allows('schedule_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;
            $workingDays = $this->loadWorkingDays($accountId);

            $exceptions = WorkingDayException::with('creator:id,name')
                ->where('account_id', $accountId)
                ->orderBy('exception_date')
                ->get();

            return $this->successResponse('Business working days retrieved.', [
                'working_days' => $workingDays,
                'exceptions' => WorkingDayExceptionResource::collection($exceptions),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@show');
        }
    }

    /**
     * PUT /api/business-working-days
     *
     * Update only the weekly pattern. Exceptions are managed via the
     * exceptions sub-resource — this endpoint does not touch them (unlike
     * the legacy `saveBusinessWorkingDays` which wiped and recreated them).
     */
    public function update(UpdateBusinessWorkingDaysRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $workingDays = [];
            foreach (self::DAYS as $day) {
                $workingDays[$day] = (bool) ($validated['working_days'][$day] ?? false);
            }

            Settings::updateOrCreate(
                ['account_id' => $accountId, 'slug' => self::SETTING_SLUG],
                [
                    'name' => 'Business Working Days',
                    'data' => json_encode($workingDays),
                    'active' => 1,
                ],
            );

            // Bump the OperatingDays version so any cached "best per-day
            // revenue" benchmark invalidates immediately. Settings doesn't
            // have a domain observer; we bump explicitly here.
            \App\Support\OperatingDays::bumpVersion($accountId);

            return $this->successResponse(
                'Business working days updated successfully.',
                ['working_days' => $workingDays],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@update');
        }
    }

    /**
     * GET /api/business-working-days/check
     *
     * Is a given date a working day? Uses `WorkingDayException::isWorkingDay`
     * which applies the exception if one exists, otherwise falls back to the
     * weekly pattern.
     */
    public function check(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('schedule_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $validated = $request->validate([
                'date' => ['required', 'date'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $date = Carbon::parse($validated['date'])->toDateString();
            $workingDays = $this->loadWorkingDays($accountId);

            $isWorking = (bool) WorkingDayException::isWorkingDay($accountId, $date, $workingDays);
            $exception = WorkingDayException::getExceptionForDate($accountId, $date);

            return $this->successResponse('Working-day check complete.', [
                'date' => $date,
                'day_of_week' => strtolower(Carbon::parse($date)->format('l')),
                'is_working' => $isWorking,
                'is_exception' => $exception !== null,
                'exception' => $exception ? new WorkingDayExceptionResource($exception) : null,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@check');
        }
    }

    /**
     * GET /api/business-working-days/calendar
     *
     * Per-date open/closed for a range. Capped at 365 days to stop abusive
     * windows. Useful for mobile calendar views.
     */
    public function calendar(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('schedule_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $validated = $request->validate([
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $start = Carbon::parse($validated['start_date']);
            $end = Carbon::parse($validated['end_date']);

            if ($start->diffInDays($end) > 365) {
                return $this->errorResponse('Date range cannot exceed 365 days.', 422);
            }

            $workingDays = $this->loadWorkingDays($accountId);

            $exceptions = WorkingDayException::where('account_id', $accountId)
                ->whereBetween('exception_date', [$start->toDateString(), $end->toDateString()])
                ->get()
                ->keyBy(fn ($e) => $e->exception_date->toDateString());

            $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $days = [];
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
                $dateStr = $cursor->toDateString();
                $exception = $exceptions->get($dateStr);
                $dayName = $dayNames[$cursor->dayOfWeek];

                $days[] = [
                    'date' => $dateStr,
                    'day_of_week' => $dayName,
                    'is_working' => $exception
                        ? (bool) $exception->is_working
                        : (bool) ($workingDays[$dayName] ?? false),
                    'is_exception' => $exception !== null,
                    'exception_title' => $exception?->title,
                ];
            }

            return $this->successResponse('Working-day calendar retrieved.', [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@calendar');
        }
    }

    // ── Exceptions sub-resource ──

    /**
     * GET /api/business-working-days/exceptions
     */
    public function indexExceptions(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('schedule_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = WorkingDayException::with('creator:id,name')
                ->where('account_id', $accountId);

            if ($request->filled('start_date')) {
                $query->whereDate('exception_date', '>=', $request->date('start_date')?->toDateString());
            }
            if ($request->filled('end_date')) {
                $query->whereDate('exception_date', '<=', $request->date('end_date')?->toDateString());
            }

            $exceptions = $query->orderBy('exception_date')->get();

            return $this->successResponse(
                'Working-day exceptions retrieved successfully.',
                WorkingDayExceptionResource::collection($exceptions),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@indexExceptions');
        }
    }

    /**
     * POST /api/business-working-days/exceptions
     *
     * Idempotent: a second call for the same date updates the existing row
     * rather than creating a duplicate (the legacy UI enforced this via
     * delete-then-recreate; we do it atomically via `updateOrCreate`).
     */
    public function storeException(StoreWorkingDayExceptionRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $exception = WorkingDayException::updateOrCreate(
                [
                    'account_id' => $accountId,
                    'exception_date' => Carbon::parse($validated['exception_date'])->toDateString(),
                ],
                [
                    'is_working' => (bool) $validated['is_working'],
                    'title' => $validated['title'] ?? null,
                    'created_by' => (int) Auth::id(),
                ],
            );

            $exception->load('creator:id,name');

            return $this->successResponse(
                $exception->wasRecentlyCreated
                    ? 'Working-day exception created.'
                    : 'Working-day exception updated.',
                new WorkingDayExceptionResource($exception),
                $exception->wasRecentlyCreated ? 201 : 200,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@storeException');
        }
    }

    /**
     * GET /api/business-working-days/exceptions/{exception}
     */
    public function showException(WorkingDayException $exception): JsonResponse
    {
        try {
            if (! Gate::allows('schedule_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if ((int) $exception->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Working-day exception not found.', 404);
            }

            $exception->load('creator:id,name');

            return $this->successResponse(
                'Working-day exception retrieved successfully.',
                new WorkingDayExceptionResource($exception),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@showException');
        }
    }

    /**
     * PATCH /api/business-working-days/exceptions/{exception}
     */
    public function updateException(UpdateWorkingDayExceptionRequest $request, WorkingDayException $exception): JsonResponse
    {
        try {
            if ((int) $exception->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Working-day exception not found.', 404);
            }

            $exception->update($request->validated());

            return $this->successResponse(
                'Working-day exception updated successfully.',
                new WorkingDayExceptionResource($exception->fresh()->load('creator:id,name')),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@updateException');
        }
    }

    /**
     * DELETE /api/business-working-days/exceptions/{exception}
     */
    public function destroyException(WorkingDayException $exception): JsonResponse
    {
        try {
            if (! Gate::allows('schedule_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $exception->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Working-day exception not found.', 404);
            }

            $exception->delete();

            return $this->successResponse('Working-day exception deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@destroyException');
        }
    }

    /**
     * POST /api/business-working-days/exceptions/bulk
     *
     * Replace-all semantics — mirrors the legacy `saveBusinessWorkingDays`
     * exception handling (delete-all-then-insert). The whole operation is
     * wrapped in a transaction so a partial failure can't half-update state.
     */
    public function bulkReplaceExceptions(BulkReplaceExceptionsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;
            $userId = (int) Auth::id();

            $inserted = DB::transaction(function () use ($validated, $accountId, $userId): int {
                WorkingDayException::where('account_id', $accountId)->delete();

                $count = 0;
                foreach ($validated['exceptions'] as $row) {
                    WorkingDayException::create([
                        'account_id' => $accountId,
                        'exception_date' => Carbon::parse($row['exception_date'])->toDateString(),
                        'is_working' => (bool) $row['is_working'],
                        'title' => $row['title'] ?? null,
                        'created_by' => $userId,
                    ]);
                    $count++;
                }

                return $count;
            });

            return $this->successResponse("Replaced working-day exceptions ({$inserted} rows).", [
                'replaced_count' => $inserted,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\BusinessWorkingDaysController@bulkReplaceExceptions');
        }
    }

    // ── internal helpers ──

    /**
     * @return array<string, bool>
     */
    private function loadWorkingDays(int $accountId): array
    {
        $setting = Settings::where('account_id', $accountId)
            ->where('slug', self::SETTING_SLUG)
            ->first();

        if ($setting && $setting->data) {
            $decoded = json_decode($setting->data, true);
            if (is_array($decoded)) {
                $normalised = [];
                foreach (self::DAYS as $day) {
                    $normalised[$day] = (bool) ($decoded[$day] ?? self::DEFAULTS[$day]);
                }

                return $normalised;
            }
        }

        return self::DEFAULTS;
    }
}
