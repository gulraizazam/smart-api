

var table_url = route('admin.invoices.datatable');

var width = (window.innerWidth > 0) ? window.innerWidth : screen.width;

if (width > 1280) {

    var table_columns = [
        {
            field: 'invoice_number',
            title: 'Invoice #',
            sortable: false,
            width: 80,
        },{
            field: 'patient_id',
            title: 'Patient ID',
            sortable: false,
            width: 80,
        },{
            field: 'name',
            title: 'Name',
            sortable: false,
            width: 120,
        },{
            field: 'phone',
            title: 'Phone',
            sortable: false,
            width: 100,
        },{
            field: 'location',
            title: 'Centre',
            sortable: false,
            width: 'auto',
        },{
            field: 'service',
            title: 'Service',
            sortable: false,
            width: 180,
        },{
            field: 'price',
            title: 'Price',
            sortable: false,
            width: 80,
        },{
            field: 'invoice_status',
            title: 'Status',
            sortable: false,
            width: 90,
        },{
            field: 'actions',
            title: 'Actions',
            sortable: false,
            width: 120,
            overflow: 'visible',
            autoHide: false,
            template: function (data) {
                return actions(data);
            }
        },{
            field: 'appointment_type_id',
            title: 'Type',
            sortable: false,
            width: 'auto',
        },{
            field: 'created_at',
            title: 'Created at',
            width: 'auto',
        }];
} else {
    var table_columns = [
        {
            field: 'invoice_number',
            title: 'Invoice #',
            sortable: false,
            width: 70,
        },{
            field: 'patient_id',
            title: 'Patient ID',
            sortable: false,
            width: 70,
        },{
            field: 'name',
            title: 'Patient Name',
            sortable: false,
            width: 90,
        },{
            field: 'phone',
            title: 'Phone',
            sortable: false,
            width: 90,
        },{
            field: 'location',
            title: 'Centre',
            sortable: false,
            width: 90,
        },{
            field: 'price',
            title: 'Price',
            sortable: false,
            width: 70,
        },{
            field: 'service',
            title: 'Service',
            sortable: false,
            width: 170,
        },{
            field: 'invoice_status',
            title: 'Status',
            sortable: false,
            width: 75,
        },{
            field: 'actions',
            title: 'Actions',
            sortable: false,
            width: 100,
            overflow: 'visible',
            autoHide: false,
            template: function (data) {
                return actions(data);
            }
        },{
            field: 'appointment_type_id',
            title: 'Type',
            sortable: false,
            width: 'auto',
        },{
            field: 'created_at',
            title: 'Created at',
            width: 'auto',
        }];
}


