<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Enums\Gender;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * API shape for an employee (User + EmployeeDetail + locations + documents).
 *
 * Grouped into four sections that mirror the web detail view:
 *   - personal_information
 *   - employment_details
 *   - emergency_contact
 *   - documents
 *
 * Sensitive fields (cnic, salary, bank_name, bank_account_last4, tax_id) are
 * gated on `hr_employees_manage`. Bank account number is encrypted at rest on
 * EmployeeDetail and exposed here only as a masked last-4.
 */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User $user */
        $user = $this->resource;
        $detail = $user->employeeDetail;

        $canSeeSensitive = Gate::allows('hr_employees_manage');

        return [
            'id' => $user->id,
            'personal_information' => $this->personalInformation($user, $canSeeSensitive),
            'employment_details' => $this->employmentDetails($user, $detail, $canSeeSensitive),
            'emergency_contact' => $this->emergencyContact($detail),
            'documents' => $this->documents($user),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personalInformation(\App\Models\User $user, bool $canSeeSensitive): array
    {
        $genderRaw = $user->getAttributes()['gender'] ?? null;

        return [
            'avatar_url' => $user->image_src ? asset($user->image_src) : null,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'cnic' => $canSeeSensitive ? $user->cnic : null,
            'dob' => $user->dob?->toIso8601String(),
            'gender' => $genderRaw !== null ? (int) $genderRaw : null,
            'gender_label' => $genderRaw !== null
                ? (Gender::tryFrom((int) $genderRaw)?->label() ?? null)
                : null,
            'address' => $user->address ?? null,
            'active' => (int) $user->active,
            'hr_managed' => (int) $user->hr_managed,
            'user_type_id' => $user->user_type_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employmentDetails(\App\Models\User $user, $detail, bool $canSeeSensitive): array
    {
        return [
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
            'salary' => $canSeeSensitive
                ? ($detail?->salary !== null ? (float) $detail->salary : null)
                : null,
            'bank_name' => $canSeeSensitive ? $detail?->bank_name : null,
            'bank_account_last4' => $canSeeSensitive
                ? $this->maskAccount($detail?->bank_account_number)
                : null,
            'tax_id' => $canSeeSensitive ? $detail?->tax_id : null,
            'locations' => $user->user_has_locations
                ->map(fn ($uhl) => [
                    'id' => $uhl->location?->id,
                    'name' => $uhl->location?->name,
                ])
                ->filter(fn (array $loc) => $loc['id'] !== null)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, ?string>|null
     */
    private function emergencyContact($detail): ?array
    {
        if ($detail === null) {
            return null;
        }

        return [
            'name' => $detail->emergency_contact_name,
            'phone' => $detail->emergency_contact_phone,
            'relation' => $detail->emergency_contact_relation,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documents(\App\Models\User $user): array
    {
        return $user->employeeDocuments
            ->map(fn (EmployeeDocument $doc) => [
                'id' => $doc->id,
                'file_name' => $doc->file_name,
                'file_extension' => $this->extensionOf($doc->file_name),
                'uploader' => $doc->uploader ? [
                    'id' => $doc->uploader->id,
                    'name' => $doc->uploader->name,
                ] : null,
                'uploaded_at' => $doc->created_at?->toIso8601String(),
                'drive_upload_status' => $doc->drive_upload_status?->value,
                'google_drive_url' => $doc->google_drive_url,
                'preview_url' => route('admin.hr.documents.preview', $doc),
                'download_url' => route('admin.hr.documents.download', $doc),
            ])
            ->values()
            ->all();
    }

    private function maskAccount(?string $account): ?string
    {
        if ($account === null || $account === '') {
            return null;
        }
        $last4 = substr($account, -4);

        return str_repeat('*', max(0, strlen($account) - 4)) . $last4;
    }

    private function extensionOf(?string $fileName): ?string
    {
        if ($fileName === null || $fileName === '') {
            return null;
        }
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);

        return $ext !== '' ? strtolower($ext) : null;
    }
}
