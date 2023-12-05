
var table_url = route('admin.plans.datatable', { id: patientCardID });

var table_columns = [
    {
        field: 'name',
        title: 'Patient',
        width: 90,
    }, {
        field: 'package_id',
        title: 'Name',
        width: 70,
    }, {
        field: 'location_id',
        title: 'Centres',
        width: 'auto',
        sortable: false,
    }, {
        field: 'session_count',
        title: 'Session count',
        width: 80,
        sortable: false,
    }, {
        field: 'total',
        title: 'Total',
        width: 80,
        sortable: false,
    }, {
        field: 'cash_receive',
        title: 'Cash receive',
        width: 80,
        sortable: false,
    }, {
        field: 'refund',
        title: 'Refund',
        width: 'auto',
        sortable: false,
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.date)
        }
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.plans.status');
            return statuses(data, status_url);
        }
    }, {
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

        let edit_url = route('admin.plans.edit', { id: id });
        let delete_url = route('admin.plans.destroy', { id: id });
        let display_url = route('admin.plans.display', { id: id });
        let log_url = route('admin.plans.log', { id: id, patient_id: patientCardID, type: 'web' });
        let sms_log_url = route('admin.packages.sms_logs', { id: id });

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

/*actions*/

function createPlan(id) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.plans.createplan', { id: id }),
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_regions").modal("show");

            setPlanData(response, id);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(Validation);
        }
    });

    getServices();

}

function setPlanData(response, patient_id) {

    let locations = response.data.locations
    let discounts = response.data.discounts;
    let discount_types = response.data.discount_type;
    let paymentmodes = response.data.paymentmodes;
    let random_id = response.data.random_id;

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
            discount_type_options += '<option value="' + discount_type[0] + '">' + discount_type[1] + '</option>';
        });
    }

    $("#add_discount_type").html(discount_type_options);


    let payment_mode_options = '<option value="">Select Payment Mode</option>';

    if (paymentmodes) {
        Object.entries(paymentmodes).forEach(function (paymentmode) {
            payment_mode_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
        });
    }

    $("#add_payment_mode_id").html(payment_mode_options);

    $("#add_patient_id").val(patient_id);

    $("#random_id_1").val(random_id);

}

