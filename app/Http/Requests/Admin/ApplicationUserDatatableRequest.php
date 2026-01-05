<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApplicationUserDatatableRequest extends FormRequest
{
    /**
     * Handle a failed validation attempt - return JSON for AJAX requests
     */
    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'data' => [],
                'meta' => ['total' => 0, 'page' => 1, 'pages' => 1, 'perpage' => 30],
                'message' => $validator->errors()->first(),
            ], 200));
        }
        
        parent::failedValidation($validator);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'query.search.name' => 'nullable|string|max:255',
            'query.search.email' => 'nullable|string|max:255',
            'query.search.phone' => 'nullable|string|max:20',
            'query.search.gender' => 'nullable|string',
            'query.search.commission' => 'nullable|string',
            'query.search.location_id' => 'nullable|string',
            'query.search.role_id' => 'nullable|string',
            'query.search.status' => 'nullable|string',
            'query.search.created_at' => 'nullable|string',
            'query.search.delete' => 'nullable|string',
            'query.search.filter' => 'nullable|string',
            'sort.field' => 'nullable|string',
            'sort.sort' => 'nullable|string|in:asc,desc',
            'pagination.page' => 'nullable|integer|min:1',
            'pagination.perpage' => 'nullable|integer|min:-1|max:500',
        ];
    }

    /**
     * Get all search filters from request
     */
    public function getFilters(): array
    {
        return [
            'name' => $this->input('query.search.name'),
            'email' => $this->input('query.search.email'),
            'phone' => $this->input('query.search.phone'),
            'gender' => $this->input('query.search.gender'),
            'commission' => $this->input('query.search.commission'),
            'location_id' => $this->input('query.search.location_id'),
            'role_id' => $this->input('query.search.role_id'),
            'status' => $this->input('query.search.status'),
            'created_at' => $this->input('query.search.created_at'),
            'apply_filter' => $this->shouldApplyFilter(),
        ];
    }

    /**
     * Get delete IDs from request
     */
    public function getDeleteIds(): array
    {
        $deleteString = $this->input('query.search.delete');
        if (empty($deleteString)) {
            return [];
        }
        return array_filter(explode(',', $deleteString));
    }

    /**
     * Get filter action
     */
    public function getFilterAction(): ?string
    {
        return $this->input('query.search.filter');
    }

    /**
     * Check if filter should be applied
     */
    public function shouldApplyFilter(): bool
    {
        return $this->getFilterAction() === 'filter';
    }

    /**
     * Check if filter should be cancelled
     */
    public function shouldCancelFilter(): bool
    {
        return $this->getFilterAction() === 'filter_cancel';
    }

    /**
     * Get sort field
     */
    public function getSortField(): string
    {
        $field = $this->input('sort.field', 'name');
        // Prefix with users. for joined queries
        if (!str_contains($field, '.')) {
            $field = 'users.' . $field;
        }
        return $field;
    }

    /**
     * Get sort direction
     */
    public function getSortDirection(): string
    {
        return $this->input('sort.sort', 'asc');
    }

    /**
     * Get current page
     */
    public function getPage(): int
    {
        return (int) $this->input('pagination.page', 1);
    }

    /**
     * Get per page count
     */
    public function getPerPage(): int
    {
        $perPage = (int) $this->input('pagination.perpage', 30);
        return $perPage < 0 ? 1000 : $perPage;
    }

    /**
     * Get offset for query
     */
    public function getOffset(): int
    {
        return ($this->getPage() - 1) * $this->getPerPage();
    }
}
