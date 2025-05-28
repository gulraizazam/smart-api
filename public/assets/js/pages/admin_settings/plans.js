
$(document).ready(function () {

    $("#add_patient_id").on("select2:select", function (e) {
        getAppointments();
    });


    /*save data for both predefined discounts and keyup trigger*/
    $("#AddPackage").click(function () {
        showSpinner();

        $('#wrongMessage').hide();
        $('#inputfieldMessage').hide();
        $('#percentageMessage').hide();
        $('#AlreadyExitMessage').hide();
        $(this).attr("disabled", true);
        var random_id = $('#random_id_1').val();
        var service_id = $('#service_id_1').val(); //Basicailly it is bundle id
        var discount_id = $('#discount_id_1').val();
        var net_amount = $('#net_amount_1').val();
        var discount_type = $('#discount_type_1').val();
        var discount_price = $('#discount_value_1').val();
        var discount_slug = $("#slug_1").val();
        var package_total = $('#package_total_1').val();

        var is_exclusive = $('#is_exclusive').val();
        var location_id = $('#location_id_1').val();

        if (service_id && net_amount && location_id) {
            if (discount_slug == 'custom') {
                if (discount_price == '') {
                    $('#inputfieldMessage').show();
                    return false;
                }
                if (discount_type == 'Percentage') {
                    if (discount_price > 100) {
                        $('#percentageMessage').show();
                        return false;
                    }
                }
            }

            var formData = {
                'random_id': random_id,
                'bundle_id': service_id, //Basicailly it is bundle id
                'discount_id': discount_id,
                'net_amount': net_amount,
                'discount_type': discount_type,
                'discount_price': discount_price,
                'package_total': package_total,
                'is_exclusive': is_exclusive,
                'location_id': location_id,
                'package_bundles[]': []
            };

            $(".package_bundles").each(function () {
                formData['package_bundles[]'].push($(this).val());
            });

            $.ajax({
                type: 'get',
                url: route('admin.packages.savepackages_service'),
                data: formData,
                success: function (resposne) {
                    let consume = 'NO';
                    if (resposne.status == '1') {

                        $('#table_1').append("" +
                            "<tr id='table_1' class='HR_" + random_id + " HR_" + resposne.myarray.record.id + "'>" +
                            "<td><a href='javascript:void(0)' onClick='toggle(" + resposne.myarray.record.id + ")'>" + resposne.myarray.service_name + "</a></td>" +
                            "<td>" + resposne.myarray.service_price.toLocaleString() + "</td>" +
                            "<td>" + resposne.myarray.discount_name + "</td>" +
                            "<td>" + resposne.myarray.discount_type + "</td>" +
                            "<td>" + resposne.myarray.discount_price + "</td>" +
                            "<td>" + resposne.myarray.record.tax_exclusive_net_amount.toLocaleString() + "</td>" +
                            "<td>" + resposne.myarray.record.tax_percenatage + "</td>" +
                            "<td>" + resposne.myarray.record.tax_including_price.toLocaleString() + "</td>" +
                            "<td>" +
                            "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + resposne.myarray.record.id + "' />" +
                            "<button class='btn btn-xs btn-danger' onClick='deleteModel(" + resposne.myarray.record.id + ")'>Delete</button>" +
                            "</td>" +
                            "</tr>");

                        jQuery.each(resposne.myarray.record_detail, function (i, record_detail) {
                            if (record_detail.is_consumed == '0') {
                                consume = 'NO';
                            } else {
                                consume = 'YES';
                            }
                            $('#table_1').append("<tr class='inner_records_hr HR_" + resposne.myarray.record.id + " " + resposne.myarray.record.id + "'><td></td><td>" + record_detail.name + "</td><td>Amount : " + record_detail.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + record_detail.tax_percenatage + "</td><td>Tax Amt. : " + record_detail.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                        });
                        toggle(resposne.myarray.record.id);

                        $("#package_total_1").val(resposne.myarray.total);

                        keyfunction_grandtotal();

                        var rows = $('#table_1 tbody tr').length;

                        if (rows >= 3) {
                            $("#location_id_1").prop("disabled", true);
                        }
                        /*we enable add button after all functionality enable*/
                        $('#AddPackage_1').attr("disabled", false);

                    } else {
                        $('#AlreadyExitMessage').show();
                        $('#AddPackage_1').attr("disabled", false);
                    }

                    hideSpinnerRestForm();
                }
            });
        } else {
            hideSpinnerRestForm();
            // toastr.error('Please fill out the required fields.')
            $('#inputfieldMessage').show();
            $(this).attr("disabled", false);
        }
    });
    /*End*/

});

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
    }, {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 300,
    }, {
        field: 'name',
        title: 'Patient',
        sortable: false,
        width: 'auto',
    }, {
        field: 'name',
        title: 'Plans',
        sortable: false,
        width: 'auto',
    }, {
        field: 'location_id',
        title: 'Centres',
        sortable: false,
        width: 'auto',
    }, {
        field: 'session_count',
        title: 'Session count',
        sortable: false,
        width: 'auto',
    }, {
        field: 'total',
        title: 'Total',
        sortable: false,
        width: 'auto',
    }, {
        field: 'cash_receive',
        title: 'Cash receive',
        sortable: false,
        width: 'auto',
    }, {
        field: 'settle_amount',
        title: 'Settle Amount',
        sortable: false,
        width: 'auto',
    }, {
        field: 'refund',
        title: 'Refund',
        sortable: false,
        width: 'auto',
    }, {
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    }, {
        field: 'status',
        title: 'Status',
        sortable: false,
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.packages.status');
            return statuses(data, status_url);
        }
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


        let edit_url = route('admin.packages.edit', { id: id });
        let display_url = route('admin.packages.display', { id: id });
        let delete_url = route('admin.packages.destroy', { id: id });
        let sms_log_url = route('admin.packages.sms_logs', { id: id });
        let log_url = route('admin.packages.log', { id: id, type: 'web' });

        if (permissions.create || permissions.log || permissions.sms_log || permissions.edit) {
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
                        <a href="'+ log_url + '" class="navi-link">\
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

function createPlan(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
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

    let location_options = '<option value="">Select Centre</option>';

    if (locations) {
        Object.entries(locations).forEach(function (location) {
            location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
        });
    }

    $("#add_location_id").html(location_options);

    let discount_options = '<option value="">Select Discount</option>';

    if (discounts) {
        Object.values(discounts).forEach(function (discount) {
            discount_options += '<option value="' + discount.id + '">' + discount.name + '</option>';
        });
    }

    $("#add_discount_id").html(discount_options);

    let discount_type_options = '<option value="">Select Discount Type</option>';

    if (discount_types) {
        Object.entries(discount_types).forEach(function (discount_type) {
            console.log(discount_type)
            discount_type_options += '<option value="' + discount_type[0] + '">' + discount_type[1] + '</option>';
        });
    }

    $("#add_discount_type").html(discount_type_options);


}

function getServices() {

    let location = $("#add_location_id").val();

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
            service_options += '<option value="' + value.id + '"> ' + value.name + ' </option>';
        });

        $("#add_service_id").html(service_options);

    } catch (error) {
        showException(error);
    }
}

