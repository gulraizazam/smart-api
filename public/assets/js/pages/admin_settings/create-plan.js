$(document).ready( function () {

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

            $(".package_bundles").each(function() {
                formData['package_bundles[]'].push($(this).val());
            });

            $.ajax({
                type: 'get',
                url: route('admin.packages.savepackages_service'),
                data: formData,
                success: function (resposne) {

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
                            "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + resposne.myarray.record.id +"' />" +
                            "<button class='btn btn-xs btn-danger' onClick='deleteModel(" + resposne.myarray.record.id + ")'>Delete</button>" +
                            "</td>" +
                            "</tr>");

                        jQuery.each(resposne.myarray.record_detail, function (i, record_detail) {
                            if (record_detail.is_consumed == '0') {
                                var consume = 'NO';
                            } else {
                                var consume = 'YES';
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
        field: 'package_id',
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

    hideMessages();
    $("#update_plane_form")[0].reset();

    $("#modal_edit_plan").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            // reInitValidation(Validation);
        }
    });


}

function appointmentCheck(package) {

    $("#edit_appointment_id").val('')
    $("#edit_appointment_id").find('option').each( function () {
        let app_id = 0;
        if ($(this).val() != '') {
            let valueArray = $(this).val().split('.');
            app_id = valueArray[0];
        }
        if (app_id == package.appointment_id) {
            $("#edit_appointment_id").val($(this).val())
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

        let patient = package.user;
        let location = package.location;

        let history_options = noRecordFoundTable(5);

        if (packageadvances.length) {

            history_options = '';
            Object.values(packageadvances).forEach(function (packageadvance) {

                if(packageadvance.cash_amount != '0' && packageadvance.is_tax == 0) {
                    history_options += '<tr>';
                    history_options += '<td>'+packageadvance.paymentmode.name+'</td>';
                    history_options += '<td>'+packageadvance.cash_flow+'</td>';
                    history_options += '<td>'+packageadvance.cash_amount+'</td>';
                    history_options += '<td>'+formatDate(packageadvance.created_at, 'MMM, DD yyyy HH:mm A')+'</td>';
                    history_options += '<tr>';
                }
            });
        }

        let service_options = noRecordFoundTable(9);

        if(packagebundles.length) {
            service_options = '';
            Object.values(packagebundles).forEach(function (packagebundle) {

                service_options += '<tr class="HR_'+packagebundle.id+'">';
                service_options += '<td><a href="javascript:void(0);" onclick="toggle('+packagebundle.id+')">'+packagebundle.bundle.name+'</a></td>';
                service_options += '<td>'+packagebundle.service_price.toFixed(2)+ '</td>';
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
              //  service_options +=  '<td>'+packagebundle.tax_price+'</td>';
                service_options +=  '<td>'+packagebundle.tax_including_price+'</td>';

                service_options += "<td><button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + packagebundle.id + ", `edit_`)'>"+trashBtn()+"</button></td>";
                service_options += '</tr>';


                Object.values(packageservices).forEach(function (packageservice) {
                    let consume = 'NO';
                    if(packageservice.package_bundle_id == packagebundle.id ) {

                        if (packageservice.is_consumed == '0') {
                            consume = 'NO';
                        } else {
                            consume = 'YES';
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

        let appointment_options = '<option value="">Select Appointment</option>';
        if (appointmentArray.length) {
            Object.values(appointmentArray).forEach( function (appointment) {
                appointment_options += '<option value="'+appointment.id+'">'+appointment.name+'</option>';
            });
        }

        let serviceOptions = '<option value="">Select Service</option>';

        if (packageservices.length) {
            Object.values(packageservices).forEach( function (packageservice) {
                serviceOptions += '<option value="'+packageservice?.service?.id+'">'+packageservice?.service?.name+'</option>';
            });
        }
        let payment_options = '<option value="">Select Payment Mode</option>';
        if (paymentmodes) {
            Object.entries(paymentmodes).forEach( function(paymentmode) {
                payment_options += '<option value="'+paymentmode[0]+'">'+paymentmode[1]+'</option>';
            });
        }


        $("#edit_appointment_id").html(appointment_options);
        $("#edit_service_id").html(serviceOptions);

        appointmentCheck(package);

        $("#edit_plan_services").html(service_options);

        $(".edit_plan_history").html(history_options);

        $("#edit_payment_mode_id").html(payment_options);

        $("#edit-patient-name").text(patient?.name)
        $("#edit-location-name").text(location?.name)
        $("#edit_random_id").val(package?.random_id)
        $("#edit_parent_id").val(package?.patient_id)
        $("#edit_location_id").val(package?.location?.id)
        $("#edit_random_id_1").val(package?.random_id)
        $("#edit_package_total_1").val(total_price)
        $("#edit_grand_total_1").val(grand_total)


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
            let sent_url = route('admin.packages.resend_sms');
            rows = '';
            Object.values(SMSLogs).forEach(function (smsLog, index) {

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

        let history_options = noRecordFoundTable(4);

        if (Object(packageadvances).length) {

            history_options = '';
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
            service_options = '';
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
                        let consume = 'NO';
                        if (packageservice.is_consumed == '0') {
                            consume = 'NO';
                        } else {
                            consume = 'YES';
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
        $("#user_name").text(package.user.name)
        $("#location_name").text(package.location.name)


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
            package_id: $("#search_plan_id").val(),
            location_id: $("#search_location_id").val(),
            status: $("#search_status").val(),
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
            package_id: '',
            location_id: '',
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
        let status = filter_values.status;

        let location_options = '<option value="">All</option>';

        let status_options = '<option value="">All</option>';

        if (locations) {
            Object.entries(locations).forEach(function (value) {
                location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
            });
        }


        if (status) {
            Object.entries(status).forEach(function (value) {
                status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
            });
        }

        $("#search_location_id").html(location_options);
        $("#search_status").html(status_options);

        $("#search_id").val(active_filters.id);

        /*if (active_filters.patient_name !== 'undefined' && active_filters.patient_name != 'undefined') {
            $("#search_patient_id").html('<option value="'+active_filters.patient_id+'">'+active_filters.patient_name+'</option>');
            $("#search_patient_id").val(active_filters?.patient_id ?? '');
        }*/

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


function createPlan(url, id) {

    hideSpinner("-save");
    hideSpinner("-add");
    hideMessages();

    $("#plan_services").html("");
    $("#modal_appointment_plan").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {

            setPlanData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setPlanData(response) {

    let locations = response.data.locations
    let discounts = response.data.discounts;
    let random_id = response.data.random_id;
    let appointmentinformation = response.data.appointmentinformation;
    let paymentmodes = response.data.paymentmodes;

    let location_options = '<option value="">Select Centre</option>';

    if (locations) {
        Object.entries(locations).forEach( function(location) {
            location_options += '<option value="'+location[0]+'">'+location[1]+'</option>';
        });
    }

    let discount_options = '<option value="">Select Discount</option>';

    if (discounts) {
        Object.values(discounts).forEach( function(discount) {
            discount_options += '<option value="'+discount.id+'">'+discount.name+'</option>';
        });
    }

    let payment_options = '<option value="">Select Payment Mode</option>';
    if (paymentmodes) {
        Object.entries(paymentmodes).forEach( function(paymentmode) {
            payment_options += '<option value="'+paymentmode[1]+'">'+paymentmode[1]+'</option>';
        });
    }

    $("#add_discount_id").html(discount_options);
    $("#payment_mode_id_1").html(payment_options);

    $("#add_plan_location_id").html(location_options).val(appointmentinformation?.location_id);
    $("#random_id_1").val(random_id);

    getServices();


}

function getServices() {

    hideMessages();

    let location = $("#add_plan_location_id").val();

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
            $('#datanotexist').show();
        }
    });
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

function getAppointments(patient) {

    let location = $("#add_plan_location_id").val();
   // let patient = $("#add_patient_id").val();

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
              //  $("#modal_edit_regions").modal("show");

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
        let appointment_options = '';

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

/*Add Plan functions*/
function getServiceDiscount($this, type = '') {

    hideMessages();

    var service_id = $this.val();
    var patient_id = $('#client_id').val();
    var location_id = $('#add_plan_location_id').val();

    $("#"+type+"add_discount_id").val('0').trigger('change');

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

                    $("#"+type+"add_discount_id").html(options);

                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);

                } else {

                    let options = '<option value="" >Select Discount</option>';

                    $("#add_discount_id").html(options);

                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);

                }
            },
        });
    }

}

function getDiscountInfo($this) {

    hideMessages();

    var service_id = $('#add_service_id').val(); //Basicailly it is bundle id
    var discount_id = $this.val();

    if (service_id == null && discount_id == null) {

        $("#add_discount_type").prop("disabled", false);
        $("#add_discount_type").val('').trigger('change');
        $("#discount_value_1").prop("disabled", false);
        $("#discount_value_1").val('');
        $("#net_amount_1").prop("disabled", false);
        $("#net_amount_1").val('');
        $("#slug_1").val('not_custom');

    } else if (discount_id == null && service_id != null) {

        $("#add_discount_type").prop("disabled", true);
        $("#add_discount_type").val('').trigger('change');
        $("#discount_value_1").prop("disabled", true);
        $("#discount_value_1").val('');
        $("#slug_1").val('not_custom');

    } else if (service_id == null && discount_id == '0') {

        $("#add_discount_type").prop("disabled", false);
        $("#add_discount_type").val('').trigger('change');
        $("#discount_value_1").prop("disabled", false);
        $("#discount_value_1").val('');
        $("#net_amount_1").prop("disabled", false);
        $("#net_amount_1").val('');
        $("#slug_1").val('not_custom');

    } else if (service_id && discount_id == '0') {
        $("#slug_1").val('not_custom');
        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo_discount_zero'),
            data: {
                'bundle_id': service_id, //Basicailly it is bundle id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#add_discount_type").prop("disabled", true);
                    $("#add_discount_type").val('').trigger('change');
                    $("#discount_value_1").prop("disabled", true);
                    $("#discount_value_1").val('');
                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);
                } else {
                    $('#wrongMessage').show();
                }
            },
        });
    } else {
        if (service_id && discount_id != '0') {
            $.ajax({
                type: 'get',
                url: route('admin.packages.getdiscountinfo'),
                data: {
                    'service_id': service_id,
                    'discount_id': discount_id
                },
                success: function (resposne) {
                    if (resposne.status) {

                        if (resposne.data.custom_checked == 0) {
                            $("#add_discount_type").val(resposne.data.discount_type).change();
                            $("#add_discount_type").prop("disabled", true);
                            $("#discount_value_1").val(resposne.data.discount_price);
                            $("#discount_value_1").prop("disabled", true);
                            $("#net_amount_1").val(resposne.data.net_amount);
                            $("#net_amount_1").prop("disabled", true);
                            $("#slug_1").val('not_custom');
                        } else {
                            $("#add_discount_type").prop("disabled", false);
                            $("#add_discount_type").val('').trigger('change');
                            $("#discount_value_1").prop("disabled", false);
                            $("#discount_value_1").val('');
                            $("#net_amount_1").prop("disabled", true);
                            $("#net_amount_1").val('');
                            $("#slug_1").val('custom');
                        }
                    } else {
                        $('#wrongMessage').show();
                    }
                },
            });
        }
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
                    $("#edit_net_amount_1").prop("disabled", true, 'EditPackage');
                    inputSpinner(false)
                } else {
                    $('#edit_DiscountRange').show();
                    inputSpinner(false, 'EditPackage')
                }
            },
            error: function () {
                inputSpinner(false, 'EditPackage')
            }
        });
    }

}

function changeDiscount($this) {

    hideMessages();

    var service_id = $('#add_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#add_discount_id').val();
    var discount_value = $('#discount_value_1').val();
    var discount_type = $this.val();

    if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#percentageMessage').show();
            return false;
        } else {
            $('#percentageMessage').hide();
        }
    }
    if (service_id && discount_id && discount_value && discount_type) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfo_custom'),
            data: {
                'service_id': service_id, //Basicailly it is bundle id
                'discount_id': discount_id,
                'discount_value': discount_value,
                'discount_type': discount_type,
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);
                } else {
                    $('#DiscountRange').show();
                    $("#net_amount_1").val('');
                    $("#net_amount_1").prop("disabled", true);

                }
            },
        });
    }

}
/*end add plan functions*/

