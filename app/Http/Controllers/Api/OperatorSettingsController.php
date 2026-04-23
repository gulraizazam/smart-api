<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ToggleOperatorTestModeRequest;
use App\Http\Requests\Admin\UpdateOperatorSettingRequest;
use App\Http\Resources\User\GlobalOperatorSettingsResource;
use App\Http\Resources\User\OperatorSettingResource;
use App\Models\GlobalOperatorSettings;
use App\Models\UserOperatorSettings;
use App\Services\UserManagement\UserOperatorSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OperatorSettingsController extends Controller
{
    public function __construct(
        private readonly UserOperatorSettingsService $service,
    ) {}

    /**
     * GET /api/operator-settings
     *
     * Paginated REST list of the caller's account-scoped operator rows.
     * Filter by `search` (operator_name contains).
     *
     * Permission: `user_operator_settings_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('user_operator_settings_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = UserOperatorSettings::query()
                ->where('account_id', $accountId);

            if ($request->filled('search')) {
                $term = '%'.$request->string('search')->trim().'%';
                $query->where('operator_name', 'like', $term);
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderBy('operator_name')->paginate($perPage);

            return $this->successResponse(
                'Operator settings retrieved successfully.',
                [
                    'items' => OperatorSettingResource::collection($paginated->items()),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\OperatorSettingsController@index');
        }
    }

    /**
     * GET /api/operator-settings/{operatorSetting}
     */
    public function show(UserOperatorSettings $operatorSetting): JsonResponse
    {
        try {
            if (! Gate::allows('user_operator_settings_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($operatorSetting)) {
                return $this->errorResponse('Operator setting not found.', 404);
            }

            return $this->successResponse(
                'Operator setting retrieved successfully.',
                new OperatorSettingResource($operatorSetting),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\OperatorSettingsController@show');
        }
    }

    /**
     * PATCH /api/operator-settings/{operatorSetting}
     *
     * Partial update. Empty/missing `password` is dropped from the payload so
     * existing credentials are never overwritten with blanks (matches the
     * legacy UI expectation of "blank means unchanged"). The service layer
     * owns the actual update + audit trail.
     */
    public function update(UpdateOperatorSettingRequest $request, UserOperatorSettings $operatorSetting): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($operatorSetting)) {
                return $this->errorResponse('Operator setting not found.', 404);
            }

            $data = $request->validated();

            if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
                unset($data['password']);
            }

            if (empty($data)) {
                return $this->errorResponse('No fields to update.', 422);
            }

            $updated = $this->service->update((int) $operatorSetting->id, $data);

            if (! $updated) {
                return $this->errorResponse('Operator setting not found.', 404);
            }

            return $this->successResponse(
                'Operator setting updated successfully.',
                new OperatorSettingResource($updated->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\OperatorSettingsController@update');
        }
    }

    /**
     * PATCH /api/operator-settings/{operatorSetting}/test-mode
     *
     * Convenience endpoint to flip the sandbox flag without having to re-send
     * every field. Useful for admin toggles on mobile.
     */
    public function toggleTestMode(ToggleOperatorTestModeRequest $request, UserOperatorSettings $operatorSetting): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($operatorSetting)) {
                return $this->errorResponse('Operator setting not found.', 404);
            }

            $updated = $this->service->update((int) $operatorSetting->id, [
                'test_mode' => (int) $request->validated()['test_mode'],
            ]);

            if (! $updated) {
                return $this->errorResponse('Operator setting not found.', 404);
            }

            return $this->successResponse(
                'Test mode updated successfully.',
                new OperatorSettingResource($updated->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\OperatorSettingsController@toggleTestMode');
        }
    }

    /**
     * GET /api/operator-settings/global
     *
     * List of global operator templates — reference data used to pre-fill
     * account-level operator configs. Read-only.
     */
    public function globalIndex(): JsonResponse
    {
        try {
            if (! Gate::allows('user_operator_settings_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $templates = GlobalOperatorSettings::query()
                ->orderBy('operator_name')
                ->get();

            return $this->successResponse(
                'Global operator templates retrieved successfully.',
                GlobalOperatorSettingsResource::collection($templates),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\OperatorSettingsController@globalIndex');
        }
    }

    /**
     * GET /api/operator-settings/global/{globalOperator}
     *
     * Detail for a single global operator template. Replaces the legacy
     * `loadOperator` POST endpoint with a REST-friendly shape.
     */
    public function globalShow(GlobalOperatorSettings $globalOperator): JsonResponse
    {
        try {
            if (! Gate::allows('user_operator_settings_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            return $this->successResponse(
                'Global operator template retrieved successfully.',
                new GlobalOperatorSettingsResource($globalOperator),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\OperatorSettingsController@globalShow');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(UserOperatorSettings $operator): bool
    {
        return (int) $operator->account_id === (int) Auth::user()->account_id;
    }
}
