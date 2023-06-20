<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers\Elastic;

use App\Models\Appointments;
use App\Models\Resources;
use Carbon\Carbon;
use Elasticsearch\ClientBuilder;

class AppointmentsElastic
{
    /**
     * Store Elasticsearch client
     */
    protected static $elastic_client;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private static function __init()
    {
        try {
            $hosts = [
                // This is effectively equal to: "https://username:password!#$?*abc@foo.com:9200/elastic"
                [
                    'host' => env('ELASTICSEARCH_HOST'),
                    'port' => env('ELASTICSEARCH_PORT'),
                    'user' => env('ELASTICSEARCH_USER'),
                    'pass' => env('ELASTICSEARCH_PASS'),
                ],
            ];

            self::$elastic_client = ClientBuilder::create()           // Instantiate a new ClientBuilder
                ->setHosts($hosts)      // Set the hosts
                ->build();              // Build the client object
        } catch (\Exception $e) {
            self::$elastic_client = null;
        }
    }

    /**
     * Function to store object in elastic
     *
     * @return bool
     */
    public static function indexObject(Appointments $appointment)
    {
        self::__init();

        $elastic_object = self::prepareObject($appointment);

        $data = [
            'index' => env('ELASTICSEARCH_INDEX'),
            'type' => 'appointments',
            'id' => $elastic_object['id'],
            'body' => $elastic_object['body'],
        ];

        try {

            if (self::$elastic_client) {
                self::$elastic_client->index($data);
            }

            return true;
        } catch (\Exception $e) {

            return false;
        }
    }

    /**
     * Function to delete object from elastic
     *
     * @param mixed
     * @return bool
     */
    public static function deleteObject($id)
    {
        self::__init();

        $data = [
            'index' => env('ELASTICSEARCH_INDEX'),
            'type' => 'appointments',
            'id' => $id,
        ];

        try {
            if (self::$elastic_client) {
                self::$elastic_client->delete($data);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Function to store object in elastic
     *
     * @param  Appointments  $appointment
     * @return mixed
     */
    public static function getAllObjects($match, $filter, $iDisplayStart, $iDisplayLength, $orderBy, $order)
    {
        self::__init();
        //dd($filter);
        //dd($match);

        $params = [
            'index' => env('ELASTICSEARCH_INDEX'),
            'type' => 'appointments',
            'body' => [
                'track_total_hits' => true,
                'query' => [
                    'bool' => [
                        'must' => $match,
                    ],
                ], 'sort' => [
                    [
                        $orderBy => $order,
                    ],
                ],
            ], 'from' => $iDisplayStart, 'size' => $iDisplayLength,

        ];

        /**
         * Filters are provided, add them to query
         */
        if (count($filter)) {
            $params['body']['query']['bool'] = [
                'must' => [$match],
                'filter' => $filter,
            ];
        }

        //        echo '<pre>';
        //        print_r($params);
        //        exit;

        try {
            if (self::$elastic_client) {
                return self::$elastic_client->search($params);
            }

            return false;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Prepare elastic object before store
     *
     *
     * @return mixed
     */
    private static function prepareObject(Appointments $appointment)
    {

        if ($appointment->resource_id) {
            $resource = Resources::find($appointment->resource_id);
        } else {
            $resource = null;
        }

        $elastic_object = [
            'id' => $appointment->id,
            'body' => [
                /**
                 * General Information
                 */
                'name' => $appointment->name,
                'random_id' => $appointment->random_id,
                'scheduled_date' => $appointment->scheduled_date,
                'scheduled_time' => $appointment->scheduled_time,
                'scheduled_datetime' => strtotime(Carbon::parse($appointment->scheduled_date.' '.$appointment->scheduled_time)->format('Y-M-d H:i:s')),
                'reason' => $appointment->reason,
                'send_message' => $appointment->send_message,
                'lead_id' => $appointment->lead_id,

                /**
                 * Patient Information
                 */
                'patient_id' => $appointment->patient_id,
                'patient_name' => $appointment->patient->name,
                'patient_email' => $appointment->patient->email,
                'patient_phone' => $appointment->patient->phone,

                /**
                 * Doctor Information
                 */
                'doctor_id' => $appointment->doctor_id,
                'doctor_name' => $appointment->doctor->name,

                /**
                 * Location Information
                 */
                'region_id' => $appointment->region_id,
                'region_name' => $appointment->region->name,
                'city_id' => $appointment->city_id,
                'city_name' => $appointment->city->name,
                'location_id' => $appointment->location_id,
                'location_name' => $appointment->location->name,

                /**
                 * General Information
                 */
                'appointment_id' => $appointment->appointment_id,
                'base_appointment_status_id' => $appointment->base_appointment_status_id,
                'base_appointment_name' => $appointment->appointment_status_base->name,
                'appointment_status_id' => $appointment->appointment_status_id,
                'appointment_status_name' => $appointment->appointment_status->name,
                'appointment_status_allow_message' => $appointment->appointment_status_allow_message,
                'cancellation_reason_id' => $appointment->cancellation_reason_id,

                /**
                 * Service Information
                 */
                'service_id' => $appointment->service_id,
                'service_name' => $appointment->service->name,

                /**
                 * Resource Information
                 */
                'resource_id' => $appointment->resource_id,
                'resource_name' => ($appointment->resource_id) ? $resource->name : null,

                /**
                 * Appointment Type Information
                 */
                'appointment_type_id' => $appointment->appointment_type_id,
                'consultancy_type' => $appointment->consultancy_type,
                'coming_from' => $appointment->coming_from ? $appointment->coming_from : null,
                'appointment_type_name' => $appointment->appointment_type->name,

                /**
                 * General Information
                 */
                'resource_has_rota_day_id' => $appointment->resource_has_rota_day_id,
                'resource_has_rota_day_id_for_machine' => $appointment->resource_has_rota_day_id_for_machine,

                /**
                 * Staff Information
                 */
                'created_by' => $appointment->created_by,
                'created_by_name' => $appointment->user->name,
                'updated_by' => $appointment->updated_by,
                'updated_by_name' => $appointment->user_updated_by->name,
                'converted_by' => $appointment->converted_by,
                'converted_by_name' => $appointment->user_converted_by->name,

                /**
                 * General Information
                 */
                'active' => $appointment->active,
                'msg_count' => $appointment->msg_count,
                'counter' => $appointment->counter,
                'created_at' => strtotime(Carbon::parse($appointment->created_at)->format('Y-M-d H:i:s')),
                'updated_at' => strtotime(Carbon::parse($appointment->updated_at)->format('Y-M-d H:i:s')),
                'deleted_at' => ($appointment->deleted_at) ? strtotime(Carbon::parse($appointment->deleted_at)->format('Y-M-d H:i:s')) : null,
                'account_id' => $appointment->account_id,
            ],
        ];

        return $elastic_object;
    }
}
