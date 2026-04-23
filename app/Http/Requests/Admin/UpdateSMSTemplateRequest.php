<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSMSTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('sms_templates_edit');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;
        $route = $this->route('smsTemplate');
        $templateId = is_object($route) ? (int) $route->id : (int) $route;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sms_templates', 'name')
                    ->ignore($templateId)
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->whereNull('deleted_at'),
            ],
            'content' => ['required', 'string', 'max:5000'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'SMS template name is required.',
            'content.required' => 'SMS template content is required.',
        ];
    }
}
