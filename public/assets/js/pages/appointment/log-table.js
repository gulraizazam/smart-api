var table_url = route('admin.appointments.viewlog', {id: appointment_id, type: 'web'});

var table_columns = [

    {
        field: 'action',
        title: 'action',
        width: 'auto',
        sortable: false,
    },{
        field: 'phone',
        title: 'Phone',
        width: 'auto',
        template: function (data) {
            if (permissions.contact) {
                return data.phone ?? 'N/A';
            }
            return '***********';
        }
    },{
        field: 'scheduled_date',
        title: 'SCHEDULED AT',
        width: 'auto',
        template: function (data) {
            return formatDate(data.scheduled_date);
        }
    },{
        field: 'doctor_id',
        title: 'DOCTOR',
        width: 'auto',
    },{
        field: 'resource_id',
        title: 'RESOURCE',
        width: 'auto',
    },{
        field: 'region_id',
        title: 'Region',
        width: 'auto',
    },{
        field: 'city_id',
        title: 'City',
        width: 'auto',
    },{
        field: 'location_id',
        title: 'Centre',
        width: 'auto',
    },{
        field: 'service_id',
        title: 'SERVICE',
        width: 'auto',
    },{
        field: 'base_appointment_status_id',
        title: 'PARENT STATUS',
        width: 'auto',
    },{
        field: 'appointment_status_id',
        title: 'CHILD STATUS',
        width: 'auto',
    },{
        field: 'appointment_type_id',
        title: 'TYPE',
        width: 'auto',
    },{
        field: 'created_at',
        title: 'CREATED AT',
        width: 'auto',
        template: function (data) {
            return formatDate(data.created_at);
        }
    },{
        field: 'created_by',
        title: 'Created By',
        width: 'auto',
    },{
        field: 'updated_by',
        title: 'Updated By',
        width: 'auto',
    },{
        field: 'converted_by',
        title: 'Rescheduled By',
        width: 'auto',
    },{
        field: 'send_message',
        title: 'MESSAGE',
        width: 'auto',
        template: function (data) {
            return data.send_message ? data.send_message == 1  ? 'Sent' : 'Not Sent' : '-' ;
        }
    }];
