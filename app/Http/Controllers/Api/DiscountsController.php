<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Discount\AllocateConfigurableRequest;
use App\Http\Requests\Discount\AllocateDiscountRequest;
use App\Http\Requests\Discount\DiscountDatatableRequest;
use App\Http\Requests\Discount\ToggleStatusRequest;
use App\Http\Requests\Discount\UpdateDiscountRequest;
use App\Services\Discount\DiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Traits\SimpleApiResponse;
use Illuminate\Support\Facades\Validator;

final class DiscountsController extends Controller
{
    use SimpleApiResponse;

    public function __construct(
        private readonly DiscountService $discountService,
    ) {}

    // ── Create Form Data ─────────────────────────────────

    public function create(): JsonResponse
    {
        if (Gate::denies('discounts_create')) {
            return $this->unauthorized();
        }

        try {
            $data = $this->discountService->getCreateFormData();

            return $this->success('Record found', $data);
        } catch (\Throwable $e) {
            Log::error('Discount Create Form Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Store ────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        if (Gate::denies('discounts_create')) {
            return $this->unauthorized();
        }

        try {
            $isConfigurable = $request->input('type') === 'Configurable';

            // Validate based on discount type
            $validator = $isConfigurable
                ? $this->validateConfigurableStore($request)
                : $this->validateSimpleStore($request);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    'data'    => null,
                ], 200);
            }

            $data               = $request->all();
            $data['account_id'] = Auth::user()->account_id;

            if (in_array($data['slug'] ?? '', ['custom', 'default'], true)) {
                $data['pre_days']  = 0;
                $data['post_days'] = 0;
            }

            $data['active'] = $data['active'] ?? '0';

            if ($isConfigurable) {
                $this->discountService->createConfigurableDiscount($data);
            } else {
                $this->discountService->createSimpleDiscount($data);
            }

