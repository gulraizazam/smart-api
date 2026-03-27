<?php

declare(strict_types=1);

namespace App\Http\Resources\Patient;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'note' => $this->note,
            'is_pinned' => $this->is_pinned,
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at
                ? Carbon::parse($this->created_at)->format('F j, Y h:i A')
                : null,
            'updated_at' => $this->updated_at
                ? Carbon::parse($this->updated_at)->format('F j, Y h:i A')
                : null,
        ];
    }
}
