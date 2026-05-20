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
    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('leads.list.view_contact');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($canViewContact, fn () => $this->email),
            'phone' => $this->when(
                $canViewContact,
                fn () => GeneralFunctions::prepareNumber4Call($this->phone),
            ),
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
                function (): ?array {
                    $s = $this->leadStatus ?? $this->lead_status;
                    if (! $s) {
                        return null;
                    }
                    return [
                        'id'           => $s->id,
                        'name'         => $s->name,
                        'parent_id'    => $s->parent_id,
                        // Flag fields so the SPA's lead-detail dropdown can
                        // derive its lock state directly off the row instead
                        // of cross-referencing /api/leads/lead_statuses
                        // (which is its own permission-gated query).
                        'is_booked'    => (bool) ($s->is_booked ?? false),
                        'is_arrived'   => (bool) ($s->is_arrived ?? false),
                        'is_converted' => (bool) ($s->is_converted ?? false),
                        'is_junk'      => (bool) ($s->is_junk ?? false),
                    ];
                },
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
