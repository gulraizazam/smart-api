<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Packages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PackageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same policy the legacy admin controller uses for status toggles.
        return Auth::check() && Gate::allows('inactivatePlan', Packages::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
