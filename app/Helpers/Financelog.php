<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Financelog
{
    static public function Calculate_Val($audit_detail)
    {
        if ($audit_detail->field_name == 'total_price') {
            $before = $audit_detail->field_before;
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'created_at') {
            $before = $audit_detail->field_before;
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'payment_mode_id') {
            $before = $audit_detail->payment_mode_before->name;
            $after = $audit_detail->payment_mode_after->name;
        } else {
            $before = $audit_detail->field_before;
            $after = $audit_detail->field_after;
        }
        return array(
            'before'=>$before,
            'after'=> $after,
        );
    }

    static public function Calculate_Val_advance($audit_detail)
    {
        if ($audit_detail->field_name == 'cash_flow') {
            $after = $audit_detail->field_after;

        } else if ($audit_detail->field_name == 'cash_amount') {
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'active') {
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'is_refund') {
            $after = $audit_detail->field_after == 1 ? 'Yes' : 'No';
        } else if ($audit_detail->field_name == 'is_adjustment') {
            $after = $audit_detail->field_after == 1 ? 'Yes' : 'No';
        } else if ($audit_detail->field_name == 'is_tax') {
            $after = $audit_detail->field_after == 1 ? 'Yes' : 'No';
        } else if ($audit_detail->field_name == 'refund_note') {
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'is_cancel') {
            $after = $audit_detail->field_after == 1 ? 'Yes' : 'No';
        } else if ($audit_detail->field_name == 'patient_id') {
            $after = $audit_detail->patients_after->name;
        } else if ($audit_detail->field_name == 'payment_mode_id') {
            $after = $audit_detail->payment_mode_after->name;
        } else if ($audit_detail->field_name == 'account_id') {
            $after = $audit_detail->accounts_after->name;
        } else if ($audit_detail->field_name == 'appointment_type_id') {
            $after = $audit_detail->appointment_type_after->name;
        } else if ($audit_detail->field_name == 'appointment_id') {
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'location_id') {
            $after = $audit_detail->locations_after->name;
        } else if ($audit_detail->field_name == 'created_by') {
            $after = $audit_detail->users_after->name;
        } else if ($audit_detail->field_name == 'updated_by') {
            $after = $audit_detail->users_after->name;
        } else if ($audit_detail->field_name == 'package_id') {
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'invoice_id') {
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'created_at') {
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'updated_at') {
            $after = $audit_detail->field_after;
        } else if ($audit_detail->field_name == 'deleted_at') {
            $after = $audit_detail->field_after;
        }
        return $after;
    }
}