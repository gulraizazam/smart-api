<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PermissionDatatableRequest extends FormRequest
{
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
            'query.search.search' => 'nullable|string|max:255',
            'query.search.delete' => 'nullable|string',
            'query.search.filter' => 'nullable|string|in:filter,filter_cancel',
            'query.search.parent_id' => 'nullable|string',
            'sort.field' => 'nullable|string|in:id,name,title,parent.name,created_at',
            'sort.sort' => 'nullable|string|in:asc,desc',
            'pagination.page' => 'nullable|integer|min:1',
            'pagination.perpage' => 'nullable|integer|min:-1|max:500',
        ];
    }

    /**
     * Get search term from request
     */
    public function getSearchTerm(): ?string
    {
        return $this->input('query.search.search');
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

        return array_filter(array_map('intval', explode(',', $deleteString)));
    }

    /**
     * Get filter action from request
     */
    public function getFilterAction(): ?string
    {
        return $this->input('query.search.filter');
    }

    /**
     * Check if filter should be cancelled
     */
    public function shouldCancelFilter(): bool
    {
        return $this->getFilterAction() === 'filter_cancel';
    }

    /**
     * Get parent_id filter from request
     */
    public function getParentId(): ?string
    {
        return $this->input('query.search.parent_id');
    }

    /**
     * Get sort field
     */
    public function getSortField(): string
    {
        return $this->input('sort.field', 'name');
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
     * Get per page limit
     */
    public function getPerPage(): int
    {
        $perPage = (int) $this->input('pagination.perpage', 30);
        return $perPage < 0 ? 1000 : $perPage; // -1 means all records
    }

    /**
     * Get offset for pagination
     */
    public function getOffset(): int
    {
        return ($this->getPage() - 1) * $this->getPerPage();
    }
}
