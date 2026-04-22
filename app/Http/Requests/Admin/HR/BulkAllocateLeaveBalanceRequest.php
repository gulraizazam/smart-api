<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkAllocateLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('hr_leave_manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'leave_type_id' => [
                'required',
                'integer',
                Rule::exists('leave_types', 'id')
                    ->where(fn ($q) => $q->where('account_id', $accountId)->whereNull('deleted_at')),
            ],
            'allocated' => ['required', 'numeric', 'min:0', 'max:365'],
            'fiscal_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/', 'max:7'],
        ];
    }
}
