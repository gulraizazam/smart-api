<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service apply-leave request. Mirrors MyHrmController@applyLeave rules.
 *
 * The enum values match App\Enums\LeaveDurationType (full, half, short, custom).
 * Hour cap vs shift_hours is enforced in the controller because it depends on
 * the employee's profile, not static rules.
 */
class ApplyLeaveRequest extends FormRequest
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
