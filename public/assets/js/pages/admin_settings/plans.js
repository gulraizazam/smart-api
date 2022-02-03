

var table_url = route('admin.packages.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    },{
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 300,
    },{
        field: 'name',
        title: 'Patient',
        sortable: false,
        width: 'auto',
    },{
        field: 'name',
        title: 'Plans',
        sortable: false,
        width: 'auto',
    },{
        field: 'location_id',
        title: 'Centres',
        sortable: false,
        width: 'auto',
    },{
        field: 'session_count',
        title: 'Session count',
        sortable: false,
        width: 'auto',
    },{
        field: 'total',
        title: 'Total',
        sortable: false,
        width: 'auto',
    },{
        field: 'cash_receive',
        title: 'Cash receive',
        sortable: false,
        width: 'auto',
    },{
        field: 'settle_amount',
        title: 'Settle Amount',
        sortable: false,
        width: 'auto',
    },{
        field: 'refund',
        title: 'Refund',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    },{
        field: 'status',
        title: 'Status',
        sortable: false,
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.packages.status');
            return statuses(data, status_url);
        }
    },{
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


        let edit_url = route('admin.packages.edit', {id: id});
        let display_url = route('admin.packages.display', {id: id});
        let delete_url = route('admin.packages.destroy', {id: id});
        let sms_log_url = route('admin.packages.sms_logs', {id: id});
        let log_url = route('admin.packages.log', {id: id, type:  'web'});

        if (permissions.create && permissions.log && permissions.sms_log && permissions.edit) {
            let actions = '<div class="dropdown dropdown-inline action-dots">\
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';

            if (permissions.edit) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
            }

            if (permissions.delete) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Delete</span>\
                        </a>\
                     </li>';
            }

            if (permissions.create) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="viewPlan(`' + display_url + '`);" class="navi-link">\
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

function editRow(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_regions").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(TownValidation);
        }
    });


}

function setEditData(response) {
    let region = response.data;
    let action = route('admin.regions.update', {id: region.id});
    $("#modal_edit_regions_form").attr("action", action);

    $("#edit_regions_name").val(region.name);

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
        let rows = '<tr><td colspan="5" class="text-center">No SMS log found.</td></tr>';
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

function viewPlan($route) {

    $("#modal_display").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setPlanData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });

}

function setPlanData(response) {

    try {

        let grand_total = response.data.grand_total;
        let discount = response.data.discount;
        let package = response.data.package;
        let packageadvances = response.data.packageadvances;
        let packagebundles = response.data.packagebundles;
        let packageservices = response.data.packageservices;
        let paymentmodes = response.data.paymentmodes;
        let services = response.data.services;

        $("#user_name").text(package.user.name);
        $("#location_name").text(package.location.name);
        $(".package_total_price").text(package?.total_price ?? 0);

        let package_rows = noRecordFoundTable(9);
        Object.values(packagebundles).forEach(function (package_bundle) {
            package_rows += '<tr> ' +
                '<td><a href="javascript:void(0);" onclick="toggle(' + package_bundle.id + ')">' +
                package_bundle.bundle.name + '</a> </td>' +
                '<td>' + package_bundle.service_price + '</td>' +
                '<td> ';
            if (package_bundle.discount_id == null) {
                package_rows += '-';
            } else if (package_bundle.discount_name) {
                package_rows += package_bundle.discount_name;
            } else {
                package_rows += package_bundle.discount.name;
            }

            package_rows += '</td>' +
                '<td>';
            if (package_rows.discount_type == null) {
                package_rows += '-';
            } else {
                package_rows += package_bundle.discount_type;
            }
            package_rows += '</td>';
            '<td>';
            if (package_bundle.discount_price == null) {
                package_rows += '0.00';
            } else {
                package_rows += packagebundles.discount_price;
            }
            package_rows += '</td><td>' + packagebundles.tax_exclusive_net_amount + '</td>' +
                '<td>' + packagebundles.tax_percenatage + '</td>' +
                '<td>' + packagebundles.tax_price + '</td>' +
                '<td>' + packagebundles.tax_including_price + '</td>' +
                '</tr>';


            Object.values(packageservices).forEach(function () {
                if (packageservice.package_bundle_id == packagebundles.id) {
                    if (packageservice.is_consumed == '0') {
                        let consume = 'NO';
                    } else {
                        let consume = 'YES';
                    }
                    package_rows += '<tr class="' + packagebundles.id + '" style="display: none"><td>' +
                        '</td><td>' + packageservice.service.name + '</td>' +
                        '<td>Amount : ' + packageservice.tax_exclusive_price + '</td>' +
                        '<td>Tax % : ' + packageservice.tax_percenatage + '</td>' +
                        '<td>Tax Amt. : ' + packageservice.tax_including_price + '</td>' +
                        '<td colspan="4">Is Consumed : ' + consume + '</td></tr>';
                }
            });
        });

        $(".display_plans").html(package_rows);

        let packageadvances_rows = noRecordFoundTable(4);;
        if (packageadvances.length) {

            Object.values(packageadvances).forEach(function (packageadvance) {

                if (packageadvance.cash_amount != '0' && packageadvance.is_tax == 0) {
                    packageadvances_rows += '<tr> ' +
                    '<td>'+packageadvance.paymentmode.name+'</td>'+
                    '<td>'+packageadvance.cash_flow+'</td>';
                    if(packageadvance.cash_flow == 'out' && packageadvance.is_tax == 0) {
                        packageadvances_rows += '<td>'+packageadvance.package_refund_price+'</td>';
                    } else if(packageadvance.is_tax == 0) {
                        packageadvances_rows += '<td class="cash-amount">'+packageadvance.cash_amount+'</td>';
                    }

                    packageadvances_rows += '<td>'+packageadvance.created_at_formated+'</td>';
                '</tr>';
                }

            });

        $(".package_advances").html(packageadvances_rows);
    }


        return false;
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


        $("#service_name").html(service?.name ?? '-');
        let service_price = getServicePrice(Invoiceinfo);
        $("#service_price").html(service_price ?? '-');

        let discount_name = '-';
        if (discount != null) {
            discount_name = discount.name
        }

        let discount_type = '-';
        if (discount != null) {
            discount_type = discount.discount_type;
        }

        let discount_price = 0;
        if (discount != null) {
            discount_price = discount.discount_price;
        }

        let subtotal = 0;
        if(Invoiceinfo.is_exclusive == '0') {
            if(Invoiceinfo.discount_price == null && $bundle.type == 'single') {
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

        let locations = filter_values.locations;
        let packages = filter_values.package;
        let patients = filter_values.patient;
        let status = filter_values.status;

        let location_options = '<option value="">All</option>';
        let package_options = '<option value="">All</option>';
        let patient_options = '<option value="">All</option>';
        let status_options = '<option value="">All</option>';

        Object.entries(locations).forEach( function (value) {
            location_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(packages).forEach( function (value) {
            package_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(status).forEach( function (value) {
            status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(patients).forEach( function (value) {
            patient_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        $("#search_patient_id").html(patient_options);
        $("#search_plan_id").html(package_options);
        $("#search_location_id").html(location_options);
        $("#search_status").html(status_options);

        hideShowAdvanceFilters(active_filters);

    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.invoice_status_id !== 'undefined' && active_filters.invoice_status_id != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}
