<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleHasServices extends Model
{
    protected $table = 'bundle_has_services';

    protected static string $_table = 'bundle_has_services';

    public $timestamps = false;

    protected $fillable = [
        'bundle_id',
        'service_id',
        'service_price',
        'calculated_price',
        'end_node',
    ];

    protected static array $_fillable = [
        'bundle_id',
        'service_id',
        'service_price',
        'end_node',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'service_price'    => 'float',
            'calculated_price' => 'float',
            'end_node'         => 'integer',
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

    public static function createRecord(array $data, mixed $parent_data): bool
    {
        $record = self::insert($data);
        $parent_id = $parent_data;

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parent_id);

        return $record;
    }

    public static function updateRecord(array $data, mixed $parent_data): bool
    {
        $record = self::insert($data);
        $parent_id = $parent_data->id;
        $old_data = '0';

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $record, $parent_id);

        return $record;
    }
}
