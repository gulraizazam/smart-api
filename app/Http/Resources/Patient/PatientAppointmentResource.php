<?php

declare(strict_types=1);

namespace App\Http\Resources\Patient;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class PatientAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('patients.list.view_contact');

        return [
            'id' => $this->id,
            'Patient_ID' => $this->patient_id ?? $this->id,
            'name' => $this->patient_name ?? '',
            'phone' => $this->when($canViewContact, fn () => $this->patient_phone ?? ''),
            'scheduled_date' => $this->scheduled_date
                ? Carbon::parse($this->scheduled_date)->format('D M, d Y h:i A')
                : '-',
            'doctor_id' => $this->doctor_name ?? '',
            'doctorId' => $this->doctor_id_raw ?? 0,
            'city_id' => $this->city_name ?? '',
            'location_id' => $this->location_name ?? '',
            'locationId' => $this->location_id_raw ?? 0,
            'service_id' => $this->service_name ?? '',
            'appointment_status_id' => $this->status_name ?? '',
            'appointment_type_id' => $this->type_id ?? 0,
            'consultancy_type' => $this->consultancy_type ?? '',
            'created_at' => $this->created_at
                ? Carbon::parse($this->created_at)->format('D M, d Y h:i A')
                : '',
            'created_by' => $this->created_by_name ?? '',
            'invoice_id' => $this->invoice_id ?? 0,
            'invoice' => $this->invoice_id ? (object) ['id' => $this->invoice_id] : null,
        ];
    }
}