function getAppointments() {
    let location = $("#add_location_id").val();
    let patient = $("#add_patient_id").val();

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
                appointment_options += '<option value="' + value.id + '"> ' + value.name + ' </option>';
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

        let history_options = noRecordFoundTable(4);

        if (packageadvances.length) {

            history_options = noRecordFoundTable(4);
            Object.values(packageadvances).forEach(function (packageadvance) {

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

        if (packagebundles.length) {
            service_options = noRecordFoundTable(9);
            Object.values(packagebundles).forEach(function (packagebundle) {
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


                Object.values(packageservices).forEach(function (packageservice) {
                    let consume = 'NO';
                    if (packageservice.package_bundle_id == packagebundle.id) {
                        if (packageservice.is_consumed == '0') {
                            consume = 'NO';
                        } else {
                            consume = 'YES';
                        }

                        service_options += '<tr class="' + packagebundle.id + '" style="display: none">';
                        service_options += '<td></td>';
                        service_options += '<td>' + packageservice.service.name + '</td>';
                        service_options += '<td>Amount : ' + packageservice.tax_exclusive_price + '</td>';
                        service_options += '<td>Tax % : ' + packageservice.tax_percenatage + '</td>';
                        service_options += '<td>Tax Amt. : ' + packageservice.tax_including_price + '</td>';
                        service_options += '<td colspan="2">Is Consumed : ' + consume + '</td>';
                       service_options += '<td colspan="2">Consumed At: ' + (packageservice.consumed_at ?? 'N/A') + '</td>';

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

        let history_options = noRecordFoundTable(4);

        if (packageadvances.length) {

            history_options = noRecordFoundTable(4);
            Object.values(packageadvances).forEach(function (packageadvance) {

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

        if (packagebundles.length) {
            service_options = noRecordFoundTable(9);
            Object.values(packagebundles).forEach(function (packagebundle) {
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


                Object.values(packageservices).forEach(function (packageservice) {
                    let consume = 'NO';
                    if (packageservice.package_bundle_id == packagebundle.id) {
                        if (packageservice.is_consumed == '0') {
                            consume = 'NO';
                        } else {
                            consume = 'YES';
                        }

                        service_options += '<tr class="' + packagebundle.id + '" style="display: none">';
                        service_options += '<td></td>';
                        service_options += '<td>' + packageservice.service.name + '</td>';
                        service_options += '<td>Amount : ' + packageservice.tax_exclusive_price + '</td>';
                        service_options += '<td>Tax % : ' + packageservice.tax_percenatage + '</td>';
                        service_options += '<td>Tax Amt. : ' + packageservice.tax_including_price + '</td>';
                        service_options += '<td colspan="2">Is Consumed : ' + consume + '</td>';
                        service_options += '<td colspan="2">Consumed At: ' + (packageservice.consumed_at ?? 'N/A') + '</td>';

                        service_options += '</tr>';
                    }

                });
            });
        }

        $(".display_plans").html(service_options);



        $(".plan_history").html(history_options);

        $(".package_total_price").text(package.total_price);
        $("#user_name").text(package.user.name)
        $("#location_name").text(package.location.name)


    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function () {

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

    $('#reset-filters').on('click', function () {
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

function resetCustomFilters() {

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
        let status_options = '<option value="">All</option>';

        if (locations) {
            Object.entries(locations).forEach(function (value) {
                location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
            });
        }

        if (packages) {
            Object.entries(packages).forEach(function (value) {
                package_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
            });
        }

        if (status) {
            Object.entries(status).forEach(function (value) {
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

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}
