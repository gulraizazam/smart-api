<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MembershipReportRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'location_id'        => 'nullable|integer',
            'membership_type_id' => 'nullable|string',
            'date_range'         => 'nullable|string',
        ];
    }

    public function locationId(): ?int
    {
        $value = $this->input('location_id');

        return $value ? (int) $value : null;
    }

    public function membershipTypeId(): ?string
    {
        $value = $this->input('membership_type_id');

        return ($value !== null && $value !== '') ? $value : null;
    }

    public function startDate(): ?string
    {
        if ($this->date_range) {
            $parts = explode(' - ', $this->date_range);

            return date('Y-m-d', strtotime($parts[0]));
        }

        return null;
    }

    public function endDate(): ?string
    {
        if ($this->date_range) {
            $parts = explode(' - ', $this->date_range);

            return date('Y-m-d', strtotime($parts[1]));
        }

        return null;
    }
}
