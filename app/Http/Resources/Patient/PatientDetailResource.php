<?php

declare(strict_types=1);

namespace App\Http\Resources\Patient;

use App\Enums\Gender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class PatientDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('contact');

        return [
            'id' => $this->id,
            'patient_code' => "C-{$this->id}",
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->when($canViewContact, fn () => $this->phone),
            'gender' => $this->resource->getAttributes()['gender'] ?? null,
            'gender_label' => Gender::tryFrom((int) ($this->resource->getAttributes()['gender'] ?? 0))?->label() ?? 'N/A',
            'cnic' => $this->cnic,
            'dob' => $this->dob?->format('Y-m-d'),
            'address' => $this->address,
            'referred_by' => $this->referred_by,
            'active' => $this->active,
            'image_url' => $this->image_src
                ? route('admin.files.patient_image', ['filename' => $this->image_src])
                : asset('images/default-avatar.png'),
            'created_at' => $this->created_at
                ? Carbon::parse($this->created_at)->format('F j, Y h:i A')
                : null,
        ];
    }
}
