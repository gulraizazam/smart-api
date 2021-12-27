<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Helpers\Filters;

class CustomFormFeedbacks extends BaseModal
{
    use SoftDeletes;

    private static $PATIENT_USER_TYPE = 3;
    protected $fillable = ['account_id', 'form_name', "form_description", 'content', "reference_id", "custom_form_id", 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at','custom_form_type'];

    protected $table = 'custom_form_feedbacks';

    /**
     * logable array and table name
     * @var array
     */
    protected static $_fillable = ['form_name', "form_description", 'content', "reference_id", "custom_form_id",'custom_form_type'];

    protected static $_table = "custom_form_feedbacks";

    const sort_field = 'id';


    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param bool $account_id
     * @return  (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false, $apply_filter = false, $id = false , $filename )
    {
        $where = self::custom_form_feedbacks_filters($request, $account_id, $apply_filter, $id, $filename );

        if (count($where)) {
            return self::join('users', 'users.id', '=', 'custom_form_feedbacks.reference_id')->where($where)->count();
        } else {
            return self::join('users', 'users.id', '=', 'custom_form_feedbacks.reference_id')->count();
        }
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
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false, $id = false, $filename)
    {
        $where = Self::custom_form_feedbacks_filters($request, $account_id, $apply_filter, $id, $filename);

        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            if ($orderBy == 'created_at') {
                $orderBy = 'custom_form_feedbacks.created_at';
            }
            $order = $request->get('order')[0]['dir'];

            Filters::put(Auth::User()->id, 'custom_form_feedbacks', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'custom_form_feedbacks', 'order', $order);
        } else {
            if(
                Filters::get(Auth::User()->id, 'custom_form_feedbacks', 'order_by')
                && Filters::get(Auth::User()->id, 'custom_form_feedbacks', 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, 'custom_form_feedbacks', 'order_by');
                $order = Filters::get(Auth::User()->id, 'custom_form_feedbacks', 'order');

                if ($orderBy == 'created_at') {
                    $orderBy = 'custom_form_feedbacks.created_at';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'created_at') {
                    $orderBy = 'custom_form_feedbacks.created_at';
                }

                Filters::put(Auth::User()->id, 'custom_form_feedbacks', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'custom_form_feedbacks', 'order', $order);
            }
        }
        if (count($where)) {
            return self::join('users', 'users.id', '=', 'custom_form_feedbacks.reference_id')->select('*', 'custom_form_feedbacks.id as internal_id','custom_form_feedbacks.created_at as created_at_form')
                ->where($where)
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderBy($orderBy,$order)->get();
        } else {
            return self::join('users', 'users.id', '=', 'custom_form_feedbacks.reference_id')->select('*', 'custom_form_feedbacks.id as internal_id','custom_form_feedbacks.created_at as created_at_form')
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderBy($orderBy,$order)
                ->get();
        }
    }

