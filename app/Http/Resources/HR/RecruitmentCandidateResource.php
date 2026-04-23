<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\RecruitmentCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecruitmentCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var RecruitmentCandidate $c */
        $c = $this->resource;

        return [
            'id' => (int) $c->id,
            'name' => (string) $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'status' => $c->status?->value,
            'status_label' => $c->status?->label(),
            'notes' => $c->notes,
            'has_cv' => ! empty($c->cv_file_path),
            'cv_drive_upload_status' => $c->cv_drive_upload_status?->value,
            'cv_google_drive_url' => $c->cv_google_drive_url,
            'designation' => $this->whenLoaded('designation', fn () => $c->designation ? [
                'id' => (int) $c->designation->id,
                'name' => (string) $c->designation->name,
            ] : null),
            'city' => $this->whenLoaded('city', fn () => $c->city ? [
                'id' => (int) $c->city->id,
                'name' => (string) $c->city->name,
            ] : null),
            'location' => $this->whenLoaded('location', fn () => $c->location ? [
                'id' => (int) $c->location->id,
                'name' => (string) $c->location->name,
            ] : null),
            'converted_user' => $this->whenLoaded('convertedUser', fn () => $c->convertedUser ? [
                'id' => (int) $c->convertedUser->id,
                'name' => (string) $c->convertedUser->name,
            ] : null),
            'converted_user_id' => $c->converted_user_id !== null ? (int) $c->converted_user_id : null,
            'interviews_count' => $this->whenCounted('interviews'),
            'interviews' => RecruitmentInterviewResource::collection($this->whenLoaded('interviews')),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }
}
