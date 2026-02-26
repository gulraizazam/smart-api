/**
 * Check if a package service is locked from consumption.
 * Returns a lock reason string if locked, or null if consumable.
 *
 * Rules:
 * 1. Ordering: Within a config_group, lower consumption_order services must be consumed first
 *    — SKIPPED if plan is fully paid (payments >= total plan value)
 * 2. Payment: Total plan payments must cover total consumed + this service price
 *    (free services skip the payment check but still require ordering when not fully paid)
 *
 * consumption_order: 0=normal, 1=BUY, 2=discounted GET, 3=free GET
 */
function checkConsumptionLock(packageservice, totalPlanPayments, totalConsumedValue, configGroupServices, isPlanFullyPaid) {
    var consumptionOrder = parseInt(packageservice.consumption_order) || 0;
    var configGroupId = packageservice.config_group_id || null;
    var servicePrice = parseFloat(packageservice.tax_including_price) || 0;

    // Normal services (consumption_order = 0) only check payment coverage
    if (consumptionOrder === 0) {
        if (servicePrice > 0 && totalPlanPayments < (totalConsumedValue + servicePrice)) {
            var shortfall = Math.ceil((totalConsumedValue + servicePrice) - totalPlanPayments);
            return 'Insufficient payment. Collect Rs. ' + shortfall.toLocaleString() + ' first.';
        }
        return null;
    }

    // Ordering check: enforce BUY-before-GET within configurable discount groups
    // Skip ordering if plan is fully paid (matches backend saveinvoice logic)
    if (!isPlanFullyPaid && configGroupId && configGroupServices[configGroupId]) {
        var siblings = configGroupServices[configGroupId];
        for (var i = 0; i < siblings.length; i++) {
            var sibling = siblings[i];
            if (sibling.id == packageservice.id) continue;
            var siblingOrder = parseInt(sibling.consumption_order) || 0;
            if (siblingOrder < consumptionOrder && sibling.is_consumed == '0') {
                if (siblingOrder === 1) {
                    return 'Consume paid sessions first before discounted/free sessions.';
                } else if (siblingOrder === 2) {
                    return 'Consume discounted sessions first before free sessions.';
                }
                return 'Consume higher-priority sessions first.';
            }
        }
    }

    // Payment coverage check (skip for free services)
    if (servicePrice > 0 && totalPlanPayments < (totalConsumedValue + servicePrice)) {
        var shortfall = Math.ceil((totalConsumedValue + servicePrice) - totalPlanPayments);
        return 'Insufficient payment. Collect Rs. ' + shortfall.toLocaleString() + ' first.';
    }

    return null;
}

