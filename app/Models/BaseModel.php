<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BaseModel extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model): void {
            if (Auth::check() && in_array('account_id', $model->getFillable(), true)) {
                $model->account_id = Auth::user()->account_id;
            }
        });
    }

    public static function getData(int $id): ?static
    {
        return static::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }

    /**
     * @param  int|array<int>  $id
     */
    public static function getBulkData(int|array $id): Collection
    {
        if (! is_array($id)) {
            $id = [$id];
        }

        return static::where('account_id', Auth::user()->account_id)
            ->whereIn('id', $id)
            ->get();
    }

    /**
     * Get bulk data for appointment images (cross-account by design).
     *
     * @param  int|array<int>  $id
     */
    public static function getBulkData_forimage(int|array $id): Collection
    {
        if (! is_array($id)) {
            $id = [$id];
        }

        return static::whereIn('id', $id)->get();
    }

    public function dateFormat(string $date, string $format = 'Y-m-d'): string
    {
        return date($format, strtotime($date));
    }
}
