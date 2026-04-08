<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyEmployeeStatsResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'records' => collect($data['records'] ?? [])->map(fn (array $row) => [
                'id' => $row['id'],
                'name' => $row['name'],
                'amount' => round((float) ($row['amount'] ?? 0), 2),
            ])->values()->all(),
        ];
    }
}
