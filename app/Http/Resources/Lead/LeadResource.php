<?php

declare(strict_types=1);

namespace App\Http\Resources\Lead;

use App\Enums\Gender;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class LeadResource extends JsonResource
{
    /**
     * Pre-loaded lead statuses dictionary for batch parent name resolution.
     * Set this before calling ::collection() to avoid N+1 queries.
     */
    public static array $statusLookup = [];

    public function toArray(Request $request): array
    {
        $canViewContact = Gate::allows('leads.list.view_contact');

        return [
            'id' => $this->id,
            'lead_id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($canViewContact, $this->email),
            // Return raw phone (digits-only, as stored). The phone field is
            // consumed by inputs that validate digits-only (e.g. the lead
            // search field on the create consultation form), so applying
            // `prepareNumber4Call` here (which prepends `+92`) would break
            // client-side validation. Display-time formatting for click-to-
            // call should be done at the presentation layer, not in the API
            // resource.
            'phone' => $this->when($canViewContact, fn () => $this->phone),
            'gender' => $this->resource->getAttributes()['gender'] ?? null,
            'gender_label' => $this->resolveGenderLabel(),
            'active' => $this->active,
            'city_id' => $this->city?->name ?? '',
            'cityId' => $this->city_id ?? 0,
            'region_id' => $this->whenLoaded('region', fn(): string => $this->region?->name ?? 'N/A', 'N/A'),
            'location' => $this->whenLoaded('location', fn(): string => $this->location?->name ?? '', $this->towns?->name ?? ''),
            'lead_status_id' => $this->resolveStatusName(),
            'service_id' => $this->when(
                $this->relationLoaded('lead_service') || $this->relationLoaded('leadServices'),
                fn(): string => $this->resolveServiceNames()
            ),
            'service_active' => $this->when(
                $this->relationLoaded('lead_service') || $this->relationLoaded('leadServices'),
                fn(): string => $this->resolveActiveServiceNames()
            ),
            'child_service' => $this->when(
                $this->relationLoaded('lead_service') || $this->relationLoaded('leadServices'),
                fn(): string => $this->resolveChildServiceNames()
            ),
            'lead_source' => $this->when(
                $this->relationLoaded('lead_source') || $this->relationLoaded('leadSource'),
                fn(): ?string => $this->leadSource?->name ?? $this->lead_source?->name
            ),
            'created_at' => Carbon::parse($this->created_at)->format('F j,Y h:i A'),
            'created_by' => $this->when(
                $this->relationLoaded('user'),
                fn(): string => $this->user?->name ?? 'N/A'
            ),
            'comments' => LeadCommentResource::collection($this->whenLoaded('lead_comments', default: $this->whenLoaded('leadComments'))),
            'services' => LeadServiceItemResource::collection($this->whenLoaded('lead_service', default: $this->whenLoaded('leadServices'))),
        ];
    }

    protected function resolveGenderLabel(): string
    {
        if ($this->gender instanceof Gender) {
            return $this->gender->label();
        }

        return Gender::tryFrom((int) $this->gender)?->label() ?? 'Unknown';
    }

    protected function resolveStatusName(): string
    {
        if (!empty(self::$statusLookup)) {
            $statusId = $this->lead_status_id;
            $status = self::$statusLookup[$statusId] ?? null;

            if (!$status) {
                return '';
            }

            $parentId = $status['parent_id'] ?? 0;

            return ($parentId == 0)
                ? ($status['name'] ?? '')
                : (self::$statusLookup[$parentId]['name'] ?? $status['name'] ?? '');
        }

        $status = $this->relationLoaded('lead_status') ? $this->lead_status
            : ($this->relationLoaded('leadStatus') ? $this->leadStatus : null);

        if (!$status) {
            return '';
        }

        if ($status->parent_id && $status->parent_id != 0) {
            $parent = \App\Models\LeadStatuses::find($status->parent_id);
            return $parent?->name ?? $status->name;
        }

        return $status->name;
    }

    protected function resolveServiceNames(): string
    {
        $services = $this->relationLoaded('leadServices') ? $this->leadServices : $this->lead_service;

        return $services
            ->pluck('service.name')
            ->filter()
            ->unique()
            ->implode(',');
    }

    protected function resolveActiveServiceNames(): string
    {
        $services = $this->relationLoaded('leadServices') ? $this->leadServices : $this->lead_service;

        return $services
            ->where('status', 1)
            ->pluck('service.name')
            ->filter()
            ->implode(',');
    }

    protected function resolveChildServiceNames(): string
    {
        $services = $this->relationLoaded('leadServices') ? $this->leadServices : $this->lead_service;

        return $services
            ->where('status', 1)
            ->pluck('childservice.name')
            ->filter()
            ->implode(',');
    }
}
