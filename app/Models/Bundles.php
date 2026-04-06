<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Bundles extends BaseModel
{
    use SoftDeletes;

    protected $table = 'bundles';

    protected static string $_table = 'bundles';

    protected $fillable = [
        'name',
        'price',
        'services_price',
        'type',
        'start',
        'end',
        'apply_discount',
        'total_services',
        'active',
        'tax_treatment_type_id',
        'account_id',
        'created_at',
        'updated_at',
    ];

    protected static array $_fillable = [
        'name',
        'price',
        'services_price',
        'type',
        'start',
        'end',
        'apply_discount',
        'total_services',
        'active',
        'tax_treatment_type_id',
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'float',
            'services_price' => 'float',
            'total_services' => 'integer',
            'apply_discount' => 'boolean',
            'active'         => 'integer',
            'created_at'     => 'datetime:F d,Y h:i A',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function resourcehasrota(): HasMany
    {
        return $this->hasMany(ResourceHasRota::class, 'bundle_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Locations::class, 'bundle_id');
    }

    public function locationsActive(): HasMany
    {
        return $this->hasMany(Locations::class, 'bundle_id')->where('active', 1);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctors::class, 'bundle_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointments::class, 'bundle_id');
    }

    public function packagebundle(): HasMany
    {
        return $this->hasMany(PackageBundles::class, 'bundle_id');
    }

    public function bundleServices(): HasMany
    {
        return $this->hasMany(BundleHasServices::class, 'bundle_id');
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(BundleServicesPriceHistory::class, 'bundle_id');
    }

    // ── Scopes ──────────────────────────────────────────

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted(int|array|false $bundleId = false, bool $get_all = false): mixed
    {
        $query = self::where(['active' => 1, 'type' => 'multiple'])
            ->where('account_id', Auth::user()->account_id);

        if ($bundleId) {
            $ids = is_array($bundleId) ? $bundleId : [$bundleId];
            $query->whereIn('id', $ids);
        }

        return $query->pluck('name', 'id');
    }

    /**
     * Get active records only.
     */
    public static function getActiveOnly(int|array|false $bundleId = false): mixed
    {
        $query = self::where('active', 1);

        if ($bundleId) {
            $ids = is_array($bundleId) ? $bundleId : [$bundleId];
            $query->whereIn('id', $ids);
        }

        return $query->orderBy('sort_number', 'asc')->get();
    }

    /**
     * Calculate price based on package price.
     *
     * @param  array<int, array{service_price: float, calculated_price: float}>  $services
     */
    public static function calculatePrices(array $services, float|string $services_price, float|string $price): array
    {
        $servicesPrice = (float) str_replace(',', '', (string) $services_price);
        $bundlePrice = (float) str_replace(',', '', (string) $price);

        if ($servicesPrice == 0) {
            return $services;
        }

        if ($servicesPrice == $bundlePrice) {
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = $services[$key]['service_price'];
            }
        } elseif ($servicesPrice > $bundlePrice) {
            $ratio = 1 - round($bundlePrice / $servicesPrice, 8);
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round(
                    $services[$key]['service_price'] - ($services[$key]['service_price'] * $ratio),
                    2
                );
            }
        } else {
            $ratio = -1 * (1 - round($bundlePrice / $servicesPrice, 8));
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round(
                    $services[$key]['service_price'] + ($services[$key]['service_price'] * $ratio),
                    2
                );
            }
        }

        // Last-session absorption: adjust last service so SUM(calculated_price) == $price exactly
        if (count($services) > 1) {
            $sumWithoutLast = 0;
            $lastKey = array_key_last($services);
            foreach ($services as $key => $service) {
                if ($key !== $lastKey) {
                    $sumWithoutLast += $services[$key]['calculated_price'];
                }
            }
            $services[$lastKey]['calculated_price'] = round($bundlePrice - $sumWithoutLast, 2);
        }

        return $services;
    }
}
