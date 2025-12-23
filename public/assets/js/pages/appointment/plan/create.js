function createAppointmentPlan(url, id) {

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
    let patient = response.data.patient;
    let random_id = response.data.random_id;
    let appointmentinformation = response.data.appointmentinformation;
    let paymentmodes = response.data.paymentmodes;

    let location_options = '<option value="">Select Centre</option>';

    if (locations) {
        Object.entries(locations).forEach(function (location) {
            location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
        });
    }

    let discount_options = '<option value="">Select Discount</option>';

    if (discounts) {
        Object.values(discounts).forEach(function (discount) {
            discount_options += '<option value="' + discount.id + '">' + discount.name + '</option>';
        });
    }

    let payment_options = '<option value="">Select Payment Mode</option>';
    if (paymentmodes) {
        Object.entries(paymentmodes).forEach(function (paymentmode) {
            payment_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
        });
    }

    $("#add_discount_id").html(discount_options);
    $("#payment_mode_id_1").html(payment_options);

    $("#add_location_id").html(location_options).val(appointmentinformation?.location_id);
    $("#patient-name").html(patient.name);
    $("#random_id_1").val(random_id);
    $("#client_id").val(patient.id).trigger('change');
    $("#parent_id_1").val(patient.id).trigger('change');
    setTimeout(function () {
        $("#add_discount_type").attr('disabled', true);
        $('#add_discount_type').val('').change();
        $('#add_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    }, 250)

    getAppointments(appointmentinformation);

    getServices();


}

function getServices() {

    hideMessages();

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
            $('#datanotexist').show();
        }
    });
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

function getAppointments(appoitmentInfo) {

    hideMessages();

    var location_id = $('#add_location_id').val();
    var patient_id = $('#client_id').val();

    $("#add_service_id").val('0').trigger('change')

    if (location_id) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getappointmentinfo'),
            data: {
                'patient_id': patient_id,
                'location_id': location_id
            },
            success: function (resposne) {
                if (resposne.status) {

                    let appointments = resposne.data.appointments;

                    let options = '';

                    jQuery.each(appointments, function (i, appointment) {
                        options += '<option value="' + appointment.id + '">' + appointment.name + '</option>';
                    });

                    $("#add_appointment_id").html(options);

                } else {
                    let options = '<option value="" >Select Appointment</option>';

                    $("#add_appointment_id").html(options);
                }
            },
        });
    } else {
        $('#inputfieldMessage').show();
    }
}

function getServiceDiscount($this) {

    hideMessages();

    var service_id = $this.val();
    var patient_id = $('#client_id').val();
    var location_id = $('#add_location_id').val();

    //$("#add_discount_id").val('0').trigger('change');
    setTimeout(function () {
        $('#discount_value_1').val('');
        $("#discount_value_1").attr('disabled', true);
        $("#add_discount_type").val('').change();
        $("#add_discount_type").attr('disabled', true);
        $('#add_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    }, 500)

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
                $('#add_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
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

                }
            },
        });
    }
    if ((service_id == null || service_id == '') && patient_id != '') {
        $("#add_discount_id").html('<option value="">Select Discount</option>');
        $("#add_discount_type").attr('disabled', true);
        setTimeout(function () {
            $('#discount_value_1').val('');
            $("#add_discount_type").val('').change();
            $('#add_service_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            $('#add_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            $("#net_amount_1").val('');
            return false;
        }, 500)
        return false;
    }

}

