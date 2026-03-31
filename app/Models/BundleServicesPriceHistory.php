<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleServicesPriceHistory extends Model
{
    protected $table = 'bundle_services_price_history';

    protected $fillable = [
        'bundle_id',
        'bundle_price',
        'bundle_services_price',
        'service_id',
        'service_price',
        'active',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'bundle_price'          => 'float',
            'bundle_services_price' => 'float',
            'service_price'         => 'float',
            'active'                => 'integer',
            'effective_from'        => 'date',
            'effective_to'          => 'date',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundles::class, 'bundle_id');
    }

    // ── Record Operations ───────────────────────────────

    public static function createRecord(array $data, int $account_id): self
    {
        $data['account_id'] = $account_id;

        return self::create($data);
    }

    public static function updateRecord(int $id, array $data, int $account_id): ?self
    {
        $data['account_id'] = $account_id;

        $record = self::where([
            'id'         => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        return $record;
    }
}
