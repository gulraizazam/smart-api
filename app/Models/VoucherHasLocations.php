<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherHasLocations extends Model
{
    use HasFactory;
    protected $fillable = ['voucher_id', 'location_id', 'service_id'];

    protected $table = 'voucher_has_locations';

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id')->withTrashed();
    }
}
