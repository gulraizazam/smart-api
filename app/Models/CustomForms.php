<?php

namespace App\Models;

use DateTime;
use Carbon\Carbon;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomForms extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'name', 'description', 'form_type', 'content', 'active', 'sort_number', 'created_by', 'updated_by', 'created_at', 'updated_at', 'custom_form_type'];

    protected static $_fillable = ['name', 'description', 'form_type', 'content', 'active', 'sort_number', 'form_type'];

    public $__fillable = ['name', 'description', 'form_type', 'content', 'active', 'sort_number', 'custom_form_type'];

    protected $table = 'custom_forms';

    protected static $_table = 'custom_forms';

    public $__table = 'custom_forms';

    const sort_field = 'sort_number';

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format('F j,Y h:i A');
    }

    public static function activateRecord($id)
    {
        try {
            $custom_form = self::getData($id);
            $custom_form->update(['active' => 1]);
            AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

            return [
                'status' => true,
                'message' => 'Record has been activated successfully.',
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Unable to process the request.',
            ];
        }
    }

    public static function inactivateRecord($id)
    {
        try {
            $custom_form = CustomForms::getData($id);
            $custom_form->update(['active' => 0]);
            AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

            return [
                'status' => true,
                'message' => 'Record has been inactivated successfully.',
            ];
        } catch (\Exception $e) {
            return [
                'status' => false,
                'message' => 'Unable to process the request.',
            ];
        }
    }

    public static function deleteRecord($id)
    {
        $custom_form = CustomForms::getData($id);
        $custom_form->delete();

    }

    public function form_fields()
    {
        //return $this->hasMany('App\Models\CustomFormFields', 'user_form_id')->where([ ['field_type', '!=', config("constants.custom_form.field_types.title")]])->orderBy(self::sort_field, 'asc');
        return $this->hasMany('App\Models\CustomFormFields', 'user_form_id')->orderBy(self::sort_field, 'asc');
    }

    public static function get_all_fields_data($id)
    {

        return self::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::User()->account_id],
        ])->with(['form_fields'])->first();
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted($cityId = false)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }
        if ($cityId) {
            return self::whereIn('id', $cityId)->get()->pluck('name', 'id');
        } else {
            return self::get()->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSortedFeatured($cityId = false)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }

        $query = self::where(['active' => 1, 'is_featured' => 1]);
        if ($cityId) {
            $query->whereIn('id', $cityId);
        }

        return $query->get()->pluck('name', 'id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveOnly($cityId = false)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }
        $query = self::where(['active' => 1]);
        if ($cityId) {
            $query->whereIn('id', $cityId);
        }

        return $query->OrderBy(self::sort_field, 'asc')->get();
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveFeaturedOnly($cityId, $account_id)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }

        $query = self::where(['active' => 1, 'is_featured' => 1, 'account_id' => $account_id]);
        if ($cityId) {
            $query->whereIn('id', $cityId);
        }

        return $query->OrderBy(self::sort_field, 'asc');
    }

    /**
     * Get Total Records
     *
     * @param  bool  $account_id
     * @return  (mixed)
     */
    public static function getAllForms($account_id = false)
    {
        $where = [];

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
        }
        $forms = self::where($where)->get();

        if ($forms) {
            return $forms;
        } else {
            return false;
        }

    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::custom_forms_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_custom_forms')) {
                return self::where($where)->count();
            } else {
                return self::where('active', 1)->where($where)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_custom_forms')) {
                return self::count();
            } else {
                return self::where('active', 1)->count();
            }

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
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::custom_forms_filters($request, $account_id, $apply_filter);

        if ($request->has('sort')) {

            [$orderBy, $order] = getSortBy($request);

            Filters::put(Auth::User()->id, 'custom_forms', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'custom_forms', 'order', $order);
        } else {
            if (
                Filters::get(Auth::User()->id, 'custom_forms', 'order_by')
                && Filters::get(Auth::User()->id, 'custom_forms', 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, 'custom_forms', 'order_by');
                $order = Filters::get(Auth::User()->id, 'custom_forms', 'order');

                if ($orderBy == 'created_at') {
                    $orderBy = 'custom_forms.created_at';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'created_at') {
                    $orderBy = 'custom_forms.created_at';
                }

                Filters::put(Auth::User()->id, 'custom_forms', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'custom_forms', 'order', $order);
            }
        }
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_custom_forms')) {
                return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
            } else {
                return self::where($where)->where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
            }

        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_custom_forms')) {
                return self::limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
            } else {
                return self::where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
            }
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
    public static function custom_forms_filters($request, $account_id, $apply_filter)
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

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'custom_forms', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'custom_forms', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'custom_forms', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'custom_forms', 'account_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::User()->id, 'custom_forms', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'custom_forms', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'custom_forms', 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'custom_forms', 'name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'form_type_id')) {
            $where[] = [
                'custom_form_type',
                '=',
                $filters['form_type_id'],
            ];
            Filters::put(Auth::User()->id, 'custom_forms', 'form_type_id', $filters['form_type_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'custom_forms', 'form_type_id');
            } else {
                if (Filters::get(Auth::User()->id, 'custom_forms', 'form_type_id')) {
                    $where[] = [
                        'custom_form_type',
                        '=',
                        Filters::get(Auth::User()->id, 'custom_forms', 'form_type_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'created_at')) {
            $where[] = ['created_at', '>=', $start_date_time];
            $where[] = ['created_at', '<=', $end_date_time];
            Filters::put(Auth::User()->id, 'custom_forms', 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'custom_forms', 'created_at');
            } else {
                if (Filters::get(Auth::User()->id, 'custom_forms', 'created_at')) {
                    $where[] = ['created_at', '>=', Filters::get(Auth::User()->id, 'custom_forms', 'created_at')];
                }
            }
        }
        if (hasFilter($filters, 'status')) {
            $where[] = [
                'active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, 'custom_forms', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'custom_forms', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'custom_forms', 'status') == 0 || Filters::get(Auth::user()->id, 'custom_forms', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'custom_forms', 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'custom_forms', 'status'),
                        ];
                    }
                }
            }
        }

        return $where;
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
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createForm($account_id, $data)
    {
        // Set Account ID
        $data['account_id'] = Auth::user()->account_id;
        $data['name'] = 'Untitled Form-'.time();
        $data['description'] = '';
        $data['form_type'] = 1;
        $data['content'] = '';
        $data['created_by'] = Auth::id();
        $record = self::create($data);
        $record->update([self::sort_field => $record->id]);
        //        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);
        return $record;
    }

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id, $user_id)
    {

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        $data['created_by'] = $user_id;
        $record = self::create($data);
        $record->update([self::sort_field => $record->id]);
        //        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);
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
        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;
        if ($request->has('name')) {
            $data['name'] = $request->get('name');
        }

        if ($request->has('description')) {
            $data['description'] = $request->get('description');
        }

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $data['updated_by'] = $user_id;
        $record->update($data);
        //        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);
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

    /**
     * Model boot for database events
     */
    public static function boot()
    {

        parent::boot();

        static::created(function ($item) {

            Event::dispatch('custom_form.created', $item);

        });

        static::updating(function ($item) {

            Event::dispatch('custom_form.updating', $item);

        });

        static::deleting(function ($item) {

            Event::dispatch('custom_form.deleting', $item);

        });

    }
}
