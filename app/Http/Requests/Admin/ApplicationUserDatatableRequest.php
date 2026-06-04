<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApplicationUserDatatableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query.search.name' => 'nullable|string|max:255',
            'query.search.email' => 'nullable|string|max:255',
            'query.search.phone' => 'nullable|string|max:20',
            'query.search.gender' => 'nullable|string',
            'query.search.location_id' => 'nullable|string',
            'query.search.role_id' => 'nullable|string',
            'query.search.status' => 'nullable|string',
            'query.search.created_at' => 'nullable|string',
            'query.search.filter' => 'nullable|string',
            // sort.field lands in ORDER BY — restrict to a bare column or
            // prefix.column so injection chars (spaces/parens/;/,) can't pass.
            'sort.field' => ['nullable', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/'],
            'sort.sort' => 'nullable|string|in:asc,desc',
            'pagination.page' => 'nullable|integer|min:1',
            'pagination.perpage' => 'nullable|integer|min:-1|max:500',
        ];
    }

    public function getFilters(): array
    {
        return [
            'name' => $this->input('query.search.name'),
            'email' => $this->input('query.search.email'),
            'phone' => $this->input('query.search.phone'),
            'gender' => $this->input('query.search.gender'),
            'location_id' => $this->input('query.search.location_id'),
            'role_id' => $this->input('query.search.role_id'),
            'status' => $this->input('query.search.status'),
            'created_at' => $this->input('query.search.created_at'),
            'apply_filter' => $this->shouldApplyFilter(),
        ];
    }

    public function getFilterAction(): ?string
    {
        return $this->input('query.search.filter');
    }

    public function shouldApplyFilter(): bool
    {
        return $this->getFilterAction() === 'filter';
    }

    public function shouldCancelFilter(): bool
    {
        return $this->getFilterAction() === 'filter_cancel';
    }

    public function getSortField(): string
    {
        $field = $this->input('sort.field', 'name');

        if (!str_contains($field, '.')) {
            $field = 'users.' . $field;
        }

        return $field;
    }

    public function getSortDirection(): string
    {
        return $this->input('sort.sort', 'asc');
    }

    public function getPage(): int
    {
        return (int) $this->input('pagination.page', 1);
    }

    public function getPerPage(): int
    {
        $perPage = (int) $this->input('pagination.perpage', 30);

        return $perPage < 0 ? 1000 : $perPage;
    }

    public function getOffset(): int
    {
        return ($this->getPage() - 1) * $this->getPerPage();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'data' => null,
            'errors' => $validator->errors()->all(),
        ], 422));
    }
}
