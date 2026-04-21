<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Foundation\Http\FormRequest;

class ArrivedNotConvertedRequest extends FormRequest
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
            'doctor_id' => 'nullable|integer',
            'service_id' => 'nullable|integer',
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
     * @return int[]|null
     */
    public function locationIds(): ?array
    {
        $value = $this->input('location_id');

        if (empty($value)) {
            return null;
        }

        return array_map('intval', (array) $value);
    }

    public function doctorId(): ?int
    {
        $value = $this->input('doctor_id');

        return $value ? (int) $value : null;
    }

    public function serviceId(): ?int
    {
        $value = $this->input('service_id');

        return $value ? (int) $value : null;
    }
}
