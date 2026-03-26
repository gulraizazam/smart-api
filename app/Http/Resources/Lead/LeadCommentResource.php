<?php

namespace App\Http\Resources\Lead;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class LeadCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'comment' => $this->comment,
            'created_by' => $this->when(
                $this->relationLoaded('user'),
                fn() => $this->user?->name ?? 'N/A'
            ),
            'created_at' => Carbon::parse($this->created_at)->format('D M, j Y h:i A'),
        ];
    }
}
