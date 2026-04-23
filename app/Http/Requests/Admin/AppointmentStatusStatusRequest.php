<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AppointmentStatusStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return (int) $this->input('status') === 1
            ? Gate::allows('appointment_statuses_active')
            : Gate::allows('appointment_statuses_inactive');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
