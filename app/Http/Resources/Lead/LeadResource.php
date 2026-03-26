<?php

namespace App\Http\Resources\Lead;

use App\Enums\Gender;
use App\Helpers\GeneralFunctions;
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
        $canViewContact = Gate::allows('contact');

        return [
            'id' => $this->id,
            'lead_id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($canViewContact, $this->email),
            'phone' => $canViewContact
                ? GeneralFunctions::prepareNumber4Call($this->phone)
                : '***********',
            'gender' => $this->gender instanceof Gender ? $this->gender->label() : (Gender::tryFrom((int) $this->gender)?->label() ?? 'Unknown'),
            'active' => $this->active,
            'city_id' => $this->city?->name ?? '',
            'cityId' => $this->city_id ?? 0,
            'region_id' => $this->whenLoaded('region', fn() => $this->region?->name ?? 'N/A', 'N/A'),
            'location' => $this->towns?->name ?? '',
            'lead_status_id' => $this->resolveStatusName(),
            'service_id' => $this->when(
                $this->relationLoaded('lead_service'),
                fn() => $this->resolveServiceNames()
            ),
            'service_active' => $this->when(
                $this->relationLoaded('lead_service'),
                fn() => $this->resolveActiveServiceNames()
            ),
            'child_service' => $this->when(
                $this->relationLoaded('lead_service'),
                fn() => $this->resolveChildServiceNames()
            ),
            'lead_source' => $this->when(
                $this->relationLoaded('lead_source'),
                fn() => $this->lead_source?->name
            ),
            'created_at' => Carbon::parse($this->created_at)->format('F j,Y h:i A'),
            'created_by' => $this->when(
                $this->relationLoaded('user'),
                fn() => $this->user?->name ?? 'N/A'
            ),
            'comments' => LeadCommentResource::collection($this->whenLoaded('lead_comments')),
            'services' => LeadServiceItemResource::collection($this->whenLoaded('lead_service')),
        ];
    }

    protected function resolveStatusName(): string
    {
        // Use batch-loaded lookup if available (avoids N+1 in datatable)
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

        // Fallback: use eager-loaded relation
        $status = $this->relationLoaded('lead_status') ? $this->lead_status : null;
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
        return $this->lead_service
            ->pluck('service.name')
            ->filter()
            ->unique()
            ->implode(',');
    }

    protected function resolveActiveServiceNames(): string
    {
        return $this->lead_service
            ->where('status', 1)
            ->pluck('service.name')
            ->filter()
            ->implode(',');
    }

    protected function resolveChildServiceNames(): string
    {
        return $this->lead_service
            ->where('status', 1)
            ->pluck('childservice.name')
            ->filter()
            ->implode(',');
    }
}
