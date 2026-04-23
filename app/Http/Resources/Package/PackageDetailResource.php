<?php

declare(strict_types=1);

namespace App\Http\Resources\Package;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full display payload for a single package — wraps the aggregate array
 * produced by `PlanService::getDisplayData()` (package + bundles +
 * services + advances + grand_total + membership + student_documents).
 *
 * We don't project the deeply-nested relationship graph into a bespoke
 * shape here: the legacy display screen has dozens of downstream
 * consumers, and re-shaping every field is both risky and scope-creep.
 * Instead we return the service's output verbatim and let the mobile
 * client cherry-pick. The fields it produces are already tenant-scoped
 * (see `PlanService::getDisplayData` — tenant guard on the `Packages`
 * load) and exclude mutation-only internals.
 */
class PackageDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return is_array($this->resource)
            ? $this->resource
            : (array) $this->resource;
    }
}