function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let invoice_status_id = data.invoice_status_id
        let cancel_id = data.cancel.id

        let display_url = route('admin.invoices.displayInvoice', {id: id});
        let sms_log_url = route('admin.invoices.sms_logs', {id: id});
        let log_url = route('admin.invoices.invoice_log', {id: id, type: 'web'});
        let cancel_url = route('admin.invoices.cancel', {id: id});

        if (permissions.manage || permissions.log || permissions.sms_log || permissions.cancel) {
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
                        <a href="javascript:void(0);" onclick="deleteRow(`' + cancel_url + '`, `POST`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-times"></i></span>\
                        <span class="navi-text">Cancel</span>\
                        </a>\
                     </li>';
            }

            if (permissions.log) {
                // actions += '<li class="navi-item">\
                //         <a href="'+log_url+'" class="navi-link">\
                //         <span class="navi-icon"><i class="la la-file"></i></span>\
                //         <span class="navi-text">Log</span>\
                //         </a>\
                //      </li>';
            }

            if (permissions.sms_log) {
                // actions += '<li class="navi-item">\
                //         <a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_log_url + '`);" class="navi-link">\
                //         <span class="navi-icon"><i class="la la-sms"></i></span>\
                //         <span class="navi-text">Sms Log</span>\
                //         </a>\
                //      </li>';
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

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setSmsLogs(response) {

    try {

        let SMSLogs = response.data.SMSLogs;
        let rows = noRecordFoundTable(5);
        if (SMSLogs.length) {
            let rows = '';
            Object.entries(SMSLogs).forEach(function (smsLog, index) {
                if(smsLog.invoice_id === null) {
                    rows += '<tr>';
                    rows += '<td>' + smsLog.to + '</td>';
                    rows += '<td><a href="javascript:void(0);" onclick="toggleText($(this))">';
                    rows += '<span class="short_text" style="display: block">' + smsLog.text.slice(0, 50).concat('...') + '</span>';
                    rows += '<span class="full_text" style="display: none; text-underline: none;">' + smsLog.text + '</span>';
                    '</a></td>';

                    if(smsLog.status) {
                        rows += '<td id="smsRow{'+smsLog.id+'">Yes</td>';
                    } else {
                        rows += '<td><span class="text-center" id="spanRow'+smsLog.id+'">No</span>\
                        <br/><a id="clickRow'+smsLog.id+'" href="javascript:void(0)" onclick="resendSMS('+smsLog.id+', `'+sent_url+'`, `POST`);" class="btn btn-sm btn-success spinner-button" data-toggle="tooltip" title="Resend SMS">' +
                            '<i class="la la-send-o"></i></a></td>';
                    }

                    if(smsLog.is_refund == "Yes") {
                        rows += '<td>smsLog.is_refund</td>';
                    } else {
                        rows += '<td></td>';
                    }

                    rows += '<td>' + formatDate(smsLog.created_at) + '</td>';
                    rows += '</tr>';
                }
            });
        }

        $("#sms_log_rows").html(rows);

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
        let discount = response.data.discount;
        let tax = response.data.tax;
        let doctor = response.data.doctor;

        // Set modal title: PatientName - Consultation with DoctorName
        let modalTitle = patient?.name || 'Patient';
        if (doctor?.name) {
            modalTitle += ' - Consultation with ' + doctor.name;
        }
        $(".rota-title").text(modalTitle);

        $("#invoice-pdf").attr("href", route('admin.invoices.invoice_pdf', Invoiceinfo.invoice_id))

        let image = asset_url + 'assets/media/logos/' + location_info.image_src;

        $(".invoice-image").attr('src', image);
        $("#invoice_info_created_at").text(Invoiceinfo.created_at);
        $("#invoice_info_id").text(Invoiceinfo.id);

        $("#client_id").text("C-" + patient.id);
        $("#client_name").text(patient.name);

        if(patient.email != "" && patient.email != null){
            $("#client_email_li").show();
            $("#client_email").text(patient.email);
        }else{
            $("#client_email_li").hide();
        }

        $("#company_name").text(account.name)
        $("#contact_no").text(company_phone_number.data)
        $("#company_email").text(account.email)
        $("#clinic_contact").text(account.contact)
        $("#clinic_name").text(location_info.name)
        $("#clinic_address").text(location_info.address)
        $("#clinic_ntn").text(location_info.ntn)
        $("#clinic_stn").text(location_info.stn)


        $("#service_name").html(service?.name ?? '-');
        let service_price = getServicePrice(Invoiceinfo);
        $("#service_price").html(service_price ?? '-');

        let discount_name = '-';
        if (discount != null) {
            discount_name = discount.name
        }

        let discount_type = '-';
        if (discount != null) {
            discount_type = Invoiceinfo.discount_type;
        }

        let discount_price = 0;
        if (discount != null) {
            discount_price = Invoiceinfo.discount_price;
        }

        let subtotal = 0;
        if(Invoiceinfo.is_exclusive == '0') {
            if(Invoiceinfo.discount_price == null ) {
                subtotal = parseFloat(Invoiceinfo.service_price)-parseFloat(Invoiceinfo.tax_price);
            } else {
                subtotal = Invoiceinfo.tax_exclusive_serviceprice;
            }
        } else if(Invoiceinfo.is_exclusive == '1') {
            subtotal = Invoiceinfo.tax_exclusive_serviceprice;
        }

        $("#invoice_subtotal").html(subtotal);
        $("#discount_price").html(discount_price);
        $("#discount_type").html(discount_type);
        $("#discount_name").html(discount_name);

        $("#invoice_tax").html(Invoiceinfo?.tax_percenatage ?? '-');
        $("#invoice_tax_price").html(Invoiceinfo?.tax_price ?? '-');
        $("#total_price").html(Invoiceinfo?.tax_including_price ?? '-');
        $("#grand_total_price").html(Invoiceinfo?.tax_including_price ?? '-');

    } catch (error) {
        showException(error);
    }

}

function getServicePrice(Invoiceinfo) {

    let service_price = 0;
    if (Invoiceinfo.is_exclusive == '0') {
        if (Invoiceinfo.service_price == '0') {

            service_price = Invoiceinfo.tax_including_price;

        } else {
            service_price = parseFloat(Invoiceinfo.service_price) - parseFloat(Invoiceinfo.tax_price);
        }
    } else if (Invoiceinfo.is_exclusive == '0') {
        if (Invoiceinfo.service_price == '0') {
            service_price = Invoiceinfo.tax_including_price;
        } else {
            service_price = Invoiceinfo.service_price;
        }
    } else if(Invoiceinfo.is_exclusive == '1') {
        if (Invoiceinfo.service_price == '0') {
            service_price = Invoiceinfo.tax_including_price;
        } else {
            service_price = Invoiceinfo.service_price;
        }
    }

    return service_price;

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            id: $("#search_id").val(),
            patient_id: $("#search_patient_id").val(),
            patient_name: $("#search_name").text(),
            location_id: $("#search_location_id").val(),
            service_id: $("#search_service_id").val(),
            invoice_status_id: $("#search_invoice_status_id").val(),
            appointment_type_id: $("#search_appointment_type_id").val(),
            created_at: $("#date_range").val(),
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
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function resetCustomFilters() {

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
            $("#search_name").html('<option value="'+active_filters.patient_id+'">'+active_filters.patient_name+'</option>');
            $("#search_name").val(active_filters.patient_id);
        }
        $("#search_location_id").val(active_filters.location_id);
        $("#search_appointment_type_id").val(active_filters.appointment_type_id);
        $("#search_invoice_status_id").val(active_filters.invoice_status_id);
        $("#search_service_id").val(active_filters.service_id);

        $("#date_range").val(active_filters.created_at);

        hideShowAdvanceFilters(active_filters);

        getUserCentre();

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

jQuery(document).ready( function () {
    $("#date_range").val("");
})
