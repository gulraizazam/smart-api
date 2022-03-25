$(document).ready(function () {

    customDatePicker();

    $('.select2').select2();

    /*Consultancy section*/
    $("#is_exclusive_consultancy").change(function () {
        if ($(this).is(":checked")) {
            $('#is_exclusive_consultancy').val('1');
        }
        else {
            $('#is_exclusive_consultancy').val('0');
        }
        var discount_id = $('#discount_id').val();

        if (discount_id) {
            $.ajax({
                type: 'get',
                url: route('admin.appointments.checkedcustom'),
                data: {
                    'discount_id': discount_id,
                },
                success: function (response) {
                    if (response.status) {
                        $('#discount_value').val('0');
                        $('#discount_id').val('0').change();
                    } else {
                        $('#discount_id').val('0').change();
                    }
                },
            });
        }
    });

    $('#discount_id').on('change', function () {

        var is_exclusive_consultancy = $('#is_exclusive_consultancy').val();
        var location_id = $('#id_location').val();
        var appointment_id = $('#invoice_appointment_id').val();
        var discount_id = $('#discount_id').val();
        var price_for_calculation = $('#price_for_calculation').val();
        var tax_treatment_type_id = $('#tax_treatment_type_id').val();

        /*Set value cash 0 when discount change*/
        $('#cash').val('0');
        /*End*/

        $.ajax({
            type: 'get',
            url: route('admin.appointments.getconsultancycalculation'),
            data: {
                'is_exclusive_consultancy': is_exclusive_consultancy,
                'location_id': location_id,
                'appointment_id': appointment_id,
                'discount_id': discount_id,
                'price_for_calculation': price_for_calculation,
                'tax_treatment_type_id': tax_treatment_type_id,
            },
            success: function (response) {
                if (response.status) {
                    $("#discount_type").val(response.discount_type).change();
                    $("#discount_type").prop("disabled", true);
                    $("#discount_value").val(response.discount_price);
                    $("#discount_value").prop("disabled", true);
                    $("#amount").val(response.price);
                    $("#tax").val(response.tax);
                    $("#tax_amt").val(response.tax_amt);
                    $('#settle').val(response.settleamount);
                    $('#outstand').val(response.outstanding);

                    $('#settleamount_cash').val(response.settleamount);
                    $('#outstanding_cash').val(response.outstanding);

                } else {
                    if (response.discount_ava_check == 'true') {
                        $("#discount_type").val('0').change();
                        $("#discount_type").prop("disabled", false);
                        $("#discount_value").val('0');
                        $("#discount_value").prop("disabled", false);
                        $("#amount").val(response.price);
                        $("#tax").val(response.tax);
                        $("#tax_amt").val(response.tax_amt);
                        $('#settle').val(response.settleamount);
                        $('#outstand').val(response.outstanding);

                        $('#settleamount_cash').val(response.settleamount);
                        $('#outstanding_cash').val(response.outstanding);

                    } else {
                        $("#discount_type").val('0').change();
                        $("#discount_type").prop("disabled", true);
                        $("#discount_value").val('0');
                        $("#discount_value").prop("disabled", true);
                        $("#amount").val(response.price);
                        $("#tax").val(response.tax);
                        $("#tax_amt").val(response.tax_amt);
                        $('#settle').val(response.settleamount);
                        $('#outstand').val(response.outstanding);

                        $('#settleamount_cash').val(response.settleamount);
                        $('#outstanding_cash').val(response.outstanding);
                    }
                }
            }
        });
    });

    $("#discount_value").keyup(function () {
        keyfunction_custom();
    });

    $("#discount_value").blur(function () {
        keyfunction_custom();
    });

    $(document).on('change', '#discount_type', function () {
        keyfunction_custom();
    });

    $("#cash").keyup(function () {
        keyfunction_cash();
    });

    $("#cash").blur(function () {
        keyfunction_cash();
    });

    $('#amount_type').on('change', function () {
        if (this.value == "") {
            $("#addinvoice").hide();
        } else {
            $("#addinvoice").show();
        }
    });

    /*Invoice Save and also package advances*/
    $("#savepackageinformation").click(function () {

        $(this).attr("disabled", true);
        showSpinner();

        $('#wrongMessage').hide();
        $('#successMessage').hide();
        $('#definefield').hide();
        $('#customfield').hide();

        var status = true;

        var appointment_id = $('#invoice_appointment_id').val();
        var amount_create = $('#amount').val();
        var tax_create = $('#tax').val();
        if ($('#amount_type').val() == 1) {
            if ($('#cash').val() == 0) {
                $('#customfield').show();
                toastr.error("Cash must be greater than zero.")
                status = false;
                $(this).attr("disabled", false);
                hideSpinner();
                return;
            }
            var price = $('#cash').val();
            var settle = 0;
            var outstand = 0;
        } else {
            var price = $('#tax_amt').val();
            var settle = $('#settle').val();
            var outstand = $('#outstand').val();
        }
        var balance = $('#balance').val();
        var cash = $('#cash').val();
        var payment_mode_id = $('#payment_mode_id').val();
        var is_exclusive = $('#is_exclusive_consultancy').val();
        var discount_id = $('#discount_id').val();
        var discount_type = $('#discount_type').val();
        var discount_value = $('#discount_value').val();
        var created_at = $('#created_at').val();
        var tax_treatment_type_id = $('#tax_treatment_type_id').val();

        if (outstand == cash) {
            $('#definefield').hide();
            status = true;
        } else {
            if (payment_mode_id == 0) {
                $('#definefield').show();
                toastr.error("Kindly define payment mode")
                status = false;
                $(this).attr("disabled", false);
                hideSpinner();
            } else {
                $('#definefield').hide();
                status = true;
            }
        }

        if (status) {
            $.ajax({
                type: 'get',
                url: route('admin.appointments.saveconsultancyinvoice'),
                data: {
                    'appointment_id': appointment_id,
                    'amount_create': amount_create,
                    'tax_create': tax_create,
                    'price': price,
                    'balance': balance,
                    'cash': cash,
                    'settle': settle,
                    'outstand': outstand,
                    'payment_mode_id': payment_mode_id,
                    'is_exclusive': is_exclusive,
                    'discount_id': discount_id,
                    'discount_type': discount_type,
                    'discount_value': discount_value,
                    'created_at': created_at,
                    'tax_treatment_type_id': tax_treatment_type_id,
                },
                success: function (resposne) {
                    if (resposne.status == '1') {
                        $('#successMessage').show();
                        toastr.success("Invoice successfully created");
                        reInitTable();
                        closeAllPopup('.modal-dialog')
                        $("#consultancy-invoice-create").remove();
                    } else {
                        $('#wrongMessage').show();
                        toastr.error(" Something Went Wrong!");
                    }

                    hideSpinner();

                }
            });
        }
    });

    if ($("#amount_type").val() == "") {
        $("#addinvoice").hide();
    } else {
        $("#addinvoice").show();
    }
    $('.custom-datepicker').datepicker({
        format: 'yyyy-mm-dd',
    }).on('changeDate', function (ev) {
        $(this).datepicker('hide');
    });

    /*end consultancy*/


    /*Start Treatment*/
    $('#package_id_create').on('change', function () {

        $('#price_create').val('0');
        $('#balance_create').val('0');
        $('#cash_create').val('0');
        $('#settle_create').val('0');
        $('#outstand_create').val('0');

        var package_id_create = $('#package_id_create').val();
        var appointment_id_create = $('#appointment_id_create').val();
        var price_create = $('#price_create').val();

        if (price_create == 0) {
            $("#addinvoice").hide();
        } else {
            $("#addinvoice").show();
        }
        if (package_id_create) {
            $.ajax({
                type: 'get',
                url: route('admin.appointments.getplansinformation'),
                data: {
                    'package_id_create': package_id_create,
                    'appointment_id_create': appointment_id_create
                },
                success: function (resposne) {

                    if (resposne.status == '1') {
                        $('#table_1').find('tbody').remove();
                        jQuery.each(resposne.packagebundles, function (i, packagebundles) {

                            if (packagebundles.discount_id == null) {
                                var discountname = '-';
                            } else {
                                var discountname = packagebundles.discountname;
                            }
                            if (packagebundles.discount_type == null) {
                                var discounttype = '-';
                            } else {
                                var discounttype = packagebundles.discount_type;
                            }
                            if (packagebundles.discount_price == null) {
                                var discountprice = '0.00';
                            } else {
                                var discountprice = packagebundles.discount_price;
                            }
                            $('#table_1').append("<tr id='table_1' class='HR_" + packagebundles.id + "'><td><a href='javascript:void(0)' onClick='toggle(" + packagebundles.id + ")'>" + packagebundles.bundlename + "</a></td><td>" + parseInt(packagebundles.service_price).toLocaleString() + "</td><td>" + discountname + "</td><td>" + discounttype + "</td><td>" + discountprice + "</td><td>" + parseInt(packagebundles.tax_exclusive_net_amount).toLocaleString() + "</td><td>" + packagebundles.tax_percenatage + "</td><td>" + packagebundles.tax_including_price.toLocaleString()+ "</td></tr>");

                            jQuery.each(resposne.packageservices, function (i, packageservices) {

                                if (packageservices.package_bundle_id == packagebundles.id) {

                                    if (packageservices.is_consumed == '0') {
                                        var consume = 'NO';
                                        $('#table_1').append("<tr class='HR_" + packagebundles.id + " " + packagebundles.id + "'><td><input type='checkbox' class='invoicecheckbox' value=" + packageservices.id + "></td><td>" + packageservices.servicename + "</td><td>Amount : " + packageservices.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + packageservices.tax_percenatage + "</td><td>Tax Amt. : " + packageservices.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                                    } else {
                                        var consume = 'YES';
                                        $('#table_1').append("<tr class='HR_" + packagebundles.id + " " + packagebundles.id + "'><td></td><td>" + packageservices.servicename + "</td><td>Amount : " + packageservices.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + packageservices.tax_percenatage + "</td><td>Tax Amt. : " + packageservices.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                                    }
                                }
                            });
                        });
                        $('.invoicecheckbox').click(function () {
                            $(".invoicecheckbox").prop('checked', false);
                            $(this).prop('checked', true);
                            /*Here I need to set the bundle id so I can Checked on save exclusive*/
                            $('#checked_bundle_id').val($(this).val());
                            calculateInvoice(id = $(this).val());
                        });
                    }
                }
            });
        }
    });

    $('#package_id_create').change();

    /*Invoice Save and also package advances*/
    $("#treatmne_savepackageinformation").click(function () {

        $(this).attr("disabled", true);
        showSpinner();

        $('#wrongMessage').hide();
        $('#successMessage').hide();
        $('#definefield').hide();
        $('#definetreatment').hide();

        var appointment_id = $('#appointment_id_create').val();
        var appointment_id_consultancy = $('#appointment_link_cons').val();
        var package_id = $('#package_id_create').val();
        var amount_create = $('#amount_create').val();
        var tax_create = $('#tax_create').val();
        var price = $('#price_create').val();
        var balance = $('#balance_create').val();
        var cash = $('#cash_create').val();
        var settle = $('#settle_create').val();
        var outstand = $('#outstand_create').val();
        var package_service_id = $('#package_service_id').val();
        var package_mode_id = $('#payment_mode_id').val();
        var checked_treatment = $('#checked_treatment').val();
        var created_at = $('#created_at').val();
        var tax_treatment_type_id = $('#tax_treatment_type_id').val();
        var  remaining = $('#remaining').val();
        var status_checked_treatment = true;

        if(checked_treatment == 0){
            var exclusive_or_bundle = $('#checked_bundle_id').val();
            if(exclusive_or_bundle == 0){
                //if treatment belongs to plan but not select to I set that varibale
                var status_checked_treatment = false;
            }
        } else {
            var exclusive_or_bundle = $('#is_exclusive').val();
        }

        var status = true;

        if (cash > 0) {
            if(package_mode_id=='') {
                status = false;
            }
        }
        if(status_checked_treatment){
            if(status){
                if (appointment_id && price && balance && cash && settle && outstand) {
                    $.ajax({
                        type: 'get',
                        url: route('admin.appointments.saveinvoice'),
                        data: {
                            'appointment_id': appointment_id,
                            'package_id': package_id,
                            'amount_create':amount_create,
                            'tax_create':tax_create,
                            'price': price,
                            'balance': balance,
                            'cash': cash,
                            'settle': settle,
                            'outstand': outstand,
                            'package_service_id': package_service_id,
                            'package_mode_id': package_mode_id,
                            'checked_treatment':checked_treatment,
                            'exclusive_or_bundle':exclusive_or_bundle,
                            'created_at':created_at,
                            'appointment_id_consultancy':appointment_id_consultancy,
                            'tax_treatment_type_id':tax_treatment_type_id,
                            'remaining':remaining
                        },
                        success: function (resposne) {
                            if (resposne.status == '1') {
                                $('#successMessage').show();
                                toastr.success("Invoice successfully created");
                                reInitTable();
                                closeAllPopup('.modal-dialog')
                                $("#treatment-invoice-create").remove();
                            } else {
                                $('#wrongMessage').show();
                                toastr.error(" Something Went Wrong!")
                            }

                            hideSpinner();
                        }
                    });
                }
            } else {
                $('#definefield').show();
                toastr.error("Kindly define payment mode")
            }
        } else {
            $('#definetreatment').show();
            toastr.error("Kindly select the treatment")
            $(this).attr("disabled", false);
            hideSpinner();
        }

    });

    /*keyup function trigger whan we enter cash amount*/
    $("#cash_create").keyup(function () {
        keyfunction();
    });

    /*blur function trigger whan we enter cash value*/
    $("#cash_create").blur(function () {
        keyfunction();
    });

    /*Trigger function when popup load*/
    $("#cash_create").blur();

    /*Make functional exclusive checked box*/
    $("#is_exclusive").change(function () {
        if ($(this).is(":checked")) {
            $('#is_exclusive').val('1');
        }
        else {
            $('#is_exclusive').val('0');
        }
        var price_orignal = $('#orignal_price_h').val();
        var location_id = $('#location_id_tax').val();
        var is_exclusive =  $('#is_exclusive').val();
        var tax_treatment_type_id =  $('#tax_treatment_type_id').val();
        if (price_orignal) {
            $.ajax({
                type: 'get',
                url: route('admin.appointments.getcalculatedPriceExclusicecheck'),
                data: {
                    'price_orignal': price_orignal,
                    'location_id': location_id,
                    'is_exclusive': is_exclusive,
                    'tax_treatment_type_id':tax_treatment_type_id,
                },
                success: function (resposne) {
                    if (resposne.status) {
                        $('#amount_create').val(resposne.amount_create);
                        $('#tax_create').val(resposne.tax_create);
                        $('#price_create').val(resposne.price);
                        $('#cash_create').val('0');
                        $("#outstand_create").val(resposne.outstdanding);
                        $("#addinvoice").hide();
                    }
                },
            });
        }

    });

    /*End Treatment*/

});


/*key function for net amount of service*/
function keyfunction_custom() {
    $('#percentageMessage').hide();
    var is_exclusive_consultancy = $('#is_exclusive_consultancy').val();
    var price = $('#price_for_calculation').val();
    var discount_id = $('#discount_id').val();
    var discount_type = $('#discount_type').val();
    var discount_value = $('#discount_value').val();
    var location_id = $('#id_location').val();
    var tax_treatment_type_id = $('#tax_treatment_type_id').val();

    var div = $(this).parents();
    if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#percentageMessage').show();
            toastr.error("Your discount limit exceeded.");
            return false;
        } else {
            $('#percentageMessage').hide();
        }
    }
    $.ajax({
        type: 'get',
        url: route('admin.appointments.checkedcustom'),
        data: {
            'discount_id': discount_id,
        },
        success: function (response) {
            if (response.status) {
                if (price && discount_id != 0 && discount_value && discount_type) {
                    $.ajax({
                        type: 'get',
                        url: route('admin.appointments.getcustomcalculation'),
                        data: {
                            'price': price, //Basicailly it is bundle id
                            'discount_id': discount_id,
                            'discount_value': discount_value,
                            'discount_type': discount_type,
                            'location_id': location_id,
                            'is_exclusive_consultancy': is_exclusive_consultancy,
                            'tax_treatment_type_id': tax_treatment_type_id
                        },
                        success: function (response) {
                            if (response.status) {
                                $("#amount").val(response.price);
                                $("#tax").val(response.tax);
                                $("#tax_amt").val(response.tax_amt);
                                $('#settle').val(response.settleamount);
                                $('#outstand').val(response.outstanding);

                                $('#settleamount_cash').val(response.settleamount);
                                $('#outstanding_cash').val(response.outstanding);

                                if (response.outstanding == '0') {
                                    $("#addinvoice").show();
                                } else {
                                    $("#addinvoice").hide();
                                }

                            } else {
                                $('#percentageMessage').show();
                                toastr.error("Your discount limit exceeded.");
                                $("#amount").val('');
                            }
                        },
                    });
                }
            }
        },
    });
}

