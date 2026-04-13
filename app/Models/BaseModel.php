<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\GuardsTenantBoundary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class BaseModel extends Model
{
    use GuardsTenantBoundary;
    /**
     * Enables `Model::factory()` for every domain model that extends
     * BaseModel. The factories live in `database/factories/<Model>Factory.php`.
     * Used by the test suite — production code never calls ->factory().
     */
    use HasFactory;

    /**
     * Scope route-model binding to the authenticated user's account,
     * preventing cross-tenant IDOR via URL parameter manipulation.
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        $query = $this->resolveRouteBindingQuery($this, $value, $field);

        if (Auth::check() && in_array('account_id', $this->getFillable(), true)) {
            $query->where($this->getTable() . '.account_id', Auth::user()->account_id);
        }

        return $query->first();
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
     * @deprecated REMOVED — this helper executed `whereIn('id', $id)` with
     * no tenant scoping and was a confirmed cross-tenant IDOR vector. The
     * single legitimate caller (AppointmentImageService::getDatatableData)
     * has been migrated to a tenant-scoped query that joins via
     * appointments.account_id. New code MUST use getBulkData() (which
     * filters by Auth::user()->account_id) or write an explicit
     * tenant-scoped query.
     *
     * Calling this method now throws to prevent accidental reintroduction.
     *
     * @param  int|array<int>  $id
     * @throws \LogicException
     */
    public static function getBulkData_forimage(int|array $id): never
    {
        throw new \LogicException(
            'BaseModel::getBulkData_forimage was removed for tenant-isolation reasons. '
            . 'Use getBulkData() or an explicit tenant-scoped query.'
        );
    }

    public function dateFormat(string $date, string $format = 'Y-m-d'): string
    {
        return date($format, strtotime($date));
    }
}
