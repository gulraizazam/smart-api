<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RoleDatatableRequest extends FormRequest
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
            'query.search.name' => 'nullable|string|max:255',
            'query.search.commission' => 'nullable|numeric',
            'query.search.delete' => 'nullable|string',
            'query.search.filter' => 'nullable|string|in:filter,filter_cancel',
            'sort.field' => 'nullable|string|in:id,name,commission,created_at',
            'sort.sort' => 'nullable|string|in:asc,desc',
            'pagination.page' => 'nullable|integer|min:1',
            'pagination.perpage' => 'nullable|integer|min:-1|max:500',
        ];
    }

    /**
     * Get name filter from request
     */
    public function getNameFilter(): ?string
    {
        return $this->input('query.search.name');
    }

    /**
     * Get commission filter from request
     */
    public function getCommissionFilter(): ?string
    {
        return $this->input('query.search.commission');
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
