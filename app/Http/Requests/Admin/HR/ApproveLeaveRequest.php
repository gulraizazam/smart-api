<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ApproveLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('hr_leave_approve');
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
