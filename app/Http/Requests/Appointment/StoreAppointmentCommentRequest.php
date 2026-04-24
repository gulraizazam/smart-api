<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAppointmentCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'appointment_id' => [
                'required',
                'integer',
                Rule::exists('appointments', 'id')
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at'),
            ],
            'comment' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => 'Appointment is required.',
            'appointment_id.exists' => 'Invalid appointment selected.',
            'comment.required' => 'Comment is required.',
            'comment.max' => 'Comment may not exceed 5000 characters.',
        ];
    }

}
