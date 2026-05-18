<?php

declare(strict_types=1);

namespace App\Http\Resources\Patient;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'document_type' => $this->document_type,
            // Relative path to the authenticated streaming route (note
            // the `false` 3rd arg → no host). The SPA fetches this via
            // its api client (forwarding the bearer/passport token or
            // Sanctum cookie) and opens the blob — a plain absolute
            // <a href> would carry no token and 401 "Unauthenticated".
            // Mirrors the EmployeeResource preview/download convention.
            'url' => $this->url
                ? route('admin.files.patient_image_api', ['filename' => basename($this->url)], false)
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
