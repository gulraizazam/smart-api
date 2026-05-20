<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UpdateBusinessWorkingDaysRequest extends FormRequest
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
            'working_days' => ['required', 'array'],
            'working_days.monday' => ['required', 'boolean'],
            'working_days.tuesday' => ['required', 'boolean'],
            'working_days.wednesday' => ['required', 'boolean'],
            'working_days.thursday' => ['required', 'boolean'],
            'working_days.friday' => ['required', 'boolean'],
            'working_days.saturday' => ['required', 'boolean'],
            'working_days.sunday' => ['required', 'boolean'],
        ];
    }
}