function getServices(type = 'add', patient_id) {

    let location = $("#" + type + "_location_id").val();

    let url = route('admin.packages.getservice');
    if (location != '') {
        url = route('admin.packages.getservice', {
            _query: {
                location_id: location,
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

    getAppointments(patient_id);
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

function getAppointments(patient_id) {

    let location = $("#add_location_id").val();

    if (patient_id != '' && patient_id != '') {

        let url = route('admin.packages.getappointmentinfo', {
            _query: {
                location_id: location,
                patient_id: patient_id,
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
        let appointment_options = '<option value=""> Select Appointment </option>';

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

        $(".patientName").text(package?.user?.name);
        $(".locationName").text(package?.location?.name);
        $("#edit_location_id").val(package?.location?.id);
        $("#edit_patient_id").val(package?.patient_id);
        $("#edit_random_id").val(package?.random_id);
        $("#edit_package_total").val(total_price);

        let history_options = noRecordFoundTable(5);

        if (packageadvances.length) {

            history_options = '';
            Object.values(packageadvances).forEach(function (packageadvance) {

                if (packageadvance.cash_amount != '0') {

                    let selector = 'history_cash_row_' + packageadvance.id;
                    history_options += '<tr id="' + selector + '">';

                    if (packageadvance.is_tax == 1 && packageadvance.cash_flow == 'out') {
                        history_options += '<td>Tax</td>';
                    } else {
                        history_options += '<td>' + packageadvance?.paymentmode?.name + '</td>';
                    }

                    history_options += '<td>' + packageadvance.cash_flow + '</td>';
                    history_options += '<td>' + packageadvance.cash_amount + '</td>';
                    history_options += '<td>' + formatDate(packageadvance.created_at, 'MMM, DD yyyy hh:mm A') + '</td>';


                    history_options += '<td>';

                    if (end_previous_date <= packageadvance?.created_at && packageadvance?.cash_flow == 'in') {
                        if (permissions.patients_plan_cash_edit) {
                            history_options += '<a onclick="planeEdit(' + packageadvance.id + ', ' + package.id + ');" class="btn btn-sm btn-info" href="javascript:void(0);">Edit</a>&nbsp;';
                        }
                        if (permissions.patients_plan_cash_delete) {
                            history_options += '<button onclick="deletePlaneHistory(`' + route('admin.packages.delete_cash') + '`, ' + packageadvance.id + ');" class="btn btn-sm btn-danger">Delete</button>';
                        }
                    }

                    history_options += '</td>';

                    history_options += '<tr>';

                }
            });
        }

        $(".edit_plan_history").html(history_options);

        let appointment_options = '<option value="">Select Appointment</option>';
        if (appointmentArray.length) {
            Object.values(appointmentArray).forEach(function (appointment) {

                appointment_options += '<option value="' + appointment.id + '">' + appointment.name + '</option>';
            });
        }

        $("#edit_appointment_id").html(appointment_options);


        $("#edit_appointment_id").find('option').each(function () {
            let app_id = 0;
            if ($(this).val() != '') {
                let valueArray = $(this).val().split('.');
                app_id = valueArray[0];
                if (app_id == package.appointment_id) {
                    $("#edit_appointment_id").val($(this).val())
                }
            }
        });

        let servic_options = '<option value="">Select Service</option>';
        if (locationhasservice.length) {
            Object.values(locationhasservice).forEach(function (service) {

                servic_options += '<option value="' + service.id + '">' + service.name + '</option>';
            });
        }

        $("#edit_service_id").html(servic_options);


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
                service_options += '<td>' + packagebundle.tax_including_price + '</td>';
                service_options += "<td><button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deleteEditPlanRow(" + packagebundle.id + ", `edit_`)'>" + trashBtn() + "</button></td>";

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
                        service_options += '<td colspan="4">Is Consumed : ' + consume + '</td>';
                        service_options += '</tr>';
                    }

                });
            });
        }

        $(".service_not_found").remove();
        $("#edit_plan_services").html(service_options);


        $(".package_total_price").text(package.total_price);
        $("#user_name").text(package?.user?.name);
        $("#location_name").text(package?.location?.name);



        let discounts = response.data.discounts;
        let discount_types = response.data.discount_type;

        let discount_options = '<option value="">Select Discount</option>';

        if (discounts) {
            Object.values(discounts).forEach(function (discount) {
                discount_options += '<option value="' + discount.id + '">' + discount.name + '</option>';
            });
        }

        $("#edit_discount_id").html(discount_options);

        let discount_type_options = '<option value="">Select Discount Type</option>';

        console.log(discount_types)
        if (discount_types) {
            Object.entries(discount_types).forEach(function (discount_type) {
                discount_type_options += '<option value="' + discount_type[0] + '">' + discount_type[1] + '</option>';
            });
        }

        $("#edit_discount_type").html(discount_type_options);


        let payment_mode_options = '<option value="">Select Payment Mode</option>';

        if (paymentmodes) {
            Object.entries(paymentmodes).forEach(function (paymentmode) {
                payment_mode_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
            });
        }

        $("#edit_payment_mode_id").html(payment_mode_options);



        let location_options = '<option value="">Select Centers</option>';
        if (locations) {
            Object.values(locations).forEach(function (location) {

                location_options += '<option value="' + location.id + '">' + location.name + '</option>';
            });
        }

        $("#edit_location_id").html(location_options);

    } catch (error) {
        showException(error);
    }

}

function planeEdit(id, package_id) {

    $("#plan_edit_cash").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.packages.edit_cash', { id: id, package_id: package_id }),
        type: "GET",
        cache: false,
        success: function (response) {
            setPlaneEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setPlaneEditData(response) {

    let paymentmodes = response.data.paymentmodes;
    let pack_adv_info = response.data.pack_adv_info;
    let package_id = response.data.package_id;

    let payment_options = '<option value="">Select Payment Mode</option>';

    if (paymentmodes) {
        Object.values(paymentmodes).forEach(function (paymentmode) {
            payment_options += '<option value="' + paymentmode.id + '">' + paymentmode.name + '</option>';
        });
    }

    if (permissions.plans_cash_edit_payment_mode) {
        $("#plane_cash_payment_mode").html(payment_options).val(pack_adv_info.payment_mode_id);
    } else {
        $("#plane_cash_payment_mode").remove();

        let input = '<input type="hidden" id="payment_mode_id" name="payment_mode_id" value="' + pack_adv_info?.payment_mode_id + '">';
        $(".append_payment_mode").append(input);
    }

    if (permissions.plans_cash_edit_amount) {
        $("#plane_cash_amount").val(pack_adv_info.cash_amount);
    } else {
        $("#plane_cash_amount").remove();

        let input = '<input type="hidden" id="cash_amount" name="cash_amount" value="' + pack_adv_info?.cash_amount + '">';
        $(".append_cash_amount").append(input);
    }

    if (permissions.plans_cash_edit_date) {
        $("#plane_cash_date").val(formatDate(pack_adv_info.created_at, 'YYYY-MM-DD'));
    } else {
        $("#plane_cash_date").remove();

        let input = '<input type="hidden" id="created_at" name="created_at" value="' + formatDate(pack_adv_info.created_at, 'YYYY-MM-DD') + '">';
        $(".append_cash_date").append(input);
    }

    $("#edit_package_advances_id").val(pack_adv_info.id);
    $("#edit_package_id").val(package_id);




}

function deletePlaneHistory(url, package_advance_id) {

    swal.fire({
        title: 'Are you sure you want to delete?',
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
                type: "POST",
                data: {
                    package_advance_id: package_advance_id,
                    cash_receveive_remain: $("#edit_total_price").val()
                },
                cache: false,
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                        let cash_remain = response.data.cash_receveive_remain;
                        $("#edit_total_price").val(cash_remain);
                        $("#history_cash_row_" + package_advance_id).remove()
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

function addServiceDiscount($this, type = 'add_') {


    var service_id = $this.val();
    var location_id = $('#add_location_id').val();
    var patient_id = $('#add_patient_id').val();

    $("#" + type + "discount_id").val('0').trigger('change');

    if (service_id && patient_id) {

        $("#net_amount_1").prop("disabled", true);
        $("#add_discount_value").val(0).change();
        $("#add_discount_type").val('').change();

        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo'),
            data: {
                'bundle_id': service_id, //Basically it is bundle id
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (resposne) {

                if (resposne.status) {

                    let discounts = resposne.data.discounts;

                    let options = '<option value="" >Select Discount</option>';

                    jQuery.each(discounts, function (i, discount) {
                        options += '<option value="' + discount.id + '">' + discount.name + '</option>';
                    });

                    $("#add_discount_id").html(options);

                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);

                } else {

                    let options = '<option value="" >Select Discount</option>';

                    $("#add_discount_id").html(options);

                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);
                    $("#add_discount_value").val(0).change();
                    $("#add_discount_type").val('').change();

                }
            },
        });
    }

}

function editServiceDiscount($this, type = '') {

    hideMessages();

    var service_id = $this.val();
    var location_id = $('#edit_location_id').val();
    var patient_id = $('#edit_patient_id').val();

    $("#edit_discount_id").val('0').trigger('change');
    $("#edit_net_amount_1").prop("disabled", true);
    $("#edit_discount_value").val(0).change();
    $("#edit_discount_type").val('').change();

    if (service_id && patient_id) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo'),
            data: {
                'bundle_id': service_id, //Basically it is bundle id
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (resposne) {

                if (resposne.status) {

                    let discounts = resposne.data.discounts;

                    let options = '<option value="" >Select Discount</option>';

                    jQuery.each(discounts, function (i, discount) {
                        options += '<option value="' + discount.id + '">' + discount.name + '</option>';
                    });

                    $("#edit_discount_id").html(options);

                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);

                } else {

                    let options = '<option value="" >Select Discount</option>';

                    $("#edit_discount_id").html(options);

                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);
                    $("#edit_discount_value").val(0).change();
                    $("#edit_discount_type").val('').change();

                }
            },
        });
    }

}

