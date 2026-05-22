<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Models\Concerns\GuardsTenantBoundary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CashflowSetting extends Model
{
    use GuardsTenantBoundary;

    protected $table = 'cashflow.settings.manage';

    protected $fillable = ['account_id', 'key', 'value', 'description'];

    /**
     * Get a setting value by key for the given account.
     */
    public static function getValue(string $key, int $accountId, $default = null)
    {
        $setting = static::where('account_id', $accountId)
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    /**
     * Set a setting value by key for the given account.
     */
    public static function setValue(string $key, $value, int $accountId, ?string $description = null): self
    {
        return static::updateOrCreate(
            ['account_id' => $accountId, 'key' => $key],
            ['value' => $value, 'description' => $description]
        );
    }

    /**
     * Get all settings for an account as key-value array.
     */
    public static function getAllForAccount(int $accountId): array
    {
        return static::where('account_id', $accountId)
            ->pluck('value', 'key')
            ->toArray();
    }
}
