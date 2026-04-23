<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Models\GlobalOperatorSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Template-operator shape. Password is masked — callers use the template
 * only to pre-fill forms, not to read credentials.
 */
class GlobalOperatorSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var GlobalOperatorSettings $op */
        $op = $this->resource;

        return [
            'id' => (int) $op->id,
            'operator_name' => $op->operator_name,
            'url' => $op->url,
            'username' => $op->username,
            'password' => '********',
            'mask' => $op->mask,
            'test_mode' => (string) $op->test_mode === '1' ? 1 : 0,
            'string_1' => $op->string_1,
            'string_2' => $op->string_2,
            'created_at' => $op->created_at?->toIso8601String(),
            'updated_at' => $op->updated_at?->toIso8601String(),
        ];
    }
}
