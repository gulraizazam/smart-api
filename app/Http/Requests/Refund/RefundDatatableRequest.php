<?php

declare(strict_types=1);

namespace App\Http\Requests\Refund;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class RefundDatatableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // Either side is enough — global module list-view or the
        // patient-card scope perm from the Patients audit.
        return $user->can('refunds.list.view') || $user->can('patients_refund_manage');
    }

    public function rules(): array
    {
        return [
            'pagination'         => 'sometimes|array',
            'pagination.perpage' => 'sometimes|integer|min:-1|max:500',
            'pagination.page'    => 'sometimes|integer|min:1',
            'start'              => 'sometimes|integer|min:0',
            'length'             => 'sometimes|integer|min:-1|max:500',
            'draw'               => 'sometimes|integer',
            'sort'               => 'sometimes|array',
            'sort.field'         => 'sometimes|string|max:50',
            'sort.sort'          => 'sometimes|string|in:asc,desc,ASC,DESC',
            'query'              => 'sometimes|nullable',
            'customActionType'   => 'sometimes|string|in:group_action',
            'action'             => 'sometimes',
            'patient_id'         => 'sometimes|integer',
            'package_id'         => 'sometimes|integer',
            'location_id'        => 'sometimes|integer',
            'created_from'       => 'sometimes|date',
            'created_to'         => 'sometimes|date',
            'status'             => 'sometimes|in:0,1',
        ];
    }

    /**
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

        $filterFields = ['patient_id', 'package_id', 'location_id', 'created_from', 'created_to', 'status'];

        foreach ($filterFields as $field) {
            if (!isset($filters[$field]) && $this->has($field)) {
                $filters[$field] = $this->input($field);
            }
        }

        return $filters;
    }

    /**
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

    public function isFilterCancel(): bool
    {
        $action = $this->input('action');

        return is_array($action) && ($action[0] ?? null) === 'filter_cancel';
    }

    public function shouldApplyFilter(): bool
    {
        $action = $this->input('action');

        return $action === 'filter';
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