/*Edit plan functions*/

function editServiceDiscount($this, type = '') {

    hideMessages();

    var service_id = $this.val();
    var location_id = $('#edit_location_id').val();
    var patient_id = $('#edit_parent_id').val();

    $("#"+type+"discount_id").val('0').trigger('change');

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

                }
            },
        });
    }

}

function editDiscountInfo($this) {

    hideMessages();

    var service_id = $('#edit_service_id').val(); //Basicailly it is bundle id
    var discount_id = $this.val();

    if (service_id == null && discount_id == null) {

        $("#edit_discount_type").prop("disabled", false);
        $("#edit_discount_type").val('').trigger('change');
        $("#discount_value_1").prop("disabled", false);
        $("#discount_value_1").val('');
        $("#edit_net_amount_1").prop("disabled", false);
        $("#edit_net_amount_1").val('');
        $("#edit_slug_1").val('not_custom');

    } else if (discount_id == null && service_id != null) {

        $("#edit_discount_type").prop("disabled", true);
        $("#edit_discount_type").val('').trigger('change');
        $("#edit_discount_value_1").prop("disabled", true);
        $("#edit_discount_value_1").val('');
        $("#edit_slug_1").val('not_custom');

    } else if (service_id == null && discount_id == '0') {

        $("#edit_discount_type").prop("disabled", false);
        $("#edit_discount_type").val('').trigger('change');
        $("#edit_discount_value_1").prop("disabled", false);
        $("#edit_discount_value_1").val('');
        $("#edit_net_amount_1").prop("disabled", false);
        $("#edit_net_amount_1").val('');
        $("#edit_slug_1").val('not_custom');

    } else if (service_id && discount_id == '0') {
        $("#slug_1").val('not_custom');
        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo_discount_zero'),
            data: {
                'bundle_id': service_id, //Basicailly it is bundle id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#edit_discount_type").prop("disabled", true);
                    $("#edit_discount_type").val('').trigger('change');
                    $("#edit_discount_value_1").prop("disabled", true);
                    $("#edit_discount_value_1").val('');
                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);
                } else {
                    $('#wrongMessage').show();
                }
            },
        });
    } else {
        if (service_id && discount_id != '0') {
            $.ajax({
                type: 'get',
                url: route('admin.packages.getdiscountinfo'),
                data: {
                    'service_id': service_id,
                    'discount_id': discount_id
                },
                success: function (resposne) {
                    if (resposne.status) {

                        if (resposne.data.custom_checked == 0) {
                            $("#edit_discount_type").val(resposne.data.discount_type).change();
                            $("#edit_discount_type").prop("disabled", true);
                            $("#edit_discount_value_1").val(resposne.data.discount_price);
                            $("#edit_discount_value_1").prop("disabled", true);
                            $("#edit_net_amount_1").val(resposne.data.net_amount);
                            $("#edit_net_amount_1").prop("disabled", true);
                            $("#edit_slug_1").val('not_custom');
                        } else {
                            $("#edit_discount_type").prop("disabled", false);
                            $("#edit_discount_type").val('').trigger('change');
                            $("#edit_discount_value_1").prop("disabled", false);
                            $("#edit_discount_value_1").val('');
                            $("#edit_net_amount_1").prop("disabled", true);
                            $("#edit_net_amount_1").val('');
                            $("#edit_slug_1").val('custom');
                        }
                    } else {
                        $('#wrongMessage').show();
                    }
                },
            });
        }
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
                    $("#net_amount_1").prop("disabled", true, 'AddPackage');
                    inputSpinner(false)
                } else {
                    $('#DiscountRange').show();
                    inputSpinner(false, 'AddPackage')
                }
            },
            error: function () {
                inputSpinner(false, 'AddPackage')
            }
        });
    }

}

