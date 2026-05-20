<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class PlanDatatableRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Used by both the patient-card datatable and the global /plans
        // page. Either gate is sufficient — the controller routes the
        // request to the appropriate scope.
        return ($this->user()?->can('plans.list.view')
            || $this->user()?->can('patients.plans.view')) ?? false;
    }

    public function rules(): array
    {
        return [
            'pagination'          => 'sometimes|array',
            'pagination.perpage'  => 'sometimes|integer|min:-1|max:500',
            'pagination.page'    => 'sometimes|integer|min:1',
            'start'              => 'sometimes|integer|min:0',
            'length'             => 'sometimes|integer|min:-1|max:500',
            'draw'               => 'sometimes|integer',
            'sort'               => 'sometimes|array',
            'sort.field'         => 'sometimes|string|max:50',
            'sort.sort'          => 'sometimes|string|in:asc,desc,ASC,DESC',
            'query'              => 'sometimes|nullable',
            'customActionType'   => 'sometimes|string|in:group_action',
            'id'                 => 'sometimes|array',
            'id.*'               => 'sometimes|integer',
            'action'             => 'sometimes',
            'package_id'         => 'sometimes|integer',
            'location_id'        => 'sometimes|integer',
            'status'             => 'sometimes|in:0,1',
            'created_at'         => 'sometimes|string|max:50',
            // String-typed because the SPA may send `C-<id>` or `P-<id>`
            // along with raw numeric ids. PlanService::addPatientFilter
            // unwraps the prefix via `GeneralFunctions::patientSearch`
            // before applying the where clause.
            'patient_id'         => ['sometimes', 'regex:/^([CcPp]-)?\d+$/'],
            // Unified search box. Server decides which column to filter
            // on based on shape — see PlanService::applyUnifiedSearch.
            // 100 chars is plenty for any name + phone composite.
            'q'                  => 'sometimes|string|max:100',
            'delete'             => 'sometimes|string',
        ];
    }

    /**
     * Extract normalized filters from request (supports KTDatatable + DataTables formats).
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $filters = [];

        $query = $this->input('query', []);

        if (isset($query['search']) && is_array($query['search'])) {
            $filters = $query['search'];
        } elseif (is_array($query)) {
            $filters = $query;
        }

        if ($this->has('sort')) {
            $filters['sort'] = $this->input('sort');
        }

        if ($this->has('action')) {
            $filters['action'] = $this->input('action');
        }

        $filterFields = ['package_id', 'location_id', 'status', 'created_at', 'patient_id', 'q'];

        foreach ($filterFields as $field) {
            if (!isset($filters[$field]) && $this->has($field)) {
                $filters[$field] = $this->input($field);
            }
        }

        if (isset($query['generalSearch']) && $query['generalSearch'] !== '') {
            $filters['generalSearch'] = $query['generalSearch'];
        }

        return $filters;
    }

    /**
     * Extract pagination parameters (supports KTDatatable + DataTables formats).
     *
     * @return array{perpage: int, page: int, start: int}
     */
    public function paginationParams(int $totalRecords = 0): array
    {
        $pagination = $this->input('pagination', []);
        $perpage = (int) ($pagination['perpage'] ?? $this->input('length', 30));
        $perpage = $perpage < 0 ? $totalRecords : $perpage;
        $page = (int) ($pagination['page'] ?? 1);
        $start = (int) $this->input('start', ($page - 1) * $perpage);

        return compact('perpage', 'page', 'start');
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data'    => null,
                'errors'  => $validator->errors()->toArray(),
            ], 422)
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this resource.',
                'data'    => null,
                'errors'  => [],
            ], 403)
        );
    }
}
