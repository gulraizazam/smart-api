
var table_url = route('admin.plans.datatable', {id: patient_id});

var table_columns = [
    {
        field: 'patient_id',
        title: 'Patient ID',
        width: 'auto',
    },{
        field: 'package_id',
        title: 'Name',
        width: 'auto',
    },{
        field: 'location_id',
        title: 'Centres',
        width: 'auto',
        sortable: false,
    },{
        field: 'session_count',
        title: 'Session count',
        width: 'auto',
        sortable: false,
    },{
        field: 'total',
        title: 'Total',
        width: 'auto',
        sortable: false,
    },{
        field: 'cash_receive',
        title: 'Cash receive',
        width: 'auto',
        sortable: false,
    },{
        field: 'refund',
        title: 'Refund',
        width: 'auto',
        sortable: false,
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.date)
        }
    },{
        field: 'status',
        title: 'status',
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.plans.status');
            return statuses(data, status_url);
        }
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
    }];


function actions(data) {

    if (typeof data.id !== 'undefined') {

        let id = data.id;

        let edit_url = route('admin.plans.edit', {id: id});
        let delete_url = route('admin.plans.destroy', {id: id});
        let display_url = route('admin.plans.display', {id: id});
        let log_url = route('admin.plans.log', {id: id, patient_id: patient_id, type: 'web'});
        let sms_log_url = route('admin.packages.sms_logs', {id: id});

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

/*actions*/

function createPlan(id) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.plans.createplan', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_regions").modal("show");

            setPlanData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(Validation);
        }
    });

    getServices();

}

function setPlanData(response) {

    let locations = response.data.locations
    let discounts = response.data.discounts;
    let discount_types = response.data.discount_type;
    let paymentmodes = response.data.paymentmodes;

    let location_options = '<option value="">Select Centre</option>';

    if (locations) {
        Object.entries(locations).forEach( function(location) {
            location_options += '<option value="'+location[0]+'">'+location[1]+'</option>';
        });
    }

    $("#add_location_id").html(location_options);

    let discount_options = '<option value="">Select Discount</option>';

    if (discounts) {
        Object.values(discounts).forEach( function(discount) {
            discount_options += '<option value="'+discount.id+'">'+discount.name+'</option>';
        });
    }

    $("#add_discount_id").html(discount_options);

    let discount_type_options = '<option value="">Select Discount Type</option>';

    if (discount_types) {
        Object.entries(discount_types).forEach( function(discount_type) {
            discount_type_options += '<option value="'+discount_type[0]+'">'+discount_type[1]+'</option>';
        });
    }

    $("#add_discount_type").html(discount_type_options);


    let payment_mode_options = '<option value="">Select Payment Mode</option>';

    if (paymentmodes) {
        Object.entries(paymentmodes).forEach( function(paymentmode) {
            payment_mode_options += '<option value="'+paymentmode[0]+'">'+paymentmode[1]+'</option>';
        });
    }

    $("#add_payment_mode_id").html(payment_mode_options);


}

function getServices(type = 'add') {

    let location = $("#" +type+"_location_id").val();

    let url = route('admin.packages.getservice');
    if (location != '') {
        url = route('admin.packages.getservice', {
            _query: {
                location_id: location
            }
        });
    }

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_regions").modal("show");

            setServices(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

    getAppointments();
}

function setServices(response) {

    try {

        let services = response.data.service;
        let service_options = '<option value=""> Select Service </option>';

        Object.values(services).forEach(function (value) {
            service_options += '<option value="'+value.id+'"> '+value.name+' </option>';
        });

        $("#add_service_id").html(service_options);

    } catch (error) {
        showException(error);
    }
}

function getAppointments() {

    let location = $("#add_location_id").val();
    let patient = $("#add_patient_id").val();
    console.log(location, patient);

    if (location != '' && patient != '') {

        let url = route('admin.packages.getappointmentinfo', {
            _query: {
                location_id: location,
                patient_id: patient,
            }
        });

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: "GET",
            cache: false,
            success: function (response) {
                $("#modal_edit_regions").modal("show");

                setAppointments(response);

            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
                //reInitValidation(AddValidation);
            }
        });

    }

}

function setAppointments(response) {

    try {

        let appointments = response.data.appointments;
        let appointment_options = '<option value=""> Select Service </option>';

        if (appointments.length) {

            Object.values(appointments).forEach(function (value) {
                appointment_options += '<option value="'+value.id+'"> '+value.name+' </option>';
            });

            $("#add_appointment_id").html(appointment_options);

        }

    } catch (error) {
        showException(error);
    }
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
        success: function (response) {
            $("#modal_edit_regions").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
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

        $(".patientName").text(package?.user?.name);
        $(".locationName").text(package?.location?.name);

        let history_options = noRecordFoundTable(4);

        if (packageadvances.length) {

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

        if(packagebundles.length) {
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

        $(".package_total_price").text(package.total_price);
        $("#user_name").text(package?.user?.name);
        $("#location_name").text(package?.location?.name);



        let discounts = response.data.discounts;
        let discount_types = response.data.discount_type;

        let discount_options = '<option value="">Select Discount</option>';

        if (discounts) {
            Object.values(discounts).forEach( function(discount) {
                discount_options += '<option value="'+discount.id+'">'+discount.name+'</option>';
            });
        }

        $("#edit_discount_id").html(discount_options);

        let discount_type_options = '<option value="">Select Discount Type</option>';

        console.log(discount_types)
        if (discount_types) {
            Object.entries(discount_types).forEach( function(discount_type) {
                discount_type_options += '<option value="'+discount_type[0]+'">'+discount_type[1]+'</option>';
            });
        }

        $("#edit_discount_type").html(discount_type_options);


        let payment_mode_options = '<option value="">Select Payment Mode</option>';

        if (paymentmodes) {
            Object.entries(paymentmodes).forEach( function(paymentmode) {
                payment_mode_options += '<option value="'+paymentmode[0]+'">'+paymentmode[1]+'</option>';
            });
        }

        $("#edit_payment_mode_id").html(payment_mode_options);



        let location_options = '<option value="">Select Centers</option>';
        if (locations) {
            Object.values(locations).forEach(function (location) {

                location_options += '<option value="'+location.id+'">'+location.name+'</option>';
            });
        }

        $("#edit_location_id").html(location_options);

    } catch (error) {
        showException(error);
    }

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

            //reInitValidation(Validation);
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

            displayData(response);

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
        let package = response.data.package;
        let packagebundles = response.data.packagebundles;
        let packageservices = response.data.packageservices;

        $(".editPatientName").text(package?.user?.name);

        let history_options = noRecordFoundTable(4);

        if (packageadvances.length) {

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

        if(packagebundles.length) {
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

        $(".package_total_price").text(package.total_price);
        $("#user_name").text(package.user.name);
        $(".patient_print").attr("href", route('admin.packages.package_pdf', {id: package?.id}))
        $("#location_name").text(package.location.name);


    } catch (error) {
        showException(error);
    }

}

/*Actions*/

function applyFilters(datatable) {

    $('#medical-search').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#medical_search_name").val(),
            created_from: $("#medical_search_created_from").val(),
            created_to: $("#medical_search_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $(".page-medical-form").find('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        $("#search_name").val(active_filters.name);
        $("#search_patient_name").val(active_filters.patient_name);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);

    } catch (error) {
        showException(error);
    }
}
