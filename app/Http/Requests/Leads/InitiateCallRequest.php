<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Models\Leads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * `POST /api/leads/{lead}/calls/initiate`
 *
 * Called by the SPA the moment the agent clicks "Call" — this endpoint
 * writes the `lead_calls` intent row and returns its id, which the
 * Plivo browser SDK then attaches as a custom-param so subsequent
 * webhook callbacks can find the row.
 *
 * The route model binding gives us the target lead; this request just
 * enforces "the current user can call leads for that lead's account/branch"
 * and that the lead actually has a phone we can dial.
 */
class InitiateCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        $lead = $this->route('lead');

        if ($user === null || ! $lead instanceof Leads) {
            return false;
        }

        // Global permission.
        if (! $user->can('leads.call')) {
            return false;
        }

        // Tenant guard: never let a user in account A initiate a call on
        // a lead in account B (belt-and-suspenders — the datatable
        // already scopes to the caller's account).
        if ((int) $lead->account_id !== (int) $user->account_id) {
            return false;
        }

        // Branch scope: if the user is restricted to specific locations,
        // the lead must belong to one of them.
        if (method_exists($user, 'locations')) {
            $branchIds = $user->locations()->pluck('locations.id')->all();
            if (! empty($branchIds)
                && $lead->location_id !== null
                && ! in_array((int) $lead->location_id, array_map('intval', $branchIds), true)
            ) {
                return false;
            }
        }

        // Nothing to dial → nothing to authorize.
        return is_string($lead->phone) && trim($lead->phone) !== '';
    }

    public function rules(): array
    {
        return [
            // Optional agent-side context; not persisted directly, just
            // logged for post-hoc debugging (e.g. "did the agent open
            // the drawer from the list or from a saved-search view?").
            'source_hint' => ['nullable', 'string', 'max:64'],
        ];
    }
}