            return $this->success('Record has been created successfully.');
        } catch (\Throwable $e) {
            Log::error('Discount Store Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Edit Form Data ───────────────────────────────────

    public function edit(int $id): JsonResponse
    {
        if (Gate::denies('discounts_edit')) {
            return $this->unauthorized();
        }

        try {
            $data = $this->discountService->getEditFormData($id);

            if (!$data) {
                return $this->success('Resource not found.', null, false);
            }

            return $this->success('Record found', $data);
        } catch (\Throwable $e) {
            Log::error('Discount Edit Form Error', ['message' => $e->getMessage(), 'id' => $id]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Update ───────────────────────────────────────────

    public function update(UpdateDiscountRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->all();

            if (in_array($data['slug'] ?? '', ['custom', 'default'], true)) {
                $data['pre_days']  = 0;
                $data['post_days'] = 0;
            }

            $data['active'] = $data['active'] ?? '0';

            if (($data['type'] ?? '') === 'Configurable') {
                $this->discountService->updateConfigurableDiscount($data, $id);
                return $this->success('Record has been updated successfully.');
            }

            $this->discountService->updateSimpleDiscount($data, $id);

            return $this->success('Record has been updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Discount Update Error', ['message' => $e->getMessage(), 'id' => $id]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Toggle Status ────────────────────────────────────

    public function status(ToggleStatusRequest $request): JsonResponse
    {
        try {
            $result = $this->discountService->toggleStatus(
                (int) $request->validated('id'),
                (int) $request->validated('status'),
            );

            if ($result) {
                return $this->success('Status has been changed successfully.');
            }

            return $this->success('Resource not found.', null, false);
        } catch (\Throwable $e) {
            Log::error('Discount Status Toggle Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Destroy ──────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        if (Gate::denies('discounts_destroy')) {
            return $this->unauthorized();
        }

        try {
            $message   = $this->discountService->deleteDiscount($id);
            $isDeleted = $message === 'Record has been deleted successfully.';

            return $this->success($message, null, $isDeleted);
        } catch (\Throwable $e) {
            Log::error('Discount Delete Error', ['message' => $e->getMessage(), 'id' => $id]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Datatable ────────────────────────────────────────

    public function datatable(DiscountDatatableRequest $request): JsonResponse
    {
        try {
            $filters      = $request->filters();
            $applyFilter  = checkFilters($filters, 'discounts');

            $bulkDeleted = false;

            // Handle bulk delete via filter
            if (hasFilter($filters, 'delete')) {
                $ids = array_filter(array_map('intval', explode(',', $filters['delete'])));
                if (!empty($ids)) {
                    \App\Models\Discount::whereIn('id', $ids)->delete();
                    $bulkDeleted = true;
                }
            }

            $datatableData = $this->discountService->getDatatableData($filters, $applyFilter);
            $pagination    = $request->paginationParams($datatableData['total']);

            [$orderBy, $order] = getSortBy($request);

            $rows = $this->discountService->getDatatableRows(
                $datatableData['where'],
                $pagination['start'],
                $pagination['perpage'],
                $request->input('startdate'),
                $request->input('enddate'),
                $filters,
            );

            $totalPages = $pagination['perpage'] > 0
                ? (int) ceil($datatableData['total'] / $pagination['perpage'])
                : 1;

            $response = [
                'data'           => $rows,
                'meta'           => [
                    'field'   => $orderBy,
                    'page'    => $pagination['page'],
                    'pages'   => $totalPages,
                    'perpage' => $pagination['perpage'],
                    'total'   => $datatableData['total'],
                    'sort'    => $order,
                ],
                'permissions'    => $this->resolvePermissions(),
                'filter_values'  => $datatableData['filter_values'],
                'active_filters' => Filters::all(Auth::id(), 'discounts'),
            ];

            if ($bulkDeleted) {
                $response['status']  = true;
                $response['message'] = 'Records has been deleted successfully!';
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            // Round 4 Crypto-H3 — drop trace, keep file/line.
            Log::error('Discount Datatable Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'meta' => ['total' => 0],
                'data' => [],
            ], 500);
        }
    }

    // ── Allocation Endpoints ─────────────────────────────

    public function displayLocation(int $id): JsonResponse
    {
        if (Gate::denies('discounts_allocate')) {
            return $this->unauthorized();
        }

        try {
            $data = $this->discountService->getAllocationData($id);

            return $this->success('Service Allocated', $data);
        } catch (\Throwable $e) {
            Log::error('Discount Allocation Display Error', ['message' => $e->getMessage(), 'id' => $id]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    public function getServices(Request $request): JsonResponse
    {
        if (Gate::denies('discounts_allocate')) {
            return $this->unauthorized();
        }

        try {
            // Accept either `location_id` (used by the SPA, the standard
            // name) or the legacy `id` parameter as a fallback so any
            // older caller still works.
            $locationId = (int) ($request->input('location_id') ?? $request->input('id'));
            $data = $this->discountService->getServicesForLocation(
                (int) $request->input('discount_id'),
                $locationId,
            );

            return $this->success('Record found', $data);
        } catch (\Throwable $e) {
            Log::error('Discount Get Services Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    public function getDiscountServices(Request $request): JsonResponse
    {
        if (Gate::denies('discounts_allocate')) {
            return $this->unauthorized();
        }

        try {
            $data = $this->discountService->getDiscountServicesForWidget((int) $request->input('id'));
            $data['locaiton_id_1'] = $request->input('id');

            return $this->success('Record found', $data);
        } catch (\Throwable $e) {
            Log::error('Discount Services Widget Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    public function getServicesForConfigurable(): JsonResponse
    {
        if (Gate::denies('discounts_create') && Gate::denies('discounts_edit')) {
            return $this->unauthorized();
        }

        try {
            $data = $this->discountService->getServicesForConfigurable();

            return $this->success('Services loaded', $data);
        } catch (\Throwable $e) {
            Log::error('Configurable Services Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    public function allocateConfigurable(AllocateConfigurableRequest $request): JsonResponse
    {
        try {
            $result = $this->discountService->allocateConfigurable(
                (int) $request->validated('discount_id'),
                (int) $request->validated('location_id'),
            );

            return response()->json([
                'status'  => $result['success'],
                'message' => $result['message'],
                'data'    => [
                    'record' => $result['record'] ?? null,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Allocate Configurable Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    public function saveAllocations(AllocateDiscountRequest $request): JsonResponse
    {
        try {
            $result = $this->discountService->saveAllocations($request->all());

            return response()->json([
                'status'  => $result['success'],
                'message' => $result['message'],
                'data'    => [
                    'records'     => $result['records'],
                    'removed_ids' => $result['removed_ids'],
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Save Allocations Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    public function deleteAllocation(Request $request): JsonResponse
    {
        if (Gate::denies('discounts_allocate')) {
            return $this->unauthorized();
        }

        try {
            $this->discountService->deleteAllocation((int) $request->input('id'));

            return $this->success('Row deleted', ['id' => $request->input('id')]);
        } catch (\Throwable $e) {
            Log::error('Delete Allocation Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    public function deleteAllocationGroup(Request $request): JsonResponse
    {
        if (Gate::denies('discounts_allocate')) {
            return $this->unauthorized();
        }

        try {
            $ids          = explode(',', $request->input('ids', ''));
            $deletedCount = $this->discountService->deleteAllocationGroup($ids);

            return $this->success($deletedCount . ' allocation(s) deleted successfully.', ['deleted_ids' => $ids]);
        } catch (\Throwable $e) {
            Log::error('Delete Allocation Group Error', ['message' => $e->getMessage()]);
            return $this->error('Something went wrong, please try again later.');
        }
    }

    // ── Private Helpers ──────────────────────────────────

    private function resolvePermissions(): array
    {
        return [
            'edit'     => Gate::allows('discounts_edit'),
            'delete'   => Gate::allows('discounts_destroy'),
            'active'   => Gate::allows('discounts_active'),
            'inactive' => Gate::allows('discounts_inactive'),
            'create'   => Gate::allows('discounts_create'),
            'allocate' => Gate::allows('discounts_allocate'),
        ];
    }


    // ── Validation ───────────────────────────────────────

    private function validateSimpleStore(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'name'          => ['required', 'string', 'max:255'],
            'discount_type' => ['required', 'string'],
            'start'         => ['required', 'date'],
            'end'           => ['required', 'date', 'after_or_equal:start'],
            'roles'         => ['required', 'array'],
            'roles.*'       => ['exists:roles,id'],
        ]);
    }

    private function validateConfigurableStore(Request $request): \Illuminate\Validation\Validator
    {
        // The SPA creates Configurable discounts bare — name, dates, roles
        // only — and then issues a follow-up PUT from the allocation dialog
        // with the actual Buy/Get rules (UpdateDiscountRequest already
        // tolerates rule-less Configurable updates the same way). Mirror
        // that here: validate the metadata, and only enforce rule-level
        // shape when the operator actually submits rules in this request.
        $rules = [
            'name'         => ['required', 'string', 'max:255'],
            'type'         => ['required', 'string'],
            'start'        => ['required', 'date'],
            'end'          => ['required', 'date', 'after_or_equal:start'],
            'sessions_buy' => ['nullable', 'integer', 'min:1'],
            'base_service' => ['nullable'],
        ];

        $sessions = $request->input('sessions', []);
        foreach ($sessions as $key => $value) {
            $rules["sessions.{$key}"]  = ['required'];
            $rules["disc_type.{$key}"] = ['required'];

            $sameService = $request->input("same_service.{$key}");
            if (!$sameService) {
                $rules["services_name.{$key}"] = ['required'];
            }
        }

        return Validator::make($request->all(), $rules);
    }
}
