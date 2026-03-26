<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadServiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'child_service_id' => $this->child_service_id,
            'status' => $this->status,
            'meta_lead_id' => $this->meta_lead_id,
            'lead_status_id' => $this->lead_status_id,
            'service' => $this->when(
                $this->relationLoaded('service'),
                fn() => ['id' => $this->service?->id, 'name' => $this->service?->name]
            ),
            'child_service' => $this->when(
                $this->relationLoaded('childservice'),
                fn() => ['id' => $this->childservice?->id, 'name' => $this->childservice?->name]
            ),
            'lead_status' => $this->when(
                $this->relationLoaded('leadStatus'),
                fn() => ['id' => $this->leadStatus?->id, 'name' => $this->leadStatus?->name]
            ),
        ];
    }
}
