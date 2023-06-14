<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Medical extends Model
{
    protected $fillable = ['user_id', 'patient_id', 'appointment_id', 'custom_form_feedback_id', 'date', 'created_at', 'updated_at'];

    protected static $_fillable = ['user_id', 'patient_id', 'appointment_id', 'custom_form_feedback_id', 'date'];

    protected $table = 'medicals';

    protected static $_table = 'medicals';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function appointment()
    {
        return $this->belongsTo('App\Models\Appointments');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /*
     * Create Record with log file
     */
    public static function CreateRecord($request, $parent_id, $user_id)
    {

        $data['patient_id'] = $request->reference_id;
        $data['user_id'] = $user_id;
        $data['appointment_id'] = $request->appointment_id;
        $data['custom_form_feedback_id'] = $parent_id;
        $data['date'] = $request->date;

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parent_id);

        return $record;
    }

    /*
     * Update Record
     */
    public static function updateRecord($request, $account_id)
    {

        $old_data = (self::find($request->medical_id))->toArray();

        $data['date'] = $request->date;

        $record = self::where([
            'id' => $request->measurement_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $request->measurement_id);

        return $record;
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $id = false, $flag = 0)
    {
        $where = [];
        if ($flag == 1) {
            if ($id != false) {
                $where[] = [
                    'patient_id',
                    '=',
                    $id,
                ];
            }
        } else {
            if ($id != false) {
                $where[] = [
                    'appointment_id',
                    '=',
                    $id,
                ];
            }
        }

        if ($request->get('user_id')) {
            $where[] = [
                'user_id',
                '=',
                $request->get('user_id'),
            ];
        }
        if ($request->get('name')) {
            $where[] = [
                'form_name',
                'like',
                '%'.$request->get('name').'%',
            ];
        }
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = [
                'medicals.created_at',
                '>=',
                $request->get('created_from').' 00:00:00',
            ];
        }
        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = [
                'medicals.created_at',
                '<=',
                $request->get('created_to').' 23:59:59',
            ];
        }

        return self::join('custom_form_feedbacks', 'medicals.custom_form_feedback_id', '=', 'custom_form_feedbacks.id')
            ->where($where)->select('custom_form_feedbacks.form_name', 'medicals.*')->count();
    }

    /**
     * Get Records
     *
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $id = false, $flag = 0)
    {
        $filters = getFilters($request->all());

        $where = [];

        if ($flag == 1) {
            if ($id != false) {
                $where[] = [
                    'patient_id',
                    '=',
                    $id,
                ];
            }
        } else {
            if ($id != false) {
                $where[] = [
                    'appointment_id',
                    '=',
                    $id,
                ];
            }
        }
        if (hasFilter($filters, 'user_id')) {
            $where[] = [
                'user_id',
                '=',
                $filters['user_id'],
            ];
        }
        if (hasFilter($filters, 'name')) {
            $where[] = [
                'form_name',
                'like',
                '%'.$filters['name'].'%',
            ];
        }
        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'medicals.created_at',
                '>=',
                $filters['created_from'].' 00:00:00',
            ];
        }
        if (hasFilter($filters, 'created_to')) {
            $where[] = [
                'medicals.created_at',
                '<=',
                $filters['created_to'].' 23:59:59',
            ];
        }

        [$orderBy, $order] = getSortBy($request, 'created_at', 'desc', 'medicals');

        return self::with('patient')->join('custom_form_feedbacks', 'medicals.custom_form_feedback_id', '=', 'custom_form_feedbacks.id')
            ->where($where)->select('custom_form_feedbacks.form_name', 'medicals.*')->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
    }
}
