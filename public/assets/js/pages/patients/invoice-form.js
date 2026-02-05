
var table_url = route('admin.invoicepatient.datatable', {id: patientCardID});

var table_columns = [
    {
        field: 'invoice_number',
        title: 'Invoice #',
        sortable: false,
        width: 80,
    },{
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 80,
    },{
        field: 'phone',
        title: 'Phone',
        sortable: false,
        width: 90,
    },{
        field: 'location',
        title: 'Centre',
        sortable: false,
        width: 150,
    },{
        field: 'service',
        title: 'Service',
        sortable: false,
        width: 100,
    },{
        field: 'invoice_status',
        title: 'Status',
        sortable: false,
        width: 70,
    },{
        field: 'price',
        title: 'Price',
        sortable: false,
      width: 70,
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
        let cancel = data?.cancel;
        let invoice = data?.invoice;

        let cancel_url = route('admin.invoicepatient.cancel', {id: id});
        let display_url = route('admin.invoices.displayInvoice', {id: id});
        let log_url = route('admin.invoicepatient.invoice_log', {id: id, type: 'web', patient_id: patientCardID });
        let sms_log_url = route('admin.invoices.sms_logs', {id: id});

        if (permissions.manage || permissions.cancel || permissions.log || permissions.sms_log) {
            let actions = '<div class="dropdown dropdown-inline action-dots">\
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';

        if(invoice?.invoice_status_id != cancel?.id) {
            if (permissions.cancel) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="cancleRow(`' + cancel_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Cancel</span>\
                        </a>\
                     </li>';
            }
        }

            if (permissions.manage) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="viewInvoicePlan(`' + display_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">Display</span>\
                    </a>\
                </li>';
            }

            if (permissions.log) {
                actions += '<li class="navi-item">\
                        <a href="'+log_url+'" class="navi-link">\
                        <span class="navi-icon"><i class="la la-file"></i></span>\
                        <span class="navi-text">Log</span>\
                        </a>\
                     </li>';
            }

            if (permissions.sms_log) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="viewInvoiceSmsLogs(`' + sms_log_url + '`);" class="navi-link">\
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

/*actions*/

function viewInvoicePlan($route) {

    $("#modal_invoice_display").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setInvoiceDisplayData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function displayData(response) {

    try {

        let packageadvances = response.data.packageadvances;
        let Invoiceinfo = response.data.Invoiceinfo;
        let location_info = response.data.location_info;
        let packagebundles = response.data.packagebundles;
        let packageservices = response.data.packageservices;
        let patient = response.data.patient;

        $(".editPatientName").text(patient?.name);

        let history_options = noRecordFoundTable(4);

        if (packageadvances) {

            history_options = noRecordFoundTable(4);
            Object.values(packageadvances).forEach(function (packageadvance) {

                if(packageadvance.cash_amount != '0' && packageadvance.is_tax == 0) {
                    history_options += '<tr>';
                    history_options += '<td>'+packageadvance.paymentmode.name+'</td>';
                    history_options += '<td>'+packageadvance.cash_flow+'</td>';
                    history_options += '<td>'+packageadvance.package_refund_price+'</td>';
                    history_options += '<td>'+packageadvance.created_at_formated+'</td>';
                    history_options += '<tr>';
                }
            });
        }


        let service_options = noRecordFoundTable(9);

        if(packagebundles) {
            service_options = noRecordFoundTable(9);
            Object.values(packagebundles).forEach(function (packagebundle) {
                service_options += '<tr>';
                service_options += '<td><a href="javascript:void(0);" onclick="toggle('+packagebundle.id+')">'+packagebundle.bundle.name+'</a></td>';
                service_options += '<td>'+packagebundle.service_price.toFixed(2)+'</td>';
                service_options += '<td>';
                if(packagebundle.discount_id == null) {
                    service_options += '-';
                } else if(packagebundle.discount_name) {
                    service_options += packagebundle.discount_name;
                } else {
                    service_options += packagebundle.discount.name;
                }
                service_options += '</td>';

                service_options += '<td>';
                if (packagebundle.discount_type == null) {
                    service_options +=  '-';
                } else {
                    service_options +=  packagebundle.discount_type;
                }
                service_options += '</td>';

                service_options += '<td>';

                if (packagebundle.discount_price == null) {
                    service_options += '0.00';
                } else {
                    service_options += packagebundle.discount_price;
                }
                service_options += '</td>';

                service_options += '<td>'+packagebundle.tax_exclusive_net_amount+'</td>';
                service_options +=  '<td>'+packagebundle.tax_percenatage+'</td>';
                service_options +=  '<td>'+packagebundle.tax_price+'</td>';
                service_options +=  '<td>'+packagebundle.tax_including_price+'</td>';

                service_options += '</tr>';


                Object.values(packageservices).forEach(function (packageservice) {

                    if(packageservice.package_bundle_id == packagebundle.id ) {
                        if (packageservice.is_consumed == '0') {
                            let consume = 'NO';
                        } else {
                            let consume = 'YES';
                        }

                        service_options += '<tr class="'+packagebundle.id+'" style="display: none">';
                        service_options += '<td></td>';
                        service_options += '<td>'+packageservice.service.name+'</td>';
                        service_options += '<td>Amount : '+packageservice.tax_exclusive_price+'</td>';
                        service_options += '<td>Tax % : '+packageservice.tax_percenatage+'</td>';
                        service_options += '<td>Tax Amt. : '+packageservice.tax_including_price+'</td>';
                        service_options += '<td colspan="4">Is Consumed : '+consume+'</td>';
                        service_options += '</tr>';
                    }

                });
            });
        }

        $(".display_plans").html(service_options);

        $(".plan_history").html(history_options);

        $(".package_total_price").text(Invoiceinfo?.total_price);
        $("#user_name").text(Invoiceinfo?.name);
        $(".patient_print").attr("href", route('admin.packages.package_pdf', {id: Invoiceinfo?.id}))
        $("#location_name").text(location_info?.name);


    } catch (error) {
        showException(error);
    }

}

// Display invoice data - copied from main invoices module (invoices.js)
function setInvoiceDisplayData(response) {

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
        $("#modal_invoice_display .rota-title").text(modalTitle);

        $("#modal_invoice_display #invoice-pdf").attr("href", route('admin.invoices.invoice_pdf', Invoiceinfo.invoice_id))

        let image = asset_url + 'assets/media/logos/' + location_info.image_src;

        $("#modal_invoice_display .invoice-image").attr('src', image);
        $("#modal_invoice_display #invoice_info_created_at").text(Invoiceinfo.created_at);
        $("#modal_invoice_display #invoice_info_id").text(Invoiceinfo.id);

        $("#modal_invoice_display #client_id").text("C-" + patient.id);
        $("#modal_invoice_display #client_name").text(patient.name);

        if(patient.email != "" && patient.email != null){
            $("#modal_invoice_display #client_email_li").show();
            $("#modal_invoice_display #client_email").text(patient.email);
        }else{
            $("#modal_invoice_display #client_email_li").hide();
        }

        $("#modal_invoice_display #company_name").text(account.name)
        $("#modal_invoice_display #contact_no").text(company_phone_number.data)
        $("#modal_invoice_display #company_email").text(account.email)
        $("#modal_invoice_display #clinic_contact").text(account.contact)
        $("#modal_invoice_display #clinic_name").text(location_info.name)
        $("#modal_invoice_display #clinic_address").text(location_info.address)
        $("#modal_invoice_display #clinic_ntn").text(location_info.ntn)
        $("#modal_invoice_display #clinic_stn").text(location_info.stn)


        $("#modal_invoice_display #service_name").html(service?.name ?? '-');
        let service_price = getInvoiceServicePrice(Invoiceinfo);
        $("#modal_invoice_display #service_price").html(service_price ?? '-');

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

        $("#modal_invoice_display #invoice_subtotal").html(subtotal);
        $("#modal_invoice_display #discount_price").html(discount_price);
        $("#modal_invoice_display #discount_type").html(discount_type);
        $("#modal_invoice_display #discount_name").html(discount_name);

        $("#modal_invoice_display #invoice_tax").html(Invoiceinfo?.tax_percenatage ?? '-');
        $("#modal_invoice_display #invoice_tax_price").html(Invoiceinfo?.tax_price ?? '-');
        $("#modal_invoice_display #total_price").html(Invoiceinfo?.tax_including_price ?? '-');
        $("#modal_invoice_display #grand_total_price").html(Invoiceinfo?.tax_including_price ?? '-');

    } catch (error) {
        showException(error);
    }

}

// Get service price - copied from main invoices module
function getInvoiceServicePrice(Invoiceinfo) {

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

function viewInvoiceSmsLogs($route) {

    $("#modal_invoice_sms_logs").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setInvoiceSmsLogs(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });

}

function setInvoiceSmsLogs(response) {

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

        $("#sms_invoice_log_rows").html(rows);

    } catch (error) {
        showException(error);
    }

}


