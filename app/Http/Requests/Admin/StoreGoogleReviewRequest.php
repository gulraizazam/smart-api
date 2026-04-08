<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoogleReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctor_id'    => 'required|integer|exists:users,id',
            'month'        => 'required|integer|min:1|max:12',
            'year'         => 'required|integer|min:2020',
            'review_count' => 'required|integer|min:0',
        ];
    }
}