function getDiscountValue($this) {

    inputSpinner(true, 'AddPackage')
    hideMessages();

    var service_id = $('#add_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#add_discount_id').val();
    var discount_type = $('#add_discount_type').val();
    var discount_value = $this.val();

    if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#percentageMessage').show();
            $("#net_amount_1").val('')
            inputSpinner(false, 'AddPackage')
            return false;
        } else {
            $('#percentageMessage').hide();
            inputSpinner(false, 'AddPackage')
        }
    }

    if (service_id && discount_id && discount_value && discount_type) {

        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfo_custom'),
            data: {
                'service_id': service_id,//Basicailly it is bundle id
                'discount_id': discount_id,
                'discount_value': discount_value,
                'discount_type': discount_type,
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);
                    inputSpinner(false, 'AddPackage')
                } else {
                    $('#DiscountRange').show();
                    // $("#net_amount_1").prop("disabled", false);
                    $("#net_amount_1").val('')
                    inputSpinner(false, 'AddPackage')
                }
            },
            error: function () {
                $("#net_amount_1").val('')
                inputSpinner(false, 'AddPackage');
                $("#net_amount_1").prop("disabled", false);
            }
        });
    }

}

function editDiscountValue($this) {
    inputSpinner(true, 'EditPackage')
    hideMessages();

    var service_id = $('#edit_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#edit_discount_id').val();
    var discount_type = $('#edit_discount_type').val();
    var discount_value = $this.val();

    if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#edit_percentageMessage').show();
            $("#edit_net_amount_1").val('')
            inputSpinner(false, 'EditPackage')
            return false;
        } else {
            $('#edit_percentageMessage').hide();
            inputSpinner(false, 'EditPackage')
        }
    }

    if (service_id && discount_id && discount_value && discount_type) {

        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfo_custom'),
            data: {
                'service_id': service_id,//Basicailly it is bundle id
                'discount_id': discount_id,
                'discount_value': discount_value,
                'discount_type': discount_type,
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);
                    inputSpinner(false, 'EditPackage')
                } else {
                    $('#edit_DiscountRange').show();
                    $("#edit_net_amount_1").val('')
                    inputSpinner(false, 'EditPackage')
                }
            },
            error: function () {
                $("#edit_net_amount_1").val('')
                inputSpinner(false, 'EditPackage');
                $("#edit_net_amount_1").prop("disabled", false);
            }
        });
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
        $("#user_name").text(package.user.name);
        $(".patient_print").attr("href", route('admin.packages.package_pdf', { id: package?.id }))
        $("#location_name").text(package.location.name);


    } catch (error) {
        showException(error);
    }

}