$(document).ready(function () {

    customDatePicker();

    $('.select2').select2();

    // Check outstanding on page load
    checkOutstandingAmount();

    /*Consultancy section*/
    $(document).on("change", "#is_exclusive_consultancy", function () {
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

    $(document).on('change', '#discount_id', function () {

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

                    $(".discount_type_section").show();
                    $(".discount_value_section").show();
                    $("#discount_type").val(response.discount_type).change();
                    $("#discount_type").prop("disabled", true);
                    $("#discount_value").val(response.discount_price);
                    $("#discount_value").prop("disabled", true);
                    $("#amount").text(response.price);
                    $(".amount").val(response.price);
                    $("#tax").text(response.tax);
                    $(".tax").val(response.tax);
                    $("#tax_amt").text(response.tax_amt);
                    $(".tax_amt").val(response.tax_amt);
                    $('#settle').text(response.settleamount);
                    $('.settle').val(response.settleamount);
                    $('#outstand').text(response.outstanding);
                    $('.outstand').val(response.outstanding);

                    $('#settleamount_cash').val(response.settleamount);
                    $('#outstanding_cash').val(response.outstanding);

                } else {
                    if (response.discount_ava_check == 'true') {
                        $("#discount_type").val('0').change();
                        $("#discount_type").prop("disabled", false);
                        $("#discount_value").val('0');
                        $("#discount_value").prop("disabled", false);
                        $("#amount").text(response.price);
                        $(".amount").val(response.price);
                        $("#tax").text(response.tax);
                        $(".tax").val(response.tax);
                        $("#tax_amt").text(response.tax_amt);
                        $(".tax_amt").val(response.tax_amt);
                        $('#settle').text(response.settleamount);
                        $('.settle').val(response.settleamount);
                        $('#outstand').text(response.outstanding);
                        $('.outstand').val(response.outstanding);

                        $('#settleamount_cash').val(response.settleamount);
                        $('#outstanding_cash').val(response.outstanding);

                    } else {
                        $("#discount_type").val('0').change();
                        $("#discount_type").prop("disabled", true);
                        $("#discount_value").val('0');
                        $("#discount_value").prop("disabled", true);
                        $("#amount").text(response.price);
                        $(".amount").val(response.price);
                        $("#tax").text(response.tax);
                        $(".tax").val(response.tax);
                        $("#tax_amt").text(response.tax_amt);
                        $(".tax_amt").val(response.tax_amt);
                        $('#settle').text(response.settleamount);
                        $('.settle').val(response.settleamount);
                        $('#outstand').text(response.outstanding);
                        $('.outstand').val(response.outstanding);

                        $('#settleamount_cash').val(response.settleamount);
                        $('#outstanding_cash').val(response.outstanding);
                    }
                }
            }
        });
    });

    $(document).on("keyup", "#discount_value", function () {
        keyfunction_custom();
    });

    $(document).on("blur", "#discount_value", function () {
        keyfunction_custom();
    });

    $(document).on('change', '#discount_type', function () {
        keyfunction_custom();
    });

    $(document).on("keyup", "#cash", function () {
        keyfunction_cash();
    });

    $(document).on("blur", "#cash", function () {
        keyfunction_cash();
    });

    $(document).on('change', '#amount_type', function () {
        if (this.value == "") {
            $("#addinvoice").hide();
        } else {
            $("#addinvoice").show();
        }
    });

    /*Invoice Save and also package advances*/
    $(document).on("click", "#savepackageinformation", function () {

        $(this).attr("disabled", true);
        showSpinner();

        $('#wrongMessage').hide();
        $('#successMessage').hide();
        $('#definefield').hide();
        $('#customfield').hide();

        var status = true;
        var numbers = /^[-+]?[0-9]+$/;
        if ($('#cash').val() == "" && $('#cash').val().match(numbers) == null) {
            toastr.warning("Amount field can not be empty")
            status = false;
            $(this).attr("disabled", false);
            hideSpinner();
            return;
        } else if ($('#cash').val() < 0) {
            toastr.warning("Amount can not be negative value")
            status = false;
            $(this).attr("disabled", false);
            hideSpinner();
            return;
        }
        var appointment_id = $('#invoice_appointment_id').val();
        var amount_create = $('.amount').val();
        var tax_create = $('.tax').val();
        /*if ($('#amount_type').val() == 1) {
            if ($('#cash').val() == 0) {
                $('#customfield').show();
                toastr.warning("Cash must be greater than zero.")
                status = false;
                $(this).attr("disabled", false);
                hideSpinner();
                return;
            }
            var price = $('#cash').val();
            var settle = 0;
            var outstand = 0;
        } else {
            var price = $('.tax_amt').val();
            var settle = $('.settle').val();
            var outstand = $('.outstand').val();
        }*/

        var price = $('#cash').val();
        var settle = 0;
        var outstand = 0;

        var balance = $('.balance').val();
        var cash = $('#cash').val();
        var payment_mode_id = $('#payment_mode_id').val();
        var is_exclusive = $('#is_exclusive_consultancy').val();
        var discount_id = $('#discount_id').val();
        var discount_type = $('#discount_type').val();
        var discount_value = $('#discount_value').val();
        var created_at = $('#created_at').val();
        var tax_treatment_type_id = $('#tax_treatment_type_id').val();

        if (outstand == cash) {
            status = true;
        } else {
            if (payment_mode_id == 0) {
                toastr.error("Kindly define payment mode")
                status = false;
                $(this).attr("disabled", false);
                hideSpinner();
            } else {
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
                    hideSpinner();
                    $('#savepackageinformation').prop('disabled', false);
                    
                    if (resposne.status == false) {
                        toastr.error("Invoice can not be generated in past and future dates!");
                    } else if (resposne.status) {
                        let invoice_id = resposne.data.invoice_id;
                        toastr.success("Invoice successfully created");
                        
                        // Enable Print Consultation Form button after invoice is created
                        $('#savepackageinformation_form').prop('disabled', false);
                        // Store invoice_id for Print Consultation Form button
                        $('#savepackageinformation_form').data('invoice-id', invoice_id);
                        
                        reInitTable('consultancy');
                        
                        // Open print invoice in new tab using setTimeout to prevent UI blocking
                        setTimeout(function() {
                            window.open(route('admin.invoices.invoice_pdf', [invoice_id, 'print', 1]), '_blank');
                        }, 100);
                    } else {
                        toastr.error(" Something Went Wrong!");
                    }
                },
                error: function() {
                    hideSpinner();
                    $('#savepackageinformation').prop('disabled', false);
                    toastr.error("Something went wrong!");
                }
            });
        }
    });

    /*Print Consultation Form button click handler*/
    $(document).on("click", "#savepackageinformation_form", function () {
        let invoice_id = $(this).data('invoice-id');
        if (invoice_id) {
            // Open print consultation form in new tab
            window.open(route('admin.invoices.invoice_pdf', [invoice_id]), '_blank');
            
            // Close the modal after printing
            closeAllPopup('.modal-dialog');
            $("#consultancy-invoice-create").remove();
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
    $(document).on('change', '#package_id_create', function () {

        $('#price_create').text('0');
        $('.price_create').val('0');
        $('#balance_create').val('0');
        $('#cash_create').val('0');
        $('#settle_create').text('0');
        $('.settle_create').val('0');
        $('#outstand_create').text('0');
        $('.outstand_create').val('0');

        var package_id_create = $('#package_id_create').val();
        var appointment_id_create = $('#appointment_id_create').val();
        var price_create = $('.price_create').val();

        if (price_create == 0) {
            $("#treatment_addinvoice").hide();
        } else {
            $("#treatment_addinvoice").show();
        }
        var TaxPrice = [];
        if (package_id_create) {
            $.ajax({
                type: 'get',
                url: route('admin.appointments.getplansinformation'),
                data: {
                    'package_id_create': package_id_create,
                    'appointment_id_create': appointment_id_create
                },
                // success: function (resposne) {
                //     console.log(resposne);
                //     if (resposne.status == '1') {
                //         $('#table_1').find('tbody').html('');
                //         jQuery.each(resposne.packagebundles, function (i, packagebundles) {
                //             //packagebundles = resposne.packagebundles;
                //             if (packagebundles.discount_id == null) {
                //                 var discountname = '-';
                //             } else {
                //                 var discountname = packagebundles.discountname;
                //             }
                //             if (packagebundles.discount_type == null) {
                //                 var discounttype = '-';
                //             } else {
                //                 var discounttype = packagebundles.discount_type;
                //             }
                //             if (packagebundles.discount_price == null) {
                //                 var discountprice = '0.00';
                //             } else {
                //                 var discountprice = packagebundles.discount_price;
                //             }
                //             $('#table_1').append("<tr class='HR_" + packagebundles.id + "'><td><a href='javascript:void(0)' onClick='toggle(" + packagebundles.id + ")'>" + packagebundles.bundlename + "</a></td><td>" + parseInt(packagebundles.service_price).toLocaleString() + "</td><td>" + discountname + "</td><td>" + discounttype + "</td><td>" + discountprice + "</td><td>" + parseInt(packagebundles.tax_exclusive_net_amount).toLocaleString() + "</td><td>" + packagebundles.tax_percenatage + "</td><td>" + packagebundles.tax_including_price.toLocaleString()+ "</td></tr>");
                //             jQuery.each(resposne.packageservices, function (i, packageservices) {

                //                 if (packageservices.package_bundle_id == packagebundles.id) {

                //                     if (packageservices.is_consumed == '0') {
                //                         TaxPrice.push({
                //                             'taxprice': packageservices.tax_including_price,
                //                             'id': packagebundles.id,
                //                             'childID': packageservices.id
                //                         });
                //                         var consume = 'NO';
                //                         $('#table_1').append("<tr class='HR_" + packagebundles.id + " " + packagebundles.id + "'><td style='vertical-align:middle;'></td><td>" + packageservices.servicename + "</td><td>Amount : " + packageservices.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + packageservices.tax_percenatage + "</td><td>Tax Amt. : " + packageservices.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                //                     } else {
                //                         var consume = 'YES';
                //                         $('#table_1').append("<tr class='HR_" + packagebundles.id + " " + packagebundles.id + "'><td style='vertical-align:middle;'></td><td>" + packageservices.servicename + "</td><td>Amount : " + packageservices.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + packageservices.tax_percenatage + "</td><td>Tax Amt. : " + packageservices.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
                //                     }
                //                 }
                //             });
                //         });
                //         $(document).on('click', '.invoicecheckbox', function () {                            
                //             $(".invoicecheckbox").prop('checked', false);
                //             $(this).prop('checked', true);
                //             /*Here I need to set the bundle id so I can Checked on save exclusive*/
                //             $('#checked_bundle_id').val($(this).val());
                //             calculateInvoice($(this).val(), 'treatment_');
                //         });
                //         const maxVal = TaxPrice.reduce((max, obj) => (obj.taxprice > max.taxprice ? obj : max), TaxPrice[0]);
                //         console.log('TaxPrice', TaxPrice);
                //         console.log('maxVal', maxVal);
                //         $('#treatment-invoice-create').find('#table_1').find('tr.HR_'+maxVal.id+'.'+maxVal.id+'').find('td:first-child').append("<input type='checkbox' class='invoicecheckbox' value=" + maxVal.childID + ">");
                //     }
                // }
                success: function (resposne) {

                    if (resposne.status == '1') {
                        $('#table_1').find('tbody').html('');

                        // Store payment/consumption data for lock checks
                        var totalPlanPayments = parseFloat(resposne.total_plan_payments) || 0;
                        var totalConsumedValue = parseFloat(resposne.total_consumed_value) || 0;
                        var isPlanFullyPaid = resposne.is_plan_fully_paid || false;
                        var configGroupServices = resposne.config_group_services || {};

                        jQuery.each(resposne.packagebundles, function (i, packagebundles) {

                            if (packagebundles.discount_id == null) {
                                var discountname = '-';
                            } else {
                                var discountname = packagebundles.discountname;
                            }
                            if (packagebundles.discount_price == null) {
                                var discountprice = '0.00';
                            } else {
                                var discountprice = packagebundles.discount_price;
                            }
                            // Calculate tax amount as difference between Amount and Discount Price
                            var taxAmount = packagebundles.tax_including_price - parseInt(packagebundles.tax_exclusive_net_amount);
                            $('#table_1').append("<tr class='HR_" + packagebundles.id + "'><td><a href='javascript:void(0)' onClick='toggle(" + packagebundles.id + ")'>" + packagebundles.bundlename + "</a></td><td>" + parseInt(packagebundles.service_price).toLocaleString() + "</td><td>" + parseInt(packagebundles.tax_exclusive_net_amount).toLocaleString() + "</td><td>" + Math.ceil(taxAmount).toLocaleString() + "</td><td>" + packagebundles.tax_including_price.toLocaleString() + "</td></tr>");

                            jQuery.each(resposne.packageservices, function (i, packageservices) {

                                if (packageservices.package_bundle_id == packagebundles.id) {
                                    var serviceTaxAmt = packageservices.tax_including_price - packageservices.tax_exclusive_price;

                                    if (packageservices.is_consumed == '0') {
                                        var consume = 'NO';
                                        // Check consumption lock for configurable discount services
                                        var lockReason = checkConsumptionLock(packageservices, totalPlanPayments, totalConsumedValue, configGroupServices, isPlanFullyPaid);

                                        if (lockReason) {
                                            // Locked: show disabled checkbox with lock icon and tooltip
                                            $('#table_1').append("<tr class='HR_" + packagebundles.id + " " + packagebundles.id + "' style='opacity: 0.6;'><td><span class='consumption-locked' title='" + lockReason + "' style='cursor: help; color: #e74c3c;'>&#128274;</span></td><td>" + packageservices.servicename + "</td><td>Amount : " + packageservices.tax_exclusive_price.toLocaleString() + "</td><td>Tax: " + Math.ceil(serviceTaxAmt).toLocaleString() + "</td><td colspan='3'><span style='color: #e74c3c; font-size: 11px;'>" + lockReason + "</span></td></tr>");
                                        } else {
                                            // Unlocked: show checkbox
                                            $('#table_1').append("<tr class='HR_" + packagebundles.id + " " + packagebundles.id + "'><td><input type='checkbox' class='invoicecheckbox' value=" + packageservices.id + " data-price='" + packageservices.tax_including_price + "'></td><td>" + packageservices.servicename + "</td><td>Amount : " + packageservices.tax_exclusive_price.toLocaleString() + "</td><td>Tax: " + Math.ceil(serviceTaxAmt).toLocaleString() + "</td><td colspan='3'>Is Consume : " + consume + "</td></tr>");
                                        }
                                    } else {
                                        var consume = 'YES';
                                        $('#table_1').append("<tr class='HR_" + packagebundles.id + " " + packagebundles.id + "'><td></td><td>" + packageservices.servicename + "</td><td>Amount : " + packageservices.tax_exclusive_price.toLocaleString() + "</td><td>Tax: " + Math.ceil(serviceTaxAmt).toLocaleString() + "</td><td colspan='3'>Is Consume : " + consume + "</td></tr>");
                                    }
                                }
                            });
                        });
                        $('.invoicecheckbox').click(function () {
                            $(".invoicecheckbox").prop('checked', false);
                            $(this).prop('checked', true);
                            /*Here I need to set the bundle id so I can Checked on save exclusive*/
                            $('#checked_bundle_id').val($(this).val());
                            calculateInvoice($(this).val(), 'treatment_');
                        });
                    }
                }
            });
        }

    });

    $('#package_id_create').change();

    /*Invoice Save and also package advances*/
    $(document).on("click", "#treatment_savepackageinformation", function () {
        $(this).attr("disabled", true);

        showSpinner();

        $('#wrongMessage').hide();
        $('#successMessage').hide();
        $('#definefield').hide();
        $('#definetreatment').hide();

        var appointment_id = $('#appointment_id_create').val();
        var appointment_id_consultancy = $('#appointment_link_cons').val();
        var package_id = $('#package_id_create').val();
        var amount_create = $('.amount_create').val();
        var tax_create = $('.tax_create').val();
        var price = $('.price_create').val();
        var balance = $('.balance_create').val();
        var cash = $('#cash_create').val();
        var settle = $('.settle_create').val();
        var outstand = $('.outstand_create').val();
        var package_service_id = $('#package_service_id').val();
        var package_mode_id = $('#payment_mode_id').val();
        var checked_treatment = $('#checked_treatment').val();
        var created_at = $('#created_at').val();
        var tax_treatment_type_id = $('#tax_treatment_type_id').val();
        var remaining = $('#remaining').val();
        var status_checked_treatment = true;

        if (checked_treatment == 0) {
            var exclusive_or_bundle = $('#checked_bundle_id').val();
            if (exclusive_or_bundle == 0) {
                //if treatment belongs to plan but not select to I set that varibale
                var status_checked_treatment = false;
            }
        } else {
            var exclusive_or_bundle = $('#is_exclusive').val();
        }

        var status = true;
        if (cash > 0) {
            if (package_mode_id == '' || package_mode_id == '0') {
                status = false;
            }
        }

        if (status_checked_treatment) {
            if (status) {
                console.log(settle, outstand)
                if (appointment_id && price && balance && cash && settle && outstand) {
                    if (appointment_id_consultancy == "" && package_service_id == "") {
                        $("#noconsultancy").show();
                        $(this).attr("disabled", false);
                        hideSpinner();
                    } else {
                        if (outstand > 0) {
                            $('#outstandingbalance').show();
                            $(this).attr("disabled", false);
                            hideSpinner();
                        } else {
                            $.ajax({
                                type: 'get',
                                url: route('admin.appointments.saveinvoice'),
                                data: {
                                    'appointment_id': appointment_id,
                                    'package_id': package_id,
                                    'amount_create': amount_create,
                                    'tax_create': tax_create,
                                    'price': price,
                                    'balance': balance,
                                    'cash': cash,
                                    'settle': settle,
                                    'outstand': outstand,
                                    'package_service_id': package_service_id,
                                    'package_mode_id': package_mode_id,
                                    'checked_treatment': checked_treatment,
                                    'exclusive_or_bundle': exclusive_or_bundle,
                                    'created_at': created_at,
                                    'appointment_id_consultancy': appointment_id_consultancy,
                                    'tax_treatment_type_id': tax_treatment_type_id,
                                    'remaining': remaining
                                },
                                success: function (resposne) {
                                    if (resposne.status) {
                                        let invoice_id = resposne.data.invoice_id;
                                        $('#successMessage').show();
                                        toastr.success("Invoice successfully created");
                                        reInitTable('treatment');
                                        closeAllPopup('.modal-dialog')
                                        $("#treatment-invoice-create").remove();
                                        // Open print view in new tab
                                        window.open(route('admin.invoices.invoice_pdf', [invoice_id, 'print']), '_blank');
                                    } else {
                                        if (resposne.data.setteled == 1) {
                                            $('#setteledMessage').show();
                                        } else if (resposne.data.consumption_locked == 1) {
                                            toastr.error(resposne.message || 'This service is locked from consumption.');
                                            $('#treatment_savepackageinformation').attr("disabled", false);
                                        } else {
                                            $('#wrongMessage').show();
                                            toastr.error(" Something Went Wrong!")
                                        }

                                    }

                                    hideSpinner();
                                }
                            });
                        }

                    }

                } else {
                    hideSpinner();
                    toastr.error("Request is not valid");
                }
            } else {
                $('#definefield').show();
                //toastr.error("Kindly define payment mode");
                hideSpinner();
            }
        } else {
            $('#definetreatment').show();
            toastr.error("Kindly select the treatment")
            $(this).attr("disabled", false);
            hideSpinner();
        }

    });

    /*keyup function trigger whan we enter cash amount*/
    $(document).on("keyup", "#cash_create", function () {
        keyfunction('treatment_');
    });

    /*blur function trigger whan we enter cash value*/
    $(document).on("blur", "#cash_create", function () {
        keyfunction('treatment_');
    });

    /*Make functional exclusive checked box*/
    $(document).on("change", "#is_exclusive", function () {
        if ($(this).is(":checked")) {
            $('#is_exclusive').val('1');
        }
        else {
            $('#is_exclusive').val('0');
        }
        var price_orignal = $('#orignal_price_h').val();
        var location_id = $('#location_id_tax').val();
        var is_exclusive = $('#is_exclusive').val();
        var tax_treatment_type_id = $('#tax_treatment_type_id').val();
        if (price_orignal) {
            $.ajax({
                type: 'get',
                url: route('admin.appointments.getcalculatedPriceExclusicecheck'),
                data: {
                    'price_orignal': price_orignal,
                    'location_id': location_id,
                    'is_exclusive': is_exclusive,
                    'tax_treatment_type_id': tax_treatment_type_id,
                },
                success: function (resposne) {
                    if (resposne.status) {
                        $('#amount_create').text(resposne.amount_create);
                        $('.amount_create').val(resposne.amount_create);
                        $('#tax_create').text(resposne.tax_create);
                        $('.tax_create').val(resposne.tax_create);
                        $('#price_create').text(resposne.price);
                        $('.price_create').val(resposne.price);
                        $('#cash_create').val('0');
                        $("#outstand_create").text(resposne.outstdanding);
                        $(".outstand_create").val(resposne.outstdanding);
                        $("#treatment_addinvoice").hide();
                    }
                },
            });
        }

    });

    /*End Treatment*/

});


/*key function for net amount of service*/
function keyfunction_custom(type = '') {

    $('#percentageMessage').hide();
    var is_exclusive_consultancy = $('#is_exclusive_consultancy').val();
    var price = $('#price_for_calculation').val();
    var discount_id = $('#discount_id').val();
    var discount_type = $('#discount_type').val();
    var discount_value = $('#discount_value').val();
    var location_id = $('#id_location').val();
    var tax_treatment_type_id = $('#tax_treatment_type_id').val();

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
                                $(".discount_type_section").show();
                                $(".discount_value_section").show();
                                $("#amount").text(response.price);
                                $(".amount").val(response.price);
                                if (type == '') {
                                    $("#tax").text(response.tax);
                                    $(".tax").val(response.tax);
                                } else {
                                    $("#tax_create").text(response.tax);
                                    $(".tax_create").val(response.tax);
                                }
                                $("#tax_amt").text(response.tax_amt);
                                $(".tax_amt").val(response.tax_amt);
                                $('#settle').text(response.settleamount);
                                $('.settle').val(response.settleamount);
                                $('#outstand').text(response.outstanding);
                                $('.outstand').val(response.outstanding);

                                $('#settleamount_cash').val(response.settleamount);
                                $('#outstanding_cash').val(response.outstanding);

                                // Check if service is in any plan
                                var serviceInPlan = $('#service_in_plan').val() === '1' || $('#service_in_plan').val() === 'true';

                                if (response.outstanding == '0') {
                                    $("#" + type + "addinvoice").show();
                                    $("#outstandingMessage").hide();
                                    $("#outstandingMessagePayment").hide();
                                } else {
                                    $("#" + type + "addinvoice").hide();
                                    
                                    // Only show error if service is not in any plan
                                    if (!serviceInPlan) {
                                        $("#outstandingMessage").show();
                                        $("#outstandingMessagePayment").hide();
                                    } else {
                                        $("#outstandingMessage").hide();
                                        $("#outstandingMessagePayment").hide();
                                    }
                                }

                            } else {
                                $('#percentageMessage').show();
                                toastr.error("Your discount limit exceeded.");
                                $("#amount").text('0');
                                $(".amount").val('');
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
function keyfunction_cash(type = '') {

    var price = $('.tax_amt').val();
    /*tax amt. amount*/
    // var balance = $('.balance').val();
    var cash = $('#cash').val();
    // var settleamount = $('.settle').val();
    // var outstanding = $('.outstand').val();
    var amount_type = $('#amount_type').val();

    if (cash == 0 || cash == '') {
        $('#paymentmode').hide();
    } else {
        $('#paymentmode').show();
    }

    if (!cash && cash == 0) {
        var settle_cash = $("#settleamount_cash").val();
        var outstand_cash = $("#outstanding_cash").val();
        if (type == '') {
            $("#settle").text(settle_cash);
            //$(".settle").val(settle_cash);
        } else {
            $("#settle_create").text(settle_cash);
            //$(".settle_create").val(settle_cash);
        }
        $("#outstand").text(outstand_cash);
        // $(".outstand").val(outstand_cash);
    }

    if (price && balance && cash) {
        $.ajax({
            type: 'get',
            url: route('admin.appointments.getfinalcalculation'),
            data: {
                'price': cash,
                'balance': balance,
                'cash': cash,
                'settleamount': settleamount,
                'outstanding': outstanding,
                'amount_type': amount_type
            },
            success: function (resposne) {
                if (resposne.status) {
                    if (type == '') {
                        $("#settle").text(resposne.settleamount);
                        $(".settle").val(resposne.settleamount);

                        $("#outstand").text(resposne.outstdanding);
                        $(".outstand").val(resposne.outstdanding);
                    } else {
                        $("#settle_create").text(resposne.settleamount);
                        $(".settle_create").val(resposne.settleamount);
                    }

                    /* if (resposne.outstdanding == '0') {
                         $("#"+type+"addinvoice").show();
                     } else {
                         $("#"+type+"addinvoice").hide();
                     }
 
                     if ((cash == 0 || cash == '') && amount_type == 0) {
                         $('#addinvoice').show();
                     }*/
                }
            },
        });
    }
}


/*keyup function for $net_amount*/
function keyfunction(type = '') {

    var price_create = $('.price_create').val();
    var balance_create = $('.balance_create').val();
    var cash_create = $('#cash_create').val();
    var settleamount_for_zero = $('#settleamount_for_zero').val();
    var outstanding_for_zero = $('#outstanding_for_zero').val();

    if (cash_create == 0 || cash_create == '') {
        $('#paymentmode').hide();
    } else {
        $('#paymentmode').show();
    }

    // if (!cash_create) {
    //     $("#settle_create").text(settleamount_for_zero);
    //     $(".settle_create").val(settleamount_for_zero);
    //     $("#outstand_create").text(outstanding_for_zero);
    //     $(".outstand_create").val(outstanding_for_zero);
    // }

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
                if (resposne.status) {

                    $("#settle_create").text(resposne.settleamount);
                    $(".settle_create").val(resposne.settleamount);
                    $("#outstand_create").text(resposne.outstdanding);
                    $(".outstand_create").val(resposne.outstdanding);
                    
                    // Check outstanding and show/hide pay section
                    // This function will handle showing/hiding the appropriate messages
                    checkOutstandingAmount();
                }
            },
        });
    }
}

