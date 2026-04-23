<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SaveAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return $this->isMethod('POST')
            ? Gate::allows('appointment_statuses_create')
            : Gate::allows('appointment_statuses_edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'is_comment' => ['nullable', 'in:0,1'],
            'is_default' => ['nullable', 'in:0,1'],
            'is_arrived' => ['nullable', 'in:0,1'],
            'is_converted' => ['nullable', 'in:0,1'],
            'is_cancelled' => ['nullable', 'in:0,1'],
            'is_unscheduled' => ['nullable', 'in:0,1'],
            'allow_message' => ['nullable', 'in:0,1'],
            'active' => ['nullable', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Appointment status name is required.',
        ];
    }
}