/*Actions*/

function applyFilters(datatable) {

    $('#plan-search').on('click', function () {

        let filters = {
            delete: '',
            location_id: $("#search_plan_location_id").val(),
            status_id: $("#search_status").val(),
            plan_id: $("#search_plan_id").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $(".page-plan-form").find('#reset-filters').on('click', function () {

        let filters = {
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

        let locations = filter_values.locations;
        let statuses = filter_values.status;

        let location_options = '<option value="">All</option>';

        if (locations) {
            Object.entries(locations).forEach(function (location) {
                location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
            });
        }

        let status_options = '<option value="">All</option>';

        if (statuses) {
            Object.entries(statuses).forEach(function (status) {
                status_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
            });
        }

        let plan_options = '<option value="">All</option>';

        $("#search_plan_location_id").html(location_options);
        $("#search_status").html(status_options);
        $("#search_plan_id").html(plan_options);

    } catch (error) {
        showException(error);
    }
}

function hideMessages() {

    $('#wrongMessage').hide();
    $('#inputfieldMessage').hide();
    $('#percentageMessage').hide();
    $('#AlreadyExitMessage').hide();
    $('#DiscountRange').hide();
    $('#datanotexist').hide();

    $('#edit_wrongMessage').hide();
    $('#edit_inputfieldMessage').hide();
    $('#edit_percentageMessage').hide();
    $('#edit_AlreadyExitMessage').hide();
    $('#edit_DiscountRange').hide();
    $('#edit_datanotexist').hide();
}

function keyfunction_grandtotal() {

    hideMessages();

    var cash_amount = $('#add_cash_amount').val();
    var total = $('#add_package_total').val();

    if (cash_amount && total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal'),
            data: {
                'cash_amount': cash_amount,
                'total': total,
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#add_total_price").val(resposne?.data?.grand_total ?? 0);
                } else {
                    $('#wrongMessage').show();
                }
            },
        });
    } else {
        $("#add_total_price").val(total);
        $('#inputfieldMessage').show();
    }
}

function edit_keyfunction_grandtotal() {

    hideMessages();

    var cash_amount = $('#edit_cash_amount').val();
    var total = $('#edit_package_total').val();
    var random_id = $('#edit_random_id').val();

    if (cash_amount && total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal_update'),
            data: {
                'cash_amount': cash_amount,
                'total': total,
                'random_id': random_id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#edit_total_price").val(resposne?.data?.grand_total ?? 0);
                } else {
                    $('#edit_wrongMessage').show();
                }
            },
        });
    } else {
        $('#edit_inputfieldMessage').show();
    }
}

