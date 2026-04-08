<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserOperatorSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operator_id' => $this->operator_id,
            'operator_name' => $this->operator_name,
            'url' => $this->url,
            'username' => $this->username,
            'password' => '********',
            'mask' => $this->mask,
            'test_mode' => $this->test_mode == 1 ? 'Yes' : 'No',
            'string_1' => $this->string_1,
            'string_2' => $this->string_2,
        ];
    }
}