function changeDiscount($this) {

    hideMessages();

    var service_id = $('#add_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#add_discount_id').val();
    var discount_value = $('#discount_value_1').val();
    var discount_type = $this.val();

    if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#percentageMessage').show();
            return false;
        } else {
            $('#percentageMessage').hide();
        }
    }
    if (service_id && discount_id && discount_value && discount_type) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfo_custom'),
            data: {
                'service_id': service_id, //Basicailly it is bundle id
                'discount_id': discount_id,
                'discount_value': discount_value,
                'discount_type': discount_type,
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#net_amount_1").val(resposne.data.net_amount);
                    $("#net_amount_1").prop("disabled", true);
                } else {
                    $('#DiscountRange').show();
                    $("#net_amount_1").val('');
                    $("#net_amount_1").prop("disabled", true);

                }
            },
        });
    }

}

/*End Edit plan functions*/

/*key function for net amount of service*/
function keyfunction_grandtotal() {

    hideMessages();

    var cash_amount = $('#cash_amount_1').val();
    var total = $('#package_total_1').val();

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
                    $("#grand_total_1").val(resposne?.data?.grand_total ?? 0);
                } else {
                    $('#wrongMessage').show();
                }
            },
        });
    } else {
        $('#'+type+'inputfieldMessage').show();
    }
}

