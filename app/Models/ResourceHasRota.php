<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResourceHasRota extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['start', 'end', 'created_at', 'updated_at', 'monday', 'monday_off', 'tuesday', 'tuesday_off', 'wednesday', 'wednesday_off', 'thursday', 'thursday_off', 'friday', 'friday_off', 'saturday', 'saturday_off', 'sunday', 'sunday_off', 'active', 'resource_id', 'resource_type_id', 'copy_all', 'account_id', 'region_id', 'city_id', 'location_id', 'is_consultancy', 'is_treatment'];

    protected static $_fillable = ['start', 'end', 'monday', 'monday_off', 'tuesday', 'tuesday_off', 'wednesday', 'wednesday_off', 'thursday', 'thursday_off', 'friday', 'friday_off', 'saturday', 'saturday_off', 'sunday', 'sunday_off', 'active', 'resource_id', 'resource_type_id', 'copy_all', 'region_id', 'city_id', 'location_id', 'is_consultancy', 'is_treatment'];

    protected $table = 'resource_has_rota';

    protected static $_table = 'resource_has_rota';

    /*
     * Get the city from resource has rota against city_id
     */
    public function city()
    {

        return $this->belongsTo('App\Models\Cities', 'city_id')->withTrashed();
    }

    /*
     * Get the region from resource has rota against region_id
     */
    public function region()
    {

        return $this->belongsTo('App\Models\Regions', 'region_id')->withTrashed();
    }

    /*
     * Get the city from resource has rota against city_id
     * */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id')->withTrashed();
    }

    /**
     * Create Record
     *
     *
     * @return (mixed)
     */
    public static function createRecord(Request $request, $account_id)
    {
        if ($request->start <= $request->end) {

            $data = $request->all();

            $resourcetype_id = ResourceTypes::find($request->resource_type_id);

            if (! $resourcetype_id) {
                return [
                    'status' => false,
                    'message' => 'Resource type not found.',
                ];
            }

            $data['resource_type_id'] = $resourcetype_id->id;

            /*checked coming rota for machine or doctor*/
            if ($request->resource_doctor || $request->resource_machine) {
                if ($request->resource_doctor) {
                    $resourcedoctor = Resources::where('external_id', '=', $request->resource_doctor)->first();
                    $data['resource_id'] = $resourcedoctor?->id;
                } else {
                    $data['resource_id'] = $request->resource_machine;
                    $data['is_consultancy'] = '0';
                }
            } else {
                return [
                    'status' => false,
                    'message' => 'Resource not selected, Kindly define',
                ];
            }
            /*End*/

            /*Checked date overlaping or not*/
            $checked = ResourceHasRota::CheckDate($request, $data);
            if ($checked == 'true') {
                return [
                    'status' => false,
                    'message' => 'Date range overlap, Kindly define again',
                ];
            }
            /*End*/

            /*Check if copy all exit or not Monday timing or not copy all*/
            if ($request->get('copy_all') == '1') {
                $week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($week as $day) {
                    if ($request->get('time_f_monday') && $request->get('time_to_monday')) {
                        if ($request->get('time_f_monday') == $request->get('time_to_monday')) {
                            return [
                                'status' => false,
                                'message' => 'Time range must be different, Kindly define again',
                            ];
                        } else {
                            $data[$day] = implode(',', [Carbon::parse($request->get('time_f_monday'))->format('H:i'), Carbon::parse($request->get('time_to_monday'))->format('H:i')]);
                        }
                    } else {
                        return [
                            'status' => false,
                            'message' => 'From or To require, kindly define again',
                        ];
                    }
                    if ($request->get('break_from_monday') && $request->get('break_to_monday')) {
                        if ($request->get('break_from_monday') == $request->get('break_to_monday')) {
                            return array(
                                'status' => false,
                                'message' => 'Time range must be different, Kindly define again',
                            );
                        } else {
                            if (
                                strtotime($request->get('break_from_monday')) >= strtotime($request->get('time_f_monday')) &&
                                strtotime($request->get('break_to_monday')) <= strtotime($request->get('time_to_monday'))
                            ) {
                                $data[$day.'_off'] = implode(',', [Carbon::parse($request->get('break_from_monday'))->format('H:i'), Carbon::parse($request->get('break_to_monday'))->format('H:i')]);
                            } else {
                                return [
                                    'status' => false,
                                    'message' => 'Break time must be between From and To, Kindly Define again',
                                ];
                            }
                        }
                    } else {
                        if (!$request->get('break_from_monday') && !$request->get('break_to_monday')) {
                            $data[$day . '_off'] = null;
                        }
                        if ($request->get('break_from_monday') || $request->get('break_to_monday')) {
                            return array(
                                'status' => false,
                                'message' => 'From Break or To Break require, kindly define again',
                            );
                        }
                    }
                }
                $data['account_id'] = Auth::User()->account_id;
                if (isset($data['city_id']) && $data['city_id']) {
                    $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;
                }

                $resourcerota = ResourceHasRota::create($data);
                AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $resourcerota);
            } else {
                $week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($week as $day) {
                    if ($request->get($day.'checked') != 'on') {
                        $data[$day] = null;
                    } else {
                        if ($request->get('time_f_' . $day) && $request->get('time_to_' . $day)) {
                            if ($request->get('time_f_' . $day) == $request->get('time_to_' . $day)) {
                                return array(
                                    'status' => false,
                                    'message' => 'Time range must be different, Kindly define again',
                                );
                            } else {
                                $data[$day] = implode(',', [Carbon::parse($request->get('time_f_'.$day))->format('H:i'), Carbon::parse($request->get('time_to_'.$day))->format('H:i')]);
                            }
                        } else {
                            return [
                                'status' => false,
                                'message' => 'From or To require, kindly define again',
                            ];
                        }
                    }
                    if ($request->get('break_from_' . $day) == null && $request->get('break_to_' . $day) == null) {
                        $data[$day . '_off'] = null;
                    } else {
                        if ($request->get('break_from_' . $day) && $request->get('break_to_' . $day)) {
                            if ($request->get('break_from_' . $day) == $request->get('break_to_' . $day)) {
                                return array(
                                    'status' => false,
                                    'message' => 'Time range must be different, Kindly define again',
                                );
                            } else {
                                if (
                                    strtotime($request->get('break_from_'.$day)) >= strtotime($request->get('time_f_'.$day)) &&
                                    strtotime($request->get('break_from_'.$day)) <= strtotime($request->get('time_to_'.$day))
                                ) {
                                    $data[$day . '_off'] = implode(',', array(Carbon::parse($request->get('break_from_' . $day))->format('H:i'), Carbon::parse($request->get('break_to_' . $day))->format('H:i')));
                                } else {
                                    return [
                                        'status' => false,
                                        'message' => 'Break time must be between From and To, Kindly Define again',
                                    ];
                                }
                            }
                        } else {
                            if (!$request->get('break_from_' . $day) && !$request->get('break_to_' . $day)) {
                                $data[$day . '_off'] = null;
                            }
                            if ($request->get('break_from_' . $day) || $request->get('break_to_' . $day)) {
                                return array(
                                    'status' => false,
                                    'message' => 'From Break or To Break require, kindly define again',
                                );
                            }
                        }
                    }
                }
                $data['account_id'] = Auth::User()->account_id;
                $data['copy_all'] = '0';

                if (isset($data['city_id']) && $data['city_id']) {
                    $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;
                }

                $resourcerota = ResourceHasRota::create($data);

                AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $resourcerota);
            }
            ResourceHasRotaDays::createRotaDaysRecord($request, $resourcerota, $week, $data);

            return [
                'status' => true,
                'message' => 'Record has been created successfully.',
            ];
        } else {
            return [
                'status' => false,
                'message' => 'Date range invalid, Kindly define again',
            ];
        }
    }

    /**
     * Inactive Record
     *
     *
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {
        $resourcehasrota = ResourceHasRota::getData($id);

        if ($resourcehasrota == null) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        $today = Carbon::now()->toDateString();

        $resource_rota_days = ResourceHasRotaDays::where('resource_has_rota_id', '=', $resourcehasrota->id)->whereDate('date', '>=', $today)->get();
        $status = true;
        foreach ($resource_rota_days as $rota_days) {
            if ($resourcehasrota->resource_type_id == 2) {
                $appointment_info = Appointments::where([
                    ['resource_has_rota_day_id', '=', $rota_days->id],
                    ['location_id', '=', $resourcehasrota->location_id]
                ])->get();
                if (count($appointment_info)) {
                    $status = false;
                }
            }
            if ($resourcehasrota->resource_type_id == 1) {
                $appointment_info = Appointments::where([
                    ['resource_has_rota_day_id_for_machine', '=', $rota_days->id],
                    ['location_id', '=', $resourcehasrota->location_id]
                ])->get();
                if (count($appointment_info)) {
                    $status = false;
                }
            }
        }
        if ($status) {
            foreach ($resource_rota_days as $rotadaysinactive) {
                $rotadaysinactive->update(['active' => 0]);
            }
            $record = $resourcehasrota->update(['active' => 0]);

            AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

            return [
                'status' => true,
                'message' => 'Record has been inactivated successfully.',
            ];
        }

        return [
            'status' => false,
            'message' => 'Rota use in appointment, unable to Inactive.',
        ];
    }

    /**
     * active Record
     *
     *
     * @return (mixed)
     */
    public static function activeRecord($id)
    {

        $resourcehasrota = ResourceHasRota::getData($id);

        if ($resourcehasrota == null) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        $resource_rota_days = ResourceHasRotaDays::where('resource_has_rota_id', '=', $resourcehasrota->id)->get();

        foreach ($resource_rota_days as $rota_day) {
            $rota_day->update(['active' => 1]);
        }

        $record = $resourcehasrota->update(['active' => 1]);

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been activated successfully.',
        ];
    }

    /**
     * delete Record
     *
     *
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {

        $resourcehasrota = ResourceHasRota::getData($id);

        if ($resourcehasrota == null) {

            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        } else {

            if (ResourceHasRota::isChildExists($id, Auth::User()->account_id)) {

                return [
                    'status' => false,
                    'message' => 'Child records exist, unable to delete resource',
                ];
            }

            $resourcerotadays = ResourceHasRotaDays::where('resource_has_rota_id', '=', $resourcehasrota->id)->forceDelete();

            $record = $resourcehasrota->delete();

            AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

            return [
                'status' => true,
                'message' => 'Record has been deleted successfully.',
            ];
        }
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        if ($request->start <= $request->end) {
            $old_data = (ResourceHasRota::find($id))->toArray();
            $resourcerota = ResourceHasRota::find($id);
            $request_data = $request->all();
            $request_data['start'] = $request->start;
            $request = new Request();
            $request->replace($request_data);
            //$resourcerotadays = ResourceHasRotaDays::where('resource_has_rota_id', '=', $resourcerota->id)->forceDelete();
            $data = $request->all();
            /*Enter resource Id to reuse checkDatefunction*/
            $data['resource_id'] = $resourcerota->resource_id;
            if ($request->copy_all == '1') {
                $week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($week as $day) {
                    if ($request->get('time_f_monday') && $request->get('time_to_monday')) {
                        if ($request->get('time_f_monday') == $request->get('time_to_monday')) {
                            return [
                                'status' => 0,
                                'message' => 'Time range must be different, Kindly define again',
                            ];
                        } else {
                            $data[$day] = implode(',', [Carbon::parse($request->time_f_monday)->format('H:i'), Carbon::parse($request->time_to_monday)->format('H:i')]);
                        }
                    } else {
                        return [
                            'status' => 0,
                            'message' => 'From or To require, kindly define again',
                        ];
                    }
                    if ($request->get('break_from_monday') && $request->get('break_to_monday')) {
                        if ($request->get('break_from_monday') == $request->get('break_to_monday')) {
                            return array(
                                'status' => 0,
                                'message' => 'Time range must be different, Kindly define again',
                            );
                        } else {
                            if (
                                strtotime($request->get('break_from_monday')) >= strtotime($request->get('time_f_monday')) &&
                                strtotime($request->get('break_to_monday')) <= strtotime($request->get('time_to_monday'))
                            ) {
                                $data[$day.'_off'] = implode(',', [Carbon::parse($request->get('break_from_monday'))->format('H:i'), Carbon::parse($request->get('break_to_monday'))->format('H:i')]);
                            } else {
                                return [
                                    'status' => 0,
                                    'message' => 'Break time must be between From and To, Kindly Define again',
                                ];
                            }
                        }
                    } else {
                        if (!$request->get('break_from_monday') && !$request->get('break_to_monday')) {
                            $data[$day . '_off'] = null;
                        }
                        if ($request->get('break_from_monday') || $request->get('break_to_monday')) {
                            return array(
                                'status' => 0,
                                'message' => 'From Break or To Break require, kindly define again',
                            );
                        }
                    }
                }
                $data['copy_all'] = '1';
            } else {
                //dd("Here Come IN Zero");
                $week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($week as $day) {
                    if ($request->get($day.'checked') != 'on') {
                        $data[$day] = null;
                    } else {
                        if ($request->get('time_f_' . $day) && $request->get('time_to_' . $day)) {
                            if ($request->get('time_f_' . $day) == $request->get('time_to_' . $day)) {
                                return array(
                                    'status' => 0,
                                    'message' => 'Time range must be different, Kindly define again',
                                );
                            } else {
                                $data[$day] = implode(',', [Carbon::parse($request->get('time_f_'.$day))->format('H:i'), Carbon::parse($request->get('time_to_'.$day))->format('H:i')]);
                            }
                        } else {
                            return [
                                'status' => 0,
                                'message' => 'From or To require, kindly define again',
                            ];
                        }
                        if ($request->get('break_from_' . $day) == null && $request->get('break_to_' . $day) == null) {
                            $data[$day . '_off'] = null;
                        } else {
                            if ($request->get('break_from_' . $day) && $request->get('break_to_' . $day)) {
                                if ($request->get('break_from_' . $day) == $request->get('break_to_' . $day)) {
                                    return array(
                                        'status' => 0,
                                        'message' => 'Time range must be different, Kindly define again',
                                    );
                                } else {
                                    if (
                                        strtotime($request->get('break_from_'.$day)) >= strtotime($request->get('time_f_'.$day)) &&
                                        strtotime($request->get('break_from_'.$day)) <= strtotime($request->get('time_to_'.$day))
                                    ) {
                                        $data[$day . '_off'] = implode(',', array(Carbon::parse($request->get('break_from_' . $day))->format('H:i'), Carbon::parse($request->get('break_to_' . $day))->format('H:i')));
                                    } else {
                                        return [
                                            'status' => 0,
                                            'message' => 'Break time must be between From and To, Kindly Define again',
                                        ];
                                    }
                                }
                            } else {
                                if (!$request->get('break_from_' . $day) && !$request->get('break_to_' . $day)) {
                                    $data[$day . '_off'] = null;
                                }
                                if ($request->get('break_from_' . $day) || $request->get('break_to_' . $day)) {
                                    return array(
                                        'status' => 0,
                                        'message' => 'From Break or To Break require, kindly define again',
                                    );
                                }
                            }
                        }
                    }
                }
                $data['copy_all'] = '0';
            }
            /*
             * Rota update patch:
             */

            $rota_days_mapping = ResourceHasRotaDays::grabRotaDaysMapping($request, $week, $data, $resourcerota);
            $rota_appointments = ResourceHasRotaDays::grabRotaDaysAppointments($request, $rota_days_mapping['rota_days_records'], $resourcerota);

            $not_allow = false;
            $not_allow_2 = false;
            if (count($rota_appointments) && count($rota_days_mapping['rota_days_records'])) {

                foreach ($rota_days_mapping['rota_days_array'] as $rota_days_record) {

                    foreach ($rota_appointments as $rota_appointment) {

                        if ($rota_appointment['scheduled_time'] && $rota_days_record['start_time'] && $rota_days_record['end_time']) {

                            if (!self::checkTime(Carbon::parse($rota_appointment['scheduled_time'])->format('h:i A'), $rota_days_record['start_time'], $rota_days_record['end_time'])) {
                                $not_allow = true;
                                break;
                            }
                            if (self::checkTime(Carbon::parse($rota_appointment['scheduled_time'])->format('h:i A'), $rota_days_record['start_off'], $rota_days_record['end_off'])) {
                                $not_allow_2 = true;
                                break;
                            }
                        }
                    }
                    if ($not_allow) {
                        break;
                    }
                }
            }
            // if ($not_allow) {
            //     return [
            //         'status' => 0,
            //         'message' => 'Provided rota timings are conflicts with appointments. Unable to update rota.',
            //     ];
            // }
            if ($not_allow_2) {
                return [
                    'status' => 0,
                    'message' => 'Provided rota break timings are conflicts with appointments. Unable to update rota.',
                ];
            }
            /*
             * Rota update patch: ENDs
             */
            if (isset($data['city_id']) && $data['city_id']) {
                // Set Region ID
                $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;
            }

            /*Date overlap function for 2 rotas only not for one*/

            $rota_overlap_status = self::RotaOverlapStatus($resourcerota, $data);

            if ($rota_overlap_status == 'true') {
                return [
                    'status' => 0,
                    'message' => 'Date range overlap, Kindly define again',
                ];
            } else {
                $resourcerota->update($data);

                AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);
            }

            /*
            * Use to Store Data in resource has rota days.
            * */
            ResourceHasRotaDays::updateRotaDaysRecord($request, $week, $data, $resourcerota);

            return [
                'status' => 1,
                'message' => 'Record has been updated successfully.',
            ];
        } else {
            return [
                'status' => 0,
                'message' => ['Date range invalid, Kindly define again'],
            ];
        }
    }

    /*
     * Check the rota Overlap with other than the given rota
     * */
    public static function RotaOverlapStatus($resourcerota, $data)
    {

        /*Get the rota information other than the given rota id*/

        $checked = 'false';
        $where = [];

        $where[] = [
            'resource_id',
            '=',
            $resourcerota->resource_id,
        ];
        $where[] = [
            'location_id',
            '=',
            $resourcerota->location_id,
        ];
        $where[] = [
            'id',
            '!=',
            $resourcerota->id,
        ];

        $resource_rota = ResourceHasRota::where($where)->get();

        if ($resource_rota) {
            foreach ($resource_rota as $rota) {
                if (
                    ($data['start'] >= $rota->start && $data['start'] <= $rota->end) || ($data['end'] >= $rota->start && $data['start'] <= $rota->end)
                ) {
                    $checked = 'true';
                }
            }
        }

        return $checked;
    }

    /*
     * Check the time in appointment on change the rota
     * */
    public static function checkTime($current_time, $start, $end, $check_equal = false)
    {

        $date1 = \DateTime::createFromFormat('H:i a', $current_time);
        $date2 = \DateTime::createFromFormat('H:i a', $start);
        $date3 = \DateTime::createFromFormat('H:i a', $end);

        if ($check_equal) {

            if ($date1 == $date2 || $date1 == $date3) {

                return true;
            }
        }

        if ($date1 >= $date2 && $date1 < $date3) {

            return true;
        } else {

            return false;
        }
    }

    /*
     * Find bigger time from two dates
     * */
    public static function getBiggerTime($time1, $time2)
    {
        $date1 = \DateTime::createFromFormat('H:i a', $time1);
        $date2 = \DateTime::createFromFormat('H:i a', $time2);

        if ($date1 == $date2) {
            return $time1;
        } elseif ($date1 > $date2) {
            return $time1;
        } else {
            return $time2;
        }
    }

    /*
     * Find smaller time from two dates
     * */
    public static function getSmallerTime($time1, $time2)
    {
        $date1 = \DateTime::createFromFormat('H:i a', $time1);
        $date2 = \DateTime::createFromFormat('H:i a', $time2);

        if ($date1 == $date2) {
            return $time1;
        } elseif ($date1 < $date2) {
            return $time1;
        } else {
            return $time2;
        }
    }

    /**
     * check that range range is valid for duplicate resource rota or not
     *
     * @param request ,resource_rota
     * @return (mixed)
     */
    public static function CheckDate($request, $data, $resouce_has_rota_id = false)
    {
        $checked = 'false';
        $where = [];

        $where[] = [
            'resource_id',
            '=',
            $data['resource_id'],
        ];
        if ($resouce_has_rota_id) {
            $where[] = [
                'id',
                '!=',
                $resouce_has_rota_id,
            ];
        }
        if (isset($request->location_id) && $request->location_id) {
            $where[] = [
                'location_id',
                '=',
                $request->location_id,
            ];
        }

        $resource_rota = ResourceHasRota::where($where)->get();
        if ($resource_rota) {
            foreach ($resource_rota as $rota) {
                if (
                    ($request->start >= $rota->start && $request->start <= $rota->end) || ($request->end >= $rota->start && $request->end <= $rota->end)
                ) {
                    $checked = 'true';
                }
            }
        }

        return $checked;
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {

        $check = 0;

        $resourcerota = ResourceHasRota::find($id);
        $reresourcerotadays = ResourceHasRotaDays::where('resource_has_rota_id', '=', $resourcerota->id)->get();

        foreach ($reresourcerotadays as $resourcedays) {

            if ($resourcerota->resource_type_id == '1') {
                $appointment = Appointments::where('resource_has_rota_day_id_for_machine', '=', $resourcedays->id)->get();
            } else {
                $appointment = Appointments::where('resource_has_rota_day_id', '=', $resourcedays->id)->get();
            }

            if (count($appointment) > 0) {
                $check++;
            }
        }
        if ($check == 0) {
            return false;
        } else {
            return true;
        }
    }
}
