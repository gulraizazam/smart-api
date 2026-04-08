<?php

declare(strict_types=1);

namespace App\Http\Resources\Lead;

use App\Enums\Gender;
use App\Helpers\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class LeadDetailResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('contact');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $canViewContact ? $this->email : '***********',
            'phone' => $canViewContact
                ? GeneralFunctions::prepareNumber4Call($this->phone)
                : '***********',
            'gender' => $this->resource->getAttributes()['gender'] ?? null,
            'gender_label' => $this->resolveGenderLabel(),
            'active' => $this->active,
            'meta_lead_id' => $this->meta_lead_id,
            'referred_by' => $this->referred_by,
            'city_id' => $this->city_id,
            'location_id' => $this->location_id,
            'town_id' => $this->town_id ?? $this->location_id,
            'lead_source_id' => $this->lead_source_id,
            'lead_status_id' => $this->lead_status_id,
            'service_id' => $this->service_id,
            'city' => $this->when(
                $this->relationLoaded('city'),
                fn(): ?array => $this->city ? ['id' => $this->city->id, 'name' => $this->city->name] : null,
            ),
            'region' => $this->when(
                $this->relationLoaded('region'),
                fn(): ?array => $this->region ? ['id' => $this->region->id, 'name' => $this->region->name] : null,
            ),
            'towns' => $this->when(
                $this->relationLoaded('towns') || $this->relationLoaded('location'),
                fn(): ?array => ($this->location ?? $this->towns)
                    ? ['id' => ($this->location ?? $this->towns)->id, 'name' => ($this->location ?? $this->towns)->name]
                    : null,
            ),
            'lead_status' => $this->when(
                $this->relationLoaded('lead_status') || $this->relationLoaded('leadStatus'),
                fn(): ?array => ($this->leadStatus ?? $this->lead_status)
                    ? [
                        'id' => ($this->leadStatus ?? $this->lead_status)->id,
                        'name' => ($this->leadStatus ?? $this->lead_status)->name,
                        'parent_id' => ($this->leadStatus ?? $this->lead_status)->parent_id,
                    ]
                    : null,
            ),
            'lead_source' => $this->when(
                $this->relationLoaded('lead_source') || $this->relationLoaded('leadSource'),
                fn(): ?array => ($this->leadSource ?? $this->lead_source)
                    ? ['id' => ($this->leadSource ?? $this->lead_source)->id, 'name' => ($this->leadSource ?? $this->lead_source)->name]
                    : null,
            ),
            'lead_service' => LeadServiceItemResource::collection(
                $this->whenLoaded('lead_service', default: $this->whenLoaded('leadServices'))
            ),
            'lead_comments' => LeadCommentResource::collection(
                $this->whenLoaded('lead_comments', default: $this->whenLoaded('leadComments'))
            ),
            'created_by' => $this->when(
                $this->relationLoaded('user'),
                fn(): ?string => $this->user?->name,
            ),
            'created_at' => Carbon::parse($this->created_at)->format('F j,Y h:i A'),
            'updated_at' => $this->updated_at?->format('F j,Y h:i A'),
        ];
    }

    protected function resolveGenderLabel(): string
    {
        if ($this->gender instanceof Gender) {
            return $this->gender->label();
        }

        return Gender::tryFrom((int) $this->gender)?->label() ?? 'Unknown';
    }
}
