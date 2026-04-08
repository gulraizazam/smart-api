<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTypeResource extends JsonResource
{
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'active' => $this->active,
            'created_at' => Carbon::parse($this->created_at)->format('F j,Y h:i A'),
            'updated_at' => Carbon::parse($this->updated_at)->format('F j,Y h:i A'),
        ];
    }
}
