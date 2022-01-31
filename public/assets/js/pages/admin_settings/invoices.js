

var table_url = route('admin.invoices.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 300,
    },{
        field: 'patient',
        title: 'Patient Name',
        sortable: false,
        width: 'auto',
    },{
        field: 'phone',
        title: 'Phone',
        sortable: false,
        width: 'auto',
    },{
        field: 'region',
        title: 'Region',
        sortable: false,
        width: 'auto',
    },{
        field: 'city',
        title: 'City',
        sortable: false,
        width: 'auto',
    },{
        field: 'centre',
        title: 'Centre',
        sortable: false,
        width: 'auto',
    },{
        field: 'appointment_type_id',
        title: 'Consultancy/Service',
        sortable: false,
        width: 'auto',
    },{
        field: 'invoice_status',
        title: 'Invoice Status',
        sortable: false,
        width: 'auto',
    },{
        field: 'type',
        title: 'Type',
        sortable: false,
        width: 'auto',
    },{
        field: 'price',
        title: 'Price',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];

function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let invoice_status_id = data.invoice_status_id
        let cancel_id = data.cancel.id

        let display_url = route('admin.invoices.displayInvoice', {id: id});
        let sms_log_url = route('admin.invoices.sms_logs', {id: id});
        let log_url = route('admin.invoices.invoice_log', {id: id, type: 'web'});
        let cancel_url = route('admin.invoices.cancel', {id: id});

        if (permissions.manage && permissions.log && permissions.sms_log && permissions.cancel) {
            let actions = '<div class="dropdown dropdown-inline action-dots">\
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';
            if (permissions.manage) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="viewInvoice(`' + display_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">Display</span>\
                    </a>\
                </li>';
            }
            if (permissions.cancel && invoice_status_id != cancel_id) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteRow(`' + cancel_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-times"></i></span>\
                        <span class="navi-text">Cancel</span>\
                        </a>\
                     </li>';
            }

            if (permissions.log) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="viewLogs(`' + log_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-file"></i></span>\
                        <span class="navi-text">Log</span>\
                        </a>\
                     </li>';
            }

            if (permissions.sms_log) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_log_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-sms"></i></span>\
                        <span class="navi-text">Sms Log</span>\
                        </a>\
                     </li>';
            }

            actions += '</ul>\
        </div>\
    </div>';

            return actions;
        }
    }
    return '';
}

function viewSmsLogs($route) {

    $("#modal_sms_logs").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setSmsLogs(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });

}

function setSmsLogs(response) {

    try {

        let SMSLogs = response.data.SMSLogs;
        let rows = '<tr><td colspan="4" class="text-center">No SMS log found.</td></tr>';
        if (SMSLogs.length) {
            let rows = '<tr>';
            Object.entries(SMSLogs).forEach(function (value, index) {
                console.log(value)
                rows += '<td></td>';
            });
            rows += '</tr>';
        }

        $("#sms_log_rows").html(rows);

    } catch (error) {
        showException(error);
    }

}

function viewLogs($route) {

    $("#modal_logs").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setLogs(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });

}

