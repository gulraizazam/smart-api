<?php

declare(strict_types=1);
namespace App\Http\Requests\Schedule;

use App\Models\Locations;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessClosureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return \Illuminate\Support\Facades\Gate::allows('business_closures.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === 'all') {
                    return;
                }
                if (! is_numeric($value) || ! Locations::whereKey((int) $value)->exists()) {
                    $fail('The selected location is invalid.');
                }
            }],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'location_ids.required' => 'Please select at least one location.',
            'location_ids.min' => 'Please select at least one location.',
            'start_date.required' => 'Start date is required.',
            'end_date.required' => 'End date is required.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }
}
