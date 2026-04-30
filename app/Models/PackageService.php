<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\PlanException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackageService extends Model
{
    use HasFactory;

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
        'tax_percentage',
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
        'tax_percentage',
        'tax_price',
        'tax_including_price',
        'sold_by',
        'base_service_id',
    ];

    protected function casts(): array
    {
        return [
            'price'              => 'float',
            'orignal_price'      => 'float',
            'actual_price'       => 'float',
            'tax_exclusive_price' => 'float',
            'tax_price'          => 'float',
            'tax_including_price' => 'float',
            'tax_percentage'    => 'float',
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

        // Configurable-discount cancel-order rule: mirrors the consume-order
        // rule in reverse. If this row sits inside a config_group, refuse to
        // un-consume it while a sibling at a HIGHER consumption_order is
        // still consumed — that sibling depended on this row being the
        // honoured BUY-side. Forcing reverse-order on cancel keeps the
        // group internally consistent and prevents the patient from
        // retaining a discounted/free GET while the paying BUY is undone.
        //
        // The bundle relation is used to look up `config_group_id`; each
        // bundle in the group shares the same group id and each
        // package_service inside those bundles carries its own
        // `consumption_order`.
        if ($packageService->package_bundle_id && $packageService->consumption_order > 0) {
            $bundle = PackageBundles::find($packageService->package_bundle_id);
            $configGroupId = $bundle?->config_group_id;
            if ($configGroupId) {
                $blockingSibling = self::query()
                    ->join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                    ->where('package_bundles.config_group_id', $configGroupId)
                    ->where('package_services.is_consumed', 1)
                    ->where('package_services.consumption_order', '>', $packageService->consumption_order)
                    ->where('package_services.id', '!=', $packageService->id)
                    ->select('package_services.id', 'services.name as service_name')
                    ->leftJoin('services', 'package_services.service_id', '=', 'services.id')
                    ->first();

                if ($blockingSibling) {
                    $svcName = $blockingSibling->service_name ?? 'a discounted service';
                    throw PlanException::invalidOperation(
                        "Cannot cancel this invoice — '{$svcName}' was consumed under the same configurable discount group. Cancel the discounted/free service first, then come back."
                    );
                }
            }
        }

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