function edit_keyfunction_grandtotal() {

    hideMessages();

    var cash_amount = $('#edit_cash_amount_1').val();
    var total = $('#edit_package_total_1').val();
    var random_id = $('#edit_random_id').val();

    if (cash_amount && total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal_update'),
            data: {
                'cash_amount': cash_amount,
                'total': total,
                'random_id':random_id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#edit_grand_total_1").val(resposne?.data?.grand_total ?? 0);
                } else {
                    $('#edit_wrongMessage').show();
                }
            },
        });
    } else {
        $('#edit_inputfieldMessage').show();
    }
}

/*End*/

function toggle(id) {
    $("." + id).toggle();
}

/*Delete The record*/
function deletePlanRow(id, type = '') {

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
    }).then(function(result) {
        if (result.value) {
            deletePlan(id, type);
        }
    });
}

function deletePlan(id, type) {

    var package_total = $('#'+type+'package_total_1').val();

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
                $("#"+type+"package_total_1").val(resposne?.data?.total ?? 0);

                keyfunction_grandtotal();

                var rows = $('#plan_services tbody tr.HR_' + $('#random_id_1').val()).length;
                if (rows <= 1) {
                    $("#add_plan_location_id").prop("disabled", false);
                    $("#edit_plan_location_id").prop("disabled", false);
                }

            } else {
                $('#'+type+'wrongMessage').show();
            }
        }
    });

}
/*End*/

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


