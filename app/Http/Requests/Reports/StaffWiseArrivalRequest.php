<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Foundation\Http\FormRequest;

class StaffWiseArrivalRequest extends FormRequest
{
    use ParsesDateRange;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_range' => 'required|string',
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'created_by' => 'nullable|integer',
        ];
    }

    public function startDate(): string
    {
        return self::parseDateRange($this->input('date_range'))[0];
    }

    public function endDate(): string
    {
        return self::parseDateRange($this->input('date_range'))[1];
    }

    /**
     * @return int[]
     */
    public function centreIds(): array
    {
        $value = $this->input('location_id');
        if (empty($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', (array) $value),
            fn (int $id): bool => $id > 0,
        ));
    }

    public function createdBy(): ?int
    {
        $value = $this->input('created_by');

        return $value ? (int) $value : null;
    }
}
