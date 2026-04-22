<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Enums\Gender;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Self-service profile — restricted to fields the employee themself is allowed
 * to see about themselves. Explicitly OMITS:
 *   - salary, bank_name, bank_account_number, tax_id
 *   - active, hr_managed flags
 *   - admin-side audit metadata (created_by, etc.)
 *
 * Employees can see their own department / designation / manager / hire_date
 * because those are visible in HR-facing UI anyway.
 */
class MyProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $this->resource;
        $detail = $user->employeeDetail;
        $genderRaw = $user->getAttributes()['gender'] ?? null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'dob' => $user->dob?->toIso8601String(),
            'gender' => $genderRaw !== null ? (int) $genderRaw : null,
            'gender_label' => $genderRaw !== null
                ? (Gender::tryFrom((int) $genderRaw)?->label() ?? null)
                : null,
            'address' => $user->address ?? null,

            'department' => $detail?->department ? [
                'id' => $detail->department->id,
                'name' => $detail->department->name,
            ] : null,
            'designation' => $detail?->designation ? [
                'id' => $detail->designation->id,
                'name' => $detail->designation->name,
            ] : null,
            'manager' => $detail?->reportingManager ? [
                'id' => $detail->reportingManager->id,
                'name' => $detail->reportingManager->name,
            ] : null,

            'hire_date' => $detail?->hire_date?->toIso8601String(),
            'employment_type' => $detail?->employment_type?->value,
            'employment_type_label' => $detail?->employment_type?->label(),
            'shift_hours' => $detail?->shift_hours !== null ? (float) $detail->shift_hours : null,

            'emergency_contact' => $detail ? [
                'name' => $detail->emergency_contact_name,
                'phone' => $detail->emergency_contact_phone,
                'relation' => $detail->emergency_contact_relation,
            ] : null,

            'locations' => $user->relationLoaded('user_has_locations')
                ? $user->user_has_locations
                    ->map(fn ($uhl) => [
                        'id' => $uhl->location?->id,
                        'name' => $uhl->location?->name,
                    ])
                    ->filter(fn (array $loc) => $loc['id'] !== null)
                    ->values()
                    ->all()
                : [],

            'documents' => $user->relationLoaded('employeeDocuments')
                ? $user->employeeDocuments
                    ->map(fn (EmployeeDocument $doc) => [
                        'id' => $doc->id,
                        'file_name' => $doc->file_name,
                        'google_drive_url' => $doc->google_drive_url,
                        'drive_upload_status' => $doc->drive_upload_status?->value,
                        'uploaded_at' => $doc->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all()
                : [],

            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
