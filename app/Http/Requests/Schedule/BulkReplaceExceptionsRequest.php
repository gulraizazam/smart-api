<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class BulkReplaceExceptionsRequest extends FormRequest
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
            'exceptions' => ['present', 'array', 'max:365'],
            'exceptions.*.exception_date' => ['required', 'date', 'distinct'],
            'exceptions.*.is_working' => ['required', 'boolean'],
            'exceptions.*.title' => ['nullable', 'string', 'max:255'],
        ];
    }
}
