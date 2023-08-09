<?php

return [
    'gender_array' => [
        1 => 'Male',
        2 => 'Female',
    ],

    'listing_array' => [
        'listing' => 'Datatable Listing',
        'elastic' => 'Elastic Search',
    ],

    'operator_array' => [
        1 => 'Telenor Corporate SMS',
        2 => 'Jazz Corporate SMS',
    ],
    'invoice_consultancy_medical_form' => [
        1 => 'Yes',
        2 => 'No',
    ],
    'trans_type' => [
        'advance_in' => 'Advance In',
        'advance_out' => 'Advance Out',
        'invoice_cancel' => 'Invoice Cancel',
        'invoice_create' => 'Invoice Create',
        'refund_in' => 'Refund Out',
        'refund_out' => 'Refund Out',
        'adjustment' => 'Adjustment',
        'tax_out' => 'Tax Out',
    ],

    'lead_status_open' => 1,
    'lead_status_junk' => 5,
    'lead_status_converted' => 3,
    'lead_source_social_media' => 7,
    'appointment_status_pending' => 1,
    'appointment_status_not_show' => 3,
    'appointment_status_cancelled' => 4,
    'cancellation_reason_other_reason' => 4,
    'appointment_status_not_interested' => 14,
    'appointment_status_arrived' => 2,

    // Appointment Types ID mapping
    'appointment_type_consultancy_string' => 'consulting',
    'appointment_type_consultancy' => 1,
    'appointment_type_service_string' => 'treatment',
    'appointment_type_service' => 2,
    //Constant for user type start

    // For invoice log
    'package_advance_table_name_log' => '25',

    'invoice_table_name_log' => '26',
    'invoice_detail_table_name_log' => '27',

    'plan_table_name_log' => '23',
    'plan_bundle_table_name_log' => '24',
    'plan_service_table_name_log' => '34',
    //end

    //Log actions
    'create_log' => '1',
    'edit_log' => '2',
    'delete_log' => '3',
    'inactive_log' => '4',
    'active_log' => '5',
    'cancel_log' => '6',
    //end

    'administrator_id' => 1,
    'application_user_id' => 2,
    'patient_id' => 3,
    'practitioner_id' => 5,
    'asthatic_operator_id' => 5,

    'resource_room_type_id' => '1',
    'resource_doctor_type_id' => '2',

    'Fixed' => 'Fixed',
    'Percentage' => 'Percentage',

    'Consultancy' => 'Consultancy',
    'Service' => 'Treatment',

    //End

    'cash_array' => [
        '' => 'All',
        'in' => 'In',
        'out' => 'out',
    ],

    'sms_array' => [
        'sms' => '1st SMS',
        '2nd_sms' => '2nd SMS',
        '3rd_sms' => '3rd SMS',
    ],
    'payment_type' => [
        '1' => 'Cash',
        '2' => 'Credit Card',
        '3' => 'Paypal',
        '4' => 'Bank/Wire Transfer',
        '5' => 'Payment Adjustment',
        '6' => 'Settle Amount',
    ],
    'payment_type_settle' => 6,

    'yesno_array' => [
        1 => 'Yes',
        2 => 'No',
    ],
    'user_form_field_type' => [
        '1' => 'Text',
        '2' => 'Paragraph',
        '3' => 'Single Select Field',
        '4' => 'Multi Select Field',
        '5' => 'Options List',
        '6' => 'Title and Description',

    ],
    'months_array' => [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ],
    'custom_form' => [
        'field_types' => [
            'text' => '1',
            'paragraph' => '2',
            'single' => '3',
            'multiple' => '4',
            'option' => '5',
            'title' => '6',
            'table_input' => '7',
        ],
        'default_field_name' => [
            'option' => 'field_option',
            'table_input' => 'field_option',
        ],
    ],

    'center_target_array' => [
        '1' => '1',
        '2' => '2',
        '3' => '3',
        '4' => '4',
        '5' => '5',
        '6' => '6',
        '7' => '7',
        '8' => '8',
        '9' => '9',
        '10' => '10',
        '11' => '11',
        '12' => '12',
        '13' => '13',
        '14' => '14',
        '15' => '15',
        '16' => '16',
        '17' => '17',
        '18' => '18',
        '19' => '19',
        '20' => '20',
        '21' => '21',
        '22' => '22',
        '23' => '23',
        '24' => '24',
        '25' => '25',
        '26' => '26',
        '27' => '27',
        '28' => '28',
        '29' => '29',
        '30' => '30',
        '31' => '31',
    ],

    'payment_use_type' => [
        'application' => 'Application',
        'system' => 'System',
    ],

    'status' => [
        '0' => 'Inactive',
        '1' => 'Active',
    ],

    'tax_both' => '1',
    'tax_is_exclusive' => '2',
    'tax_is_inclusive' => '3',

    'consultancy_type_array' => [
        'in_person' => 'In Person',
        'virtual' => 'Virtual',
    ],
    'consultancy_type' => [
        1 => 'Yes',
        2 => 'No',
    ],

    'user_types' => [
        'application user' => 'application user',
        'patient' => 'patient',
        'practitioner' => 'practitioner',
    ],

    'api_status' => [
        'success' => 200,
        'error' => 500,
        'unauthorized' => 401,
    ],

    'discount_groups' => [
        'default' => 'Default',
        'custom' => 'Custom',
        'birthday' => 'Birthday',
    ],
    'discount_types' => [
        'Treatment' => 'Treatment',
        'Consultancy' => 'Consultancy',
    ],
    'amount_types' => [
        'Fixed' => 'Fixed',
        'Percentage' => 'Percentage',
    ],
    'export-appointment-limit' => 1000,
    'export-lead-excel-limit' => 1000,
    'export-lead-pdf-limit' => 100,
];
