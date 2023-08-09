<?php

namespace App\Models;

use DateTime;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomFormFeedbacks extends BaseModal
{
    use SoftDeletes;

    private static $PATIENT_USER_TYPE = 3;

    protected $fillable = ['account_id', 'form_name', 'form_description', 'content', 'reference_id', 'custom_form_id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at', 'custom_form_type'];

    protected $table = 'custom_form_feedbacks';

    /**
     * logable array and table name
     *
     * @var array
     */
    protected static $_fillable = ['form_name', 'form_description', 'content', 'reference_id', 'custom_form_id', 'custom_form_type'];

    protected static $_table = 'custom_form_feedbacks';

    const sort_field = 'id';

    /**
     * Get Total Records
     *
     * @param  bool  $account_id
     * @return  (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id, $apply_filter, $id, $filename)
    {
        $where = self::custom_form_feedbacks_filters($request, $account_id, $apply_filter, $id, $filename);

        if (count($where)) {
            return self::join('users', 'users.id', '=', 'custom_form_feedbacks.reference_id')->where($where)->count();
        } else {
            return self::join('users', 'users.id', '=', 'custom_form_feedbacks.reference_id')->count();
        }
    }

    /**
     * Get Records
     *
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id, $apply_filter, $id, $filename)
    {
        $where = self::custom_form_feedbacks_filters($request, $account_id, $apply_filter, $id, $filename);

        if ($request->has('sort')) {

            [$orderBy, $order] = getSortBy($request, 'created_at', 'desc', 'custom_form_feedbacks');

            Filters::put(Auth::User()->id, $filename, 'order_by', $orderBy);
            Filters::put(Auth::User()->id, $filename, 'order', $order);
        } else {
            if (
                Filters::get(Auth::User()->id, $filename, 'order_by')
                && Filters::get(Auth::User()->id, $filename, 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, $filename, 'order_by');
                $order = Filters::get(Auth::User()->id, $filename, 'order');

                if ($orderBy == 'created_at') {
                    $orderBy = 'custom_form_feedbacks.created_at';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'created_at') {
                    $orderBy = 'custom_form_feedbacks.created_at';
                }

                Filters::put(Auth::User()->id, $filename, 'order_by', $orderBy);
                Filters::put(Auth::User()->id, $filename, 'order', $order);
            }
        }

        if (count($where)) {
            return self::join('users', 'users.id', '=', 'custom_form_feedbacks.reference_id')->select('*', 'custom_form_feedbacks.id as internal_id', 'custom_form_feedbacks.created_at as created_at_form')
                ->where($where)
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderBy($orderBy, $order)->get();
        } else {
            return self::join('users', 'users.id', '=', 'custom_form_feedbacks.reference_id')->select('*', 'custom_form_feedbacks.id as internal_id', 'custom_form_feedbacks.created_at as created_at_form')
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderBy($orderBy, $order)
                ->get();
        }
    }

    /**
     * Get filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @param  (boolean)  $apply_filter
     * @return (mixed)
     */
    public static function custom_form_feedbacks_filters($request, $account_id, $apply_filter, $id, $filename)
    {
        $where = [];

        $filters = getFilters($request->all());
        if (hasFilter($filters, 'created_at')) {
            $date_range = explode(' - ', $filters['created_at']);
            $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 0);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
        } else {
            $start_date_time = null;
            $end_date_time = null;
        }

        if ($id != false) {
            $where[] = [
                'users.id',
                '=',
                $id,
            ];
            Filters::put(Auth::user()->id, $filename, 'id', $id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'id')) {
                    $where[] = [
                        'users.id',
                        '=',
                        Filters::get(Auth::user(), $filename, 'id'),
                    ];
                }
            }
        }

        if ($account_id) {
            $where[] = [
                'users.account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, $filename, 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'account_id')) {
                    $where[] = [
                        'users.account_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'account_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'custom_form_feedbacks.form_name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'name');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'name')) {
                    $where[] = [
                        'custom_form_feedbacks.form_name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, $filename, 'name').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'id')) {
            $where[] = [
                'users.id',
                'like',
                '%'.\App\Helpers\GeneralFunctions::patientSearch($filters['id']).'%',
            ];
            Filters::put(Auth::User()->id, $filename, 'id', \App\Helpers\GeneralFunctions::patientSearch($filters['id']));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'id')) {
                    $where[] = [
                        'users.id',
                        'like',
                        '%'.Filters::get(Auth::User()->id, $filename, 'id').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'patient_name')) {
            $where[] = [
                'users.name',
                'like',
                '%'.$filters['patient_name'].'%',
            ];
            Filters::put(Auth::User()->id, $filename, 'patient_name', $filters['patient_name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'patient_name');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'patient_name')) {
                    $where[] = [
                        'users.name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, $filename, 'patient_name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'created_at')) {
            $where[] = ['custom_form_feedbacks.created_at', '>=', $start_date_time];
            $where[] = ['custom_form_feedbacks.created_at', '<=', $end_date_time];
            Filters::put(Auth::User()->id, $filename, 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_at');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_at')) {
                    $where[] = [
                        'custom_form_feedbacks.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_at'),
                    ];
                }
            }
        }

        $where[] = [
            'custom_form_feedbacks.custom_form_type',
            '=',
            '0',
        ];

        return $where;
    }

    public static function records()
    {
        return self::where(['account_id' => Auth::User()->account_id])->with(['user'])->get();
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

    public function user()
    {
        return $this->belongsTo(User::class, 'reference_id');
    }

    /**
     * Get All Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id])->get()->getDictionary();
    }

    /**
     * Create Record
     *
     *
     * @return (mixed)
     */
    public static function createRecord(Request $request, $id, $account_id, $user_id, $data = false)
    {
        $custom_form = CustomForms::get_all_fields_data($id);
        // Set Account ID
        $data['account_id'] = $account_id;
        $data['form_name'] = $custom_form->name;
        $data['form_description'] = $custom_form->description;
        $data['content'] = $custom_form->content;
        $data['custom_form_id'] = $custom_form->id;
        if ($request->has('reference_id')) {
            $data['reference_id'] = $request->get('reference_id');
        } else {
            $data['reference_id'] = 0;
        }

        $data['created_by'] = $user_id;
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
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id, $user_id)
    {

        $old_data = (self::find($id))->toArray();

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        // Set Account ID
        $data['account_id'] = $account_id;

        if ($request->has('reference_id')) {
            $data['reference_id'] = $request->get('reference_id');
        }

        $data['updated_by'] = $user_id;
        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $record);

        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
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
            ['account_id', '=', Auth::User()->account_id],
        ])->with(['form_fields', 'patient'])->first();
    }

    public function form_fields()
    {
        return $this->hasMany('App\Models\CustomFormFeedbackDetails', 'custom_form_feedback_id');
    }

    public function patient()
    {
        return $this->hasOne(User::class, 'id', 'reference_id');
    }
}
