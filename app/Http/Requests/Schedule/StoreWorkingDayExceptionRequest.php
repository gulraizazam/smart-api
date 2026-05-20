<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StoreWorkingDayExceptionRequest extends FormRequest
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
            'exception_date' => ['required', 'date'],
            'is_working' => ['required', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
