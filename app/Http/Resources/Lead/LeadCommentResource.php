<?php

declare(strict_types=1);

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
                fn(): string => $this->user?->name ?? 'N/A'
            ),
            'user' => $this->when(
                $this->relationLoaded('user'),
                fn(): ?array => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null
            ),
            'created_at' => Carbon::parse($this->created_at)->format('D M, j Y h:i A'),
        ];
    }
}