function getDiscountInfo($this) {

    hideMessages();

    var service_id = $('#add_service_id').val(); //Basicailly it is bundle id
    var discount_id = $this.val();
    var patient_id = $('#add_patients_id').val();
    setTimeout(function () {
        $('#add_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    }, 500)
    if (service_id == null && (discount_id == null || discount_id == '')) {

        $("#add_discount_type").prop("disabled", false);
        $("#add_discount_type").val('').trigger('change');
        $("#discount_value_1").prop("disabled", false);
        $("#discount_value_1").val('');
        $("#net_amount_1").prop("disabled", false);
        $("#net_amount_1").val('');
        $("#slug_1").val('not_custom');

    } else if ((discount_id == null || discount_id == '') && service_id != null) {

        $("#add_discount_type").prop("disabled", true);
        $("#add_discount_type").val('').trigger('change');
        $("#discount_value_1").prop("disabled", true);
        $("#discount_value_1").val('');
        $("#slug_1").val('not_custom');
        setTimeout(function () {
            $('#add_discount_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            if ($("#net_amount_1").val() == '') {
                $("#add_service_id").val($("#add_service_id").val()).change();
            } else {
                getServiceDiscount($("#add_service_id"));
            }
        }, 100);

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
                    'discount_id': discount_id,
                    'patient_id': patient_id
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

function getDiscountValue($this) {

    //inputSpinner(true, 'AddPackage')
    hideMessages();

    var service_id = $('#add_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#add_discount_id').val();
    var discount_type = $('#add_discount_type').val();
    var discount_value = $this.val();
    var patient_id = $('#add_patients_id').val();
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
                'patient_id': patient_id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#net_amount_1").val(parseFloat(resposne.data.net_amount).toFixed(2));
                    $("#net_amount_1").prop("disabled", true, 'AddPackage');
                    inputSpinner(false)
                } else {
                    $("#AddPackage").attr("disabled", true);
                    $('#DiscountRange').show();
                    //inputSpinner(false, 'AddPackage')
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
    var patient_id = $('#add_patients_id').val();
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
                'patient_id': patient_id
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
                    if (resposne?.data?.grand_total == 1 || resposne?.data?.grand_total == 0) {
                        $("#grand_total_1").val(0);
                    } else {
                        $("#grand_total_1").val(resposne?.data?.grand_total ?? 0);
                    }
                } else {
                    $('#wrongMessage').show();
                }
            },
        });
    } else {
        $('#inputfieldMessage').show();
    }
}

/*End*/

function toggle(id) {
    $("." + id).toggle();
}

/*Delete The record*/
function deletePlanRow(id) {

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

    var package_total = $('#package_total_1').val();

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
                $("#package_total_1").val(resposne?.data?.total ?? 0);

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
/*End*/

function hideMessages() {

    $('#wrongMessage').hide();
    $('#inputfieldMessage').hide();
    $('#percentageMessage').hide();
    $('#AlreadyExitMessage').hide();
    $('#DiscountRange').hide();
    $('#datanotexist').hide();
    $('#successMessage').hide();
}


jQuery(document).ready(function () {

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
        var location_id = $('#add_location_id').val();
        var user_id = $('#add_patients_id').val();
        if (service_id && net_amount && location_id) {

            showSpinner("-add");

            if (discount_slug == 'custom' && discount_id != '') {
                if (discount_price == '') {
                    hideSpinner("-add");
                    toastr.error("Please add discount value");
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
                'user_id': user_id,
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
                            $('#plan_services').append("<tr class='inner_records_hr HR_" + resposne.data.myarray.record.id + " " + resposne.data.myarray.record.id + "'><td></td><td>" + record_detail.name + "</td><td>Amount : " + record_detail.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + record_detail.tax_percenatage + "</td><td>Total Amount. : " + record_detail.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                        });

                        // toggle(resposne.data.myarray.record.id);

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
        var patient_id = $('#client_id').val();
        var total = $('#package_total_1').val();
        var payment_mode_id = $('#payment_mode_id_1').val();
        var cash_amount = $('#cash_amount_1').val();
        var grand_total = $('#grand_total_1').val();
        var location_id = $('#add_location_id').val();
        var is_exclusive = $('#is_exclusive').val();
        var appointment_id = $('#add_appointment_id').val();
        var base_service_id = $('#add_location_id').val();

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
            // 'base_service_id':base_service_id,
            'package_bundles[]': []
        };

        $(".package_bundles").each(function () {
            formData['package_bundles[]'].push($(this).val());
        });
        var status = 0;
        if (cash_amount > 0) {
            var status = 1;
        }

        if (payment_mode_id == '' && cash_amount > 0) {
            toastr.error("Please select the payment mode");
            return false;
        }
        if (total <= 0) {
            toastr.error("Please add atleast one session");
            return false;
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

});

