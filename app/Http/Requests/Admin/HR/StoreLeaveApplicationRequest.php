<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * HR-on-behalf apply-leave request. Extends the self-service rules with
 * `user_id`, which must belong to the caller's account. The balance /
 * overlap / shift-hours checks live in the controller because they depend
 * on the target employee's profile, matching the self-service flow.
 */
class StoreLeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('hr_leave_approve');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists('leave_types', 'id')
                    ->where(fn ($q) => $q->where('account_id', $accountId)->whereNull('deleted_at')),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'duration_type' => ['required', 'in:full,half,short,custom'],
            'custom_hours' => ['nullable', 'required_if:duration_type,custom', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
            'auto_approve' => ['nullable', 'boolean'],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
