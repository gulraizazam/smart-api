<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FeedbackDatatableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('feedbacks_manage');
    }

    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.patient_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter.location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'filter.doctor_id' => ['nullable', 'integer', 'exists:users,id'],
            'filter.created_at' => ['nullable', 'string'],
            'pagination' => ['nullable', 'array'],
            'pagination.page' => ['nullable', 'integer', 'min:1'],
            'pagination.perpage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
