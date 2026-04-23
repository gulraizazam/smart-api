<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SMSTemplateStatusRequest;
use App\Http\Requests\Admin\StoreSMSTemplateRequest;
use App\Http\Requests\Admin\UpdateSMSTemplateRequest;
use App\Http\Resources\SMSTemplate\SMSTemplateResource;
use App\Models\SMSTemplates;
use App\Services\SMS\SMSTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SMSTemplatesController extends Controller
{
    public function __construct(
        private readonly SMSTemplateService $service,
    ) {}

    /**
     * GET /api/sms_templates
     *
     * Paginated account-scoped list. Callers without
     * `view_inactive_smstemplates` see active rows only — mirrors the
     * legacy datatable behaviour.
     *
     * Permission: `sms_templates_manage`.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'slug' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = SMSTemplates::query()->where('account_id', $accountId);

            if (! Gate::allows('view_inactive_smstemplates')) {
                $query->where('active', 1);
            } elseif ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }

            if ($request->filled('slug')) {
                $query->where('slug', 'like', '%'.$request->string('slug')->trim().'%');
            }

            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('id')->paginate($perPage);

            SMSTemplateResource::$includeVariables = false;

            return $this->successResponse(
                'SMS templates retrieved successfully.',
                [
                    'items' => SMSTemplateResource::collection($paginated->items()),
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
            return $this->handleException($e, 'Api\\SMSTemplatesController@index');
        }
    }

    /**
     * GET /api/sms_templates/dropdown
     *
     * Active templates as `{ id, name, slug }[]` — the shape mobile
     * pickers expect.
     */
    public function dropdown(): JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $templates = SMSTemplates::query()
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);

            return $this->successResponse(
                'SMS templates dropdown retrieved successfully.',
                $templates->map(fn (SMSTemplates $t): array => [
                    'id' => (int) $t->id,
                    'name' => (string) $t->name,
                    'slug' => $t->slug,
                ])->values(),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\SMSTemplatesController@dropdown');
        }
    }

    /**
     * POST /api/sms_templates/create
     *
     * Custom templates are saved with `slug='custom'` — matches the legacy
     * `SMSTemplates::createRecord` behaviour. Variable set is derived from
     * the slug and can't be injected by the client.
     */
    public function store(StoreSMSTemplateRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;

            $result = $this->service->validateAndCreate($request->validated(), $accountId);

            if (! $result['success']) {
                return $this->errorResponse(
                    (string) ($result['error'] ?? 'Something went wrong.'),
                    500,
                    isset($result['errors']) ? (array) $result['errors'] : [],
                );
            }

            $record = SMSTemplates::query()
                ->where('account_id', $accountId)
                ->orderByDesc('id')
                ->first();

            SMSTemplateResource::$includeVariables = true;

            return $this->successResponse(
                (string) $result['message'],
                $record ? new SMSTemplateResource($record) : null,
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\SMSTemplatesController@store');
        }
    }

    /**
     * GET /api/sms_templates/{smsTemplate}
     *
     * Returns the template including the list of allowed variables for
     * the template's slug (so the UI can render a variable picker).
     */
    public function show(SMSTemplates $smsTemplate): JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($smsTemplate)) {
                return $this->errorResponse('SMS template not found.', 404);
            }

            SMSTemplateResource::$includeVariables = true;

            return $this->successResponse(
                'SMS template retrieved successfully.',
                new SMSTemplateResource($smsTemplate),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\SMSTemplatesController@show');
        }
    }

    /**
     * GET /api/sms_templates/{smsTemplate}/variables
     *
     * Returns just the allowed variable list for a template slug — lighter
     * alternative to `show` when the UI only needs the variable picker.
     */
    public function variables(SMSTemplates $smsTemplate): JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($smsTemplate)) {
                return $this->errorResponse('SMS template not found.', 404);
            }

            return $this->successResponse(
                'Template variables retrieved successfully.',
                [
                    'slug' => $smsTemplate->slug,
                    'variables' => GeneralFunctions::smsTemplateVariables($smsTemplate->slug) ?? [],
                ],
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\SMSTemplatesController@variables');
        }
    }

    /**
     * PATCH /api/sms_templates/{smsTemplate}
     */
    public function update(UpdateSMSTemplateRequest $request, SMSTemplates $smsTemplate): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($smsTemplate)) {
                return $this->errorResponse('SMS template not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;

            $result = $this->service->validateAndUpdate(
                $request->validated(),
                (int) $smsTemplate->id,
                $accountId,
            );

            if (! $result['success']) {
                return $this->errorResponse(
                    (string) ($result['error'] ?? 'Something went wrong.'),
                    404,
                    isset($result['errors']) ? (array) $result['errors'] : [],
                );
            }

            SMSTemplateResource::$includeVariables = true;

            return $this->successResponse(
                (string) $result['message'],
                new SMSTemplateResource($smsTemplate->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\SMSTemplatesController@update');
        }
    }

    /**
     * PATCH /api/sms_templates/{smsTemplate}/status
     *
     * Activate (`status=1`) or deactivate (`status=0`). Gates enforced in
     * the Form Request — `sms_templates_active` vs `sms_templates_inactive`.
     */
    public function status(SMSTemplateStatusRequest $request, SMSTemplates $smsTemplate): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($smsTemplate)) {
                return $this->errorResponse('SMS template not found.', 404);
            }

            $result = $this->service->changeStatus(
                (int) $smsTemplate->id,
                (int) $request->validated()['status'],
            );

            if (! $result['success']) {
                return $this->errorResponse((string) ($result['error'] ?? 'Something went wrong.'), 404);
            }

            SMSTemplateResource::$includeVariables = false;

            return $this->successResponse(
                (string) $result['message'],
                new SMSTemplateResource($smsTemplate->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\SMSTemplatesController@status');
        }
    }

    /**
     * DELETE /api/sms_templates/{smsTemplate}
     *
     * Soft-delete (legacy admin UI did not wire this, but the service
     * method exists and is safe — no child-record guard is needed for
     * templates).
     */
    public function destroy(SMSTemplates $smsTemplate): JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($smsTemplate)) {
                return $this->errorResponse('SMS template not found.', 404);
            }

            $result = $this->service->deleteTemplate((int) $smsTemplate->id);

            if (! $result['success']) {
                return $this->errorResponse((string) ($result['error'] ?? 'Something went wrong.'), 404);
            }

            return $this->successResponse((string) $result['message']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\SMSTemplatesController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(SMSTemplates $smsTemplate): bool
    {
        return (int) $smsTemplate->account_id === (int) Auth::user()->account_id;
    }
}
