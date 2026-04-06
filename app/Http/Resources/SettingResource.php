<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    private static ?SettingsService $settingsService = null;

    public function toArray(Request $request): array
    {
        self::$settingsService ??= app(SettingsService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'data' => self::$settingsService->formatForDisplay($this->resource),
            'active' => $this->active,
        ];
    }
}
