<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemTarget extends Model
{
    use HasFactory;

    protected $table = 'system_targets';

    protected $fillable = [
        'account_id',
        'target_key',
        'target_value',
        'label',
        'updated_by',
    ];

    /**
     * Get a target value by key.
     */
    public static function getValue(string $key, int $accountId, $default = 0)
    {
        $record = self::where([
            'account_id' => $accountId,
            'target_key' => $key,
        ])->first();

        return $record ? (float) $record->target_value : $default;
    }

    /**
     * Get all targets for an account as key => value array.
     */
    public static function getAllForAccount(int $accountId): array
    {
        return self::where('account_id', $accountId)
            ->pluck('target_value', 'target_key')
            ->map(fn($v) => (float) $v)
            ->toArray();
    }

    /**
     * Key-to-label mapping for system targets.
     */
    public static array $labels = [
        'conversion_pct' => 'Conversion Rate Target (%)',
        'avg_conversion_revenue' => 'Avg Conversion Revenue',
        'feedback_score' => 'Feedback Score Target',
        'upselling_target' => 'Upselling Target',
    ];

    /**
     * Update or create a target value.
     */
    public static function setValue(string $key, float $value, int $accountId, ?int $updatedBy = null): self
    {
        return self::updateOrCreate(
            ['account_id' => $accountId, 'target_key' => $key],
            [
                'target_value' => $value,
                'label' => self::$labels[$key] ?? $key,
                'updated_by' => $updatedBy,
            ]
        );
    }
}
