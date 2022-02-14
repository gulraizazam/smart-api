var table_url = route('admin.leads.datatable');

var table_columns = [{
    field: 'id',
    sortable: false,
    width: 'auto',
    title: renderCheckbox(),
    template: function(data) {
        return childCheckbox(data);
    }
}, {
    field: 'PatientId',
    title: 'ID',
    sortable: false,
    width: 'auto',
}, {
    field: 'name',
    title: 'Full Name',
    sortable: false,
    width: 'auto',
}, {
    field: 'phone',
    title: 'Phone',
    sortable: false,
    width: 'auto',
    template: function (data) {
        let phone = data.phone;
        return '<a href="javascript:void(0);" class="clipboard" data-toggle="tooltip" title="" data-clipboard-text="'+phone+'" data-original-title="Click to Copy" aria-describedby="tooltip'+data.id+'">'+phone+'</a>';
    }
}, {
    field: 'city_id',
    title: 'City',
    sortable: false,
    width: 'auto',
    template: function (data) {
        let city_id = data.city_id;
        let city = '<span class="text text-danger">Empty</span>';
        if (city_id != '') {
            city = city_id;
        }
        return '<a href="javascript:void(0);" onclick="editInline(`' + data.id + '`)" class="lead_city" id="lead-'+data.id+'">'+city+'</a>';
    }
}, {
    field: 'region_id',
    title: 'Region',
    sortable: false,
    width: 'auto',
}, {
    field: 'lead_status_id',
    title: 'Lead Status',
    sortable: false,
    width: 'auto',
}, {
    field: 'service_id',
    title: 'Service',
    sortable: false,
    width: 'auto',
}, {
    field: 'created_at',
    title: 'Created At',
    sortable: false,
    width: 'auto',
}, {
    field: 'created_by',
    title: 'Created By',
    sortable: false,
    width: 'auto',
}, {
    field: 'status',
    title: 'Status',
    sortable: false,
    width: 'auto',
    template: function(data) {
        let status_url = route('admin.leads.status');
        return statuses(data, status_url);
    }
}, {
    field: 'actions',
    title: 'Actions',
    sortable: false,
    width: 80,
    overflow: 'visible',
    autoHide: false,
    template: function(data) {
        return actions(data);
    }
}];

function actions(data) {

    if (typeof data.id !== 'undefined') {
        let id = data.id;


        let edit_url = route('admin.packages.edit', { id: id });
        let display_url = route('admin.packages.display', { id: id });
        let delete_url = route('admin.packages.destroy', { id: id });
        let sms_log_url = route('admin.packages.sms_logs', { id: id });
        let log_url = route('admin.packages.log', { id: id, type: 'web' });

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
                        <a href="' + log_url + '" class="navi-link">\
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

function createLead(url) {

    $('#msg_new_patient').hide();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            $("#modal_edit_regions").modal("show");

            setLeadData(response);

        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(Validation);
        }
    });

}

function setLeadData(response) {

    let Services = response.data.Services;
    let cities = response.data.cities;
    let employees = response.data.employees;
    let gender = response.data.gender;
    let leadServices = response.data.leadServices;
    let lead_sources = response.data.lead_sources;
    let lead_statuses = response.data.lead_statuses;

    let service_options = '<option value="">Select Service</option>';
    let city_options = '<option value="">Select a City</option>';
    let employee_options = '<option value="">Select a Referrer</option>';
    let gender_options = '<option value="">Select a Gender</option>';
    let lead_sources_options = '<option value="">Select a Lead Sources</option>';
    let lead_statuses_options = '<option value="">Select a Lead Status</option>';

    if (Services) {
        Object.entries(Services).forEach(function(service) {
            service_options += '<option value="' + service[0] + '">' + service[1] + '</option>';
        });
    }

    if (cities) {
        Object.entries(cities).forEach(function(city) {
            city_options += '<option value="' + city[0] + '">' + city[1] + '</option>';
        });
    }

    if (employees) {
        Object.entries(employees).forEach(function(employee) {
            employee_options += '<option value="' + employee[0] + '">' + employee[1] + '</option>';
        });
    }

    if (gender) {
        Object.entries(gender).forEach(function(gender) {
            gender_options += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
        });
    }

    if (lead_sources) {
        Object.entries(lead_sources).forEach(function(source) {
            lead_sources_options += '<option value="' + source[0] + '">' + source[1] + '</option>';
        });
    }

    if (lead_statuses) {
        Object.entries(lead_statuses).forEach(function(status) {
            lead_statuses_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
        });
    }

    $("#add_service_id").html(service_options);
    $("#add_city_id").html(city_options);
    $("#add_referred_by_id").html(employee_options);
    $("#add_gender_id").html(gender_options);
    $("#add_lead_source_id").html(lead_sources_options);
    $("#add_lead_status_id").html(lead_statuses_options);


}

function editRow(url) {

    $("#modal_edit_plan").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            $("#modal_edit_regions").modal("show");
            setEditData(response);

        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            // reInitValidation(Validation);
        }
    });


}

