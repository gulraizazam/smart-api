<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

/**
 * Handles both regular and configurable discount creation/update.
 * Uses $this->input('type') to determine which rule set applies,
 * and builds dynamic session rules for Configurable type — matching
 * the verifyFields() / verifyConfigurableFields() helper logic exactly.
 */
class StoreDiscountRequest extends AdminApiFormRequest
{
    public function rules(): array
    {
        if ($this->input('type') === 'Configurable') {
            return $this->configurableRules();
        }

        return [
            'name'          => 'required',
            'discount_type' => 'required',
            'start'         => 'required',
            'end'           => 'required',
            'roles'         => 'required|array',
            'roles.*'       => 'exists:roles,id',
        ];
    }

    private function configurableRules(): array
    {
        $rules = [
            'name'          => 'required',
            'type'          => 'required',
            'start'         => 'required',
            'end'           => 'required',
            'sessions_buy'  => 'required',
            'base_service'  => 'required',
        ];

        $sessions = $this->input('sessions', []);
        foreach ($sessions as $key => $value) {
            $rules["sessions.{$key}"]   = 'required';
            $sameService = $this->input("same_service.{$key}");
            if (!$sameService) {
                $rules["services_name.{$key}"] = 'required';
            }
            $rules["disc_type.{$key}"] = 'required';
        }

        return $rules;
    }
}
