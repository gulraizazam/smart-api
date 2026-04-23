<?php

declare(strict_types=1);

namespace App\Http\Resources\Package;

use App\Enums\PlanType;
use App\Models\Packages;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact package shape for list views. Use `PackageDetailResource` for
 * the full display payload (package + bundles + services + advances + cash
 * summary) when rendering a single package.
 */
class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Packages $package */
        $package = $this->resource;

        $planType = $package->plan_type instanceof PlanType
            ? $package->plan_type->value
            : $package->plan_type;

        return [
            'id' => (int) $package->id,
            'random_id' => $package->random_id,
            'name' => $package->name,
            'plan_name' => $package->plan_name,
            'plan_type' => $planType,
            'session_count' => $package->sessioncount !== null ? (int) $package->sessioncount : null,
            'total_price' => (float) $package->total_price,
            'is_exclusive' => (bool) $package->is_exclusive,
            'is_refund' => (bool) $package->is_refund,
            'active' => (bool) $package->active,
            'patient_id' => $package->patient_id !== null ? (int) $package->patient_id : null,
            'patient_name' => $this->whenLoaded('user', fn (): ?string => $package->user?->name),
            'location_id' => $package->location_id !== null ? (int) $package->location_id : null,
            'location_name' => $this->whenLoaded('location', fn (): ?string => $package->location?->name),
            'appointment_id' => $package->appointment_id !== null ? (int) $package->appointment_id : null,
            'created_at' => $package->created_at?->toIso8601String(),
            'updated_at' => $package->updated_at?->toIso8601String(),
        ];
    }
}