/*End*/

/*function to check cash is equal to amt amount or not*/
function keyfunction_cash() {

    var price = $('#tax_amt').val();
    /*tax amt. amount*/
    var balance = $('#balance').val();
    var cash = $('#cash').val();
    var settleamount = $('#settle').val();
    var outstanding = $('#outstand').val();
    var amount_type = $('#amount_type').val();

    if (cash == 0 || cash == '') {
        $('#paymentmode').hide();
    } else {
        $('#paymentmode').show();
    }

    if (!cash && cash == 0) {
        var settle_cash = $("#settleamount_cash").val();
        var outstand_cash = $("#outstanding_cash").val();
        $("#settle").val(settle_cash);
        $("#outstand").val(outstand_cash);
    }
    var div = $(this).parents();
    if (price && balance && cash) {
        $.ajax({
            type: 'get',
            url: route('admin.appointments.getfinalcalculation'),
            data: {
                'price': price,
                'balance': balance,
                'cash': cash,
                'settleamount': settleamount,
                'outstanding': outstanding,
                'amount_type': amount_type
            },
            success: function (resposne) {
                if (resposne.status == '1') {
                    $("#settle").val(resposne.settleamount);
                    $("#outstand").val(resposne.outstdanding);
                    if (resposne.outstdanding == '0') {
                        $("#addinvoice").show();
                    } else {
                        $("#addinvoice").hide();
                    }
                }
            },
        });
    }
}


