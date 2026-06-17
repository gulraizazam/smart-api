<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentType;
use App\Models\ServiceBundle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PackageBundles extends Model
{
    use SoftDeletes;

    protected $table = 'package_bundles';

    protected static string $_table = 'package_bundles';

    protected $fillable = [
        'random_id',
        'qty',
        'discount_name',
        'discount_type',
        'discount_price',
        'service_price',
        'net_amount',
        'is_exclusive',
        'tax_exclusive_net_amount',
        'tax_percentage',
        'tax_price',
        'tax_including_price',
        'location_id',
        'discount_id',
        'config_group_id',
        'bundle_id',
        'source_type',
        'membership_type_id',
        'membership_code_id',
        'is_allocate',
        'package_id',
        'account_id',
        'active',
        'created_at',
        'updated_at',
        'deleted_at',
        'base_service_id',
    ];

    protected static array $_fillable = [
        'qty',
        'discount_name',
        'discount_type',
        'discount_price',
        'service_price',
        'net_amount',
        'is_exclusive',
        'tax_exclusive_net_amount',
        'tax_percentage',
        'tax_price',
        'tax_including_price',
        'location_id',
        'discount_id',
        'bundle_id',
        'package_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'service_price'           => 'float',
            'net_amount'              => 'float',
            'discount_price'          => 'float',
            'tax_exclusive_net_amount' => 'float',
            'tax_price'               => 'float',
            'tax_including_price'     => 'float',
            'tax_percentage'         => 'float',
            'is_exclusive'            => 'boolean',
            'is_allocate'             => 'boolean',
            'active'                  => 'boolean',
            'account_id'              => 'integer',
        ];
    }

    /**
     * Stamp the tenant `account_id` on every new row. The column is
     * nullable + additive (crm2 never writes it), so a row created by a
     * path this hook can't resolve simply stays NULL and is picked up by
     * the backfill. Web plan flows always run as the acting user within
     * their own account (== the parent package's account), so Auth is
     * the cheap, correct source; CLI / queue / seeder contexts with no
     * auth fall back to deriving from the linked package. Covers all
     * ~9 PackageBundles::create() call sites without touching each one.
     * Mirrors the static::booted persistence-seam pattern in App\Models\Services.
     */
    protected static function booted(): void
    {
        static::creating(function (self $bundle): void {
            if ($bundle->getAttribute('account_id') !== null) {
                return;
            }

            $accountId = Auth::check() ? (int) (Auth::user()->account_id ?? 0) : 0;

            if ($accountId <= 0) {
                $accountId = self::deriveAccountIdFromPackage($bundle) ?? 0;
            }

            if ($accountId > 0) {
                $bundle->setAttribute('account_id', $accountId);
            }
        });

        // Type-tag invariant (the single guard against the recurring
        // wrong-record class). `bundle_id` is an OVERLOADED FK whose meaning
        // depends entirely on `source_type` (service → services.id, bundle →
        // bundles.id, service_bundle → service_bundles.id). These tables share
        // id ranges, so a row written WITHOUT a valid source_type silently
        // resolves the WRONG catalog downstream (wrong name/price on invoices,
        // reports, the plan dialog). Fail LOUD at write time instead of letting
        // a mis-resolved row ship.
        //
        // Membership lines are keyed by their own `membership_type_id` column
        // and intentionally leave source_type NULL (matches crm2) — exempt.
        // crm3-only: crm2 has its own model, so this never affects crm2's
        // writes to the shared DB, nor any existing row (creating-only).
        static::creating(function (self $bundle): void {
            if (! empty($bundle->getAttribute('membership_type_id'))) {
                return; // membership line — source_type NULL by design
            }
            $sourceType = $bundle->getAttribute('source_type');
            $valid = ['service', 'bundle', 'service_bundle'];
            if (! in_array($sourceType, $valid, true)) {
                throw new \RuntimeException(
                    'package_bundles write rejected: a non-membership line must carry '
                    .'source_type one of {service, bundle, service_bundle} (got '
                    .var_export($sourceType, true).'). bundle_id is an overloaded FK; '
                    .'an untagged row silently resolves the wrong catalog.'
                );
            }
        });
    }

    /**
     * Resolve the owning package's account_id from package_id, then
     * random_id — the same two keys the backfill migration uses.
     */
    private static function deriveAccountIdFromPackage(self $bundle): ?int
    {
        $packageId = $bundle->getAttribute('package_id');
        if ($packageId !== null) {
            $accountId = Packages::whereKey($packageId)->value('account_id');
            if ($accountId !== null) {
                return (int) $accountId;
            }
        }

        $randomId = $bundle->getAttribute('random_id');
        if (! empty($randomId)) {
            $accountId = Packages::where('random_id', $randomId)->value('account_id');
            if ($accountId !== null) {
                return (int) $accountId;
            }
        }

        return null;
    }

    // ── Relationships ───────────────────────────────────

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundles::class, 'bundle_id')->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'bundle_id')->withTrashed();
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discounts::class, 'discount_id')->withTrashed();
    }

    public function packageservice(): HasMany
    {
        return $this->hasMany(PackageService::class, 'package_bundle_id');
    }

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Packages::class, 'package_id');
    }

    /**
     * Service bundle relationship (for source_type='service_bundle').
     * bundle_id references service_bundles.id when source_type is 'service_bundle'.
     */
    public function serviceBundle(): BelongsTo
    {
        return $this->belongsTo(ServiceBundle::class, 'bundle_id');
    }

    // ── Creation ────────────────────────────────────────

    public static function createPackagebundle(array $data): self
    {
        $packageRecord = Packages::where('random_id', $data['random_id'])->first();
        $discountType = Discounts::find($data['discount_id']);

        if ($discountType?->type === 'Configurable') {
            $findBaseService = BaseDiscountService::where('discount_id', $discountType->id)->first();
            $data['base_service_id'] = $findBaseService?->service_id;
            $data['package_id'] = $packageRecord?->id;

            if ((float) ($data['tax_including_price'] ?? 0) > 0) {
                $data['discount_type'] = $data['discount_type'] ?? 'Configurable';
            } else {
                $data['discount_type'] = 'Percentage';
                $data['discount_price'] = 100;
                $data['net_amount'] = 0;
                $data['tax_including_price'] = 0;
                $data['tax_exclusive_net_amount'] = 0;
                $data['tax_price'] = 0;
            }
        }

        if (!isset($data['package_id']) || $data['package_id'] === null) {
            $data['package_id'] = $packageRecord?->id;
        }

        return self::create($data);
    }

    // ── Record Operations ───────────────────────────────

    public static function createRecord(self|Packages $package, array $request): bool
    {
        $parentId = $package->id;
        // Stamp account_id at allocation (when the row is bound to a plan). The
        // create-time hook can't always derive it before the plan exists, which
        // left allocated rows with NULL account_id and broke the remove-service
        // flow (e.g. plan 50611). The plan's account is authoritative here.
        $updateDetails = ['package_id' => $package->id, 'is_allocate' => 1, 'account_id' => $package->account_id];

        foreach ($request['package_bundles'] as $bundleId) {
            self::where(['id' => $bundleId, 'random_id' => $package->random_id])
                ->update($updateDetails);
        }

        $packageBundles = self::where(['package_id' => $package->id, 'is_allocate' => '1'])->get();
        $packageBundleIds = self::where(['package_id' => $package->id, 'is_allocate' => '1'])->pluck('id');

        $getPackage = Packages::findOrFail($packageBundles->first()->package_id);
        $getAppointment = Appointments::join('invoices', 'appointments.id', 'invoices.appointment_id')
            ->select('appointments.id', 'appointments.service_id')
            ->where([
                'appointments.patient_id'        => $getPackage->patient_id,
                'appointments.appointment_type_id' => AppointmentType::Consultancy->value,
            ])
            ->latest('invoices.created_at')
            ->first();

        $getInvoiceInfo = Invoices::where(['appointment_id' => $getAppointment->id])->first();

        $packageServices = PackageService::with('service')
            ->whereIn('package_bundle_id', $packageBundleIds)
            ->where('created_at', '>', Carbon::parse($getInvoiceInfo->created_at))
            ->get();

        foreach ($packageServices as $svc) {
            if ($svc->service->parent_id != $getAppointment->service_id) {
                $getAppointment->update(['service_id' => $packageServices->first()->service->parent_id]);
                break;
            }
        }

        foreach ($packageBundles as $bundle) {
            AuditTrails::addEventLogger(self::$_table, 'create', $bundle->toArray(), self::$_fillable, $bundle, $parentId);
            PackageService::createRecord($bundle);
        }

        return true;
    }

    public static function updateRecord(self|Packages $package, array $request): bool
    {
        $parentId = $package->id;
        // Stamp account_id at allocation (when the row is bound to a plan). The
        // create-time hook can't always derive it before the plan exists, which
        // left allocated rows with NULL account_id and broke the remove-service
        // flow (e.g. plan 50611). The plan's account is authoritative here.
        $updateDetails = ['package_id' => $package->id, 'is_allocate' => 1, 'account_id' => $package->account_id];

        if (!empty($request['package_bundles'])) {
            foreach ($request['package_bundles'] as $bundleId) {
                self::where([
                    ['id', '=', $bundleId],
                    ['random_id', '=', $package->random_id],
                ])->update($updateDetails);
            }

            $bundles = self::where([
                ['package_id', '=', $package->id],
                ['is_allocate', '=', '1'],
            ])->get();

            foreach ($bundles as $bundle) {
                AuditTrails::editEventLogger(self::$_table, 'Edit', $bundle->toArray(), self::$_fillable, '0', $bundle, $parentId);
                PackageService::updateRecord($bundle);
            }
        }

        $bundleIds = self::where(['package_id' => $package->id, 'is_allocate' => '1'])->pluck('id');

        $getAppointment = Appointments::join('invoices', 'appointments.id', 'invoices.appointment_id')
            ->select('appointments.id', 'appointments.service_id')
            ->where([
                'appointments.patient_id'        => $package->patient_id,
                'appointments.appointment_type_id' => AppointmentType::Consultancy->value,
            ])
            ->latest('invoices.created_at')
            ->first();

        $getInvoiceInfo = Invoices::where(['appointment_id' => $getAppointment->id])->first();

        $packageServices = PackageService::with('service')
            ->whereIn('package_bundle_id', $bundleIds)
            ->where('created_at', '>', Carbon::parse($getInvoiceInfo->created_at))
            ->get();

        foreach ($packageServices as $svc) {
            if ($svc->service->parent_id != $getAppointment->service_id) {
                $getAppointment->update(['service_id' => $packageServices->first()->service->parent_id]);
                break;
            }
        }

        return true;
    }
}