    /**
     * Get filters
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     * @param (boolean) $apply_filter
     * @return (mixed)
     */
    static public function custom_form_feedbacks_filters($request, $account_id, $apply_filter,$id, $filename )
    {
        $where = array();

        if($id != false){
            $where[] = array(
                'users.id',
                '=',
                $id
            );
            Filters::put(Auth::user()->id, $filename, 'id', $id );
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $filename , 'id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'id')){
                    $where[] = array(
                        'users.id',
                        '=',
                        Filters::get(Auth::user(),$filename, 'id')
                    );
                }
            }
        }

        if ($account_id) {
            $where[] = array(
                'users.account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, $filename, 'account_id', $account_id);
        }  else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'account_id')) {
                    $where[] = array(
                        'users.account_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'account_id')
                    );
                }
            }
        }
        if ($request->get('name') && $request->get('name') != '') {
            $where[] = array(
                'custom_form_feedbacks.form_name',
                'like',
                '%' . $request->get('name') . '%'
            );
            Filters::put(Auth::User()->id, $filename, 'name', $request->get('name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'name');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'name')) {
                    $where[] = array(
                        'custom_form_feedbacks.form_name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'name') . '%'
                    );
                }
            }
        }
        if ($request->get('id') && $request->get('id') != '') {
            $where[] = array(
                'users.id',
                'like',
                '%' . \App\Helpers\GeneralFunctions::patientSearch($request->get('id')) . '%'
            );
            Filters::put(Auth::User()->id, $filename, 'id', \App\Helpers\GeneralFunctions::patientSearch($request->get('id')));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'id')) {
                    $where[] = array(
                        'users.id',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'id') . '%'
                    );
                }
            }
        }
        if ($request->get('patient_name')) {
            $where[] = array(
                'users.name',
                'like',
                '%' . $request->get('patient_name') . '%'
            );
            Filters::put(Auth::User()->id, $filename, 'patient_name', $request->get('patient_name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'patient_name');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'patient_name')) {
                    $where[] = array(
                        'users.name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, $filename, 'patient_name') . '%'
                    );
                }
            }
        }
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'custom_form_feedbacks.created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $filename, 'created_from', $request->get('created_from'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
                    $where[] = array(
                        'custom_form_feedbacks.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from') . ' 00:00:00'
                    );
                }
            }
        }
        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = array(
                'custom_form_feedbacks.created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $filename, 'created_to', $request->get('created_to'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
                    $where[] = array(
                        'custom_form_feedbacks.created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to') . ' 23:59:59'
                    );
                }
            }
        }
        $where[] = array(
            'custom_form_feedbacks.custom_form_type',
            '=',
            '0'
        );


        return $where;
    }

    public static function records(){
        return self::where(["account_id"=>Auth::User()->account_id])->with(["user"])->get();
    }

    public static function deleteRecord($id)
    {
        $custom_form_feedback = self::getData($id);
        $custom_form_feedback->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);
    }

    public static function inactivateRecord($id)
    {
        $custom_form_feedback = CustomFormFeedbacks::getData($id);
        $custom_form_feedback->update(['active' => 0]);
        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);
    }

    public static function activateRecord($id)
    {
        $custom_form_feedback = CustomFormFeedbacks::getData($id);
        $custom_form_feedback->update(['active' => 1]);
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);
    }

    public function user(){
       return $this->belongsTo("App\User","reference_id");
    }

    /**
     * Get All Records
     *
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id])->get()->getDictionary();
    }


    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function createRecord(Request $request, $id, $account_id, $user_id, $data = false)
    {
        $custom_form = CustomForms::get_all_fields_data($id);
        // Set Account ID
        $data['account_id'] = $account_id;
        $data["form_name"] = $custom_form->name;
        $data["form_description"] = $custom_form->description;
        $data["content"] = $custom_form->content;
        $data["custom_form_id"] = $custom_form->id;
        if ($request->has("reference_id"))
            $data["reference_id"] = $request->get("reference_id");
        else
            $data["reference_id"] = 0;

        $data["created_by"] = $user_id;
        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);
        foreach ($custom_form->form_fields as $field) {
            CustomFormFeedbackDetails::createRecord($request, $custom_form->id, $field, $record->id, $account_id, $user_id);
        }

        return $record;
    }

    /**
     * Update Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function updateRecord($id, $request, $account_id, $user_id)
    {

        $old_data = (self::find($id))->toArray();


        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }

        // Set Account ID
        $data['account_id'] = $account_id;

        if ($request->has("reference_id")) {
            $data["reference_id"] = $request->get("reference_id");
        }


        $data["updated_by"] = $user_id;
        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable,$old_data,$record);
        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @param
     *
     * @return (boolean)
     */
    static public function isChildExists($id, $account_id)
    {

        return false;
    }

    public static function submitForm($id)
    {

    }

    public static function getAllFields($id)
    {


        return self::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::User()->account_id]
        ])->with(['form_fields','patient'])->first();
    }

    public function form_fields()
    {
        return $this->hasMany('App\Models\CustomFormFeedbackDetails', 'custom_form_feedback_id');
    }

    public function patient(){
        return $this->hasOne("App\User",'id','reference_id');
    }

}