/*keyup function for $net_amount*/
function keyfunction() {
    var price_create = $('#price_create').val();
    var balance_create = $('#balance_create').val();
    var cash_create = $('#cash_create').val();
    var settleamount_for_zero = $('#settleamount_for_zero').val();
    var outstanding_for_zero = $('#outstanding_for_zero').val();

    if (cash_create == 0 || cash_create == '') {
        $('#paymentmode').hide();
    } else {
        $('#paymentmode').show();
    }

    if (!cash_create) {
        $("#settle_create").val(settleamount_for_zero);
        $("#outstand_create").val(outstanding_for_zero);
    }
    var div = $(this).parents();
    if (price_create && balance_create && cash_create) {
        $.ajax({
            type: 'get',
            url: route('admin.appointments.getinvoicecalculation'),
            data: {
                'price_create': price_create,
                'balance_create': balance_create,
                'cash_create': cash_create,
                'settleamount_for_zero': settleamount_for_zero,
                'outstanding_for_zero': outstanding_for_zero
            },
            success: function (resposne) {
                if (resposne.status == '1') {

                    $("#settle_create").val(resposne.settleamount);
                    $("#outstand_create").val(resposne.outstdanding);
                    if (resposne.outstdanding == '0') {
                        $("#addinvoice").show();
                    } else {
                        $("#addinvoice").hide();
                    }
                }
            },
        });
    }
}

