<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\EmployeeDocument $doc */
        $doc = $this->resource;

        return [
            'id' => $doc->id,
            'user_id' => (int) $doc->user_id,
            'file_name' => $doc->file_name,
            'google_drive_url' => $doc->google_drive_url,
            'google_drive_file_id' => $doc->google_drive_file_id,
            'drive_upload_status' => $doc->drive_upload_status?->value,
            'uploaded_by' => $doc->uploader ? [
                'id' => $doc->uploader->id,
                'name' => $doc->uploader->name,
            ] : null,
            'created_at' => $doc->created_at?->toIso8601String(),
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ];
    }
}
