<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipReportResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'user_id' => $data['user_id'] ?? null,
            'user_name' => $data['user_name'] ?? 'N/A',
            'location' => $data['location'] ?? 'N/A',
            'service_name' => $data['service_name'] ?? 'N/A',
            'service_status' => $data['service_status'] ?? 'N/A',
            'membership_code' => $data['membership_code'] ?? 'No membership',
            'membership_type' => $data['membership_type'] ?? 'No membership',
            'membership_type_id' => $data['membership_type_id'] ?? 0,
            'assigned_at' => $data['assigned_at'] ?? null,
        ];
    }
}