/*Calcuate invoice data and return data according to price*/
function calculateInvoice(id) {
    $('#wrongMessage').hide();
    $('#definetreatment').hide();

    var appointment_id_create = $('#appointment_id_create').val();
    var package_id_create = $('#package_id_create').val();

    $.ajax({
        type: 'get',
        url: route('admin.appointments.getpackageprice'),
        data: {
            'package_service_id': id,
            'appointment_id_create': appointment_id_create,
            'package_id_create': package_id_create
        },
        success: function (resposne) {
            if (resposne.status == '1') {

                $('#amount_create').val(resposne.amount);
                $('#tax_create').val(resposne.tax_price);
                $('#price_create').val(resposne.serviceprice);
                $('#remaining').val(resposne.remaining);
                $('#balance_create').val(resposne.balance);
                $('#settle_create').val(resposne.settleamount);
                $('#outstand_create').val(resposne.outstanding);
                $('#settleamount_for_zero').val(resposne.settleamount);
                $('#outstanding_for_zero').val(resposne.outstanding);
                $('#package_service_id').val(id);

                if (resposne.outstanding == '0') {
                    $("#addinvoice").show();
                } else {
                    $("#addinvoice").hide();
                }

            } else {
                $('#wrongMessage').show();
                toastr.error(" Something Went Wrong!");
            }
        },
    });
}

/*Toogle Function for display and hide package content*/
function toggle(id) {
    $("." + id).toggle();
}


