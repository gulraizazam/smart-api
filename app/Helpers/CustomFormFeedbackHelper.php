<?php
/**
 * Created by PhpStorm.
 * User: shehbaz@redsignal@biz
 * Date: 6/27/18
 * Time: 4:13 PM
 */


namespace App\Helpers;

class CustomFormFeedbackHelper
{

    const DEFAULT_FIELD_OPTION_NAME = 'field_option';

    const DEFAULT_TEXT_FIELD_PLACEHOLDER = "Your Answer Here";
    const DEFAULT_TEXT_FIELD_NAME = "answer";


    const DEFAULT_PARAGRAPH_FIELD_PLACEHOLDER = "Your Detail Answer Here";
    const DEFAULT_PARAGRAPH_FIELD_NAME = "answer";
    const DEFAULT_FIELD_TYPE_NAME = "field_type";

    const DEFAULT_SELECT_PATIENT_NAME = "reference_id";
    const DEFAULT_SELECT_PATIENT_SERVICENAME = "service_id";
    const DEFAULT_SELECT_PATIENT_PRIORITY = "priority";
    const DEFAULT_SELECT_PATIENT_DATE = "date";
    const DEFAULT_SELECT_PATIENT_APPOINTMENT = "appointment_id";
    const DEFAULT_SELECT_PATIENT_TYPE = "type";
    const DEFAULT_SELECT_PATIENT_MEASUREMENT = "measurement_id";
    const DEFAULT_SELECT_PATIENT_MEDICAL = "medical_id";

    const DEFAULT_FIELD_TYPE_TEXT = "1";
    const DEFAULT_FIELD_TYPE_PARAGRAPH = "2";
    const DEFAULT_FIELD_TYPE_SINGLE = "3";
    const DEFAULT_FIELD_TYPE_MULTIPLE = "4";
    const DEFAULT_FIELD_TYPE_OPTION = "5";
    const DEFAULT_FIELD_TYPE_TITLE = "6";
    const DEFAULT_TABLE_INPUT = "7";

    public static function getFieldOptionId($field_id, $label)
    {
        return "f_" . $field_id . "_item_" . $label;
    }

}
