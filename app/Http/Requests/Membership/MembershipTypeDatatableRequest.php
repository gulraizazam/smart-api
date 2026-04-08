<?php

declare(strict_types=1);

namespace App\Http\Requests\Membership;

use Illuminate\Foundation\Http\FormRequest;

final class MembershipTypeDatatableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return getFilters($this->all());
    }

    /**
     * @return array{start: int, perpage: int, pages: int, page: int}
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
