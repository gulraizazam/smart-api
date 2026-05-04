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

        // Force select option lists to serialize as a JSON **object** —
        // Laravel's JsonResource::resolve() reindexes numeric-keyed arrays
        // back into sequential `[0, 1, ...]` positions, which collapses
        // a list like `['2' => 'Exclusive', '3' => 'Inclusive']` into
        // `["Exclusive","Inclusive"]`. The SPA dropdown then uses the
        // array indices ('0','1') as option ids, the user's pick is
        // saved as '1' instead of '3', and downstream `getOrgTaxTreatment`
        // / display formatters treat the value as unknown. Casting the
        // list to stdClass preserves the original string keys end-to-end.
        if (isset($meta['extra']['list']) && is_array($meta['extra']['list'])) {
            $meta['extra']['list'] = (object) $meta['extra']['list'];
        }

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
