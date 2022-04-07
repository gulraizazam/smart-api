
var table_url = route('admin.home.datatable');
var changePages = 10;
var table_columns = [

    {
        field: 'Patient_ID',
        title: 'ID',
        width: 60,
        sortable: false,
    },{
        field: 'name',
        title: 'Patient',
        width: 100,
    },{
        field: 'phone',
        title: 'Phone',
        width: 100,
        template: function (data) {
            return phoneClip(data);
        }
    },{
        field: 'scheduled_date',
        title: 'Scheduled',
        width: 'auto',
        template: function (data) {
            if (data.appointment_status_id == "Arrived" || data.appointment_status_id == "Cancelled") {
                return '<span>'+data.scheduled_date+'</span>';
            } else {
                return '<a href="javascript:void(0);" onclick="editSchedule(' + data.id + ');"><br> ' + data.scheduled_date + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
            }
        }
    },{
        field: 'service_id',
        title: 'Service',
        width: 'auto',
    },{
        field: 'appointment_type_id',
        title: 'Type',
        width: 100,
    },{
        field: 'doctor_id',
        title: 'Doctor',
        width: 'auto',
    },{
        field: 'appointment_status_id',
        title: 'Status',
        width: 100,
        template: function (data) {

            let unscheduled_appointment_status = data.unscheduled_appointment_status;
            let appointment_status = data.appointment_status;

            if (permissions.status) {
                if (unscheduled_appointment_status && (appointment_status == unscheduled_appointment_status.id)) {
                    return '<span class="badge badge-dark">'+data.appointment_status_id+'</span>';
                } else {
                    return '<a href="javascript:void(0);" onclick="editStatus(' + data.id + ');">' + data.appointment_status_id + ' <i style="color: #cc8600; font-size: large" class="la la-pencil"></i></a>';
                }
            } else {
                return '<span class="badge badge-dark">'+data.appointment_status_id+'</span>';
            }
        }
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
        field: 'consultancy_type',
        title: 'Consultancy Type',
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created At',
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
    }];

function setTotal(meta) {

    $(".total-members").text(meta?.total ?? 0)
}


function getArrivalsByDate($this, date, time, type = 'today') {

    console.log({
        'date': date,
        'time': time,
        'type': type,
    })
    datatable.search({
        'date': date,
        'time': time,
        'type': type,
    }, 'filter');
}
