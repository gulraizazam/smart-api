<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service update-own-leave request. Same shape as ApplyLeaveRequest —
 * ownership + pending-status guard live in the controller.
 */
class UpdateMyLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'leave_type_id' => ['required', 'integer', "exists:leave_types,id,account_id,{$accountId}"],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_type' => ['required', 'in:full,half,short,custom'],
            'custom_hours' => ['nullable', 'required_if:duration_type,custom', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