function setEditData(response) {

    try {

        let appointmentArray = response.data.appointmentArray;
        let end_previous_date = response.data.end_previous_date;
        let grand_total = response.data.grand_total;
        let locationhasservice = response.data.locationhasservice;
        let locations = response.data.locations;
        let package = response.data.package;
        let packageadvances = response.data.packageadvances;
        let packagebundles = response.data.packagebundles;
        let packageservices = response.data.packageservices;
        let paymentmodes = response.data.paymentmodes;
        let range = response.data.range;
        let total_price = response.data.total_price;

        let history_options = noRecordFoundTable(4);

        if (packageadvances.length) {

            history_options = noRecordFoundTable(4);
            Object.values(packageadvances).forEach(function(packageadvance) {

                if (packageadvance.cash_amount != '0' && packageadvance.is_tax == 0) {
                    history_options += '<tr>';
                    history_options += '<td>' + packageadvance.paymentmode.name + '</td>';
                    history_options += '<td>' + packageadvance.cash_flow + '</td>';
                    history_options += '<td>' + packageadvance.package_refund_price + '</td>';
                    history_options += '<td>' + packageadvance.created_at_formated + '</td>';
                    history_options += '<tr>';
                }
            });
        }


        let service_options = noRecordFoundTable(9);

        if (packagebundles.lengths) {
            service_options = noRecordFoundTable(9);
            Object.values(packagebundles).forEach(function(packagebundle) {
                service_options += '<tr>';
                service_options += '<td><a href="javascript:void(0);" onclick="toggle(' + packagebundle.id + ')">' + packagebundle.bundle.name + '</a></td>';
                service_options += '<td>' + packagebundle.service_price.toFixed(2) + '</td>';
                service_options += '<td>';
                if (packagebundle.discount_id == null) {
                    service_options += '-';
                } else if (packagebundle.discount_name) {
                    service_options += packagebundle.discount_name;
                } else {
                    service_options += packagebundle.discount.name;
                }
                service_options += '</td>';

                service_options += '<td>';
                if (packagebundle.discount_type == null) {
                    service_options += '-';
                } else {
                    service_options += packagebundle.discount_type;
                }
                service_options += '</td>';

                service_options += '<td>';

                if (packagebundle.discount_price == null) {
                    service_options += '0.00';
                } else {
                    service_options += packagebundle.discount_price;
                }
                service_options += '</td>';

                service_options += '<td>' + packagebundle.tax_exclusive_net_amount + '</td>';
                service_options += '<td>' + packagebundle.tax_percenatage + '</td>';
                service_options += '<td>' + packagebundle.tax_price + '</td>';
                service_options += '<td>' + packagebundle.tax_including_price + '</td>';

                service_options += '</tr>';


                Object.values(packageservices).forEach(function(packageservice) {

                    if (packageservice.package_bundle_id == packagebundle.id) {
                        if (packageservice.is_consumed == '0') {
                            let consume = 'NO';
                        } else {
                            let consume = 'YES';
                        }

                        service_options += '<tr class="' + packagebundle.id + '" style="display: none">';
                        service_options += '<td></td>';
                        service_options += '<td>' + packageservice.service.name + '</td>';
                        service_options += '<td>Amount : ' + packageservice.tax_exclusive_price + '</td>';
                        service_options += '<td>Tax % : ' + packageservice.tax_percenatage + '</td>';
                        service_options += '<td>Tax Amt. : ' + packageservice.tax_including_price + '</td>';
                        service_options += '<td colspan="4">Is Consumed : ' + consume + '</td>';
                        service_options += '</tr>';
                    }

                });
            });
        }

        $(".display_plans").html(service_options);



        $(".plan_history").html(history_options);

        $(".package_total_price").text(package.total_price);
        $("#user_name").text(package?.user?.name)
        $("#location_name").text(package?.location?.name)


    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters = {
            delete: '',
            id: $("#search_id").val(),
            patient_id: $("#search_patient_id").val(),
            patient_name: $("#search_patient_id").text(),
            package_id: $("#search_plan_id").text(),
            location_id: $("#search_location_id").val(),
            service_id: $("#search_service_id").val(),
            invoice_status_id: $("#search_invoice_status_id").val(),
            appointment_type_id: $("#search_appointment_type_id").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters = {
            delete: '',
            id: '',
            patient_id: '',
            package_id: '',
            location_id: '',
            service_id: '',
            invoice_status_id: '',
            appointment_type_id: '',
            created_from: '',
            created_to: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let locations = filter_values.locations;
        let packages = filter_values.package;
        let patients = filter_values.patient;
        let status = filter_values.status;

        let location_options = '<option value="">All</option>';
        let package_options = '<option value="">All</option>';
        let status_options = '<option value="">All</option>';

        if (locations) {
            Object.entries(locations).forEach(function(value) {
                location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
            });
        }

        if (packages) {
            Object.entries(packages).forEach(function(value) {
                package_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
            });
        }

        if (status) {
            Object.entries(status).forEach(function(value) {
                status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
            });
        }

        $("#search_plan_id").html(package_options);
        $("#search_location_id").html(location_options);
        $("#search_status").html(status_options);

        $("#search_id").val(active_filters.id);

        if (active_filters.patient_name !== 'undefined' && active_filters.patient_name != 'undefined') {
            $("#search_patient_id").html('<option value="' + active_filters.patient_id + '">' + active_filters.patient_name + '</option>');
            $("#search_patient_id").val(active_filters.patient_id);
        }

        $("#search_plan_id").val(active_filters.package_id);
        $("#search_location_id").val(active_filters.location_id);
        $("#search_status").val(active_filters.status);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);

        hideShowAdvanceFilters(active_filters);

    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '') ||
        (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '') ||
        (typeof active_filters.status !== 'undefined' && active_filters.status != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}

function newPatient() {

    $('#new_patient').change(function () {
        if ($(this).is(":checked")) {
            $('#new_patient').val('1');
            $('#msg_new_patient').show();
        } else {
            $('#new_patient').val('0');
            $('#msg_new_patient').hide();
        }
    });
}

function editInline($lead_id) {

    $("#lead-" + $lead_id).append('');

}
