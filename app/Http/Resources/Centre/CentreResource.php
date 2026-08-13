<?php

declare(strict_types=1);

namespace App\Http\Resources\Centre;

use App\Models\Locations;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CentreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Locations $centre */
        $centre = $this->resource;

        return [
            'id' => (int) $centre->id,
            'name' => (string) $centre->name,
            'slug' => $centre->slug,
            'fdo_name' => $centre->fdo_name,
            'fdo_phone' => $centre->fdo_phone,
            'address' => $centre->address,
            'google_map' => $centre->google_map,
            'city_id' => $centre->city_id !== null ? (int) $centre->city_id : null,
            'city_name' => $this->whenLoaded('city', fn (): ?string => $centre->city?->name),
            'region_id' => $centre->region_id !== null ? (int) $centre->region_id : null,
            'region_name' => $this->whenLoaded('region', fn (): ?string => $centre->region?->name),
            'ntn' => $centre->ntn,
            'stn' => $centre->stn,
            'tax_percentage' => $centre->tax_percentage !== null ? (float) $centre->tax_percentage : null,
            'parent_id' => $centre->parent_id !== null ? (int) $centre->parent_id : null,
            'image_src' => $centre->image_src,
            'image_url' => $this->resolveImageUrl($centre->image_src),
            'active' => (bool) $centre->active,
            'sort_no' => $centre->sort_no !== null ? (int) $centre->sort_no : null,
            'service_ids' => $this->whenLoaded(
                'service_has_locations',
                fn (): array => $centre->service_has_locations->pluck('service_id')->map(fn ($id) => (int) $id)->values()->all(),
            ),
            'created_at' => $centre->created_at?->toIso8601String(),
            'updated_at' => $centre->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Resolve the public URL for the centre logo from Cloudflare R2.
     *
     * Prefers a signed temporary URL when the R2 bucket is private (no
     * `CLOUDFLARE_R2_URL` / public base configured). Falls back to the
     * disk's configured public URL otherwise, and degrades gracefully if
     * the disk cannot produce a URL at all.
     */
    private function resolveImageUrl(?string $filename): ?string
    {
        if (! $filename) {
            return null;
        }

        $path = 'centre_logo/'.$filename;
        $disk = Storage::disk('r2');

        try {
            if (config('filesystems.disks.r2.url')) {
                return $disk->url($path);
            }

            return $disk->temporaryUrl($path, now()->addMinutes(15));
        } catch (Throwable) {
            return null;
        }
    }
}
