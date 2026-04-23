<?php

declare(strict_types=1);

namespace App\Http\Resources\Schedule;

use App\Models\WorkingDayException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkingDayExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var WorkingDayException $exception */
        $exception = $this->resource;

        return [
            'id' => (int) $exception->id,
            'exception_date' => $exception->exception_date?->toDateString(),
            'exception_date_formatted' => $exception->exception_date?->format('l, d M Y'),
            'is_working' => (bool) $exception->is_working,
            'title' => $exception->title,
            'created_by' => $this->whenLoaded('creator', fn () => $exception->creator ? [
                'id' => (int) $exception->creator->id,
                'name' => (string) $exception->creator->name,
            ] : null),
            'created_at' => $exception->created_at?->toIso8601String(),
            'updated_at' => $exception->updated_at?->toIso8601String(),
        ];
    }
}
