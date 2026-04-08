<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackageService extends Model
{
    protected $table = 'package_services';

    protected static string $_table = 'package_services';

    protected $fillable = [
        'random_id',
        'package_id',
        'package_bundle_id',
        'service_id',
        'created_at',
        'updated_at',
        'is_consumed',
        'consumed_at',
        'consumption_order',
        'price',
        'orignal_price',
        'actual_price',
        'is_exclusive',
        'tax_exclusive_price',
        'tax_percenatage',
        'tax_price',
        'tax_including_price',
        'sold_by',
        'base_service_id',
    ];

    protected static array $_fillable = [
        'random_id',
        'package_id',
        'package_bundle_id',
        'service_id',
        'is_consumed',
        'consumed_at',
        'consumption_order',
        'price',
        'orignal_price',
        'actual_price',
        'is_exclusive',
        'tax_exclusive_price',
        'tax_percenatage',
        'tax_price',
        'tax_including_price',
        'sold_by',
        'base_service_id',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'price'              => 'float',
            'orignal_price'      => 'float',
            'actual_price'       => 'float',
            'tax_exclusive_price' => 'float',
            'tax_price'          => 'float',
            'tax_including_price' => 'float',
            'tax_percenatage'    => 'float',
            'is_exclusive'       => 'boolean',
            'is_consumed'        => 'boolean',
            'consumed_at'        => 'datetime',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id')->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Packages::class, 'package_id')->withTrashed();
    }

    public function soldBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function packagebundle(): BelongsTo
    {
        return $this->belongsTo(PackageBundles::class, 'package_bundle_id')->withTrashed();
    }

    // ── Creation ────────────────────────────────────────

    public static function createPackageService(array $data): self
    {
        $findPackageBundle = PackageBundles::findOrFail($data['package_bundle_id']);
        $findDiscount = Discounts::find($findPackageBundle->discount_id);

        // Fetch actual price from services table
        if (isset($data['service_id'])) {
            $service = Services::find($data['service_id']);
            $data['actual_price'] = $service?->price;
        }

        $findPackage = Packages::where('random_id', $data['random_id'])->first();
        if ($findPackage) {
            $data['package_id'] = $findPackage->id;
        }

        if ($findDiscount?->type === 'Configurable') {
            $findBaseService = BaseDiscountService::where('discount_id', $findDiscount->id)->first();
            $data['base_service_id'] = $findBaseService?->service_id;

            if ((float) ($data['tax_including_price'] ?? 0) === 0.0) {
                $data['price'] = 0;
                $data['tax_price'] = 0;
                $data['tax_exclusive_price'] = 0;
                $data['tax_including_price'] = 0;
            }
        }

        return self::create($data);
    }

    // ── Record Operations ───────────────────────────────

    public static function createRecord(PackageBundles $packageBundle): bool
    {
        self::where([
            ['random_id', '=', $packageBundle->random_id],
            ['package_bundle_id', '=', $packageBundle->id],
        ])->update(['package_id' => $packageBundle->package_id]);

        $services = self::where('package_bundle_id', $packageBundle->id)->get();

        foreach ($services as $svc) {
            AuditTrails::addEventLogger(
                self::$_table,
                'create',
                $svc->toArray(),
                self::$_fillable,
                $svc,
                $packageBundle->id,
            );
        }

        return true;
    }

    public static function updateRecord(PackageBundles $packageBundle): bool
    {
        // Use Eloquent to avoid SQL injection from raw DB::statement
        self::where('random_id', $packageBundle->random_id)
            ->where('package_bundle_id', $packageBundle->id)
            ->update([
                'package_id' => $packageBundle->package_id,
                'updated_at' => DB::raw('updated_at'), // preserve original timestamp
            ]);

        $services = self::where('package_bundle_id', $packageBundle->id)->get();

        foreach ($services as $svc) {
            AuditTrails::editEventLogger(
                self::$_table,
                'Edit',
                $svc->toArray(),
                self::$_fillable,
                '0',
                $svc,
                $packageBundle->id,
            );
        }

        return true;
    }

    public static function updateRecordInvoice(self $packagesService): bool
    {
        AuditTrails::editEventLogger(
            self::$_table,
            'Edit',
            $packagesService->toArray(),
            self::$_fillable,
            '0',
            $packagesService,
            $packagesService->package_bundle_id,
        );

        return true;
    }

    public static function InvoiceCancel(object $invoiceDetail, int|string $accountId): bool
    {
        $packageService = self::findOrFail($invoiceDetail->package_service_id);
        $oldData = $packageService->toArray();

        $packageService->update(['is_consumed' => '0', 'consumed_at' => null]);

        AuditTrails::editEventLogger(
            self::$_table,
            'Edit',
            $packageService->fresh()->toArray(),
            self::$_fillable,
            $oldData,
            $packageService->toArray(),
            $packageService->package_bundle_id,
        );

        return true;
    }
}
