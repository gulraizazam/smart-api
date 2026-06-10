<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membershipTypeName = $this->whenLoaded('membershipType', fn () => $this->membershipType?->name, 'N/A');
        $isStudentMembership = is_string($membershipTypeName) && str_contains(strtolower($membershipTypeName), 'student');

        return [
            'id'                      => $this->id,
            'code'                    => $this->code,
            'active'                  => (bool) $this->active,
            'start_date'              => $this->start_date,
            'end_date'                => $this->end_date,
            'membership_type_id'      => $membershipTypeName,
            'membership_type_id_raw'  => $this->membership_type_id,
            'is_student_membership'   => $isStudentMembership,
            'patient'                 => $this->whenLoaded('patient', fn () => $this->patient?->name, 'N/A'),
            'patient_id'              => $this->whenLoaded('patient', fn () => $this->patient?->id, 'N/A'),
            'patient_unique_id'       => $this->whenLoaded('patient', fn () => $this->patient?->unique_id),
            'patient_phone'           => $this->whenLoaded('patient', fn () => $this->patient?->phone),
            'is_referral'             => (bool) $this->is_referral,
            'parent_membership_code'  => $this->parent_membership_code,
            'assigned_at'             => $this->assigned_at,
            'created_at'              => $this->created_at?->format('F j,Y h:i A'),
        ];
    }
}
