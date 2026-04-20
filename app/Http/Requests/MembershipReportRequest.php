<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MembershipReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_id'        => 'nullable|array',
            'location_id.*'      => 'integer|exists:locations,id',
            'membership_type_id' => 'nullable|string',
            'date_range'         => 'nullable|string',
        ];
    }

    /**
     * @return int[]|null
     */
    public function locationIds(): ?array
    {
        $value = $this->input('location_id');

        if (empty($value)) {
            return null;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $value), fn (int $id): bool => $id > 0));

        return empty($ids) ? null : $ids;
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
