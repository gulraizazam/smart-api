<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRecruitmentInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Gate::allows('hr_recruitment_interview_manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $accountId = (int) Auth::user()->account_id;

        $rules = [
            'interviewer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'interview_date' => ['required', 'date'],
            'interview_type' => ['required', 'in:ceo,director,hr'],
            'interview_notes' => ['nullable', 'string', 'max:150'],
            'score_communication' => ['nullable', 'integer', 'min:1', 'max:5'],
            'score_technical' => ['nullable', 'integer', 'min:1', 'max:5'],
            'score_cultural_fit' => ['nullable', 'integer', 'min:1', 'max:5'],
            'score_experience' => ['nullable', 'integer', 'min:1', 'max:5'],
            'score_personality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'verdict' => ['nullable', 'in:strong_hire,hire,no_hire,strong_no_hire'],
        ];

        // When a candidate is not route-bound (flat POST), require candidate_id.
        if (! $this->route('candidate')) {
            $rules['candidate_id'] = [
                'required',
                'integer',
                Rule::exists('recruitment_candidates', 'id')
                    ->where(fn ($q) => $q->where('account_id', $accountId)->whereNull('deleted_at')),
            ];
        }

        return $rules;
    }
}