function toggle(id) {
    $("." + id).toggle();
}

/*Delete The record*/
function deletePlanRow(id = '') {

    hideMessages();

    swal.fire({
        title: 'Are you sure you want to delete?',
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
            deletePlan(id);
        }
    });
}

function deletePlan(id) {

    var package_total = $('#add_package_total').val();

    $.ajax({
        type: 'post',
        url: route('admin.packages.deletepackages_service'),
        data: {
            '_token': $('input[name=_token]').val(),
            'id': id,
            'package_total': package_total
        },
        success: function (resposne) {

            if (resposne.status) {

                $('.HR_' + resposne.data.id).remove();
                $("#add_package_total").val(resposne?.data?.total ?? 0);
                $("#add_total_price").val(resposne?.data?.total ?? 0);

                keyfunction_grandtotal();

                var rows = $('#plan_services tbody tr.HR_' + $('#random_id_1').val()).length;
                if (rows <= 1) {
                    $("#add_location_id").prop("disabled", false);
                }

            } else {
                $('#wrongMessage').show();
            }
        }
    });

}

function deleteEditPlanRow(id, type = '') {

    hideMessages();

    swal.fire({
        title: 'Are you sure you want to delete?',
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
            deleteEditPlan(id, type);
        }
    });
}

function deleteEditPlan(id) {

    var package_total = $('#edit_package_total').val();

    $.ajax({
        type: 'post',
        url: route('admin.packages.deletepackages_service'),
        data: {
            '_token': $('input[name=_token]').val(),
            'id': id,
            'package_total': package_total,
            'update_status': 1
        },
        success: function (resposne) {

            if (resposne.status) {

                $('.edit_HR_' + resposne.data.id).remove();
                $("#edit_package_total").val(resposne?.data?.total ?? 0);
                $("#edit_total_price").val(resposne?.data?.total ?? 0);

                edit_keyfunction_grandtotal();

                var rows = $('#edit_plan_services tbody tr.HR_' + $('#edit_random_id').val()).length;
                if (rows <= 1) {
                    $("#edit_location_id").prop("disabled", false);
                }

            } else {
                toastr.error(resposne.message);
                $('#edit_consumeservice').show();
            }
        }
    });

}

