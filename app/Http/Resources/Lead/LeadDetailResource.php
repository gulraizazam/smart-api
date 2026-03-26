<?php

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
        $canViewContact = Gate::allows('contact');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $canViewContact ? $this->email : '***********',
            'phone' => $canViewContact
                ? GeneralFunctions::prepareNumber4Call($this->phone)
                : '***********',
            'gender' => $this->gender instanceof Gender ? $this->gender->label() : (Gender::tryFrom((int) $this->gender)?->label() ?? 'Unknown'),
            'active' => $this->active,
            'meta_lead_id' => $this->meta_lead_id,
            'referred_by' => $this->referred_by,
            'city' => $this->when($this->relationLoaded('city'), fn() => [
                'id' => $this->city?->id,
                'name' => $this->city?->name,
            ]),
            'region' => $this->when($this->relationLoaded('region'), fn() => [
                'id' => $this->region?->id,
                'name' => $this->region?->name,
            ]),
            'location' => $this->when($this->relationLoaded('towns'), fn() => [
                'id' => $this->towns?->id,
                'name' => $this->towns?->name,
            ]),
            'lead_status' => $this->when($this->relationLoaded('lead_status'), fn() => [
                'id' => $this->lead_status?->id,
                'name' => $this->lead_status?->name,
                'parent_id' => $this->lead_status?->parent_id,
            ]),
            'lead_source' => $this->when($this->relationLoaded('lead_source'), fn() => [
                'id' => $this->lead_source?->id,
                'name' => $this->lead_source?->name,
            ]),
            'services' => LeadServiceItemResource::collection($this->whenLoaded('lead_service')),
            'comments' => LeadCommentResource::collection($this->whenLoaded('lead_comments')),
            'created_by' => $this->when($this->relationLoaded('user'), fn() => $this->user?->name),
            'created_at' => Carbon::parse($this->created_at)->format('F j,Y h:i A'),
            'updated_at' => $this->updated_at?->format('F j,Y h:i A'),
        ];
    }
}