jQuery(document).ready( function () {

    /*save data for both predefined discounts and keyup trigger*/
    $("#AddPackage").click(function () {

        hideMessages();

        $(this).attr("disabled", true);
        var random_id = $('#random_id_1').val();
        var service_id = $('#add_service_id').val(); //Basicailly it is bundle id
        var discount_id = $('#add_discount_id').val();
        var net_amount = $('#net_amount_1').val();
        var discount_type = $('#add_discount_type').val();
        var discount_price = $('#discount_value_1').val();
        var discount_slug = $("#slug_1").val();
        var package_total = $('#package_total_1').val();

        var is_exclusive = $('#is_exclusive').val();
        var location_id = $('#add_plan_location_id').val();

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

            $(".package_bundles").each(function() {
                formData['package_bundles[]'].push($(this).val());
            });

            $.ajax({
                type: 'get',
                url: route('admin.packages.savepackages_service'),
                data: formData,
                success: function (resposne) {
                    let consume = 'NO';
                    if (resposne.status) {

                        $("#package_total_1").val(resposne?.data?.myarray?.total ?? 0);

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
                            "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + resposne.data.myarray.record.id +"' />" +
                            "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + resposne.data.myarray.record.id + ")'>"+trashBtn()+"</button>" +
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

                       // toggle(resposne.data.myarray.record.id);

                        keyfunction_grandtotal();

                        var rows = $('#plan_services tbody tr').length;

                        if (rows >= 3) {
                            $("#add_plan_location_id").prop("disabled", true);
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
        var patient_id = $('#add_location_id').val();
        var total = $('#package_total_1').val();
        var payment_mode_id = $('#payment_mode_id_1').val();
        var cash_amount = $('#cash_amount_1').val();
        var grand_total = $('#grand_total_1').val();
        var location_id = $('#add_plan_location_id').val();
        var is_exclusive = $('#is_exclusive').val();
        var appointment_id = $('#add_appointment_id').val();
        var base_service_id = $('#add_plan_location_id').val();

        var formData = {
            'random_id': random_id,
            'patient_id': patient_id,
            'location_id': location_id,
            'total': total,
            'payment_mode_id': payment_mode_id,
            'cash_amount': cash_amount,
            'grand_total': grand_total,
            'is_exclusive': is_exclusive,
            'appointment_id':appointment_id,
           // 'base_service_id':base_service_id,
            'package_bundles[]': []
        };

        $(".package_bundles").each(function() {
            formData['package_bundles[]'].push($(this).val());
        });
        var status = 0;
        if(cash_amount > 0){
            var status = 1;
        }

        if (random_id && (patient_id > 0) && total && status==1?payment_mode_id:true && cash_amount >= 0 && grand_total && location_id) {

            showSpinner("-save");

            $.ajax({
                type: 'get',
                url: route('admin.packages.savepackages'),
                data: formData,
                success: function (resposne) {

                    if (resposne.status) {
                        $('#successMessage').show();
                        toastr.success(" Plan successfully created")
                       closePopup('modal_appointment_plan_section');
                       reInitTable();
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

    $("#cash_amount_1").keyup(function () {
        keyfunction_grandtotal();
    });

    $("#edit_cash_amount_1").keyup(function () {
        edit_keyfunction_grandtotal();
    });


    /*save data for both predefined discounts and keyup trigger*/
    $("#EditPackage").click(function () {

        hideMessages();

        $(this).attr("disabled", true);
        var random_id = $('#edit_random_id_1').val();
        var service_id = $('#edit_service_id').val(); //Basicailly it is bundle id
        var discount_id = $('#edit_discount_id').val();
        var net_amount = $('#edit_net_amount_1').val();
        var discount_type = $('#edit_discount_type').val();
        var discount_price = $('#edit_discount_value_1').val();
        var discount_slug = $("#edit_slug_1").val();
        var package_total = $('#edit_package_total_1').val();

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

            $(".package_bundles").each(function() {
                formData['package_bundles[]'].push($(this).val());
            });

            $.ajax({
                type: 'get',
                url: route('admin.packages.savepackages_service'),
                data: formData,
                success: function (resposne) {
                    let consume = 'NO';
                    if (resposne.status) {

                        $("#edit_package_total_1").val(resposne?.data?.myarray?.total ?? 0);

                        $('#edit_plan_services').append("" +
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
                            "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + resposne.data.myarray.record.id +"' />" +
                            "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + resposne.data.myarray.record.id + ")'>"+trashBtn()+"</button>" +
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

        var random_id = $('#edit_random_id_1').val();
        var patient_id = $('#edit_parent_id').val();
        var total = $('#edit_package_total_1').val();
        var payment_mode_id = $('#edit_payment_mode_id').val();
        var cash_amount = $('#edit_cash_amount_1').val();
        var grand_total = $('#edit_grand_total_1').val();
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
            'appointment_id':appointment_id,
            'package_bundles[]': []
        };

        $(".package_bundles").each(function() {
            formData['package_bundles[]'].push($(this).val());
        });
        var status = 0;
        if(cash_amount > 0){
            var status = 1;
        }

        if (random_id && (patient_id > 0) && total && status==1?payment_mode_id:true && cash_amount >= 0 && grand_total && location_id) {

            showSpinner("-edit-save");

            $.ajax({
                type: 'get',
                url: route('admin.packages.updatepackages'),
                data: formData,
                success: function (resposne) {

                    if (resposne.status) {
                        $('#successMessage').show();
                        toastr.success(resposne.message)
                        closePopup('update_plane_form');
                        reInitTable();
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

