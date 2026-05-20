<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanType;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class Packages extends BaseModel
{
    use ParsesDateRange;
    use SoftDeletes;

    protected $table = 'packages';

    protected static string $_table = 'packages';

    protected $fillable = [
        'random_id',
        'name',
        'sessioncount',
        'total_price',
        'is_exclusive',
        'plan_type',
        'account_id',
        'patient_id',
        'active',
        'created_at',
        'updated_at',
        'deleted_at',
        'location_id',
        'appointment_id',
        'is_refund',
    ];

    protected static array $_fillable = [
        'name',
        'sessioncount',
        'total_price',
        'is_exclusive',
        'plan_type',
        'patient_id',
        'active',
        'location_id',
        'appointment_id',
        'is_refund',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'float',
            'active' => 'boolean',
            'is_exclusive' => 'boolean',
            'is_refund' => 'boolean',
            'plan_type' => PlanType::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id')->withTrashed();
    }

    public function packagesadvances(): HasMany
    {
        return $this->hasMany(PackageAdvances::class, 'package_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointments::class);
    }

    public function packageservice(): HasMany
    {
        return $this->hasMany(PackageService::class, 'package_id');
    }

    public function packagebundles(): HasMany
    {
        return $this->hasMany(PackageBundles::class, 'package_id');
    }

    // ── CRUD Operations ─────────────────────────────────

    public static function createRecord(array $data, array|Request $request): self
    {
        $record = self::create($data);
        $record->update(['name' => sprintf('%05d', $record->id)]);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);
        PackageBundles::createRecord($record, $request instanceof Request ? $request->all() : $request);

        return $record;
    }

    public static function updateRecord(array $data, string $randomId, array|Request $request): self
    {
        $record = self::where('random_id', $randomId)->firstOrFail();
        $oldData = $record->toArray();

        $record->update($data);
        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $oldData, $record->id);
        PackageBundles::updateRecord($record, $request instanceof Request ? $request->all() : $request);

        return $record;
    }

    public static function updateRecordRefunds(int|string $packageId): self
    {
        $record = self::findOrFail($packageId);
        $oldData = $record->toArray();

        $record->update(['is_refund' => '1']);
        AuditTrails::editEventLogger(self::$_table, 'Edit', $record->toArray(), self::$_fillable, $oldData, $record->id);

        return $record;
    }

    public static function inactiveRecord(int|string $id): array
    {
        $package = self::getData($id);

        if (! $package) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $package->update(['active' => 0]);
        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return ['status' => true, 'message' => 'Record has been inactivated successfully.'];
    }

    public static function activeRecord(int|string $id): array
    {
        $package = self::getData($id);

        if (! $package) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $package->update(['active' => 1]);
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return ['status' => true, 'message' => 'Record has been activated successfully.'];
    }

    public static function DeleteRecord(int|string $id): array
    {
        $package = self::getData($id);

        if (! $package) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        if (self::isChildExists($id, Auth::user()->account_id)) {
            return ['status' => false, 'message' => 'Child records exist, unable to delete resource'];
        }

        $package->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    public static function isChildExists(int|string $id, int|string $accountId): bool
    {
        return InvoiceDetails::where('package_id', $id)->exists()
            || PackageAdvances::where('package_id', $id)->exists();
    }

    // ── Query Helpers ───────────────────────────────────

    public static function getTotalRecords(Request $request, int|string $accountId, int|string|false $id, bool $applyFilter, string $filename): int
    {
        $where = self::filters($request, $accountId, $id, $applyFilter, $filename);

        $query = self::when(! empty($where), fn ($q) => $q->where($where))
            ->whereIn('location_id', ACL::getUserCentres());

        if (! Gate::allows('plans.list.view_inactive')) {
            $query->where('active', 1);
        }

        return $query->count();
    }

    public static function getRecords(
        Request $request,
        int|string $iDisplayStart,
        int|string $iDisplayLength,
        int|string $accountId,
        int|string|false $id,
        bool $applyFilter,
        string $filename,
    ): Collection {
        $where = self::filters($request, $accountId, $id, $applyFilter, $filename);
        [$orderBy, $order] = getSortBy($request, 'updated_at', 'DESC');

        $query = self::when(! empty($where), fn ($q) => $q->where($where))
            ->whereIn('location_id', ACL::getUserCentres())
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order);

        if (! Gate::allows('plans.list.view_inactive')) {
            $query->where('active', 1);
        }

        return $query->get();
    }

    public static function filters(Request $request, int|string $accountId, int|string|false $id, bool $applyFilter, string $filename): array
    {
        $where = [];
        $filters = getFilters($request->all());
        $applyFilter = checkFilters($filters, $filename);

        [$startDateTime, $endDateTime] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );

        $userId = Auth::id();

        // Patient ID
        if ($id !== false) {
            $where[] = ['patient_id', '=', $id];
            Filters::put($userId, $filename, 'patient_id', $id);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, $filename, 'patient_id');
            }
        }

        // Account ID
        if ($accountId) {
            $where[] = ['account_id', '=', $accountId];
            Filters::put($userId, $filename, 'account_id', $accountId);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'account_id');
        } elseif ($cached = Filters::get($userId, $filename, 'account_id')) {
            $where[] = ['account_id', '=', $cached];
        }

        if (hasFilter($filters, 'patient_id')) {
            $where[] = ['patient_id', '=', $filters['patient_id']];
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'patient_id');
        }

        if (hasFilter($filters, 'id')) {
            $patientId = GeneralFunctions::patientSearch($filters['id']);
            $where[] = ['patient_id', '=', $patientId];
            Filters::put($userId, $filename, 'patient_id', $patientId);
            Filters::put($userId, $filename, 'id', $patientId);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'id');
        }

        if (hasFilter($filters, 'package_id')) {
            $where[] = ['id', '=', $filters['package_id']];
            Filters::put($userId, $filename, 'package_id', $filters['package_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'package_id');
        } elseif ($cached = Filters::get($userId, $filename, 'package_id')) {
            $where[] = ['id', '=', $cached];
        }

        if (hasFilter($filters, 'created_at') && $startDateTime && $endDateTime) {
            $where[] = ['created_at', '>=', $startDateTime];
            $where[] = ['created_at', '<=', $endDateTime];
            Filters::put($userId, $filename, 'created_at', $filters['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'created_at');
        } elseif ($cached = Filters::get($userId, $filename, 'created_at')) {
            $where[] = ['created_at', '>=', $cached];
        }

        if (hasFilter($filters, 'location_id')) {
            $where[] = ['location_id', '=', $filters['location_id']];
            Filters::put($userId, $filename, 'location_id', $filters['location_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'location_id');
        } elseif ($cached = Filters::get($userId, $filename, 'location_id')) {
            $where[] = ['location_id', '=', $cached];
        }

        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, $filename, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'status');
        } else {
            $cached = Filters::get($userId, $filename, 'status');
            if ($cached === 0 || $cached === 1 || $cached === '0' || $cached === '1') {
                $where[] = ['active', '=', $cached];
            }
        }

        return $where;
    }
}
