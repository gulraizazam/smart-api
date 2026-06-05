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
        $canViewContact = Gate::allows('patients.list.view_contact');

        return [
            'id' => $this->id,
            'patient_code' => "C-{$this->id}",
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->when($canViewContact, fn () => $this->phone),
            'gender' => $this->resource->getAttributes()['gender'] ?? null,
            'gender_label' => Gender::tryFrom((int) ($this->resource->getAttributes()['gender'] ?? 0))?->label() ?? 'N/A',
            // CNIC (national ID) gated on the same `contact` permission as phone
            // — was previously returned to any authenticated caller. Key is
            // omitted entirely when denied (SPA types cnic as optional/nullable).
            // Security audit 2026-06.
            'cnic' => $this->when($canViewContact, fn () => $this->cnic),
            'dob' => $this->dob?->format('Y-m-d'),
            'referred_by' => $this->referred_by,
            'active' => $this->active,
            // Null when no image — was `asset('images/default-avatar.png')`,
            // but `public/images/default-avatar.png` does not exist on
            // disk; the fallback resolved to a broken URL. SPA's patient
            // types already declare image_url as nullable and render an
            // initials-based AvatarFallback for the null case.
            'image_url' => $this->image_src
                ? route('admin.files.patient_image_api', ['filename' => $this->image_src])
                : null,
            'created_at' => $this->created_at
                ? Carbon::parse($this->created_at)->format('F j, Y h:i A')
                : null,
        ];
    }
}
