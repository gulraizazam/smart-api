<?php

declare(strict_types=1);

namespace App\Http\Resources\Patient;

use App\Enums\Gender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class PatientResource extends JsonResource
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
            'active' => $this->active,
            'created_at' => $this->created_at
                ? Carbon::parse($this->created_at)->format('F j, Y h:i A')
                : null,
            'membership' => $this->whenLoaded('membership', fn () => $this->membership ? [
                'id' => $this->membership->id,
                'code' => $this->membership->code,
                'membership_type_id' => $this->membership->membership_type_id,
                'end_date' => $this->membership->end_date,
                'active' => $this->membership->active,
                'is_referral' => $this->membership->is_referral ?? false,
            ] : null),
        ];
    }
}
