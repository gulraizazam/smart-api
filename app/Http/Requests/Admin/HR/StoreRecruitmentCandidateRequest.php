<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Mirrors the admin web rules so the API and web share one validation
 * contract. The interview fields stay required here because the existing
 * business rule is "creating a candidate always schedules a first interview".
 */
class StoreRecruitmentCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('hr_recruitment_create');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'designation_id' => [
                'required',
                'integer',
                Rule::exists('designations', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'city_id' => [
                'required',
                'integer',
                Rule::exists('cities', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'cv' => ['nullable', 'file', 'max:5120', 'mimes:pdf,doc,docx'],
            'interview_type' => ['required', 'in:ceo,director,hr'],
            'interviewer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'interview_date' => ['required', 'date'],
        ];
    }
}
