<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentType;
use App\Enums\CashFlow;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Services\PatientManagement\PatientSearchService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PackageAdvances extends BaseModel
{
    use ParsesDateRange;
    use SoftDeletes;

    protected $table = 'package_advances';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'cash_flow',
        'cash_amount',
        'active',
        'patient_id',
        'payment_mode_id',
        'account_id',
        'appointment_type_id',
        'appointment_id',
        'location_id',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'package_id',
        'deleted_at',
        'invoice_id',
        // Inventory-module link column. No longer written — inventory orders
        // are overlay-only and do not create package_advances rows. Kept in
        // fillable/schema (column is unapplied on prod) but inert.
        'order_id',
        'is_cancel',
        'is_tax',
        'is_setteled',
        'is_refund',
        'refund_note',
        'is_adjustment',
    ];

    protected static array $_fillable = [
        'cash_flow',
        'cash_amount',
        'active',
        'patient_id',
        'payment_mode_id',
        'appointment_type_id',
        'appointment_id',
        'location_id',
        'created_by',
        'updated_by',
        'package_id',
        'invoice_id',
        'is_cancel',
        'is_tax',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected static string $_table = 'package_advances';

    protected function casts(): array
    {
        return [
            'cash_amount' => 'float',
            'active' => 'boolean',
            'is_cancel' => 'boolean',
            'is_tax' => 'boolean',
            'is_setteled' => 'boolean',
            'is_refund' => 'boolean',
            'is_adjustment' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Override create to prevent manual id assignment.
     */
    public static function create(array $attributes = []): static
    {
        unset($attributes['id']);

        return static::query()->create($attributes);
    }

    // ── Relationships ───────────────────────────────────

    public function paymentmode(): BelongsTo
    {
        return $this->belongsTo(PaymentModes::class, 'payment_mode_id')->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Packages::class, 'package_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id')->withTrashed();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoices::class, 'invoice_id')->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointments::class, 'appointment_id')->withTrashed();
    }

    // ── Record Operations ───────────────────────────────

    public static function createRecord(array $data, object $parentData): self
    {
        $record = new self;
        $record->cash_flow = CashFlow::In->value;
        $record->cash_amount = $data['cash_amount'];
        $record->account_id = Auth::user()->account_id;
        $record->patient_id = $data['patient_id'];
        $record->payment_mode_id = $data['payment_mode_id'];
        $record->created_by = Auth::id();
        $record->updated_by = Auth::id();
        $record->package_id = $data['package_id'];
        $record->location_id = $data['location_id'];
        $record->updated_at = Filters::getCurrentTimeStamp();
        $record->appointment_id = $parentData->appointment_id;
        $record->save();

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parentData->id);

        self::createPlanInvoice($record);
        self::updateLeadStatusToConverted(
            $data['package_id'],
            $data['account_id'] ?? Auth::user()->account_id,
        );

        return $record;
    }

    public static function createRecord_forinvoice(array $data): self
    {
        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        if (($data['cash_flow'] ?? '') === CashFlow::In->value) {
            self::createPlanInvoice($record);
        }

        if (($data['cash_flow'] ?? '') === CashFlow::In->value && isset($data['package_id'])) {
            self::updateLeadStatusToConverted(
                $data['package_id'],
                $data['account_id'] ?? Auth::user()->account_id,
            );
        }

        return $record;
    }

    public static function updateRecord(array $data, object $parentData): self
    {
        $package = Packages::find($data['package_id']);
        $packageCreatedAt = $package?->created_at;
        $appointmentId = $parentData->appointment_id;

        $arrivedStatus = AppointmentStatuses::where([
            'account_id' => Auth::user()->account_id,
            'is_arrived' => 1,
        ])->first();

        if ($arrivedStatus && $packageCreatedAt) {
            $packageBundleIds = PackageBundles::where('package_id', $data['package_id'])->pluck('id');

            $newPackageServices = PackageService::whereIn('package_bundle_id', $packageBundleIds)
                ->where('created_at', '>', $packageCreatedAt)
                ->count();

            if ($newPackageServices > 0) {
                $latestArrivedAppointment = Appointments::where([
                    'patient_id' => $data['patient_id'],
                    'appointment_type_id' => AppointmentType::Consultancy->value,
                    'base_appointment_status_id' => $arrivedStatus->id,
                ])
                    ->where('updated_at', '>', $packageCreatedAt)
                    ->orderByDesc('updated_at')
                    ->first();

                if ($latestArrivedAppointment) {
                    $appointmentId = $latestArrivedAppointment->id;
                }
            }
        }

        $record = new self;
        $record->cash_flow = CashFlow::In->value;
        $record->cash_amount = $data['cash_amount'];
        $record->account_id = Auth::user()->account_id;
        $record->patient_id = $data['patient_id'];
        $record->payment_mode_id = $data['payment_mode_id'];
        $record->created_by = Auth::id();
        $record->updated_by = Auth::id();
        $record->package_id = $data['package_id'];
        $record->location_id = $data['location_id'];
        $record->updated_at = Filters::getCurrentTimeStamp();
        $record->appointment_id = $appointmentId;
        $record->save();

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, '0', $parentData->id);

        self::updateLeadStatusToConverted(
            $data['package_id'],
            $data['account_id'] ?? Auth::user()->account_id,
        );

        return $record;
    }

    public static function updateRecordFinanceedit(array $input, int $accountId, bool $amountStatus): ?bool
    {
        // Tenant-scoped fetch + row lock in a single statement. Two
        // benefits over the previous "bare findOrFail then re-scoped
        // where" pattern:
        //   1. The cross-tenant case never populates `$oldData`, so
        //      another tenant's amount / patient / mode can't appear in
        //      exception traces or audit logs.
        //   2. `lockForUpdate` serialises concurrent edits to the same
        //      advance row — without it, two simultaneous edits could
        //      both read the same baseline amount and both fire the
        //      observer's pool-delta logic, double-applying the change.
        $record = self::query()
            ->where('id', $input['package_advances_id'])
            ->where('account_id', $accountId)
            ->lockForUpdate()
            ->first();

        if (! $record) {
            return null;
        }

        $oldData = $record->toArray();

        $data = [
            'payment_mode_id' => $input['payment_mode_id'],
            'created_at' => $input['created_at'].' '.Carbon::now()->toTimeString(),
            'updated_at' => now(),
        ];

        if ($amountStatus) {
            $data['cash_amount'] = $input['cash_amount'];
        }

        $record->update($data);
        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $oldData, $input['package_advances_id']);

        return true;
    }

    public static function createRecord_onlyadvances(array $data): self
    {
        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        if (($data['cash_flow'] ?? '') === CashFlow::In->value) {
            self::createPlanInvoice($record);
        }

        if (($data['cash_flow'] ?? '') === CashFlow::In->value && isset($data['package_id'])) {
            self::updateLeadStatusToConverted(
                $data['package_id'],
                $data['account_id'] ?? Auth::user()->account_id,
            );
        }

        return $record;
    }

    /**
     * Update lead + leads_services status to Converted when a package
     * payment is received. Wired to every `cash_flow='in'` write path
     * (createRecord, createRecord_forinvoice, updateRecord, etc.).
     *
     * Gated on the **appointment** already being Arrived or Converted —
     * the Plan-side conversion writer (`PlanService::markAppointment-
     * AsConvertedOptimized`) is the single source of truth for the
     * "should this conversion fire?" decision; we simply look at the
     * post-state it produced. Without this gate a pre-payment (cash-in
     * before the consultation invoice exists) would silently leak
     * Converted into the lead row even though the appointment-side
     * gate refused to convert. See agent reports + `WrongConversion-
     * Service` for the historical data tail this prevents.
     *
     * Failures are caught and logged — this is a best-effort cascade
     * inside a parent payment transaction; a write failure here must
     * not roll back the parent.
     */
    public static function updateLeadStatusToConverted(int|string $packageId, int|string $accountId): void
    {
        $packageId = (int) $packageId;
        $accountId = (int) $accountId;

        try {
            $package = Packages::find($packageId);
            if (! $package?->appointment_id) {
                return;
            }

            $appointment = Appointments::find($package->appointment_id);
            if (! $appointment?->lead_id) {
                return;
            }

            // Conversion gate — defer the "should we convert?" decision
            // to the appointment's current status, which `PlanService::
            // markAppointmentAsConvertedOptimized` (the canonical gate)
            // sets only when its full preconditions are met.
            $apptStatus = AppointmentStatuses::find($appointment->appointment_status_id);
            $shouldConvert = ($apptStatus?->is_arrived ?? false) || ($apptStatus?->is_converted ?? false);
            if (! $shouldConvert) {
                return;
            }

            $convertedStatus = LeadStatuses::where([
                'account_id' => $accountId,
                'is_converted' => 1,
            ])->first();

            if (! $convertedStatus) {
                return;
            }

            DB::transaction(function () use ($appointment, $convertedStatus): void {
                Leads::where('id', $appointment->lead_id)
                    ->update(['lead_status_id' => $convertedStatus->id]);

                LeadsServices::where('lead_id', $appointment->lead_id)
                    ->where('service_id', $appointment->service_id)
                    ->update(['lead_status_id' => $convertedStatus->id]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to update lead status to converted: '.$e->getMessage());
        }
    }

    /**
     * Create corresponding plan_invoice record when cash_flow = 'in'.
     */
    protected static function createPlanInvoice(self $packageAdvance): void
    {
        try {
            if ($packageAdvance->cash_flow !== CashFlow::In->value || ! $packageAdvance->package_id) {
                return;
            }

            $invoiceNumber = PlanInvoice::generateInvoiceNumber(
                (int) $packageAdvance->patient_id,
                (int) $packageAdvance->package_id,
            );

            PlanInvoice::create([
                'invoice_number' => $invoiceNumber,
                'total_price' => $packageAdvance->cash_amount,
                'account_id' => $packageAdvance->account_id,
                'patient_id' => $packageAdvance->patient_id,
                'created_by' => $packageAdvance->created_by,
                'location_id' => $packageAdvance->location_id,
                'payment_mode_id' => $packageAdvance->payment_mode_id,
                'active' => 1,
                'package_id' => $packageAdvance->package_id,
                'package_advance_id' => $packageAdvance->id,
                'invoice_type' => 'exempt',
                'created_at' => $packageAdvance->created_at,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create plan_invoice for package_advance', [
                'package_advance_id' => $packageAdvance->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function updateRecord_onlyadvances(array $data, int|string $id): self
    {
        $oldData = self::findOrFail($id)->toArray();
        $record = self::where('id', $id)->firstOrFail();

        $record->update($data);
        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $oldData, $id);

        return $record;
    }

    public static function inactiveRecord(int|string $id): bool
    {
        $advance = self::getData($id);

        if (! $advance) {
            flash('Resource not found.')->error()->important();

            return false;
        }

        $advance->update(['active' => 0]);
        flash('Record has been inactivated successfully.')->success()->important();
        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return true;
    }

    public static function activeRecord(int|string $id): bool
    {
        $advance = self::getData($id);

        if (! $advance) {
            flash('Resource not found.')->error()->important();

            return false;
        }

        $advance->update(['active' => 1]);
        flash('Record has been activated successfully.')->success()->important();
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return true;
    }

    public static function DeleteRecord(int|string $id): bool
    {
        $advance = self::getData($id);

        if (! $advance) {
            flash('Resource not found.')->error()->important();

            return false;
        }

        if (self::isChildExists($id, Auth::user()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();

            return false;
        }

        $advance->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);
        flash('Record has been deleted successfully.')->success()->important();

        return true;
    }

    public static function deletefinaceRecord(int $packageAdvanceId): ?bool
    {
        $advance = self::withTrashed()->findOrFail($packageAdvanceId);
        $advance->delete();

        AuditTrails::softDeleteEventLogger(
            self::$_table,
            'delete',
            $advance->toArray(),
            self::$_fillable,
            $packageAdvanceId,
        );

        return true;
    }

    public static function CancelRecord(int|string $id, int|string $accountId): ?self
    {
        $record = self::where(['id' => $id, 'account_id' => $accountId])->first();

        if (! $record) {
            return null;
        }

        $record->update(['is_cancel' => '1']);

        return $record;
    }

    public static function isChildExists(int|string $id, int|string $accountId): bool
    {
        // Currently no child constraints; kept for interface compatibility
        return false;
    }

    // ── Query Helpers ───────────────────────────────────

    public static function getTotalRecords(Request $request, int $accountId, int|false $id, bool $applyFilter, string $filename): int
    {
        $where = self::filters_packageAdvances($request, $accountId, $id, $applyFilter, $filename);

        return self::when(! empty($where), fn ($q) => $q->where($where))
            ->where('cash_amount', '!=', 0)
            ->count();
    }

    public static function getRecords(
        Request $request,
        int $iDisplayStart,
        int $iDisplayLength,
        int $accountId,
        int|false $id,
        bool $applyFilter,
        string $filename,
    ): Collection {
        [$orderBy, $order] = getSortBy($request, 'created_at', 'DESC');
        $where = self::filters_packageAdvances($request, $accountId, $id, $applyFilter, $filename);

        return self::when(! empty($where), fn ($q) => $q->where($where))
            ->where('cash_amount', '!=', 0)
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->get();
    }

    public static function filters_packageAdvances(Request $request, int $accountId, int|false $id, bool $applyFilter, string $filename): array
    {
        $where = [];
        $filters = getFilters($request->all());
        $userId = Auth::id();

        [$startDateTime, $endDateTime] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );

        if ($id !== false) {
            $where[] = ['patient_id', '=', $id];
            Filters::put($userId, $filename, 'id', $id);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'id');
        } elseif ($cached = Filters::get($userId, $filename, 'id')) {
            $where[] = ['patient_id', '=', $cached];
        }

        if ($accountId) {
            $where[] = ['account_id', '=', $accountId];
            Filters::put($userId, $filename, 'account_id', $accountId);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'account_id');
        } elseif ($cached = Filters::get($userId, $filename, 'account_id')) {
            $where[] = ['account_id', '=', $cached];
        }

        if (hasFilter($filters, 'patient_id')) {
            $where[] = ['patient_id', '=', PatientSearchService::patientSearch($filters['patient_id'])];
            Filters::put($userId, $filename, 'patient_id', $filters['patient_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'patient_id');
        } elseif ($cached = Filters::get($userId, $filename, 'patient_id')) {
            $where[] = ['patient_id', '=', $cached];
        }

        if (hasFilter($filters, 'package_id')) {
            $where[] = ['package_id', '=', (int) $filters['package_id']];
        }

        if (hasFilter($filters, 'cash_flow')) {
            $where[] = ['cash_flow', '=', $filters['cash_flow']];
        }

        if (hasFilter($filters, 'payment_mode_id')) {
            $where[] = ['payment_mode_id', '=', (int) $filters['payment_mode_id']];
        }

        if (hasFilter($filters, 'is_refund')) {
            $where[] = ['is_refund', '=', $filters['is_refund']];
        }

        if (hasFilter($filters, 'is_cancel')) {
            $where[] = ['is_cancel', '=', $filters['is_cancel']];
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

        return $where;
    }

    public static function getAppointmentPackage(int|string $appointmentId, int|string $patientId, int|string|null $id = null): float
    {
        if ($id === null) {
            return (float) self::where([
                ['appointment_id', '=', $appointmentId],
                ['patient_id', '=', $patientId],
                ['cash_flow', '=', CashFlow::Out->value],
            ])->sum('cash_amount');
        }

        return (float) self::where([
            ['id', '=', $id],
            ['appointment_id', '=', $appointmentId],
            ['patient_id', '=', $patientId],
            ['cash_flow', '=', CashFlow::Out->value],
        ])->value('cash_amount') ?? 0;
    }

    public static function getRefundedRecords(
        Request $request,
        int $iDisplayStart,
        int $iDisplayLength,
        int $accountId,
        int|false $id,
        bool $applyFilter,
        string $filename,
    ): Collection {
        $where = self::filters($request, $accountId, $id, $applyFilter, $filename);
        [$orderBy, $order] = getSortBy($request, 'id', 'DESC');

        // Eager-load relations the caller (RefundService::buildGlobalRefundRows)
        // accesses on every row: $package->user->{name,phone,id}, $package->location,
        // $package->package. Without these, each row triggers a separate SELECT
        // (N+1) — for a 50-row datatable that's 150+ extra queries per request.
        // The aliased patient_id/location_id/package_id columns from the SELECT
        // expose foreign keys that Eloquent's eager loader can resolve.
        $query = self::with(['user', 'location.city', 'package'])
            ->when(! empty($where), fn ($q) => $q->where($where))
            ->where('is_refund', 1)
            ->whereIn('location_id', ACL::getUserCentres());

        if (! Gate::allows('plans.list.view_inactive')) {
            $query->where('active', 1);
        }

        return $query->select('package_id',
            DB::raw('MIN(id) as id'),
            DB::raw('MIN(patient_id) as patient_id'),
            DB::raw('MIN(location_id) as location_id'),
            DB::raw('MAX(created_at) as created_at'))
            ->groupBy('package_id')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->get();
    }

    public static function getPatientRefundedRecords(
        Request $request,
        int $iDisplayStart,
        int $iDisplayLength,
        int $accountId,
        int $patientId,
        bool $applyFilter,
        string $filename,
    ): Collection {
        $where = self::filters($request, $accountId, $patientId, $applyFilter, $filename);

        $query = self::with(['user', 'location.city', 'package'])
            ->when(! empty($where), fn ($q) => $q->where($where))
            ->where('is_refund', 1)
            ->whereIn('location_id', ACL::getUserCentres());

        if (! Gate::allows('plans.list.view_inactive')) {
            $query->where('active', 1);
        }

        return $query->select('package_id',
            DB::raw('MIN(id) as id'),
            DB::raw('MIN(patient_id) as patient_id'),
            DB::raw('MIN(location_id) as location_id'),
            DB::raw('MAX(created_at) as created_at'))
            ->groupBy('package_id')
            ->orderByDesc(DB::raw('MAX(created_at)'))
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->get();
    }

    public static function getTotalPatientRefundedRecords(
        Request $request,
        int $accountId,
        int $patientId,
        bool $applyFilter,
        string $filename,
    ): int {
        $where = self::filters($request, $accountId, $patientId, $applyFilter, $filename);

        $query = Packages::where('is_refund', 1)
            ->whereIn('location_id', ACL::getUserCentres());

        if (! empty($where)) {
            $query->where($where);
        }

        if (! Gate::allows('plans.list.view_inactive')) {
            $query->where('active', 1);
        }

        return $query->count();
    }

    public static function getTotalRefundedRecords(
        Request $request,
        int $accountId,
        int|false $id,
        bool $applyFilter,
        string $filename,
    ): int {
        $where = self::filters($request, $accountId, $id, $applyFilter, $filename);

        $query = Packages::whereIn('location_id', ACL::getUserCentres());

        if (! empty($where)) {
            $query->where($where)->where('is_refund', 1);
        }

        if (! Gate::allows('plans.list.view_inactive')) {
            $query->where('active', 1);
        }

        return $query->count();
    }

    public static function filters(
        Request $request,
        int $accountId,
        int|false $id,
        bool $applyFilter,
        string $filename,
    ): array {
        $where = [];
        $filters = getFilters($request->all());
        $applyFilter = checkFilters($filters, $filename);
        $userId = Auth::id();

        if ($id !== false) {
            $where[] = ['patient_id', '=', $id];
            Filters::put($userId, $filename, 'patient_id', $id);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'patient_id');
        }

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
            $patientId = PatientSearchService::patientSearch($filters['id']);
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

        if (hasFilter($filters, 'created_from')) {
            $where[] = ['created_at', '>=', $filters['created_from'].' 00:00:00'];
            Filters::put($userId, $filename, 'created_from', $filters['created_from'].' 00:00:00');
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'created_from');
        } elseif ($cached = Filters::get($userId, $filename, 'created_from')) {
            $where[] = ['created_at', '>=', $cached.' 00:00:00'];
        }

        if (hasFilter($filters, 'created_to')) {
            $where[] = ['created_at', '<=', $filters['created_to'].' 23:59:59'];
            Filters::put($userId, $filename, 'created_to', $filters['created_to'].' 23:59:59');
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'created_to');
        } elseif ($cached = Filters::get($userId, $filename, 'created_to')) {
            $where[] = ['created_at', '<=', $cached.' 23:59:59'];
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
