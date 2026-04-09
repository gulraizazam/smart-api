<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CashflowSetting extends Model
{
    protected $table = 'cashflow_settings';

    protected $fillable = ['account_id', 'key', 'value', 'description'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model): void {
            if (Auth::check() && in_array('account_id', $model->getFillable(), true)) {
                $model->account_id = Auth::user()->account_id;
            }
        });
    }

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
