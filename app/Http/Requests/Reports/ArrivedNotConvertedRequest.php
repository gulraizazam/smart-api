<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ArrivedNotConvertedRequest extends FormRequest
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
            'date_range'  => 'required|string',
            'location_id' => 'nullable|integer',
            'doctor_id'   => 'nullable|integer',
            'service_id'  => 'nullable|integer',
        ];
    }

    public function startDate(): string
    {
        $parts = explode(' - ', $this->input('date_range'));

        return date('Y-m-d', strtotime($parts[0]));
    }

    public function endDate(): string
    {
        $parts = explode(' - ', $this->input('date_range'));

        return date('Y-m-d', strtotime($parts[1]));
    }

    public function locationId(): ?int
    {
        $value = $this->input('location_id');

        return $value ? (int) $value : null;
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
