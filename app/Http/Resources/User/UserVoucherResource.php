<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserVoucherResource extends JsonResource
{
    public static array $usedVouchersLookup = [];

    public function toArray(Request $request): array
    {
        $key = $this->user_id . '_' . $this->voucher_id;
        $isUsedInPackages = isset(self::$usedVouchersLookup[$key]);

        return [
            'id' => $this->id,
            'patient_id' => $this->user_id,
            'name' => $this->whenLoaded('user', fn (): string => $this->user?->name ?? 'N/A', 'N/A'),
            'voucher_type' => $this->whenLoaded('voucher', fn (): string => $this->voucher?->name ?? 'N/A', 'N/A'),
            'total_amount' => $this->total_amount ?? 0,
            'amount' => $this->amount,
            'created_at' => $this->created_at?->format('F d,Y h:i A') ?? '',
            'can_edit' => !$isUsedInPackages,
            'can_delete' => !$isUsedInPackages,
        ];
    }
}