function cancleRow(url) {

    swal.fire({
        title: 'Are you sure you want to cancle?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {
        if (result.value) {

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: url,
                type: "Post",
                cache: false,
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                        patientDatatable['.invoice-form'].search({ datatable_reload: 'reload' }, 'search');
                    } else {
                        toastr.error(response.message);
                    }

                },
                error: function (xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                }
            });

        }
    });


}

/*Actions*/

function applyFilters(datatable) {

    $('#invoice-search').on('click', function() {

        let filters =  {
            delete: '',
            invoice_status_id: $("#search_invoice_status_id").val(),
            location_id: $("#search_location_id").val(),
            service_id: $("#search_service_id").val(),
            created_from: $("#invoice_search_created_from").val(),
            created_to: $("#invoice_search_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $(".page-invoice-form").find('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            invoice_status_id: '',
            location_id: '',
            service_id: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {
        let services = filter_values.services;
        let locations = filter_values.locations;
        let invoicestatus = filter_values.invoicestatus;

        let service_options = '<option value="">Select Service</option>';

        if (services) {
            Object.values(services).forEach(function (service) {
                if (typeof service.id !== 'undefined') {
                    service_options += '<option value="'+service.id+'">'+service.name+'</option>';
                }
            });
        }

        let location_options = '<option value="">Select Location</option>';

        if (locations) {
            Object.entries(locations).forEach(function (location) {
                location_options += '<option value="'+location[0]+'">'+location[1]+'</option>';
            });
        }

        let invoice_options = '<option value="">Select Invoice Status</option>';

        if (invoicestatus) {
            Object.entries(invoicestatus).forEach(function (invoice_status) {
                invoice_options += '<option value="'+invoice_status[0]+'">'+invoice_status[1]+'</option>';
            });
        }

        $("#search_service_id").html(service_options);
        $("#search_location_id").html(location_options);
        $("#search_invoice_status_id").html(invoice_options);

        $("#search_invoice_status_id").val(active_filters.invoice_status_id);
        $("#search_location_id").val(active_filters.location_id);
        $("#search_service_id").val(active_filters.service_id);
        $("#invoice_search_created_from").val(active_filters.created_from);
        $("#invoice_search_created_to").val(active_filters.created_to);

    } catch (error) {
        showException(error);
    }
}


$(document).ready(function () {

    /*function for final package advances information save*/
    $(document).on("click", "#AddAmount_1", function () {

        $('#inputFieldMessage').hide();

        var patient_id = $('#patient_id_1').val();
        var package_id = $('#add_package_id').val();
        var total_price = $('#add_finance_total_price').val();
        var cash_total_amount = $('#add_finance_cash_receive').val();
        var payment_mode_id = $('#add_payment_mode').val();
        var cash_amount = $('#add_finance_cash_amount').val();

        if (patient_id && package_id && payment_mode_id && payment_mode_id && cash_amount) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'post',
                url: route('admin.finances.savepackagesadvances'),
                data: {
                    'patient_id': patient_id,
                    'package_id': package_id,
                    'total_price': total_price,
                    'cash_total_amount': cash_total_amount,
                    'payment_mode_id': payment_mode_id,
                    'cash_amount': cash_amount,
                },
                success: function (response) {

                    if (response.status) {
                        toastr.success(response.message);
                        $("#modal_add_finance_form").modal("hide");
                        patientDatatable['.finance-form'].search({ datatable_reload: 'reload' }, 'search');
                        $("#add-finance-form")[0].reset();
                    } else {
                        toastr.error(response.message);
                    }
                }
            });
        } else {
            $('#inputFieldMessage').show();
        }
    });
    /*End*/

});
