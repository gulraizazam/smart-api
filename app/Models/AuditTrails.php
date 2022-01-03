<?php

namespace App\Models;

use App\Models\AuditTrailChanges;
use App\Models\AuditTrailTables;
use App\Models\AuditTrailActions;
use App\User;
use Auth;
use Carbon\Carbon;
use App\Models\Cities;


use Illuminate\Database\Eloquent\Model;

class AuditTrails extends BaseModal
{
    protected $fillable = ['audit_trail_action_name', 'audit_trail_table_name', 'table_record_id', 'user_id', 'created_at', 'updated_at', 'parent_id'];

    protected $table = 'audit_trails';


    /**
     * sent the location name to resource with location_id.
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /*
     * Function for add audit trail function
     * */
    /**
     * @param $table_name
     * @param $table_action
     * @param $table_request
     * @param $table_fillable
     * @param $record
     * @param string $parent_id
     * @return mixed
     */
    static public function addEventLogger($table_name, $table_action, $table_request, $table_fillable, $record, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $action = AuditTrailActions::where('name', '=', $table_action)->select('id')->first();
        $table = AuditTrailTables::where('name', '=', $table_name)->select('id')->first();
        $audit_tail['audit_trail_action_name'] = $action->id ?? 0;
        $audit_tail['audit_trail_table_name'] = $table->id ?? 0;
        if ($parent_id == '0') {

            $audit_tail['table_record_id'] = $record->id;
        } else {
            $audit_tail['table_record_id'] = $parent_id;
        }
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::User()->id;

        $audit_tailObj = self::create($audit_tail);

        foreach ($table_fillable as $fills) {
            if (isset($table_request[$fills])) {
                $audit_changes[] = array(
                    'audit_trail_id' => $audit_tailObj->id,
                    'field_name' => $fills,
                    'field_before' => $table_request[$fills],
                    'field_after' => $table_request[$fills],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                );
            }
        }
        return AuditTrailChanges::insert($audit_changes);
    }
    /*End*/
    /*
     * Function for delete audit trail
     * */
    /**
     * @param $table_name
     * @param $table_action
     * @param $table_fillable
     * @param $record_id
     * @param string $parent_id
     * @return mixed
     */
    static public function deleteEventLogger($table_name, $table_action, $table_fillable, $record_id, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $action = AuditTrailActions::where('name', '=', $table_action)->select('id')->first();
        $table = AuditTrailTables::where('name', '=', $table_name)->select('id')->first();
        if(is_null($table)){
            die("Add Entity name to log it : $table_name");
        }
        $audit_tail['audit_trail_action_name'] = $action->id;
        $audit_tail['audit_trail_table_name'] = $table->id;
        $audit_tail['table_record_id'] = $record_id;
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::User()->id;

        $audit_tailObj = self::create($audit_tail);

        $audit_changes['audit_trail_id'] = $audit_tailObj->id;
        $audit_changes['field_name'] = 'delete_at';
        $audit_changes['field_before'] = 'null';
        $audit_changes['field_after'] = carbon::now();
        $audit_changes['created_at'] = Carbon::now();
        $audit_changes['updated_at'] = Carbon::now();

        return AuditTrailChanges::insert($audit_changes);
    }
    /*End*/
    /*
     * function for inactive audit trail
     * */
    /**
     * @param $table_name
     * @param $table_action
     * @param $table_fillable
     * @param $record_id
     * @param string $parent_id
     * @return mixed
     */
    static public function inactiveEventLogger($table_name, $table_action, $table_fillable, $record_id, $parent_id = '0')
    {

        $audit_tail = [];
        $audit_changes = [];

        $action = AuditTrailActions::where('name', '=', $table_action)->select('name', 'id')->first();
        $table = AuditTrailTables::where('name', '=', $table_name)->select('name', 'id')->first();

        $audit_tail['audit_trail_action_name'] = $action->id ?? 0;
        $audit_tail['audit_trail_table_name'] = $table->id ?? 0;
        $audit_tail['table_record_id'] = $record_id;
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::User()->id;

        $audit_tailObj = self::create($audit_tail);

        $audit_changes['audit_trail_id'] = $audit_tailObj->id;
        $audit_changes['field_name'] = $action->name ?? '';
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
     * @param $table_name
     * @param $table_action
     * @param $table_fillable
     * @param $record_id
     * @param string $parent_id
     * @return mixed
     */
    static public function activeEventLogger($table_name, $table_action, $table_fillable, $record_id, $parent_id = '0')
    {

        $audit_tail = [];
        $audit_changes = [];

        $action = AuditTrailActions::where('name', '=', $table_action)->select('name', 'id')->first();
        $table = AuditTrailTables::where('name', '=', $table_name)->select('name', 'id')->first();

        $audit_tail['audit_trail_action_name'] = $action->id ?? 0;
        $audit_tail['audit_trail_table_name'] = $table->id ?? 0;
        $audit_tail['table_record_id'] = $record_id;
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::User()->id;

        $audit_tailObj = self::create($audit_tail);

        $audit_changes['audit_trail_id'] = $audit_tailObj->id;
        $audit_changes['field_name'] = $action->name ?? '';
        $audit_changes['field_before'] = '0';
        $audit_changes['field_after'] = '1';
        $audit_changes['created_at'] = Carbon::now();
        $audit_changes['updated_at'] = Carbon::now();

        return AuditTrailChanges::insert($audit_changes);
    }
    /*End*/
    /**
     * @param $table_name
     * @param $table_action
     * @param $table_request  appointment data which is going to update
     * @param $table_fillable
     * @param string $old_data
     * @param $record_id
     * @param string $parent_id
     * @return mixed
     */
    static public function editEventLogger($table_name, $table_action, $table_request, $table_fillable, $old_data = '0', $record_id, $parent_id = '0')
    {
        $audit_tail = [];
        $audit_changes = [];

        $action = AuditTrailActions::where('name', '=', $table_action)->select('id')->first();
        $table = AuditTrailTables::where('name', '=', $table_name)->select('id')->first();

        $audit_tail['audit_trail_action_name'] = $action->id ?? 0;
        $audit_tail['audit_trail_table_name'] = $table->id ?? 0;

        if ($parent_id == '0') {

            $audit_tail['table_record_id'] = $record_id;
        } else {
            $audit_tail['table_record_id'] = $parent_id;
        }

        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::User()->id;

        $audit_tailObj = self::create($audit_tail);

        if ($old_data == '0') {
            foreach ($table_fillable as $fills) {
                if (isset($table_request[$fills])) {
                    $audit_changes[] = array(
                        'audit_trail_id' => $audit_tailObj->id,
                        'field_name' => $fills,
                        'field_before' => $table_request[$fills],
                        'field_after' => $table_request[$fills],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    );
                }
            }
        } else {
            foreach ($table_fillable as $fills) {
                if (isset($table_request[$fills])) {
                    if($old_data[$fills] != $table_request[$fills]){
                        $audit_changes[] = array(
                            'audit_trail_id' => $audit_tailObj->id,
                            'field_name' => $fills,
                            'field_before' => is_null($old_data[$fills])? "":$old_data[$fills],
                            'field_after' => is_null($table_request[$fills])? "":$table_request[$fills] ,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        );
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
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords()
    {
        return self::where('parent_id', '=', '0')->count();
    }

    /**
     * Get Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $iDisplayStart Start Index
     * @param (int) $iDisplayLength Total Records Length
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getRecords($iDisplayStart, $iDisplayLength, $account_id = false)
    {
        return self::limit($iDisplayLength)->offset($iDisplayStart)->where('parent_id', '=', '0')->orderBy('id', 'DESC')->get();
    }

    /*
     * Get changes according to audit trails
     *
     * */

    public function auditTrailChanges()
    {
        return $this->hasMany(AuditTrailChanges::class, 'audit_trail_id','id');
    }
    /*
     * Function for soft delete audit trail
     * */
    /**
     * @param $table_name
     * @param $table_action
     * @param $table_fillable
     * @param $record_id
     * @param string $parent_id
     * @return mixed
     */

    static public function softDeleteEventLogger( $table_name, $table_action, $table_request, $table_fillable, $record_id, $parent_id = '0' )
    {
        $audit_tail = [];
        $audit_changes = [];

        $action = AuditTrailActions::where('name', '=', $table_action)->select('id')->first();
        $table = AuditTrailTables::where('name', '=', $table_name)->select('id')->first();
        if(is_null($table)){
            die("Add Entity name to log it : $table_name");
        }
        $audit_tail['audit_trail_action_name'] = $action->id;
        $audit_tail['audit_trail_table_name'] = $table->id;
        $audit_tail['table_record_id'] = $record_id ;
        $audit_tail['parent_id'] = $parent_id;

        $audit_tail['user_id'] = Auth::User()->id;

        $audit_tailObj = self::create($audit_tail);

        foreach ($table_fillable as $fills) {

            if (isset($table_request[$fills])) {

                $audit_changes[] = array(
                    'audit_trail_id' => $audit_tailObj->id,
                    'field_name' => $fills,
                    'field_before' => $table_request[$fills],
                    'field_after' => $table_request[$fills],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                );
            }
        }
        return AuditTrailChanges::insert($audit_changes);
    }


    /*
     * Get the users according
     *
     * */

    public function userr()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