var planeEditValidation = function () {
    // Private functions
    var planeValidation = function () {
        let modal_id = 'plane_edit_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    payment_mode_id: {
                        validators: {
                            notEmpty: {
                                message: 'The payment mode field is required'
                            }
                        }
                    },
                    cash_amount: {
                        validators: {
                            notEmpty: {
                                message: 'The cash amount field is required'
                            }
                        }
                    },
                    created_at: {
                        validators: {
                            notEmpty: {
                                message: 'The date field is required'
                            }
                        }
                    },
                    consultancy_type_id: {
                        validators: {
                            notEmpty: {
                                message: 'The consultancy type field is required'
                            }
                        }
                    },
                },

                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    // Bootstrap Framework Integration
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    // Validate fields when clicking the Submit button
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );
        validate.on('core.form.invalid', function (e) {
            select2Validation();
        });
        validate.on('core.form.valid', function (event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {

                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    closePopup('plane_edit_form');
                    closePopup('modal_edit_plan_form');
                    reloadTable('.plan-form');
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function () {
            planeValidation();
        }
    };
}();


jQuery(document).ready(function () {

    planeEditValidation.init();

    $("#add_cash_amount").keyup(function () {
        keyfunction_grandtotal();
    });

    $("#add_cash_amount").blur(function () {
        keyfunction_grandtotal();
    });

    /*save data for both predefined discounts and keyup trigger*/
    $("#AddPackage").click(function () {

        hideMessages();

        $(this).attr("disabled", true);
        var random_id = $('#random_id_1').val();
        var service_id = $('#add_service_id').val(); //Basicailly it is bundle id
        var discount_id = $('#add_discount_id').val();
        var net_amount = $('#net_amount_1').val();
        var discount_type = $('#add_discount_type').val();
        var discount_price = $('#add_discount_value').val();
        var discount_slug = $("#slug_1").val();
        var package_total = $('#add_package_total').val();

        var is_exclusive = $('#is_exclusive').val();
        var location_id = $('#add_location_id').val();

        if (service_id && net_amount && location_id) {

            showSpinner("-add");

            if (discount_slug == 'custom') {
                if (discount_price == '') {
                    hideSpinner("-add");
                    $('#inputfieldMessage').show();
                    return false;
                }
                if (discount_type == 'Percentage') {
                    if (discount_price > 100) {
                        $('#percentageMessage').show();
                        hideSpinner("-add");
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
                    if (resposne.status) {

                        $("#add_package_total").val(resposne?.data?.myarray?.total ?? 0);
                        $('.not_found').remove();
                        $('#plan_services').append("" +
                            "<tr id='table_1' class='HR_" + random_id + " HR_" + resposne.data.myarray.record.id + "'>" +
                            "<td><a href='javascript:void(0)' onClick='toggle(" + resposne.data.myarray.record.id + ")'>" + resposne.data.myarray.service_name + "</a></td>" +
                            "<td>" + resposne.data.myarray.service_price.toLocaleString() + "</td>" +
                            "<td>" + resposne.data.myarray.discount_name + "</td>" +
                            "<td>" + resposne.data.myarray.discount_type + "</td>" +
                            "<td>" + resposne.data.myarray.discount_price + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_exclusive_net_amount.toLocaleString() + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_percenatage + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_including_price.toLocaleString() + "</td>" +
                            "<td>" +
                            "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + resposne.data.myarray.record.id + "' />" +
                            "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + resposne.data.myarray.record.id + ")'>" + trashBtn() + "</button>" +
                            "</td>" +
                            "</tr>");

                        jQuery.each(resposne.data.myarray.record_detail, function (i, record_detail) {
                            if (record_detail.is_consumed == '0') {
                                consume = 'NO';
                            } else {
                                consume = 'YES';
                            }
                            $('#plan_services').append("<tr class='inner_records_hr HR_" + resposne.data.myarray.record.id + " " + resposne.data.myarray.record.id + "'><td></td><td>" + record_detail.name + "</td><td>Amount : " + record_detail.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + record_detail.tax_percenatage + "</td><td>Tax Amt. : " + record_detail.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                        });

                        keyfunction_grandtotal();

                        var rows = $('#plan_services tbody tr').length;

                        if (rows >= 3) {
                            $("#add_location_id").prop("disabled", true);
                        }
                        /*we enable add button after all functionality enable*/
                        // $('#AddPackage_1').attr("disabled", false);

                    } else {
                        $('#AlreadyExitMessage').show();
                        // $('#AddPackage_1').attr("disabled", false);
                    }

                    hideSpinner("-add");
                },
                error: function () {
                    hideSpinner("-add");
                }
            });
        } else {
            $('#inputfieldMessage').show();
            $(this).attr("disabled", false);
            hideSpinner("-add");
        }
    });
    /*End*/

    /*function for final package information save*/
    $("#AddPackageFinal").click(function () {

        hideMessages();

        var random_id = $('#random_id_1').val();
        var patient_id = $('#add_patient_id').val();
        var total = $('#add_package_total').val();
        var payment_mode_id = $('#add_payment_mode_id').val();
        var cash_amount = $('#add_cash_amount').val();
        var grand_total = $('#add_total_price').val();
        var location_id = $('#add_location_id').val();
        var is_exclusive = $('#is_exclusive').val();
        var appointment_id = $('#add_appointment_id').val();

        var formData = {
            'random_id': random_id,
            'patient_id': patient_id,
            'location_id': location_id,
            'total': total,
            'payment_mode_id': payment_mode_id,
            'cash_amount': cash_amount,
            'grand_total': grand_total,
            'is_exclusive': is_exclusive,
            'appointment_id': appointment_id,
            'package_bundles[]': []
        };

        $(".package_bundles").each(function () {
            formData['package_bundles[]'].push($(this).val());
        });
        var status = 0;
        if (cash_amount > 0) {
            var status = 1;
        }

        if (random_id && (patient_id > 0) && total && status == 1 ? payment_mode_id : true && cash_amount >= 0 && grand_total && location_id) {

            showSpinner("-save");

            $.ajax({
                type: 'get',
                url: route('admin.packages.savepackages'),
                data: formData,
                success: function (resposne) {

                    if (resposne.status) {
                        $('#successMessage').show();
                        toastr.success(" Plan successfully created")
                        /*closePopup('add_patient_plane');
                        reloadTable('.plan-form')*/
                        setTimeout(function () {
                            window.location.reload();
                        }, 200);
                    } else {
                        $('#wrongMessage').show();
                    }

                    hideSpinner("-save");
                },
                error: function () {
                    hideSpinner("-save");
                }
            });
        } else {
            $('#inputfieldMessage').show();
            $(this).attr("disabled", false);
            hideSpinner("-save");
        }
    });
    /*End*/


    /*save data for both predefined discounts and keyup trigger*/
    $("#EditPackage").click(function () {

        hideMessages();

        $(this).attr("disabled", true);
        var random_id = $('#edit_random_id').val();
        var service_id = $('#edit_service_id').val(); //Basicailly it is bundle id
        var discount_id = $('#edit_discount_id').val();
        var net_amount = $('#edit_net_amount_1').val();
        var discount_type = $('#edit_discount_type').val();
        var discount_price = $('#edit_discount_value').val();
        var discount_slug = $("#edit_slug").val();
        var package_total = $('#edit_package_total').val();

        var is_exclusive = $('#edit_is_exclusive').val();
        var location_id = $('#edit_location_id').val();

        if (service_id && net_amount && location_id) {

            showSpinner("-edit-add");

            if (discount_slug == 'custom') {
                if (discount_price == '') {
                    hideSpinner("-edit-add");
                    $('#edit_inputfieldMessage').show();
                    return false;
                }
                if (discount_type == 'Percentage') {
                    if (discount_price > 100) {
                        $('#edit_percentageMessage').show();
                        hideSpinner("-edit-add");
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
                    if (resposne.status) {

                        $("#edit_package_total").val(resposne?.data?.myarray?.total ?? 0);

                        $(".not_found").remove();

                        $('#edit_plan_services').append("" +
                            "<tr id='table_1' class='HR_" + random_id + " edit_HR_" + resposne.data.myarray.record.id + "'>" +
                            "<td><a href='javascript:void(0)' onClick='toggle(" + resposne.data.myarray.record.id + ")'>" + resposne.data.myarray.service_name + "</a></td>" +
                            "<td>" + resposne.data.myarray.service_price.toLocaleString() + "</td>" +
                            "<td>" + resposne.data.myarray.discount_name + "</td>" +
                            "<td>" + resposne.data.myarray.discount_type + "</td>" +
                            "<td>" + resposne.data.myarray.discount_price + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_exclusive_net_amount.toLocaleString() + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_percenatage + "</td>" +
                            "<td>" + resposne.data.myarray.record.tax_including_price.toLocaleString() + "</td>" +
                            "<td>" +
                            "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + resposne.data.myarray.record.id + "' />" +
                            "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deleteEditPlanRow(" + resposne.data.myarray.record.id + ")'>" + trashBtn() + "</button>" +
                            "</td>" +
                            "</tr>");

                        jQuery.each(resposne.data.myarray.record_detail, function (i, record_detail) {
                            if (record_detail.is_consumed == '0') {
                                consume = 'NO';
                            } else {
                                consume = 'YES';
                            }
                            $('#edit_plan_services').append("<tr class='inner_records_hr HR_" + resposne.data.myarray.record.id + " " + resposne.data.myarray.record.id + "'><td></td><td>" + record_detail.name + "</td><td>Amount : " + record_detail.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + record_detail.tax_percenatage + "</td><td>Tax Amt. : " + record_detail.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                        });

                        edit_keyfunction_grandtotal();

                    } else {
                        $('#edit_AlreadyExitMessage').show();
                    }

                    hideSpinner("-edit-add");
                },
                error: function () {
                    hideSpinner("-edit-add");
                }
            });
        } else {
            $('#edit_inputfieldMessage').show();
            $(this).attr("disabled", false);
            hideSpinner("-edit-add");
        }
    });
    /*End*/

    /*function for final package information save*/
    $("#EditPackageFinal").click(function () {

        hideMessages();

        var random_id = $('#edit_random_id').val();
        var patient_id = $('#edit_patient_id').val();
        var total = $('#edit_package_total').val();
        var payment_mode_id = $('#edit_payment_mode_id').val();
        var cash_amount = $('#edit_cash_amount').val();
        var grand_total = $('#edit_total_price').val();
        var location_id = $('#edit_location_id').val();
        var is_exclusive = $('#edit_is_exclusive').val();
        var appointment_id = $('#edit_appointment_id').val();

        var formData = {
            'random_id': random_id,
            'patient_id': patient_id,
            'location_id': location_id,
            'total': total,
            'payment_mode_id': payment_mode_id,
            'cash_amount': cash_amount,
            'grand_total': grand_total,
            'is_exclusive': is_exclusive,
            'appointment_id': appointment_id,
            'package_bundles[]': []
        };

        $(".package_bundles").each(function () {
            formData['package_bundles[]'].push($(this).val());
        });
        var status = 0;
        if (cash_amount > 0) {
            var status = 1;
        }

        if (random_id && (patient_id > 0) && total && status == 1 ? payment_mode_id : true && cash_amount >= 0 && grand_total && location_id) {

            showSpinner("-edit-save");

            $.ajax({
                type: 'get',
                url: route('admin.packages.updatepackages'),
                data: formData,
                success: function (resposne) {

                    if (resposne.status) {
                        $('#successMessage').show();
                        toastr.success(resposne.message)
                        /* closePopup('update_plane_form');
                         reInitTable();*/
                        setTimeout(function () {
                            window.location.reload();
                        }, 200);
                    } else {
                        $('#edit_wrongMessage').show();
                        toastr.error(resposne.message)
                    }

                    hideSpinner("-edit-save");
                },
                error: function () {
                    hideSpinner("-edit-save");
                }
            });
        } else {
            $('#edit_inputfieldMessage').show();
            $(this).attr("disabled", false);
            toastr.error("Kindly enter required fields or you enter wrong value.")
            hideSpinner("-edit-save");
        }
    });
    /*End*/

});
