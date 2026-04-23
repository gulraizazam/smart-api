<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Models\UserOperatorSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * REST-flavoured variant of UserOperatorSettingsResource. Password stays
 * masked (credentials never leave the server). Unlike the legacy resource
 * this emits `test_mode` as int and adds timestamps + the global template
 * link so the mobile client can render context.
 */
class OperatorSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var UserOperatorSettings $op */
        $op = $this->resource;

        return [
            'id' => (int) $op->id,
            'operator_id' => $op->operator_id !== null ? (int) $op->operator_id : null,
            'operator_name' => $op->operator_name,
            'url' => $op->url,
            'username' => $op->username,
            'password' => '********',
            'mask' => $op->mask,
            'test_mode' => (int) $op->test_mode,
            'string_1' => $op->string_1,
            'string_2' => $op->string_2,
            'created_at' => $op->created_at?->toIso8601String(),
            'updated_at' => $op->updated_at?->toIso8601String(),
        ];
    }
}
