<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingEditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $service = app(SettingsService::class);
        $meta = $service->resolveFieldMeta($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'data' => $this->data,
            'active' => $this->active,
            'field_type' => $meta['field_type'],
            ...$meta['extra'],
        ];
    }
}
