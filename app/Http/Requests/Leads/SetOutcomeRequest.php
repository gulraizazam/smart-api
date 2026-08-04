<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Models\LeadCall;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * `POST /api/leads/{lead}/calls/{call}/outcome`
 *
 * Agent's post-call disposition (Interested / Not Interested / Callback /
 * Wrong Number / No Answer / Other) + optional free-text notes. Only the
 * agent who placed the call may update it (a call-quality reviewer role
 * comes later — post-phase-1).
 */
class SetOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        $call = $this->route('call');

        if ($user === null || ! $call instanceof LeadCall) {
            return false;
        }

        // Same tenant.
        if ((int) $call->account_id !== (int) $user->account_id) {
            return false;
        }

        // Only the placing agent may set the outcome.
        return (int) $call->user_id === (int) $user->id;
    }

    public function rules(): array
    {
        return [
            'outcome' => [
                'required',
                'string',
                'in:interested,not_interested,callback,wrong_number,no_answer,other',
            ],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