function setLogs(response) {

    try {

        let resource = response.data.resource;
        let machine_types = response.data.machine_types;
        let resource_types = response.data.resource_types;
        let locations = response.data.locations;

        $("#modal_edit_resources_form").attr("action", route('admin.resources.update', {id: resource.id}));


        let machine_options = '<option value="">Select</option>';
        let location_options = '<option value="">Select</option>';
        let resource_options = '<option value="">Select</option>';

        Object.entries(machine_types).forEach(function (value, index) {
            machine_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value, index) {
            location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(resource_types).forEach(function (value, index) {
            resource_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        $("#edit_machine_type_id").html(machine_options);
        $("#edit_location_id").html(location_options);
        $("#edit_resource_type_id").html(resource_options);

        $("#edit_name").val(resource.name);
        $("#edit_location_id").val(resource.location_id);
        $("#edit_machine_type_id").val(resource.machine_type_id);
        $("#edit_resource_type_id").val(resource.resource_type_id);

    } catch (error) {
        showException(error);
    }

}

function viewInvoice($route) {

    $("#modal_display").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setInvoiceData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });

}

function setInvoiceData(response) {

    try {

        let location_info = response.data.location_info;
        let Invoiceinfo = response.data.Invoiceinfo;
        let patient = response.data.patient;
        let company_phone_number = response.data.company_phone_number;
        let account = response.data.account;
        let service = response.data.service;

        let image = asset_url +'assets/media/logos/'+ location_info.image_src;

        $(".invoice-image").attr('src', image);
        $("#invoice_info_created_at").text(Invoiceinfo.created_at);
        $("#invoice_info_id").text(Invoiceinfo.id);

        $("#client_id").text("C-" + patient.id);
        $("#client_name").text(patient.name);
        $("#client_email").text(patient.email);

        $("#company_name").text(account.name)
        $("#contact_no").text(company_phone_number.data)
        $("#company_email").text(account.email)
        $("#clinic_contact").text(account.contact)
        $("#clinic_name").text(location_info.name)
        $("#clinic_address").text(location_info.address)
        $("#clinic_ntn").text(location_info.ntn)
        $("#clinic_stn").text(location_info.stn)


        $("#service_name").html(service.name);

        $("#discount_name").html(service.name);

    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            id: $("#search_id").val(),
            patient_id: $("#search_patient_id").val(),
            patient_name: $("#search_patient_id").text(),
            location_id: $("#search_location_id").val(),
            service_id: $("#search_service_id").val(),
            invoice_status_id: $("#search_invoice_status_id").val(),
            appointment_type_id: $("#search_appointment_type_id").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            id: '',
            patient_id: '',
            patient_name: '',
            location_id: '',
            service_id: '',
            invoice_status_id: '',
            appointment_type_id: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function resetInvoiceFilters() {

    $(".filter-field").val('');
    addUsers();
    $('.select2').val(null).trigger('change');
}

function setFilters(filter_values, active_filters) {

    try {

        let appointment_types = filter_values.appointment_types;
        let invoicestatus = filter_values.invoicestatus;
        let leadServices = filter_values.leadServices;
        let locations = filter_values.locations;
        let services = filter_values.services;

        let types_options = '<option value="">All</option>';
        let location_options = '<option value="">All</option>';
        let service_options = '<option value="">Select a Service</option>';
        let invoice_status_options = '<option value="">All</option>';

        Object.entries(appointment_types).forEach( function (value) {
            types_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(locations).forEach( function (value) {
            location_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.values(services).forEach( function (value) {
            if (typeof value.id !== 'undefined') {
                service_options += '<option value="'+value.id+'">'+value.name+'</option>';
            }
        });

        Object.entries(invoicestatus).forEach( function (value) {
            invoice_status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        $("#search_appointment_type_id").html(types_options);
        $("#search_location_id").html(location_options);
        $("#search_service_id").html(service_options);
        $("#search_invoice_status_id").html(invoice_status_options);

        $("#search_id").val(active_filters.id);
        if (typeof active_filters.patient_id !== 'undefined') {
            $("#search_patient_id").html('<option value="'+active_filters.patient_id+'">'+active_filters.patient_name+'</option>');
            $("#search_patient_id").val(active_filters.patient_id);
        }
        $("#search_location_id").val(active_filters.location_id);
        $("#search_appointment_type_id").val(active_filters.appointment_type_id);
        $("#search_invoice_status_id").val(active_filters.invoice_status_id);
        $("#search_service_id").val(active_filters.service_id);

        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);

        hideShowAdvanceFilters(active_filters);

    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.invoice_status_id !== 'undefined' && active_filters.invoice_status_id != '')
        || (typeof active_filters.appointment_type_id !== 'undefined' && active_filters.appointment_type_id != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}
