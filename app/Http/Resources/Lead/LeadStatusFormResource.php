<?php

declare(strict_types=1);

namespace App\Http\Resources\Lead;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Edit-form projection for a lead status. Returns raw foreign keys and
 * boolean flags (as 0/1) so the v2 SPA form can pre-fill <select>s and
 * radio groups. The default LeadStatusResource transforms parent_id into
 * the parent's display name and booleans into "Yes"/"No" strings — great
 * for the datatable row, unusable for editing.
 */
class LeadStatusFormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => (int) ($this->parent_id ?? 0),
            'is_comment' => (int) $this->is_comment,
            'is_default' => (int) $this->is_default,
            'is_booked' => (int) $this->is_booked,
            'is_arrived' => (int) $this->is_arrived,
            'is_converted' => (int) $this->is_converted,
            'is_junk' => (int) $this->is_junk,
            'active' => (int) $this->active,
        ];
    }
}