/*Calcuate invoice data and return data according to price*/
function calculateInvoice(id, type = '') {
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
            if (resposne.status === true || resposne.status == '1') {

                $('#amount_create').text(resposne.amount);
                $('.amount_create').val(resposne.amount);
                $('#tax_create').text(resposne.tax_price);
                $('.tax_create').val(resposne.tax_price);
                $('#price_create').text(resposne.serviceprice);
                $('.price_create').val(resposne.serviceprice);
                $('#remaining').val(resposne.remaining);
                $('#balance_create').val(resposne.balance);
                $('#settle_create').text(resposne.settleamount);
                $('.settle_create').val(resposne.settleamount);
                $('#outstand_create').text(resposne.outstanding);
                $('.outstand_create').val(resposne.outstanding);
                $('#settleamount_for_zero').val(resposne.settleamount);
                $('#outstanding_for_zero').val(resposne.outstanding);
                $('#package_service_id').val(id);

                if (resposne.outstanding == '0') {
                    $("#" + type + "addinvoice").show();
                    $("#outstandingMessage").hide();
                    $("#outstandingMessagePayment").hide();
                } else {
                    $("#" + type + "addinvoice").hide();
                    // Show payment error message when outstanding > 0
                    $("#outstandingMessage").hide();
                    $("#outstandingMessagePayment").show();
                }

                // Check outstanding and show/hide pay section
                checkOutstandingAmount();

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


function triggerDate($class) {

    $("." + $class).trigger("click");

    $(".custom_field").click(function () {
        $('.custom_field').datepicker("show");
    });

}

function showHideDiscount($this) {

    if ($this.val() != '') {

    }
}

// Function to check outstanding amount and show/hide pay section
function checkOutstandingAmount() {
    var outstanding = parseFloat($('.outstand_create').val()) || parseFloat($('.outstand').val()) || 0;
    var serviceInPlan = $('#service_in_plan').val() === '1' || $('#service_in_plan').val() === 'true';

    if (outstanding > 0) {
        // Hide pay input and payment mode
        $('#pay_section').hide();
        $('#paymentmode').hide();
        
        // Show appropriate error message based on whether service is in plan
        if (!serviceInPlan) {
            $('#outstandingMessage').show();
            $('#outstandingMessagePayment').hide();
        } else {
            $('#outstandingMessage').hide();
            $('#outstandingMessagePayment').show();
        }
        
        // Hide invoice buttons
        $('#treatment_addinvoice').hide();
        $('#addinvoice').hide();
    } else {
        // Show pay input
        $('#pay_section').show();
        // Hide both messages
        $('#outstandingMessage').hide();
        $('#outstandingMessagePayment').hide();
    }
}
