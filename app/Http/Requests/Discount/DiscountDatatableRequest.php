<?php

declare(strict_types=1);

namespace App\Http\Requests\Discount;

use Illuminate\Foundation\Http\FormRequest;

final class DiscountDatatableRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'startdate' => ['nullable', 'date'],
            'enddate'   => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return getFilters($this->all());
    }

    /**
     * @return array{start: int, perpage: int, page: int}
     */
    public function paginationParams(int $total): array
    {
        [$perpage, $start, $pages, $page] = getPaginationElement($this, $total);

        return [
            'start'   => $start,
            'perpage' => $perpage,
            'pages'   => $pages,
            'page'    => $page,
        ];
    }
}
