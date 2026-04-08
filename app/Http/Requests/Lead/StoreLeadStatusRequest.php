<?php

declare(strict_types=1);
namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreLeadStatusRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return Gate::allows('lead_statuses_create');
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'is_comment' => 'nullable|in:0,1',
            'is_default' => 'nullable|in:0,1',
            'is_arrived' => 'nullable|in:0,1',
            'is_converted' => 'nullable|in:0,1',
            'is_booked' => 'nullable|in:0,1',
            'is_junk' => 'nullable|in:0,1',
            'active' => 'nullable|in:0,1',
        ];
    }

    #[\Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Lead status name is required.',
        ];
    }
}
