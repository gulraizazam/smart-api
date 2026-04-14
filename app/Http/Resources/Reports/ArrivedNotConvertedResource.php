<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class ArrivedNotConvertedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'patient_id' => $this->id,
            'patient_name' => $this->name ?? 'N/A',
            'phone' => $this->when(Gate::allows('contact'), fn () => $this->phone),
            'service' => $this->service_name ?? 'N/A',
            'doctor' => $this->doctor_name ?? 'N/A',
            'centre' => $this->location_name ?? 'N/A',
            'scheduled_date' => $this->scheduled_date,
        ];
    }
}
