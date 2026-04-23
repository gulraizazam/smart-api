<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCentreTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('centre_targets_create');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'working_days' => ['nullable', 'integer', 'min:0', 'max:31'],
            'target_amount' => ['required', 'array', 'min:1'],
            'target_amount.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_amount.required' => 'At least one centre target amount is required.',
            'target_amount.*.numeric' => 'Each target amount must be a valid number.',
        ];
    }
}
