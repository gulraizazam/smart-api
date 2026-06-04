<?php

declare(strict_types=1);
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AuditTrails extends BaseModel
{
    /**
     * Field names whose values must NEVER be persisted to the audit log.
     * The audit trail captures pre-mutation request arrays, so a `password`
     * value here is the user's plaintext (the model mutator hashes it later).
     * Anything matching this list is replaced with REDACTED_PLACEHOLDER.
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'remember_token',
        'api_token',
        'token',
        'secret',
        'cvv',
        'card_number',
        // cnic is PII (national ID) — keep it masked in audit logs even
        // though it is no longer encrypted at rest (2026-06-04). Without
        // this entry, AuditTrails::addEventLogger captures the pre-mutation
        // value from the request, leaking PII into audit_trail_changes.
        'cnic',
    ];

    private const REDACTED_PLACEHOLDER = '[REDACTED]';

    /**
     * Cache TTL for audit trail action/table id lookups.
     * audit_trail_actions has 6 rows, audit_trail_tables has 48 rows — both
     * are essentially static reference tables. Caching for 24 hours
     * eliminates ~88K lookup queries per month.
     */
    private const LOOKUP_CACHE_TTL = 86400;

    protected $fillable = ['audit_trail_action_name', 'audit_trail_table_name', 'table_record_id', 'user_id', 'created_at', 'updated_at', 'parent_id'];

    protected $table = 'audit_trails';

    /**
     * Replace sensitive field values with a redacted placeholder before
     * they are written to audit_trail_changes. Field-name match is
     * case-insensitive.
     */
    private static function maskSensitive(string $field, mixed $value): mixed
    {
        return in_array(strtolower($field), self::SENSITIVE_FIELDS, true)
            ? self::REDACTED_PLACEHOLDER
            : $value;
    }

    /**
     * Resolve audit_trail_actions row id by name with 24-hour caching.
     * Returns 0 if the action name is not registered (matches the prior
     * `?? 0` fallback semantics in callers).
     */
    private static function resolveActionId(string $actionName): int
    {
        return (int) Cache::remember(
            'audit_trail_action_id:' . $actionName,
            self::LOOKUP_CACHE_TTL,
            fn () => AuditTrailActions::where('name', $actionName)->value('id') ?? 0
        );
    }

    /**
     * Resolve audit_trail_tables row id by name with 24-hour caching.
     * Returns 0 if the table name is not registered.
     */
    private static function resolveTableId(string $tableName): int
    {
        return (int) Cache::remember(
            'audit_trail_table_id:' . $tableName,
            self::LOOKUP_CACHE_TTL,
            fn () => AuditTrailTables::where('name', $tableName)->value('id') ?? 0
        );
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:D M, j Y, h:i:a',
        ];
    }

    /**
     * sent the location name to resource with location_id.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditTable(): BelongsTo
    {
        return $this->belongsTo(AuditTrailTables::class, 'audit_trail_table_name', 'id');
    }

    public function auditAction(): BelongsTo
    {
        return $this->belongsTo(AuditTrailActions::class, 'audit_trail_action_name', 'id');
    }

    /*
     * Function for add audit trail function
     * */
    /**
     * @param  string  $parent_id
     * @return mixed
     */
    public static function addEventLogger($table_name, $table_action, $table_request, $table_fillable, $record, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $audit_tail['audit_trail_action_name'] = self::resolveActionId($table_action);
        $audit_tail['audit_trail_table_name'] = self::resolveTableId($table_name);
        if ($parent_id == '0') {
            $audit_tail['table_record_id'] = $record->id;
        } else {
            $audit_tail['table_record_id'] = $parent_id;
        }
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::user()->id;

        $audit_tailObj = self::create($audit_tail);

        foreach ($table_fillable as $fills) {
            if (isset($table_request[$fills])) {
                $maskedValue = self::maskSensitive($fills, $table_request[$fills]);
                $audit_changes[] = [
                    'audit_trail_id' => $audit_tailObj->id,
                    'field_name' => $fills,
                    'field_before' => $maskedValue,
                    'field_after' => $maskedValue,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        return AuditTrailChanges::insert($audit_changes);
    }

    /*End*/
    /*
     * Function for delete audit trail
     * */
    /**
     * @param  string  $parent_id
     * @return mixed
     */
    public static function deleteEventLogger($table_name, $table_action, $table_fillable, $record_id, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $actionId = self::resolveActionId($table_action);
        $tableId = self::resolveTableId($table_name);
        if ($tableId === 0) {
            Log::info("Add Entity name to log it : $table_name");
        }
        $audit_tail['audit_trail_action_name'] = $actionId;
        $audit_tail['audit_trail_table_name'] = $tableId;
        $audit_tail['table_record_id'] = $record_id;
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::user()->id;

        $audit_tailObj = self::create($audit_tail);

        $audit_changes['audit_trail_id'] = $audit_tailObj->id;
        $audit_changes['field_name'] = 'delete_at';
        $audit_changes['field_before'] = 'null';
        $audit_changes['field_after'] = Carbon::now();
        $audit_changes['created_at'] = Carbon::now();
        $audit_changes['updated_at'] = Carbon::now();

        return AuditTrailChanges::insert($audit_changes);
    }

    /*End*/
    /*
     * function for inactive audit trail
     * */
    /**
     * @param  string  $parent_id
     * @return mixed
     */
    public static function inactiveEventLogger($table_name, $table_action, $table_fillable, $record_id, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $audit_tail['audit_trail_action_name'] = self::resolveActionId($table_action);
        $audit_tail['audit_trail_table_name'] = self::resolveTableId($table_name);
        $audit_tail['table_record_id'] = $record_id;
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::user()->id;

        $audit_tailObj = self::create($audit_tail);

        $audit_changes['audit_trail_id'] = $audit_tailObj->id;
        $audit_changes['field_name'] = $table_action;
        $audit_changes['field_before'] = '1';
        $audit_changes['field_after'] = '0';
        $audit_changes['created_at'] = Carbon::now();
        $audit_changes['updated_at'] = Carbon::now();

        return AuditTrailChanges::insert($audit_changes);
    }

    /*End*/
    /*
     * function for Active audit trail
     * */
    /**
     * @param  string  $parent_id
     * @return mixed
     */
    public static function activeEventLogger($table_name, $table_action, $table_fillable, $record_id, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $audit_tail['audit_trail_action_name'] = self::resolveActionId($table_action);
        $audit_tail['audit_trail_table_name'] = self::resolveTableId($table_name);
        $audit_tail['table_record_id'] = $record_id;
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::user()->id;

        $audit_tailObj = self::create($audit_tail);

        $audit_changes['audit_trail_id'] = $audit_tailObj->id;
        $audit_changes['field_name'] = $table_action;
        $audit_changes['field_before'] = '0';
        $audit_changes['field_after'] = '1';
        $audit_changes['created_at'] = Carbon::now();
        $audit_changes['updated_at'] = Carbon::now();

        return AuditTrailChanges::insert($audit_changes);
    }

    /*End*/
    /**
     * @param $table_request  appointment data which is going to update
     * @param  string  $old_data
     * @param  string  $parent_id
     * @return mixed
     */
    public static function editEventLogger($table_name, $table_action, $table_request, $table_fillable, $old_data, $record_id, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $audit_tail['audit_trail_action_name'] = self::resolveActionId($table_action);
        $audit_tail['audit_trail_table_name'] = self::resolveTableId($table_name);

        if ($parent_id == '0') {
            $audit_tail['table_record_id'] = $record_id;
        } else {
            $audit_tail['table_record_id'] = $parent_id;
        }

        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::user()->id;
        $audit_tail['created_at'] = date('Y-m-d H:i:s');
        $audit_tail['updated_at'] = date('Y-m-d H:i:s');

        $audit_tailObj = self::create($audit_tail);

        if ($old_data == '0') {
            foreach ($table_fillable as $fills) {
                if (isset($table_request[$fills])) {
                    $maskedValue = self::maskSensitive($fills, $table_request[$fills]);
                    $audit_changes[] = [
                        'audit_trail_id' => $audit_tailObj->id,
                        'field_name' => $fills,
                        'field_before' => $maskedValue,
                        'field_after' => $maskedValue,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }
        } else {
            foreach ($table_fillable as $fills) {
                if (isset($table_request[$fills])) {
                    // Defensive null-coalesce: if old_data is missing the
                    // fillable key (e.g. the old snapshot couldn't be
                    // fetched), fall back to null so the diff walk still
                    // emits a change row instead of erroring on an
                    // undefined-index warning.
                    $oldValue = $old_data[$fills] ?? null;
                    if ($oldValue != $table_request[$fills]) {
                        $audit_changes[] = [
                            'audit_trail_id' => $audit_tailObj->id,
                            'field_name' => $fills,
                            'field_before' => self::maskSensitive($fills, $oldValue ?? ''),
                            'field_after' => self::maskSensitive($fills, $table_request[$fills] ?? ''),
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    }
                }
            }
        }

        return AuditTrailChanges::insert($audit_changes);
    }

    /*End*/
    /**
     * Get Total Records
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords()
    {
        return self::where('parent_id', '=', '0')->count();
    }

    /**
     * Get Records
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords($iDisplayStart, $iDisplayLength, $account_id = false)
    {
        return self::with(['auditTable', 'auditAction', 'user'])->limit($iDisplayLength)->offset($iDisplayStart)->where('parent_id', '=', '0')->orderBy('id', 'DESC')->get();
    }

    /*
     * Get changes according to audit trails
     *
     * */

    public function auditTrailChanges(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'audit_trail_id', 'id');
    }

    /*
     * Function for soft delete audit trail
     * */
    /**
     * @param  string  $parent_id
     * @return mixed
     */
    public static function softDeleteEventLogger($table_name, $table_action, $table_request, $table_fillable, $record_id, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $tableId = self::resolveTableId($table_name);
        if ($tableId === 0) {
            // Previously called exit() here, which terminated the entire HTTP
            // request mid-flight. Log + early-return so the caller's main
            // operation completes even when audit logging is misconfigured.
            Log::warning("AuditTrails::softDeleteEventLogger: unregistered table '$table_name' — audit row skipped");
            return false;
        }
        $audit_tail['audit_trail_action_name'] = self::resolveActionId($table_action);
        $audit_tail['audit_trail_table_name'] = $tableId;
        $audit_tail['table_record_id'] = $record_id;
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::user()->id;

        $audit_tailObj = self::create($audit_tail);

        foreach ($table_fillable as $fills) {
            if (isset($table_request[$fills])) {
                $maskedValue = self::maskSensitive($fills, $table_request[$fills]);
                $audit_changes[] = [
                    'audit_trail_id' => $audit_tailObj->id,
                    'field_name' => $fills,
                    'field_before' => $maskedValue,
                    'field_after' => $maskedValue,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        return AuditTrailChanges::insert($audit_changes);
    }

    /*
     * Get the users according
     *
     * */

    public function userr(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
