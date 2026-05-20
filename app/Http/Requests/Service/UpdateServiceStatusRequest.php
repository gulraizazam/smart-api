<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateServiceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('services.activate') || Gate::allows('services.deactivate');
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'id'     => 'required|integer|exists:services,id',
            'status' => 'required|in:0,1',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required'     => 'Service ID is required.',
            'id.exists'       => 'Service not found.',
            'status.required' => 'Status is required.',
            'status.in'       => 'Status must be 0 (inactive) or 1 (active).',
        ];
    }
}
