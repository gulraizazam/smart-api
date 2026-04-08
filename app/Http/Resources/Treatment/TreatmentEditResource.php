<?php

declare(strict_types=1);

namespace App\Http\Resources\Treatment;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for the treatment edit modal data.
 */
final class TreatmentEditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'appointment'              => $this->resource['appointment'],
            'services'                 => $this->resource['services'],
            'doctors'                  => $this->resource['doctors'],
            'resourceRotaDay'          => $this->resource['resourceRotaDay'],
            'machineRotaDay'           => $this->resource['machineRotaDay'],
            'biggerTime'               => $this->resource['biggerTime'],
            'smallerTime'              => $this->resource['smallerTime'],
            'permissions'              => $this->resource['permissions'],
            'is_arrived_or_converted'  => $this->resource['is_arrived_or_converted'],
        ];
    }
}
