<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UpdateWorkingDayExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('business_working_days.edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'exception_date' => ['sometimes', 'required', 'date'],
            'is_working' => ['sometimes', 'required', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
