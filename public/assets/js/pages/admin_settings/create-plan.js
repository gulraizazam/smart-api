// Tax treatment type constants (mirror PHP Config::get('constants.*'))
var TAX_BOTH = 1;
var TAX_IS_EXCLUSIVE = 2;
var TAX_IS_INCLUSIVE = 3;

// Client-side tax calculation matching backend calculateServiceTaxForPackage
function calculatePlanTax(netAmount, taxPct, taxTreatmentTypeId, isExclusive) {
    netAmount = parseFloat(netAmount) || 0;
    taxPct = parseFloat(taxPct) || 0;
    isExclusive = parseInt(isExclusive) || 0;

    var result = { tax_exclusive_net_amount: 0, tax_price: 0, tax_including_price: 0 };

    var useExclusive = (taxTreatmentTypeId == TAX_IS_EXCLUSIVE) ||
                       (taxTreatmentTypeId == TAX_BOTH && isExclusive == 1);

    if (useExclusive) {
        result.tax_exclusive_net_amount = netAmount;
        result.tax_price = Math.ceil(netAmount * (taxPct / 100));
        result.tax_including_price = Math.ceil(netAmount + result.tax_price);
    } else {
        result.tax_including_price = netAmount;
        if (taxPct > 0) {
            result.tax_exclusive_net_amount = Math.ceil((100 * netAmount) / (taxPct + 100));
        } else {
            result.tax_exclusive_net_amount = netAmount;
        }
        result.tax_price = Math.ceil(netAmount - result.tax_exclusive_net_amount);
    }
    return result;
}

function updatePlanYouSave(prefix) {
    prefix = prefix || '';
    var priceField = '#' + prefix + 'net_amount_1';
    var saveField = '#' + prefix + 'you_save_1';
    var infoStore = prefix === 'edit_' ? window.editPlanServiceInfo : window.createPlanServiceInfo;
    var netRaw = $(priceField).val();
    if (netRaw === '' || netRaw === null || netRaw === undefined) {
        $(saveField).val('');
        return;
    }
    var net = parseFloat(netRaw) || 0;
    var regular = (infoStore && infoStore.service_price) ? parseFloat(infoStore.service_price) : 0;
    var savings = regular - net;
    $(saveField).val(savings > 0 ? savings.toFixed(2) : '0.00');
}

$(function () {
    setInterval(function () {
        if ($('#you_save_1').length) { updatePlanYouSave(''); }
        if ($('#edit_you_save_1').length) { updatePlanYouSave('edit_'); }
    }, 300);
});

// Build a preview table row for plan services (no DB call)
function buildPlanServiceRow(data, deleteHtml) {
    var soldByDisplay = data.soldByName || 'N/A';
    var editSoldByBtn = '';
    if (data.soldBy && data.showEditSoldBy !== false) {
        editSoldByBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-sm me-2' onclick='editNewRowSoldBy(this)'>" +
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3699FF" viewBox="0 0 16 16">' +
            '<path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>' +
            '</svg></button>';
    }
    return "<tr id='table_1' class='HR_" + data.groupClass + "'>" +
        "<td><a href='javascript:void(0)' style='color: #009ef7;'>" + data.serviceName + "</a></td>" +
        "<td>" + parseFloat(data.regularPrice).toLocaleString() + "</td>" +
        "<td>" + (data.discountName || '-') + "</td>" +
        "<td>" + (data.discountValue || 0) + "</td>" +
        "<td>" + parseFloat(data.subtotal).toLocaleString() + "</td>" +
        "<td>" + data.tax + "</td>" +
        "<td>" + parseFloat(data.total).toLocaleString() + "</td>" +
        "<td>No</td>" +
        "<td>N/A</td>" +
        "<td class='sold-by-display'>" + soldByDisplay + "</td>" +
        "<td class='d-none'>" +
        "<input type='hidden' class='bundle_id' name='bundle_id' value='" + data.serviceId + "' />" +
        "<input type='hidden' class='discount_id' name='discount_id' value='" + (data.discountId || '') + "' />" +
        "<input type='hidden' class='discount_type' name='discount_type' value='" + (data.discountType || '') + "' />" +
        "<input type='hidden' class='service_tax_type' name='service_tax_type' value='" + (data.taxTreatmentTypeId || '') + "' />" +
        "<input type='hidden' class='config_group_id' name='config_group_id' value='" + (data.configGroupId || '') + "' />" +
        "<input type='hidden' class='row_type' name='row_type' value='" + (data.rowType || '') + "' />" +
        "<input type='hidden' class='source_type' name='source_type' value='" + (data.sourceType || '') + "' />" +
        "</td>" +
        "<td>" +
        "<input type='hidden' class='package_bundles_sold_by' name='sold_by[]' value='" + (data.soldBy || '') + "' />" +
        editSoldByBtn +
        (deleteHtml || '') +
        "</td>" +
        "</tr>";
}

function patient_search_createpalan() {
    $("#add_patient_id_selector").select2({
        ajax: {
            type: "GET",
            url: route('admin.users.getpatient.id'),
            dataType: 'json',
            delay: 250,
            data: function (params) {

                return {
                    search: params.term // search term
                };
            },
            processResults: function (response) {
                return {
                    results: response.data.patients,
                };
            },
            cache: true
        },
        placeholder: 'Search for a repository',
        templateResult: formatRepo,
        templateSelection: formatRepoSelection

    });

    $("#patient_search_id_selector").on("select2:select", function (e) {
        var thisID = $(this).val();
        $(this).parent().parent('div').find('.search_field').val(thisID).change();
    });

    function formatRepo(repo) {
        var $container, search_id = 'patient_search_id_selector', flag = 1;
        if (repo.loading) {
            $container = $(
                "<div class='select2-result-repository__avatar'>Searching</div>"
            );
        } else {
            $container = $(
                '<div class="select2-result-repository__avatar tst">' + repo.name + " - C " + repo.id + "</div>"
            );
        }
        return $container;
    }

    function formatRepoSelection(repo) {
        return repo.name || repo.text;
    }
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
                    closePopup('update_plane_form');
                    reInitTable('plan');
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

$(document).ready(function () {
    // getUserCentre is called from setFilters/createPlan to populate location dropdown
    // Call it on page load as well to populate the filter dropdown
    if (typeof getUserCentre === 'function') {
        getUserCentre();
    }
    
    patient_search_createpalan();
    planeEditValidation.init();

    // ESC key handler - listen on document for both modals
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            // Check if add modal is open
            if ($('#modal_add_plan').is(':visible')) {
                e.preventDefault();
                e.stopPropagation();
                resetVoucherAdd(e);
                return false;
            }
            // Check if edit modal is open
            else if ($('#modal_edit_plan').is(':visible')) {
                e.preventDefault();
                e.stopPropagation();
                resetVoucherEdit(e);
                return false;
            }
        }
    });

    $("#add_patient_id_selector").on("select2:select", function (e) {
        $("#add_appointment_id").empty();
        $('#add_appointment_id').val(null).trigger('change');
        getAppointments($(this).val());

    });


    /*save data for both predefined discounts and keyup trigger*/
    // $("#AddPackage").click(function () {
    //     showSpinner();

    //     $('#wrongMessage').hide();
    //     $('#inputfieldMessage').hide();
    //     $('#percentageMessage').hide();
    //     $('#AlreadyExitMessage').hide();
    //     $(this).attr("disabled", true);
    //     var random_id = $('#random_id_1').val();
    //     var service_id = $('#service_id_1').val(); //Basicailly it is bundle id
    //     var discount_id = $('#discount_id_1').val();
    //     var net_amount = $('#net_amount_1').val();
    //     var discount_type = $('#discount_type_1').val();
    //     var discount_price = $('#discount_value_1').val();
    //     var discount_slug = $("#slug_1").val();
    //     var package_total = $('#package_total_1').val();

    //     var is_exclusive = $('#is_exclusive').val();
    //     var location_id = $('#location_id_1').val();

    //     if (service_id && net_amount && location_id) {
    //         if (discount_slug == 'custom' && discount_id != '') {
    //             if (discount_price == '') {
    //                 $('#inputfieldMessage').show();
    //                 return false;
    //             }
    //             if (discount_type == 'Percentage') {
    //                 if (discount_price > 100) {
    //                     $('#percentageMessage').show();
    //                     return false;
    //                 }
    //             }
    //         }

    //         var formData = {
    //             'random_id': random_id,
    //             'bundle_id': service_id, //Basicailly it is bundle id
    //             'discount_id': discount_id,
    //             'net_amount': net_amount,
    //             'discount_type': discount_type,
    //             'discount_price': discount_price,
    //             'package_total': package_total,
    //             'is_exclusive': is_exclusive,
    //             'location_id': location_id,
    //             'package_bundles[]': []
    //         };

    //         $(".package_bundles").each(function () {
    //             formData['package_bundles[]'].push($(this).val());
    //         });

    //         $.ajax({
    //             type: 'get',
    //             url: route('admin.packages.savepackages_service'),
    //             data: formData,
    //             success: function (resposne) {


    //                 let consume = 'No';
    //                 if (resposne.status == '1') {

    //                     $('#table_1').append("" +
    //                         "<tr id='table_1' class='HR_" + random_id + " HR_" + resposne.myarray.record.id + "'>" +
    //                         "<td><a href='javascript:void(0)' onClick='toggle(" + resposne.myarray.record.id + ")'>" + resposne.myarray.service_name + "</a></td>" +
    //                         "<td>" + resposne.myarray.service_price.toLocaleString() + "</td>" +
    //                         "<td>" + resposne.myarray.discount_name + "</td>" +
    //                         "<td>" + resposne.myarray.discount_type + "</td>" +
    //                         "<td>" + resposne.myarray.discount_price + "</td>" +
    //                         "<td>" + resposne.myarray.record.tax_exclusive_net_amount.toLocaleString() + "</td>" +
    //                         "<td>" + resposne.myarray.record.tax_price + "</td>" +
    //                         "<td>" + resposne.myarray.record.tax_including_price.toLocaleString() + "</td>" +
    //                         "<td>" +
    //                         "<input type='hidden' class='package_bundles' name='package_bundles[]' value='" + resposne.myarray.record.id + "' />" +
    //                         "<button class='btn btn-xs btn-danger' onClick='deleteModel(" + resposne.myarray.record.id + ")'>Delete</button>" +
    //                         "</td>" +
    //                         "</tr>");

    //                     jQuery.each(resposne.myarray.record_detail, function (i, record_detail) {
    //                         if (record_detail.is_consumed == '0') {
    //                             consume = 'No';
    //                         } else {
    //                             consume = 'Yes';
    //                         }
    //                         $('#table_1').append("<tr class='inner_records_hr HR_" + resposne.myarray.record.id + " " + resposne.myarray.record.id + "'><td></td><td>" + record_detail.name + "</td><td>Amount : " + record_detail.tax_exclusive_price.toLocaleString() + "</td><td>Tax % : " + record_detail.tax_price + "</td><td>Total Amount : " + record_detail.tax_including_price.toLocaleString() + "</td><td colspan='4'>Is Consume : " + consume + "</td></tr>");
    //                     });

    //                     $("#package_total_1").val(resposne.myarray.total);
    //                     toggle(resposne.myarray.record.id);



    //                     keyfunction_grandtotal();

    //                     var rows = $('#table_1 tbody tr').length;

    //                     if (rows >= 3) {
    //                         $("#location_id_1").prop("disabled", true);
    //                     }
    //                     /*we enable add button after all functionality enable*/
    //                     $('#AddPackage_1').attr("disabled", false);
    //                     $('#add_service_id').val('').change();
    //                     $('#add_service_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");

    //                     $('#add_discount_id').val('').change();
    //                     $('#add_discount_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");

    //                     $('#add_discount_type').val('').change();
    //                     $('#add_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");

    //                     $('#discount_value_1').val('');
    //                     $('#discount_value_1').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");

    //                     $('#net_amount_1').val('');
    //                     $('#net_amount_1').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");

    //                 } else {

    //                     $('#AlreadyExitMessage').show();
    //                     $('#AddPackage_1').attr("disabled", false);
    //                 }

    //                 hideSpinnerRestForm();
    //             }
    //         });
    //     } else {
    //         hideSpinnerRestForm();
    //         // toastr.error('Please fill out the required fields.')
    //         $('#inputfieldMessage').show();
    //         $(this).attr("disabled", false);
    //     }
    // });
    /*End*/

    patientSearch('search_patient');

    $(document).on("click", ".croxcli", function () {
        $('.search_field').val('').change();
        $('.package_id').val(null).trigger('change');

        $('.search_patient').val(null).trigger('change');
    });

});

// OPTIMIZED: Using new endpoint with 90% performance improvement
// Check if we're in patient card context and use patient-specific datatable
var table_url;
if (typeof window.isPatientCardContext !== 'undefined' && window.isPatientCardContext && typeof window.patientCardPatientId !== 'undefined') {
    table_url = route('admin.plans.optimized.datatable', { patient_id: window.patientCardPatientId });
    console.log('Patient Card Context - table_url:', table_url, 'patientId:', window.patientCardPatientId);
} else {
    table_url = route('admin.plans.optimized.global.datatable');
    console.log('Global Context - table_url:', table_url);
}

var table_columns = [
    {
        field: 'package_id',
        title: 'Plan ID',
        sortable: false,
        width: 50,
        template: function (data) {
            let display_url = route('admin.packages.display', { id: data.id });
            return '<a href="javascript:void(0);" onclick="viewPlan(`' + display_url + '`)">' + data.package_id + '</a>';
        }
    }, {
        field: 'plan_name',
        title: 'Plan Name',
        sortable: false,
        width: 150,
        template: function (data) {
            return data.plan_name || '-';
        }
    }, {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 60,
        template: function (data) {
            return data.patient_id ? 'C-' + data.patient_id : 'N/A';
        }
    }, {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 80,
    },  {
        field: 'total',
        title: 'Total',
        sortable: false,
        width: 60,
    }, {
        field: 'cash_receive',
        title: 'Cash In',
        sortable: false,
        width: 60,
    }, {
        field: 'settle_amount',
        title: 'Settled',
        sortable: false,
        width: 60,
    }, {
        field: 'refunded',
        title: 'Refund',
        sortable: false,
        width: 60,
    }, {
        field: 'balance',
        title: 'Balance',
        sortable: false,
        width: 70,
    },{
        field: 'location_id',
        title: 'Centre',
        sortable: false,
        width: 'auto',
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
    }, {
        field: 'status',
        title: 'Status',
        sortable: false,
        width: 70,
        template: function (data) {
            let status_url = route('admin.packages.status');
            return statuses(data, status_url);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 170,
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
        let details_url = route('admin.packages.view.package', { id: id });
        let delete_url = route('admin.packages.destroy', { id: id });
        let sms_log_url = route('admin.packages.sms_logs', { id: id });
        let log_url = route('admin.packages.log', { id: id, type: 'web' });
        let refund_url = route('admin.refunds.refund_create', { id: id });

        if (permissions.create || permissions.log || permissions.sms_log || permissions.edit) {
            let actions = '<div class="dropdown dropdown-inline action-dots">';
            if (permissions.edit) {
                // Check plan_type to determine which edit function to call
                let editFunction = 'editRow';
                if (data.plan_type === 'bundle') {
                    editFunction = 'editBundle';
                } else if (data.plan_type === 'membership') {
                    editFunction = 'editMembership';
                }
                actions += '<a href="javascript:void(0);" onclick="' + editFunction + '(`' + edit_url + '`, ' + id + ');" class="btn btn-icon btn-primary btn-sm">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                    </a>';
            }

            actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';

            if (permissions.create) {

                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="viewPlan(`' + display_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">Display</span>\
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

            if (permissions.delete) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Delete</span>\
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
    total_amountArray = [];
    edit_amountArray = [];
    ExistingTotal = 0;
    window.editPlanLocked = false;
    $('.error-msg').html('');
    $('#edit_service_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    $("#edit_discount_id").html('<option value="">Select Discount/Voucher</option>');
    $("#edit_discount_type").attr('disabled', true);
    $("#edit_discount_value_1").val('');
    $("#edit_discount_value_1").attr('disabled', true);
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
    $("#edit_appointment_id").find('option').each(function () {
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

function refund(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {

            refundData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(RefundValidation);
        }
    });
}

function refundData(response) {

    try {

        let refund = response.data;

        // if (refund.refundable_amount == 0) {
        //     $("#modal_edit_refunds").modal("hide");
        //     toastr.error("Insufficient amount to refund");
        //     return false;
        // }
        let paymentmodes = response.data.paymentmodes;
        let payment_options = '<option value="">Select Payment Mode</option>';
        if (paymentmodes) {
            Object.entries(paymentmodes).forEach(function (paymentmode) {
                payment_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
            });
        }
        $("#modal_edit_refunds").modal("show");

        $("#modal_edit_refunds_form").attr("action", route('admin.refunds.store'));


        if (refund.document) {
            $("#document-label").text('Documentation Charges Already Taken');
            $("#documentationcharges").hide();
        } else {
            $("#document-label").text('Documentation Charges');
            $("#documentationcharges").show();
        }
        $("#refund_amount").html(refund.refundable_amount);
        $("#documentationcharges").val(refund.documentationcharges.data);
        $("#balance").val(refund.refundable_amount);
        $("#refund_amount").attr('max', refund.refundable_amount);

        $("#package_id").val(refund.id);
        $("#is_adjustment_amount").val(refund.is_adjustment_amount);
        $("#return_tax_amount").val(refund.return_tax_amount);
        $("#date_backend").val(refund.date_backend);
        $("#refund_payment_mode_id").html(payment_options);
        $("#received_amount").val(refund.cash_amount);

    } catch (error) {
        showException(error);
    }

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
        let users = response.data.users;
        let range = response.data.range;
        let total_price = response.data.total_price;
        let patient = package.user;
        let location = package.location;
        let history_options = noRecordFoundTable(5);
        let membership = response.data.membership;
        let selected_user_id = response.data.selectedUserId;
        if (packageadvances.length) {
    history_options = '';
    Object.values(packageadvances).forEach(function (packageadvance) {
        // Skip zero amounts only for 'in' flow, not for 'out' flow
        if (packageadvance.cash_amount != '0' || packageadvance.cash_flow == 'out') {
            let selector = 'history_cash_row_' + packageadvance.id;
            history_options += '<tr id="' + selector + '">';
            if (packageadvance.is_tax == 1 && packageadvance.cash_flow == 'out') {
                history_options += '<td>Tax</td>';
            } else {
                history_options += '<td>' + packageadvance?.paymentmode?.name + '</td>';
            }

            if (packageadvance.is_refund == 1) {
                history_options += '<td>out / refund</td>';
            } else if (packageadvance.is_setteled == 1) {
                history_options += '<td>out / settled</td>';
            }
            else {
                history_options += '<td>' + packageadvance.cash_flow + '</td>';
            }

            history_options += '<td>' + packageadvance.cash_amount + '</td>';
            history_options += '<td>' + formatDate(packageadvance.created_at, 'MMM, DD yyyy hh:mm A') + '</td>';

            history_options += '<td>';
            if (packageadvance?.cash_flow == 'in') {
                if (permissions.plans_cash_edit) {
                    history_options += '<a onclick="planeEdit(' + packageadvance.id + ', ' + package.id + ');" class="btn btn-sm btn-info" href="javascript:void(0);">Edit</a>&nbsp;';
                }
                if (permissions.plans_cash_delete) {
                    history_options += '<button onclick="deletePlaneHistory(`' + route('admin.packages.delete_cash') + '`, ' + packageadvance.id + ');" class="btn btn-sm btn-danger">Delete</button>';
                }
            }

            history_options += '</td>';

            history_options += '</tr>';
        }
    });
}

        let service_options = noRecordFoundTable(10);

        // Detect out-of-order config group consumption (GET consumed before BUY)
        // This is the ONLY condition that locks the Add button
        window.editPlanLocked = false;
        // Build a map: config_group_id -> [{consumption_order, is_consumed}, ...]
        var configGroupConsumption = {};
        if (packageservices && Object.keys(packageservices).length) {
            Object.values(packageservices).forEach(function (ps) {
                if (ps.package_bundle_id) {
                    // Find the bundle for this service to get config_group_id
                    var pb = null;
                    Object.values(packagebundles).forEach(function (b) {
                        if (b.id == ps.package_bundle_id) pb = b;
                    });
                    if (pb && pb.config_group_id) {
                        if (!configGroupConsumption[pb.config_group_id]) {
                            configGroupConsumption[pb.config_group_id] = [];
                        }
                        configGroupConsumption[pb.config_group_id].push({
                            consumption_order: parseInt(ps.consumption_order) || 0,
                            is_consumed: ps.is_consumed == '1'
                        });
                    }
                }
            });
        }
        // Check each config group for out-of-order consumption
        Object.keys(configGroupConsumption).forEach(function (groupId) {
            var services = configGroupConsumption[groupId];
            var hasConsumedHigherOrder = false;
            var maxConsumedOrder = -1;
            var minUnconsumedOrder = Infinity;
            services.forEach(function (s) {
                if (s.is_consumed && s.consumption_order > maxConsumedOrder) {
                    maxConsumedOrder = s.consumption_order;
                }
                if (!s.is_consumed && s.consumption_order < minUnconsumedOrder) {
                    minUnconsumedOrder = s.consumption_order;
                }
            });
            // Out-of-order: a consumed service has higher order than an unconsumed one
            if (maxConsumedOrder > minUnconsumedOrder) {
                window.editPlanLocked = true;
            }
        });
        // Also build a set of bundle IDs that belong to consumed config groups (for delete button hiding)
        var consumedConfigGroupBundleIds = {};
        if (packageservices && Object.keys(packageservices).length) {
            // First find config groups that have any consumed service
            var consumedConfigGroups = {};
            Object.values(packageservices).forEach(function (ps) {
                if (ps.is_consumed == '1' && ps.package_bundle_id) {
                    var pb = null;
                    Object.values(packagebundles).forEach(function (b) {
                        if (b.id == ps.package_bundle_id) pb = b;
                    });
                    if (pb && pb.config_group_id) {
                        consumedConfigGroups[pb.config_group_id] = true;
                    }
                }
            });
            // Then mark all bundles in those groups
            Object.values(packagebundles).forEach(function (pb) {
                if (pb.config_group_id && consumedConfigGroups[pb.config_group_id]) {
                    consumedConfigGroupBundleIds[pb.id] = true;
                }
            });
        }
        // Build set of individually consumed bundle IDs
        var consumedBundleIds = {};
        if (packageservices && Object.keys(packageservices).length) {
            Object.values(packageservices).forEach(function (ps) {
                if (ps.is_consumed == '1') {
                    consumedBundleIds[ps.package_bundle_id] = true;
                }
            });
        }

        if (packagebundles.length) {
            service_options = '';
            
            // Group rows into configurable discount groups using config_group_id from DB
            // Each configurable discount addition gets a unique config_group_id when saved
            var configGroups = {}; // groupKey -> [pb.id, pb.id, ...]
            var pbToGroup = {};    // pb.id -> groupKey
            
            // First pass: group by config_group_id (new data)
            var hasAnyConfigGroupId = false;
            Object.values(packagebundles).forEach(function (pb) {
                if (pb.config_group_id) {
                    hasAnyConfigGroupId = true;
                    if (!configGroups[pb.config_group_id]) {
                        configGroups[pb.config_group_id] = [];
                    }
                    configGroups[pb.config_group_id].push(pb.id);
                    pbToGroup[pb.id] = pb.config_group_id;
                }
            });
            
            // Fallback for legacy data without config_group_id:
            // Group consecutive rows with same discount_id that have no config_group_id
            // Only group rows where the discount is actually Configurable type
            if (!hasAnyConfigGroupId) {
                var legacyGroupCounter = 0;
                var prevDiscountId = null;
                Object.values(packagebundles).forEach(function (pb) {
                    var isConfigType = pb.discount_type === 'Configurable' || (pb.discount && pb.discount.type === 'Configurable');
                    if (pb.discount_id && !pb.config_group_id && isConfigType) {
                        if (pb.discount_id != prevDiscountId) {
                            legacyGroupCounter++;
                            prevDiscountId = pb.discount_id;
                        }
                        var legacyKey = 'legacy_' + pb.discount_id + '_' + legacyGroupCounter;
                        if (!configGroups[legacyKey]) {
                            configGroups[legacyKey] = [];
                        }
                        configGroups[legacyKey].push(pb.id);
                        pbToGroup[pb.id] = legacyKey;
                    } else {
                        prevDiscountId = null;
                    }
                });
            }
            
            // Configurable groups have more than 1 row
            var configurableGroupKeys = {};
            Object.keys(configGroups).forEach(function(groupKey) {
                if (configGroups[groupKey].length > 1) {
                    configurableGroupKeys[groupKey] = true;
                }
            });
            
            Object.values(packagebundles).forEach(function (packagebundle) {

                var del_icon;
                var editIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3699FF" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/></svg>';
                
                // Check if this row is part of a configurable discount group
                var groupKey = pbToGroup[packagebundle.id] || null;
                var isConfigurableDiscount = groupKey && configurableGroupKeys[groupKey];
                var isBaseService = isConfigurableDiscount && configGroups[groupKey][0] === packagebundle.id;
                var configRowIds = isConfigurableDiscount ? configGroups[groupKey] : null;
                
                // Per-row delete visibility: hide if consumed or belongs to consumed config group
                var hideDelete = consumedBundleIds[packagebundle.id] || consumedConfigGroupBundleIds[packagebundle.id];
                
                if (isConfigurableDiscount && !isBaseService) {
                    // Non-base service row in configurable group - no buttons
                    del_icon = "";
                } else {
                    // Build edit button (permission-based, shown on every service row)
                    var editBtn = "";
                    if (permissions.plans_edit_sold_by) {
                        editBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-sm me-2' onClick='editBundleSoldBy(" + packagebundle.id + ", " + location.id + (configRowIds ? ", " + JSON.stringify(configRowIds) : "") + ")'>" + editIcon + "</button>";
                    }

                    // Build delete button (hidden for consumed rows)
                    var deleteBtn = "";
                    if (!hideDelete) {
                        if (isConfigurableDiscount && isBaseService) {
                            deleteBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' data-config-rows='" + JSON.stringify(configRowIds) + "' onClick='deleteConfigurablePlanRowsEdit(this)'>" + trashBtn() + "</button>";
                        } else {
                            deleteBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRow(" + packagebundle.id + ", `edit_`)'>" + trashBtn() + "</button>";
                        }
                    }

                    del_icon = editBtn + deleteBtn;
                }

                service_options += '<tr id="table_1" data-existing="1" class="HR_' + packagebundle.id + (isConfigurableDiscount ? ' configurable-group-' + configGroups[groupKey][0] : '') + '">';
                // Count child services for this bundle
                let childServiceCount = Object.values(packageservices).filter(function (ps) {
                    return ps.package_bundle_id == packagebundle.id;
                }).length;

                // Resolve service/bundle name using source_type (per-row discriminator)
                // source_type: 'service' = bundle_id holds services.id, 'bundle' = bundle_id holds bundles.id, 'membership' = uses membership_type_id
                let sourceType = packagebundle.source_type || '';
                let bundleNameText = '-';
                
                if (sourceType === 'service' && packagebundle.service && packagebundle.service.name) {
                    bundleNameText = packagebundle.service.name;
                } else if (sourceType === 'bundle' && packagebundle.bundle && packagebundle.bundle.name) {
                    bundleNameText = packagebundle.bundle.name;
                } else if (sourceType === 'service_bundle' && packagebundle.service_bundle && packagebundle.service_bundle.service) {
                    bundleNameText = packagebundle.qty + 'x ' + packagebundle.service_bundle.service.name;
                } else if (sourceType === 'membership' && packagebundle.membership_type && packagebundle.membership_type.name) {
                    bundleNameText = packagebundle.membership_type.name;
                } else if (packagebundle.service && packagebundle.service.name) {
                    bundleNameText = packagebundle.service.name;
                } else if (packagebundle.bundle && packagebundle.bundle.name) {
                    bundleNameText = packagebundle.bundle.name;
                } else if (packagebundle.membership_type && packagebundle.membership_type.name) {
                    bundleNameText = packagebundle.membership_type.name;
                }

                // Use <a> tag so Save button can find service name via td:first-child a
                if ((sourceType === 'bundle' || sourceType === 'service_bundle') && childServiceCount > 1) {
                    service_options += '<td><a href="javascript:void(0);" onclick="toggle(' + packagebundle.id + ')">' + bundleNameText + '</a></td>';
                } else {
                    service_options += '<td><a href="javascript:void(0)" style="color: #009ef7;">' + bundleNameText + '</a></td>';
                }
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
                if (packagebundle.discount_price == null) {
                    service_options += '0.00';
                } else {
                    service_options += packagebundle.discount_price;
                }
                service_options += '</td>';

                service_options += '<td>' + packagebundle.tax_exclusive_net_amount + '</td>';
                service_options += '<td>' + packagebundle.tax_price + '</td>';
                service_options += '<td>' + packagebundle.tax_including_price + '</td>';

                // Get is_consumed, consumed_at and sold_by names for this bundle
                let isConsumed = 'No';
                let consumedAt = 'N/A';
                let soldByNames = [];
                let firstSoldById = null;
                Object.values(packageservices).forEach(function (ps) {
                    if (ps.package_bundle_id == packagebundle.id) {
                        if (ps.is_consumed == '1') {
                            isConsumed = 'Yes';
                            if (ps.consumed_at) {
                                let date = new Date(ps.consumed_at);
                                let day = String(date.getDate()).padStart(2, '0');
                                let month = String(date.getMonth() + 1).padStart(2, '0');
                                let year = String(date.getFullYear()).slice(-2);
                                let hours = String(date.getHours()).padStart(2, '0');
                                let minutes = String(date.getMinutes()).padStart(2, '0');
                                consumedAt = day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
                            }
                        }
                        if (ps.sold_by && ps.sold_by.name && !soldByNames.includes(ps.sold_by.name)) {
                            soldByNames.push(ps.sold_by.name);
                        }
                        if (!firstSoldById && ps.sold_by) {
                            firstSoldById = (typeof ps.sold_by === 'object') ? ps.sold_by.id : ps.sold_by;
                        }
                    }
                });
                // For bundles (more than 1 child service), hide Consumed and Consumed At columns
                if (childServiceCount > 1) {
                    service_options += '<td>-</td>';
                    service_options += '<td>-</td>';
                } else {
                    service_options += '<td>' + isConsumed + '</td>';
                    service_options += '<td>' + consumedAt + '</td>';
                }
                service_options += '<td class="sold-by-display">' + (soldByNames.length > 0 ? soldByNames.join(', ') : 'N/A') + '</td>';

                // Derive row_type from consumption_order of first matching package_service
                var existingRowType = '';
                Object.values(packageservices).forEach(function (ps) {
                    if (ps.package_bundle_id == packagebundle.id && !existingRowType) {
                        var co = parseInt(ps.consumption_order) || 0;
                        if (co === 1) existingRowType = 'buy';
                        else if (co === 2 || co === 3) existingRowType = 'get';
                    }
                });

                // Hidden inputs for Save button data collection (td:nth-child(11))
                service_options += "<td class='d-none'>";
                service_options += "<input type='hidden' name='bundle_id' value='" + (packagebundle.bundle_id || '') + "' />";
                service_options += "<input type='hidden' name='discount_id' value='" + (packagebundle.discount_id || '') + "' />";
                service_options += "<input type='hidden' name='config_group_id' value='" + (packagebundle.config_group_id || '') + "' />";
                service_options += "<input type='hidden' name='row_type' value='" + existingRowType + "' />";
                service_options += "</td>";

                // Sold by hidden input + action buttons (td:nth-child(12))
                service_options += "<td>";
                service_options += "<input type='hidden' name='sold_by[]' value='" + (firstSoldById || '') + "' />";
                service_options += del_icon;
                service_options += "</td>";
                service_options += '</tr>';

                // Add child service rows for bundles (toggle functionality) - only if more than 1 child service
                if (childServiceCount > 1) {
                    Object.values(packageservices).forEach(function (packageservice) {
                        if (packageservice.package_bundle_id == packagebundle.id) {
                            let consume = packageservice.is_consumed == '0' ? 'No' : 'Yes';
                            let psConsumedAt = 'N/A';
                            if (packageservice.consumed_at) {
                                let date = new Date(packageservice.consumed_at);
                                let day = String(date.getDate()).padStart(2, '0');
                                let month = String(date.getMonth() + 1).padStart(2, '0');
                                let year = String(date.getFullYear()).slice(-2);
                                let hours = String(date.getHours()).padStart(2, '0');
                                let minutes = String(date.getMinutes()).padStart(2, '0');
                                psConsumedAt = day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
                            }
                            let originalPrice = packageservice.original_price ? parseFloat(packageservice.original_price).toFixed(2) : '-';
                            service_options += '<tr class="' + packagebundle.id + '" style="display: none; background-color: #f9f9f9;">';
                            service_options += '<td>' + packageservice.service.name + '</td>';
                            service_options += '<td>' + originalPrice + '</td>';
                            service_options += '<td>-</td>';
                            service_options += '<td>-</td>';
                            service_options += '<td>' + packageservice.tax_exclusive_price + '</td>';
                            service_options += '<td>' + packageservice.tax_price + '</td>';
                            service_options += '<td>' + packageservice.tax_including_price + '</td>';
                            service_options += '<td>' + consume + '</td>';
                            service_options += '<td>' + psConsumedAt + '</td>';
                            service_options += '<td class="sold-by-display">' + (packageservice.sold_by ? packageservice.sold_by.name : 'N/A') + '</td>';
                            service_options += '<td></td>';
                            service_options += '</tr>';
                        }
                    });
                }
            });
        }

        let selectedAppointmentId = response.data.selectedAppointmentId;
        let appointment_options = '<option value="">Select Appointment</option>';
        if (appointmentArray && Object.keys(appointmentArray).length > 0) {
            Object.values(appointmentArray).forEach(function (appointment) {
                let selected = (appointment.id === selectedAppointmentId) ? 'selected' : '';
                appointment_options += '<option value="' + appointment.id + '" ' + selected + '>' + appointment.name + '</option>';
            });
        }

        let serviceOptions = '<option value="">Select Service</option>';
        let userOptions = '<option value="">Select</option>';
         if (users) {
            Object.entries(users).forEach(function ([id, name]) {
                let selected = (parseInt(id) === parseInt(selected_user_id)) ? 'selected' : '';
                userOptions += '<option value="' + id + '" ' + selected + '>' + name + '</option>';
            });
        }

        if (locationhasservice.length) {
            Object.values(locationhasservice).forEach(function (packageservice) {
                serviceOptions += '<option value="' + packageservice?.id + '">' + packageservice?.name + '</option>';
            });
        }
        let payment_options = '<option value="">Select Payment Mode</option>';
        if (paymentmodes) {
            Object.entries(paymentmodes).forEach(function (paymentmode) {
                payment_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
            });
        }


        $("#edit_appointment_id").html(appointment_options);
        $("#edit-membership-name").text(membership);
        $("#edit_service_id").html(serviceOptions);

        appointmentCheck(package);

        $("#edit_plan_services").html(service_options);

        $(".edit_plan_history").html(history_options);

        $("#edit_payment_mode_id").html(payment_options);
        $("#edit_sold_by").html(userOptions);
        $("#edit-patient-name").text(patient?.name)
        $("#edit-location-name").text(location?.name)
        $("#edit_random_id").val(package?.random_id)
        $("#edit_parent_id").val(package?.patient_id)
        $("#edit_location_id").val(package?.location?.id)
        $("#edit_random_id_1").val(package?.random_id)
        $("#edit_package_total_1").val(total_price.toFixed(2));
        $("#edit_grand_total_1").val(grand_total);
        ExistingTotal = parseFloat(total_price) || 0;
        $('#edit_cash_amount_1').val(0);
        $('#edit_cash_amount_1').prop('disabled', true);
        $('#edit_payment_mode_id').val('');

        // Lock Add button only if a config group has out-of-order consumption
        if (window.editPlanLocked) {
            $('#EditPackage').attr('disabled', true).css('opacity', '0.5');
            $('#edit_service_id').prop('disabled', true);
            $('#edit_discount_id').prop('disabled', true);
            $('#edit_discount_type').prop('disabled', true);
            $('#edit_discount_value_1').prop('disabled', true);
            $('#edit_net_amount_1').prop('disabled', true);
            $('#edit_sold_by').prop('disabled', true);
            toastr.info('This plan has a configurable discount with out-of-order consumption. Please consume the BUY services first or create a new plan to add services.');
        } else {
            $('#EditPackage').attr('disabled', false).css('opacity', '1');
            $('#edit_service_id').prop('disabled', false);
        }

    } catch (error) {
        showException(error);
    }

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
                    cash_receveive_remain: $("#edit_grand_total_1").val()
                },
                cache: false,
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                        let cash_remain = response.data.cash_receveive_remain;
                        $("#edit_grand_total_1").val(cash_remain);
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

        let input = '<label class="required fw-bold fs-6 mb-2 pl-0">Payment Mode <span class="text text-danger">*</span></label><input type="text" id="payment_mode_id" name="payment_mode_id" value="' + pack_adv_info?.payment_mode_id + '" readonly class="form-control">';

        $(".append_payment_mode").html(input);

    }

    if (permissions.plans_cash_edit_amount) {
        $("#plane_cash_amount").val(pack_adv_info.cash_amount);
    } else {
        $("#plane_cash_amount").remove();

        let input = '<label class="required fw-bold fs-6 mb-2 pl-0">Amount <span class="text text-danger">*</span></label><input type="text" id="cash_amount" name="cash_amount" value="' + pack_adv_info?.cash_amount + '" readonly class="form-control">';

        $(".append_cash_amount").html(input);

    }

    if (permissions.plans_cash_edit_date) {
        $("#plane_cash_date").val(formatDate(pack_adv_info.created_at, 'YYYY-MM-DD'));
    } else {
        $("#plane_cash_date").remove();

        let input = '<label class="required fw-bold fs-6 mb-2 pl-0">Date <span class="text text-danger">*</span></label><input type="text" id="created_at" name="created_at" value="' + formatDate(pack_adv_info.created_at, 'YYYY-MM-DD') + '" readonly class="form-control">';

        $(".append_cash_date").html(input);

    }

    $("#edit_package_advances_id").val(pack_adv_info.id);
    $("#edit_package_id").val(package_id);




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

                if (smsLog.invoice_id === null) {
                    rows += '<tr>';
                    rows += '<td>' + smsLog.to + '</td>';
                    rows += '<td><a href="javascript:void(0);" onclick="toggleText($(this))">';
                    rows += '<span class="short_text" style="display: block">' + smsLog.text.slice(0, 50).concat('...') + '</span>';
                    rows += '<span class="full_text" style="display: none; text-underline: none;">' + smsLog.text + '</span>';
                    '</a></td>';

                    if (smsLog.status) {
                        rows += '<td id="smsRow{' + smsLog.id + '">Yes</td>';
                    } else {
                        rows += '<td><span class="text-center" id="spanRow' + smsLog.id + '">No</span>\
                        <br/><a id="clickRow'+ smsLog.id + '" href="javascript:void(0)" onclick="resendSMS(' + smsLog.id + ', `' + sent_url + '`, `POST`);" class="btn btn-sm btn-success spinner-button" data-toggle="tooltip" title="Resend SMS">' +
                            '<i class="la la-send-o"></i></a></td>';
                    }

                    if (smsLog.is_refund == "Yes") {
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
        let membership = response.data.membership;

        $("#package_pdf").attr("href", route('admin.packages.package_pdf', package.id))

        let history_options = noRecordFoundTable(4);

        if (Object(packageadvances).length) {

            history_options = '';
            Object.values(packageadvances).forEach(function (packageadvance) {

                if (packageadvance.cash_amount != '0') {
                    history_options += '<tr>';
                    history_options += '<td>' + packageadvance.paymentmode.name + '</td>';
                    if (packageadvance.is_refund == 1) {
                        history_options += '<td>out / refund</td>';
                    } else if (packageadvance.is_setteled == 1) {
                        history_options += '<td>out / settled</td>';
                    }
                    else {
                        history_options += '<td>' + packageadvance.cash_flow + '</td>';
                    }
                    history_options += '<td>' + packageadvance.cash_amount + '</td>';
                    history_options += '<td>' + formatDate(packageadvance.created_at, 'MMM, DD yyyy hh:mm A') + '</td>';
                    history_options += '<tr>';
                }
            });
        }


        let service_options = noRecordFoundTable(10);

        if (packagebundles.length) {
            service_options = '';
            Object.values(packagebundles).forEach(function (packagebundle) {
                service_options += '<tr>';
                // Handle both bundle and membership types
                let itemName = '-';
                if (packagebundle.bundle && packagebundle.bundle.name) {
                    itemName = '<a href="javascript:void(0);" onclick="toggle(' + packagebundle.id + ')">' + packagebundle.bundle.name + '</a>';
                } else if (packagebundle.membership_type && packagebundle.membership_type.name) {
                    itemName = packagebundle.membership_type.name;
                }
                service_options += '<td>' + itemName + '</td>';
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
                if (packagebundle.discount_price == null) {
                    service_options += '0.00';
                } else {
                    service_options += packagebundle.discount_price;
                }
                service_options += '</td>';

                service_options += '<td>' + packagebundle.tax_exclusive_net_amount + '</td>';

                service_options += '<td>' + packagebundle.tax_price + '</td>';
                service_options += '<td>' + packagebundle.tax_including_price + '</td>';

                // Get packageservices for this bundle
                let bundleServices = [];
                let soldByNames = [];
                Object.values(packageservices).forEach(function (ps) {
                    if (ps.package_bundle_id == packagebundle.id) {
                        bundleServices.push(ps);
                        if (ps.sold_by && ps.sold_by.name && !soldByNames.includes(ps.sold_by.name)) {
                            soldByNames.push(ps.sold_by.name);
                        }
                    }
                });
                
                // Check if this is a bundle (multiple services) or plan (single service)
                let isBundle = bundleServices.length > 1;
                
                if (isBundle) {
                    // For bundles: show aggregated consumed status, expandable rows will show details
                    let anyConsumed = bundleServices.some(ps => ps.is_consumed == '1');
                    service_options += '<td>' + (anyConsumed ? '-' : 'No') + '</td>';
                    service_options += '<td>-</td>';
                } else {
                    // For plans (single service): show consumed status directly
                    let isConsumed = 'No';
                    let consumedAt = 'N/A';
                    if (bundleServices.length > 0 && bundleServices[0].is_consumed == '1') {
                        isConsumed = 'Yes';
                        if (bundleServices[0].consumed_at) {
                            let date = new Date(bundleServices[0].consumed_at);
                            let day = String(date.getDate()).padStart(2, '0');
                            let month = String(date.getMonth() + 1).padStart(2, '0');
                            let year = String(date.getFullYear()).slice(-2);
                            let hours = String(date.getHours()).padStart(2, '0');
                            let minutes = String(date.getMinutes()).padStart(2, '0');
                            consumedAt = day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
                        }
                    }
                    service_options += '<td>' + isConsumed + '</td>';
                    service_options += '<td>' + consumedAt + '</td>';
                }
                service_options += '<td class="sold-by-display">' + (soldByNames.length > 0 ? soldByNames.join(', ') : 'N/A') + '</td>';

                service_options += '</tr>';
                
                // For bundles: add expandable rows for each service
                if (isBundle) {
                    bundleServices.forEach(function (ps) {
                        let consume = ps.is_consumed == '1' ? 'Yes' : 'No';
                        let psConsumedAt = 'N/A';
                        if (ps.consumed_at) {
                            let date = new Date(ps.consumed_at);
                            let day = String(date.getDate()).padStart(2, '0');
                            let month = String(date.getMonth() + 1).padStart(2, '0');
                            let year = String(date.getFullYear()).slice(-2);
                            let hours = String(date.getHours()).padStart(2, '0');
                            let minutes = String(date.getMinutes()).padStart(2, '0');
                            psConsumedAt = day + '/' + month + '/' + year + ' ' + hours + ':' + minutes;
                        }
                        // Child row: Service Name | - | - | - | Subtotal | Tax | Total | Consumed | Consumed At | Sold By
                        service_options += '<tr class="' + packagebundle.id + '" style="display: none; background-color: #f5f5f5;">';
                        service_options += '<td style="padding-left: 30px; font-style: italic;">&nbsp;&nbsp;↳ ' + ps.service.name + '</td>';
                        service_options += '<td>-</td>'; // Regular Price
                        service_options += '<td>-</td>'; // Discount Name
                        service_options += '<td>-</td>'; // Discount
                        service_options += '<td>' + ps.tax_exclusive_price + '</td>'; // Subtotal
                        service_options += '<td>' + ps.tax_price + '</td>'; // Tax
                        service_options += '<td>' + ps.tax_including_price + '</td>'; // Total
                        service_options += '<td>' + consume + '</td>'; // Consumed
                        service_options += '<td>' + psConsumedAt + '</td>'; // Consumed At
                        service_options += '<td>' + (ps.sold_by ? ps.sold_by.name : 'N/A') + '</td>'; // Sold By
                        service_options += '</tr>';
                    });
                }
            });
        }

        $(".display_plans").html(service_options);
        $("#membership_name").text(membership);


        $(".plan_history").html(history_options);
        var totalam = Math.round(response.data.grand_total);
        $(".package_total_price").text(totalam);
        $("#user_name").text(package.user.name)
        $("#location_name").text(package.location.name)


    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function () {

        let patientId = $("#search_patient_id").val();

        let filters = {
            delete: '',
            id: $("#search_id").val(),
            patient_id: patientId,
            patient_name: patientId ? $("#search_patient_id").find('option:selected').text() : '',
            package_id: $("#search_plan_id").val(),
            location_id: $("#search_location_id").val(),
            created_at: $("#date_range").val(),
            action: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function () {
        // Clear all filter fields
        $('#search_patient_id').val(null).trigger('change');
        $('#search_plan_id').val(null).trigger('change');
        $('#search_location_id').val(null).trigger('change');
        $('#date_range').val('');
        
        let filters = {
            delete: '',
            id: '',
            patient_id: '',
            package_id: '',
            location_id: '',
            created_at: '',
            action: ['filter_cancel'],
        }
        datatable.search(filters, 'search');
    });

}

function resetCustomFilters() {

    $(".filter-field").val('');
    $('#search_patient_id').val(null).trigger('change');
    $('#search_plan_id').val(null).trigger('change');
    $('#search_location_id').val(null).trigger('change');
    $('#date_range').val('');
    addUsers();
    $('.select2').val(null).trigger('change');
}


function setFilters(filter_values, active_filters) {

    try {
        // Safety check - if filter_values is undefined or empty, just return
        if (!filter_values || typeof filter_values !== 'object') {
            console.warn('setFilters called with invalid filter_values:', filter_values);
            return;
        }

        let locations = filter_values.locations;

        let location_options = '<option value="">All</option>';

        let validLocationIds = {};
        if (locations) {
            Object.entries(locations).forEach(function (value) {
                location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
                validLocationIds[String(value[0])] = true;
            });
        }

        $("#search_location_id").html(location_options);

        $("#search_id").val(active_filters.id);

        let activeLocId = active_filters.location_id;
        if (activeLocId && !validLocationIds[String(activeLocId)]) {
            activeLocId = '';
        }
        $("#search_location_id").val(activeLocId).trigger('change');
        $("#date_range").val(active_filters.created_at);

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
    total_amountArray = [];
    edit_amountArray = [];
    ExistingTotal = 0;
    $('#add_service_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    setTimeout(function () {
        $("#add_discount_id").html('<option value="">Select Discount</option>');
        
        // If in patient card context, pre-fill patient ID (patient info will be loaded in setPlanData)
        if (typeof window.isPatientCardContext !== 'undefined' && window.isPatientCardContext && typeof window.patientCardPatientId !== 'undefined') {
            $("#add_patient_id").val(window.patientCardPatientId).trigger('change');
            // Hide patient search field since patient is already selected
            $("#add_patient_id").closest('.fv-row').find('.select2-container').hide();
        } else {
            $("#add_patient_id").val(null).trigger('change');
        }
        
        $(".search_patient").val('');
        $("#net_amount_1").val('');
        $("#package_total_1").val('');
        $("#grand_total_1").val('');
        // Don't clear membership in patient card context - it will be set by loadPatientInfoForCreate
        if (!(typeof window.isPatientCardContext !== 'undefined' && window.isPatientCardContext)) {
            $('#packages_add').find('#patient_membership').val('');
        }
        $('#packages_add').find('#discount_value_1').val('');
        $('#packages_add').find("#add_appointment_id").empty();
        $('#packages_add').find('#add_appointment_id').val(null).trigger('change');
        $('#add_service_id').val(null).trigger('change');
        $('#add_sold_by').html('<option value="">Select</option>').val(null).trigger('change');
    }, 500)

    $("#add_discount_type").attr('disabled', true);
    $("#add_discount_value_1").val('');
    $("#add_discount_value_1").attr('disabled', true);

    $('#successMessage').hide();
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
            $("#cash_amount_1").val(0);

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
        Object.entries(locations).forEach(function (location) {
            // Skip "All Cities-All Centres" option
            if (location[1] !== 'All Cities-All Centres') {
                location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
            }
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

    $("#add_plan_location_id").html(location_options).val(appointmentinformation?.location_id);
    $("#random_id_1").val(random_id);
    $('#cash_amount_1').prop('disabled', true);

    // Auto-select location if user has only one location assigned
    if (locations) {
        // Filter out "All Cities-All Centres" option
        var validLocations = Object.entries(locations).filter(function(location) {
            return location[1] !== 'All Cities-All Centres';
        });
        if (validLocations.length === 1) {
            $("#add_plan_location_id").val(validLocations[0][0]).trigger('change');
        }
    }

    getServices();

    // getUserCentre is defined in packages/index.blade.php inline script
    // Only call it if it exists (not in patient card context)
    if (typeof getUserCentre === 'function') {
        getUserCentre();
    }
    
    // If in patient card context, load patient info
    if (typeof window.isPatientCardContext !== 'undefined' && window.isPatientCardContext && typeof window.patientCardPatientId !== 'undefined') {
        loadPatientInfoForCreate(window.patientCardPatientId);
    }

}

// Load patient info for create modal when in patient card context
function loadPatientInfoForCreate(patientId) {
    $.ajax({
        url: route('admin.patients.getPatient', { id: patientId }),
        type: 'GET',
        success: function(response) {
            if (response.status && response.data) {
                let patient = response.data.patient;
                let membership = response.data.membership;
                
                // Scope selectors to the modal to avoid conflicts with other elements on page
                let $modal = $('#modal_add_plan');
                
                // Set patient name (h3 element in patient card context)
                $modal.find('#add-patient-name').text(patient?.name || '');
                
                // Set membership info (h4 element in patient card context, input in main module)
                let membershipText = 'No Membership';
                if (membership) {
                    // Format: Gold - CA12345 - Active (Exp: Jan 29, 2027)
                    let statusText = membership.is_active ? 'Active' : 'Inactive';
                    let expDate = membership.end_date ? new Date(membership.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                    membershipText = (membership.type || 'Gold') + ' - ' + (membership.code || '') + ' - ' + statusText + (expDate ? ' (Exp: ' + expDate + ')' : '');
                }
                let $membershipEl = $modal.find('#patient_membership');
                if ($membershipEl.is('h4')) {
                    $membershipEl.text(membershipText);
                } else {
                    $membershipEl.val(membershipText);
                }
                
                // Set hidden patient ID
                $modal.find('#add_patient_id').val(patientId);
                // Trigger patient change to load appointments
                getAppointments(patientId);
            }
        },
        error: function() {
            console.log('Failed to load patient info');
        }
    });
}

function getServices() {

    hideMessages();

    let location = $("#add_plan_location_id").val();

    // Don't call API if no location is selected
    if (!location || location == '') {
        return;
    }

    let url = route('admin.packages.getservice', {
        _query: {
            location_id: location
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

            setServices(response);


        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            $('#datanotexist').show();
        }
    });
}

function setServices(response) {

    getAppointments($("#add_patient_id").val());

    try {
        // Check if response has data and service array
        if (!response.data || !response.data.service) {
            $('#datanotexist').show();
            $("#add_service_id").html('<option value=""> Select Service </option>');
            return;
        }

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
function setSoldBy(response) {
    try {
        let users = response.data.users;
        let selected_doctor_id = response.data.selected_doctor_id;
        let user_options = '<option value=""> Select </option>';

        Object.entries(users).forEach(function ([id, name]) {
            let selected = (parseInt(id) === parseInt(selected_doctor_id)) ? 'selected' : '';
            user_options += '<option value="' + id + '" ' + selected + '> ' + name + ' </option>';
        });

        $("#add_sold_by").html(user_options);

    } catch (error) {
        showException(error);
    }
}
function getAppointments(patient) {
    let location = $("#add_plan_location_id").val();

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
                setAppointments(response);
                    setSoldBy(response);
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
        let latestConsultationId = response.data.latest_consultation_id;
        let appointment_options = '<option value="">Select Appointment</option>';
        let membership = response.data.membership;
        let appointmentKeys = [];

        // Check if appointments object has any keys
        if (appointments && Object.keys(appointments).length > 0) {

            Object.entries(appointments).forEach(function ([id, value]) {
                appointment_options += '<option value="' + id + '"> ' + value.name + ' </option>';
                appointmentKeys.push(id);
            });

        }
        
        $("#add_appointment_id").html(appointment_options);
        
        // Pre-select the latest consultation
        if (latestConsultationId) {
            $("#add_appointment_id").val(latestConsultationId).trigger('change');
        } else if (appointmentKeys.length === 1) {
            // Fallback: Auto-select if only one appointment exists
            $("#add_appointment_id").val(appointmentKeys[0]).trigger('change');
        }
        
        $("#patient_membership").val(membership);
        $("#patient_membership").attr('disabled', true);
    } catch (error) {
        showException(error);
    }
}

/*Add Plan functions*/
function getServiceDiscount($this, type = '') {
    hideMessages();
    var service_id = $this.val();
    var patient_id = $('#add_patient_id').val();
    var location_id = $('#add_plan_location_id').val();
    //$("#"+type+"add_discount_id").val('0').trigger('change');
    if (service_id == "") {
        $("#add_service_id_error").show()
    } else {
        $("#add_service_id_error").hide()
    }
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
            url: route('admin.packages.getserviceinfo_for_plan'),
            data: {
                'service_id': service_id, // Direct service_id for simple plans
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (resposne) {
                $('#add_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
                // Store service info for client-side Add button
                window.createPlanServiceInfo = {
                    service_id: service_id,
                    service_name: resposne.data.service_name,
                    service_price: resposne.data.net_amount,
                    tax_treatment_type_id: resposne.data.tax_treatment_type_id,
                    location_tax_percentage: resposne.data.location_tax_percentage
                };
                window.createPlanDiscountInfo = null; // Reset discount info when service changes
                if (resposne.status) {

                    let discounts = resposne.data.discounts;

                    let options = '<option value="" >Select Discount</option>';

                    jQuery.each(discounts, function (i, discount) {
                        options += '<option value="' + discount.id + '">' + discount.name + '</option>';
                    });

                    $("#" + type + "add_discount_id").html(options);

                    $("#net_amount_1").val((resposne.data.net_amount).toFixed(2));
                    $("#net_amount_1").prop("disabled", true);

                } else {

                    let options = '<option value="" >Select Discount</option>';

                    $("#add_discount_id").html(options);

                    $("#net_amount_1").val((resposne.data.net_amount).toFixed(2));
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
    $("#add_discount_type_error").hide()
    var service_id = $('#add_service_id').val(); //Basicailly it is bundle id
    var discount_id = $this.val();
    var patient_id = $('#add_patient_id').val();
    var location_id = $('#add_plan_location_id').val();
    setTimeout(function () {
        $('#add_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    }, 500)
    if (discount_id == "") {
        $("#AddPackage").prop('disabled', false);
    }
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
        $('#configurable_preview').remove();
        $("#select_discount_type").css("display", "block");
        $("#configurable_discount_type").css("display", "none");
        $("#discount_value_div").css("display", "block");
        $("#price_div").css("display", "block");
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
                    $("#net_amount_1").val((resposne.data.net_amount).toFixed(2));
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
                url: route('admin.packages.getdiscountinfo_for_plan'),
                data: {
                    'service_id': service_id,
                    'discount_id': discount_id,
                    'patient_id': patient_id,
                    'location_id': location_id
                },
                success: function (resposne) {

                    if (resposne.status) {
                        // Store discount info for client-side Add button
                        window.createPlanDiscountInfo = resposne.data;

                        if (resposne.data.custom_checked == 0) {
                            $("#add_discount_type").val(resposne.data.discount_type).change();
                            $("#edit_discount_type").prop("disabled", true);
                            if (resposne.data.is_configurable || resposne.data.discount_type == "Configurable") {
                                $("#select_discount_type").css("display", "none");
                                $("#configurable_discount_type").css("display", "block");
                                $("#discount_value_div").css("display", "none");
                                $("#price_div").css("display", "none");
                                // Show configurable preview table
                                $('#configurable_preview').remove();
                                if (resposne.data.preview_rows && resposne.data.preview_rows.length) {
                                    var html = '<div id="configurable_preview" class="mt-3 alert alert-info" style="color: white;">';
                                    html += '<strong>Configurable Discount Preview:</strong>';
                                    html += '<table class="table table-sm table-bordered mt-2 mb-0" style="color: white;">';
                                    html += '<thead><tr><th style="color: white !important;">Service</th><th style="color: white !important;">Regular Price</th><th style="color: white !important;">Discount</th><th style="color: white !important;">Net Amount</th></tr></thead><tbody>';
                                    jQuery.each(resposne.data.preview_rows, function (i, row) {
                                        var badge = row.row_type === 'buy'
                                            ? '<span class="badge badge-primary" style="color: white;">BUY</span>'
                                            : '<span class="badge badge-success" style="color: white;">GET</span>';
                                        html += '<tr>';
                                        html += '<td style="color: white !important;">' + badge + ' ' + row.service_name + '</td>';
                                        html += '<td style="color: white !important;">' + parseFloat(row.service_price).toLocaleString() + '</td>';
                                        html += '<td style="color: white !important;">' + row.discount_type + '</td>';
                                        html += '<td style="color: white !important;">' + parseFloat(row.net_amount).toLocaleString() + '</td>';
                                        html += '</tr>';
                                    });
                                    html += '</tbody></table></div>';
                                    $('#net_amount_1').closest('.fv-row, .form-group').after(html);
                                }
                                $("#net_amount_1").val(resposne.data.total_net_amount ?? 0);
                                $("#net_amount_1").prop("disabled", true);
                                $("#slug_1").val('not_custom');
                                setTimeout(function () { $("#AddPackage").removeAttr('disabled'); }, 200);
                                inputSpinner(false);
                                return;
                            } else {
                                $("#select_discount_type").css("display", "block");
                                $("#configurable_discount_type").css("display", "none");
                                $("#discount_value_div").css("display", "block");
                                $("#add_discount_type").prop("disabled", true);
                                $('#configurable_preview').remove();
                            }
                            $("#discount_value_1").val(resposne.data.discount_price);
                            $("#discount_value_1").prop("disabled", true);
                            $("#net_amount_1").val((resposne.data.net_amount).toFixed(2));
                            $("#net_amount_1").prop("disabled", true);
                            $("#slug_1").val('not_custom');
                            if (resposne.data.discount_type == 'Percentage') {
                                if (resposne.data.discount_price > 100) {
                                    $('#percentageMessage').show();

                                    return false;
                                } else {
                                    $('#percentageMessage').hide();

                                }
                            } else {

                                if (resposne.data.discount_price > resposne.data.net_amount) {
                                 
                                if(resposne.data.discount_is_voucher == 1){
                                    setTimeout(function () {
                                        $("#AddPackage").removeAttr('disabled');
                                        $("#discount_value_1").val(resposne.data.discount_price);
                                    }, 500);
                                }
                                else{
                                    setTimeout(function () {
                                        $("#AddPackage").attr('disabled', 'disabled');
                                        $("#discount_value_1").val('');
                                    }, 500);

                                }
                                    
                                } else {
                                    
                                    setTimeout(function () {
                                        $("#AddPackage").removeAttr('disabled');
                                    }, 500);

                                }
                            }
                        } else {
                            // Custom discount - enable type selection and store allocation limits
                            $("#add_discount_type").prop("disabled", false);
                            $("#add_discount_type").val('').trigger('change');
                            $("#discount_value_1").prop("disabled", false);
                            $("#discount_value_1").val('');
                            $("#net_amount_1").val(parseFloat(resposne.data.service_price).toFixed(2));
                            $("#net_amount_1").prop("disabled", true);
                            $("#slug_1").val('custom');
                            
                            // Store allocation limits for validation
                            window.customDiscountLimits = {
                                allocation_type: resposne.data.allocation_type,
                                allocation_amount: resposne.data.allocation_amount,
                                service_price: resposne.data.service_price,
                                max_percentage: resposne.data.max_percentage,
                                max_fixed_amount: resposne.data.max_fixed_amount
                            };
                        }
                    } else {
                        $('#wrongMessage').show();
                    }

                    inputSpinner(false);
                },
            });
        }
    }

}

function editDiscountValue($this) {
   
    // Don't hide percentage message here - it will be managed by validation logic
    $('#edit_wrongMessage').hide();
    $('#edit_inputfieldMessage').hide();
    $('#edit_AlreadyExitMessage').hide();
    $('#edit_DiscountRange').hide();
    $('#edit_datanotexist').hide();

    if ($("#edit_discount_value_1").val() < 0) {
        $("#edit_discount_value_1").val('');
    } else {
        var service_id = $('#edit_service_id').val();//Basicailly it is bundle id
        var discount_id = $('#edit_discount_id').val();
        var discount_type = $('#edit_discount_type').val();
        var discount_value = parseFloat($this.val()) || 0;
        var patient_id = $('#edit_parent_id').val();
        var slug = $('#edit_slug_1').val();
        var location_id = $('#edit_location_id').val();
        var inputVal = $this.val() ? $this.val().toString() : '';
        
        if (inputVal.includes('.')) {
            var parts = $this.val().split('.');
            if (parts.length > 1 && parts[1].length > 2) {
                alert("Maximum 2 digits allowed after the decimal point.");
                discount_value = parseFloat($this.val().slice(0, -1)) || 0;
                $("#edit_discount_value_1").val(discount_value)
            } else {
                $("#edit_discount_value_1").val(discount_value);
            }
        }

        console.log('Edit discount value - service_id:', service_id, 'discount_id:', discount_id, 'discount_type:', discount_type, 'discount_value:', discount_value, 'location_id:', location_id, 'slug:', slug, 'limits:', window.editCustomDiscountLimits);
        
        // If discount value field is enabled (custom discount) but limits not set, fetch them first
        var isCustomDiscount = slug == 'custom' || !$('#edit_discount_value_1').prop('disabled');
        
        if (isCustomDiscount && !window.editCustomDiscountLimits && service_id && discount_id && location_id) {
            $.ajax({
                type: 'get',
                url: route('admin.packages.getdiscountinfo_for_plan'),
                async: false,
                data: {
                    'service_id': service_id,
                    'discount_id': discount_id,
                    'patient_id': patient_id,
                    'location_id': location_id
                },
                success: function (resposne) {
                    console.log('Fetched limits for edit:', resposne);
                    if (resposne.status && resposne.data.custom_checked == 1) {
                        window.editCustomDiscountLimits = {
                            allocation_type: resposne.data.allocation_type,
                            allocation_amount: resposne.data.allocation_amount,
                            service_price: resposne.data.service_price,
                            max_percentage: resposne.data.max_percentage,
                            max_fixed_amount: resposne.data.max_fixed_amount
                        };
                        // Also set the slug
                        $('#edit_slug_1').val('custom');
                    }
                }
            });
        }
      
        // Custom discount validation with allocation limits
        var validationFailed = false;
        
        if (window.editCustomDiscountLimits) {
            var limits = window.editCustomDiscountLimits;
            var numDiscountValue = parseFloat(discount_value) || 0;
            var numMaxPercentage = parseFloat(limits.max_percentage) || 0;
            var numMaxFixed = parseFloat(limits.max_fixed_amount) || 0;
            
            console.log('Validating - numDiscountValue:', numDiscountValue, 'numMaxPercentage:', numMaxPercentage, 'comparison:', numDiscountValue > numMaxPercentage);
            
            if (discount_type == 'Percentage' && numDiscountValue > numMaxPercentage) {
                console.log('PERCENTAGE LIMIT EXCEEDED - showing alert');
                validationFailed = true;
                var msgEl = document.getElementById('edit_percentageMessage');
                if (msgEl) {
                    msgEl.innerHTML = 'Maximum allowed percentage is ' + limits.max_percentage + '%';
                    msgEl.className = 'alert alert-danger';
                    msgEl.style.display = 'block';
                    msgEl.style.marginBottom = '15px';
                }
                $("#EditPackage").attr("disabled", true);
                // Use toastr if available, otherwise show native alert
                if (typeof toastr !== 'undefined') {
                    toastr.error('Maximum allowed percentage is ' + limits.max_percentage + '%');
                }
            } else if (discount_type == 'Percentage') {
                $('#edit_percentageMessage').addClass('display-hide').css('display', 'none');
            } else if (discount_type == 'Fixed' && numDiscountValue > numMaxFixed) {
                console.log('FIXED LIMIT EXCEEDED - showing alert');
                validationFailed = true;
                var msgEl = document.getElementById('edit_percentageMessage');
                if (msgEl) {
                    msgEl.innerHTML = 'Maximum allowed amount is ' + limits.max_fixed_amount.toFixed(2);
                    msgEl.className = 'alert alert-danger';
                    msgEl.style.display = 'block';
                    msgEl.style.marginBottom = '15px';
                }
                $("#EditPackage").attr("disabled", true);
                if (typeof toastr !== 'undefined') {
                    toastr.error('Maximum allowed amount is ' + limits.max_fixed_amount.toFixed(2));
                }
            } else if (discount_type == 'Fixed') {
                $('#edit_percentageMessage').addClass('display-hide').css('display', 'none');
            }
        } else if (discount_type == 'Percentage') {
            var numDiscountValue = parseFloat(discount_value) || 0;
            if (numDiscountValue > 100) {
                validationFailed = true;
                $('#edit_percentageMessage').html('Maximum allowed percentage is 100%').removeClass('display-hide').css('display', 'block');
                $("#EditPackage").attr("disabled", true);
                if (typeof toastr !== 'undefined') {
                    toastr.error('Maximum allowed percentage is 100%');
                }
            } else {
                $('#edit_percentageMessage').addClass('display-hide').css('display', 'none');
            }
        }
        
        // Don't make AJAX call if validation failed
        if (validationFailed) {
            return false;
        }
        
        if (service_id && discount_id && discount_type && discount_value > 0) {

            $.ajax({
                type: 'get',
                url: route('admin.packages.getdiscountinfocustom_for_plan'),
                data: {
                    'service_id': service_id,
                    'discount_id': discount_id,
                    'discount_value': discount_value,
                    'discount_type': discount_type,
                    'patient_id': patient_id,
                    'location_id': location_id
                },
                success: function (resposne) {
                    console.log('Edit AJAX response:', resposne);
                    if (resposne.status) {
                        $("#edit_net_amount_1").val(parseFloat(resposne.data.net_amount).toFixed(2));
                        $("#edit_net_amount_1").prop("disabled", true);
                        $("#EditPackage").attr("disabled", false);
                        inputSpinner(false)
                    } else {
                        $("#EditPackage").attr("disabled", true);
                        $('#edit_DiscountRange').show();
                    }
                },
                error: function (xhr) {
                    console.log('Edit AJAX Error:', xhr);
                    inputSpinner(false, 'EditPackage')
                }
            });
        } else if (service_id && discount_id && discount_type && discount_value == 0) {
            // Reset to service price when discount value is 0
            if (window.editCustomDiscountLimits) {
                $("#edit_net_amount_1").val(parseFloat(window.editCustomDiscountLimits.service_price).toFixed(2));
            }
        }
    }

}

function changeDiscount($this) {
   
    hideMessages();

    var service_id = $('#add_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#add_discount_id').val();
    var discount_value = $('#discount_value_1').val();
    var discount_type = $this.val();
    var patient_id = $('#add_patient_id').val();
    $("#edit_discount_value_1").val(0);
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
            url: route('admin.packages.getdiscountinfocustom_for_plan'),
            data: {
                'service_id': service_id,
                'discount_id': discount_id,
                'discount_value': discount_value,
                'discount_type': discount_type,
                'patient_id': patient_id,
                'location_id': location_id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#net_amount_1").val((resposne.data.net_amount).toFixed(2));
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
    $('#service_id').html('');
    hideMessages();

    var service_id = $this.val();
    var location_id = $('#edit_location_id').val();
    var patient_id = $('#edit_parent_id').val();
    var package_id = $('#edit_random_id').val(); // Get package ID

    //$("#"+type+"discount_id").val('0').trigger('change');
    setTimeout(function () {
        $('#edit_discount_value_1').val('');
        $("#edit_discount_value_1").attr('disabled', true);
        $("#edit_discount_type").val('').change();
        $("#edit_discount_type").attr('disabled', true);
        $('#edit_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    }, 500)
    // Restore visibility of fields that configurable discount may have hidden
    $("#select_edit_discount_type").css("display", "block");
    $("#edit_configurable_discount_type").css("display", "none");
    $("#edit_discount_value_div").css("display", "block");
    $('#configurable_preview').remove();
    if (service_id && patient_id) {
        // Check if service is duplicate and update sold by dropdown
        $.ajax({
            type: 'get',
            url: route('admin.packages.checkDuplicateServiceForSoldBy'),
            data: {
                'bundle_id': service_id,
                'package_id': package_id,
                'location_id': location_id
            },
            success: function (response) {
                if (response.status) {
                    // Update sold by dropdown with returned users
                    let userOptions = '<option value="">Select</option>';
                    if (response.data.users) {
                        Object.entries(response.data.users).forEach(function ([id, name]) {
                            userOptions += '<option value="' + id + '">' + name + '</option>';
                        });
                    }
                    $("#edit_sold_by").html(userOptions);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                // If endpoint fails, keep existing sold by dropdown
                console.error('Error checking duplicate service:', thrownError);
            }
        });

        $.ajax({
            type: 'get',
            url: route('admin.packages.getserviceinfo_for_plan'),
            data: {
                'service_id': service_id, // Direct service_id for simple plans
                'location_id': location_id,
                'patient_id': patient_id
            },
            success: function (resposne) {
                // Store service info for client-side Add button (edit plan)
                window.editPlanServiceInfo = {
                    service_id: service_id,
                    service_name: resposne.data.service_name,
                    service_price: resposne.data.net_amount,
                    tax_treatment_type_id: resposne.data.tax_treatment_type_id,
                    location_tax_percentage: resposne.data.location_tax_percentage
                };
                window.editPlanDiscountInfo = null;

                if (resposne.status) {
                    let discounts = resposne.data.discounts;
                    let options = '<option value="" >Select Discount/Voucher</option>';
                    jQuery.each(discounts, function (i, discount) {
                        options += '<option value="' + discount.id + '">' + discount.name+' ('+discount.discount_type+')' + '</option>';
                    });
                    $("#edit_discount_id").html(options);
                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);

                } else {
                    let options = '<option value="" >Select Discount/Voucher</option>';
                    $("#edit_discount_id").html(options);
                    $("#edit_net_amount_1").val(resposne.data.net_amount);
                    $("#edit_net_amount_1").prop("disabled", true);

                }
            },
        });

    }

    if ((service_id == null || service_id == '') && patient_id != '') {
        $("#edit_discount_id").html('<option value="">Select Discount/Voucher</option>');
        $("#edit_discount_type").attr('disabled', true);
        $("#edit_discount_value_1").attr('disabled', true);
        setTimeout(function () {
            $('#edit_discount_value_1').val('');
            $("#edit_discount_type").val('').change();
            $('#edit_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            $('#edit_service_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            $("#edit_net_amount_1").val('');
            return false;
        }, 100)
        return false;
    }

}

function editDiscountInfo($this) {

    $('#edit_DiscountRange').hide();
    hideMessages();

    var service_id = $('#edit_service_id').val(); //Basicailly it is bundle id
    var discount_id = $this.val();
    if (discount_id == "") {
        $("#EditPackage").prop('disabled', false);
    }
    setTimeout(function () {
        $('#edit_discount_type').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
    }, 500)
    if (service_id == null && (discount_id == null || discount_id == '')) {

        $("#edit_discount_type").prop("disabled", false);
        $("#edit_discount_type").val('').trigger('change');
        $("#discount_value_1").prop("disabled", false);
        $("#discount_value_1").val('');
        $("#edit_net_amount_1").prop("disabled", false);
        $("#edit_net_amount_1").val('');
        $("#edit_slug_1").val('not_custom');


    } else if ((discount_id == null || discount_id == '') && service_id != null) {

        $("#edit_discount_type").prop("disabled", true);
        $("#edit_discount_type").val('').trigger('change');
        $("#edit_discount_value_1").prop("disabled", true);
        $("#edit_discount_value_1").val('');
        $("#edit_slug_1").val('not_custom');
        setTimeout(function () {
            $('#edit_discount_id').parents(".modal").find(".select2-selection").removeClass("select2-is-invalid");
            if ($("#edit_net_amount_1").val() == '') {
                $("#edit_service_id").val($("#edit_service_id").val()).change();
            } else {
                editServiceDiscount($("#edit_service_id"));
            }
        }, 100);

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
                url: route('admin.packages.getdiscountinfo_for_plan'),
                data: {
                    'service_id': service_id,
                    'discount_id': discount_id,
                    'patient_id': $('#edit_parent_id').val(),
                    'location_id': $('#edit_location_id').val()
                },
                success: function (resposne) {

                    if (resposne.status) {
                        // Store discount info for client-side Add button (edit plan)
                        window.editPlanDiscountInfo = resposne.data;

                        if (resposne.data.custom_checked == 0) {
                            $("#edit_discount_type").val(resposne.data.discount_type).change();
                            $("#edit_discount_type").prop("disabled", true);
                            if (resposne.data.discount_type == "Configurable" || resposne.data.is_configurable) {
                                $("#select_edit_discount_type").css("display", "none");
                                $("#edit_configurable_discount_type").css("display", "block");
                                $("#edit_discount_value_div").css("display", "none");
                                $("#edit_price_div").css("display", "none");
                                // Show configurable preview table
                                $('#configurable_preview').remove();
                                if (resposne.data.preview_rows && resposne.data.preview_rows.length) {
                                    var html = '<div id="configurable_preview" class="mt-3 alert alert-info" style="color: white;">';
                                    html += '<strong>Configurable Discount Preview:</strong>';
                                    html += '<table class="table table-sm table-bordered mt-2 mb-0" style="color: white;">';
                                    html += '<thead><tr><th style="color: white !important;">Service</th><th style="color: white !important;">Regular Price</th><th style="color: white !important;">Discount</th><th style="color: white !important;">Net Amount</th></tr></thead><tbody>';
                                    jQuery.each(resposne.data.preview_rows, function (i, row) {
                                        var badge = row.row_type === 'buy'
                                            ? '<span class="badge badge-primary" style="color: white;">BUY</span>'
                                            : '<span class="badge badge-success" style="color: white;">GET</span>';
                                        html += '<tr>';
                                        html += '<td style="color: white !important;">' + badge + ' ' + row.service_name + '</td>';
                                        html += '<td style="color: white !important;">' + parseFloat(row.service_price).toLocaleString() + '</td>';
                                        html += '<td style="color: white !important;">' + row.discount_type + '</td>';
                                        html += '<td style="color: white !important;">' + parseFloat(row.net_amount).toLocaleString() + '</td>';
                                        html += '</tr>';
                                    });
                                    html += '</tbody></table></div>';
                                    $('#edit_net_amount_1').closest('.fv-row, .form-group').after(html);
                                }
                                $("#edit_net_amount_1").val(resposne.data.total_net_amount ?? 0);
                                $("#edit_net_amount_1").prop("disabled", true);
                                $("#edit_slug_1").val('not_custom');
                                setTimeout(function () { $("#EditPackage").removeAttr('disabled'); }, 200);
                                return;
                            } else {
                                $("#select_edit_discount_type").css("display", "block");
                                $("#edit_configurable_discount_type").css("display", "none");
                                $("#edit_discount_type_configurable").prop("disabled", true);
                                $("#edit_discount_value_div").css("display", "block");
                                $('#configurable_preview').remove();
                            }

                            $("#edit_discount_value_1").val(resposne.data.discount_price);
                            $("#edit_discount_value_1").prop("disabled", true);
                            $("#edit_net_amount_1").val((resposne.data.net_amount).toFixed(2));
                            $("#edit_net_amount_1").prop("disabled", true);
                            $("#edit_slug_1").val('not_custom');
                           
                            if (resposne.data.discount_type == 'Percentage') {
                                if (resposne.data.discount_price > 100) {
                                    $('#edit_percentageMessage').show();

                                    return false;
                                } else {
                                    $('#edit_percentageMessage').hide();
                                    $("#EditPackage").prop("disabled", false);
                                }
                            } else {
                                $('#edit_DiscountRange').hide();
                                $("#EditPackage").prop("disabled", false);
                            }
                        } else {
                            // Custom discount - enable type selection and store allocation limits
                            $("#edit_discount_type").prop("disabled", false);
                            $("#edit_discount_type").val('').trigger('change');
                            $("#edit_discount_value_1").prop("disabled", false);
                            $("#edit_discount_value_1").val('');
                            $("#edit_net_amount_1").val(parseFloat(resposne.data.service_price).toFixed(2));
                            $("#edit_net_amount_1").prop("disabled", true);
                            $("#edit_slug_1").val('custom');
                            
                            // Store allocation limits for validation
                            window.editCustomDiscountLimits = {
                                allocation_type: resposne.data.allocation_type,
                                allocation_amount: resposne.data.allocation_amount,
                                service_price: resposne.data.service_price,
                                max_percentage: resposne.data.max_percentage,
                                max_fixed_amount: resposne.data.max_fixed_amount
                            };
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
    
    hideMessages();
    var service_id = $('#add_service_id').val();//Basicailly it is bundle id
    var discount_id = $('#add_discount_id').val();
    var discount_type = $('#add_discount_type').val();
    var discount_value = parseFloat($this.val()) || 0;
    var patient_id = $('#add_patient_id').val();
    var slug = $('#slug_1').val();
    var location_id = $('#add_plan_location_id').val();
    
    if ($this.val().includes('.')) {
        var parts = $this.val().split('.');
        if (parts.length > 1 && parts[1].length > 2) {
            alert("Maximum 2 digits allowed after the decimal point.");
            discount_value = parseFloat($this.val().slice(0, -1)) || 0;
            $("#discount_value_1").val(discount_value)
        } else {
            $("#discount_value_1").val(discount_value);
        }
    }

    if (discount_value != "") {
        $("#add_discount_value_error").hide()
    } else {
        $("#add_discount_value_error").show()
    }

    // Custom discount validation with allocation limits
    if (slug == 'custom' && window.customDiscountLimits) {
        var limits = window.customDiscountLimits;
        
        if (discount_type == 'Percentage') {
            // Max percentage is the allocation amount (e.g., 40%)
            if (discount_value > limits.max_percentage) {
                $('#percentageMessage').text('Maximum allowed percentage is ' + limits.max_percentage + '%').show();
                return false;
            } else {
                $('#percentageMessage').hide();
            }
        } else if (discount_type == 'Fixed') {
            // Max fixed amount is the percentage of service price
            if (discount_value > limits.max_fixed_amount) {
                $('#percentageMessage').text('Maximum allowed amount is ' + limits.max_fixed_amount.toFixed(2)).show();
                return false;
            } else {
                $('#percentageMessage').hide();
            }
        }
    } else if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#percentageMessage').text('Maximum allowed percentage is 100%').show();
            return false;
        } else {
            $('#percentageMessage').hide();
        }
    }

    if (service_id && discount_id && discount_type && discount_value > 0) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfocustom_for_plan'),
            data: {
                'service_id': service_id,
                'discount_id': discount_id,
                'discount_value': discount_value,
                'discount_type': discount_type,
                'patient_id': patient_id,
                'location_id': location_id
            },
            success: function (resposne) {
                if (resposne.status) {
                    $("#net_amount_1").val(parseFloat(resposne.data.net_amount).toFixed(2));
                    $("#net_amount_1").prop("disabled", true);
                    $("#AddPackage").removeAttr('disabled');
                    inputSpinner(false)
                } else {
                    $("#AddPackage").attr("disabled", true);
                    $('#DiscountRange').show();
                    toastr.error(resposne.message);
                }
            },
            error: function (xhr) {
                console.log('Error:', xhr);
                toastr.error('Something went wrong. Please try again.');
            }
        });
    } else if (service_id && discount_id && discount_type && discount_value == 0) {
        // Reset to service price when discount value is 0
        if (window.customDiscountLimits) {
            $("#net_amount_1").val(parseFloat(window.customDiscountLimits.service_price).toFixed(2));
        }
    }

}

function changeDiscount($this, type) {
   
    $("#edit_discount_value_1").val("");
    var discount_type = $this.val();
    if (discount_type != "") {
        $('#add_discount_type_error').hide();
    } else {
        $('#add_discount_type_error').show();
    }
    if (type && type != 'undefined') {
        if (type == 'edit') {
            if ($this.val()) {
                $('#edit_discount_value_1').prop('disabled', false);
            } else {
                $('#edit_discount_value_1').val('');
                $('#edit_discount_value_1').prop('disabled', true);
            }

            var discount_value = $('#edit_discount_value_1').val();
            var service_id = $('#edit_service_id').val();//Basicailly it is bundle id
            var discount_id = $('#edit_discount_id').val();
            var patient_id = $('#edit_parent_id').val();
        }
    } else {
        if (discount_type) {
            $('#discount_value_1').prop('disabled', false);
        } else {
            $('#discount_value_1').val('');
            $('#discount_value_1').prop('disabled', true);

        }

        var discount_value = $('#discount_value_1').val();
        var service_id = $('#add_service_id').val();//Basicailly it is bundle id
        var discount_id = $('#add_discount_id').val();
        var patient_id = $('#add_patient_id').val();
    }


    hideMessages();
    if (discount_type == 'Percentage') {
        if (discount_value > 100) {
            $('#percentageMessage').show();
            return false;
        } else {
            $('#percentageMessage').hide();
        }
    }
    $('#DiscountRange').hide();
    if (service_id && discount_id) {
        $.ajax({
            type: 'get',
            url: route('admin.packages.getdiscountinfocustom_for_plan'),
            data: {
                'service_id': service_id,
                'discount_id': discount_id,
                'discount_value': discount_value ?? 0,
                'discount_type': discount_type,
                'patient_id': patient_id,
                'location_id': type == 'edit' ? $('#edit_location_id').val() : $('#add_plan_location_id').val()
            },
            success: function (resposne) {
                if (resposne.status) {
                    if (type && type != 'undefined') {
                        if (type == 'edit') {
                            $("#edit_net_amount_1").val((resposne.data.net_amount).toFixed(2));
                            $("#edit_net_amount_1").prop("disabled", true);

                        } else {

                        } $("#net_amount_1").val((resposne.data.net_amount).toFixed(2));
                        $("#net_amount_1").prop("disabled", true);
                    } else {
                        $("#net_amount_1").val((resposne.data.net_amount).toFixed(2));
                        $("#net_amount_1").prop("disabled", true);
                    }
                } else {

                    $('#DiscountRange').show();

                    if (type && type != 'undefined') {
                        if (type == 'edit') {

                            $("#edit_net_amount_1").prop("disabled", true);
                            $("#EditPackage").prop("disabled", true);
                        } else {
                            $("#net_amount_1").val('');
                            $("#net_amount_1").prop("disabled", true);
                            $("#EditPackage").prop("disabled", false);
                        }
                    } else {

                        $("#net_amount_1").prop("disabled", true);
                        $("#EditPackage").prop("disabled", false);
                    }
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

    if (total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal'),
            data: {
                'cash_amount': cash_amount ?? 0,
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

function edit_keyfunction_grandtotal() {

    hideMessages();

    var cash_amount = $('#edit_cash_amount_1').val();
    var total = $('#edit_package_total_1').val();
    var random_id = $('#edit_random_id').val();

    if (total) {
        $.ajax({
            type: 'GET',
            url: route('admin.packages.getgrandtotal_update'),
            data: {
                'cash_amount': cash_amount ?? 0,
                'total': total,
                'random_id': random_id
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
        // $('#edit_inputfieldMessage').show();
    }
}

function checkpaymentMode() {
    if ($('#edit_payment_mode_id').val()) {
        $('#edit_cash_amount_1').prop('disabled', false);
    } else {
        $('#edit_cash_amount_1').val(0);
        $('#edit_cash_amount_1').prop('disabled', true);
        edit_keyfunction_grandtotal();
    }
}

/*End*/

function toggle(id) {
    $("." + id).toggle();
}
function checkAppointmentVal() {
    if ($("#edit_appointment_id").val() != "") {
        $("#edit_appointment_id_error").hide()
    } else {
        $("#edit_appointment_id_error").show()
    }
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
    }).then(function (result) {
        if (result.value) {
            deletePlan(id, type);
        }
    });
}
function deleteConfPlanRow(id, type = '') {

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
            deleteConfPlan(id, type);
        }
    });
}
function deletePlan(id, type) {

    // Use bundle-specific selectors when the edit bundle modal is open
    var isBundleModal = $('#modal_edit_bundle').hasClass('show');
    var package_total = isBundleModal
        ? $('#edit_bundle_package_total_1').val()
        : $('#' + type + 'package_total_1').val();
    var random_id = isBundleModal
        ? $('#edit_bundle_random_id_1').val()
        : $('#edit_random_id_1').val();

    $('#edit_payment_mode_id').val('').change();
    $.ajax({
        type: 'post',
        url: route('admin.packages.deletepackages_service'),
        data: {
            '_token': $('input[name=_token]').val(),
            'id': id,
            'package_total': package_total,
            'random_id': random_id
        },
        success: function (resposne) {

            if (resposne.status) {

                ExistingTotal = resposne.data.old_total;

                var RowIndex = jQuery('.modal.show #appointment_detail, .modal.show #edit_centre_target_location').find('#plan_services, #edit_plan_services').find('tr[class*="HR_' + resposne.data.id + '"]').index();
                var RowNextIndex = jQuery('.modal.show #appointment_detail, .modal.show #edit_centre_target_location').find('#plan_services, #edit_plan_services').find('tr[class*="HR_' + resposne.data.id + '"] + tr:not([id="table_1"])').length;

                removeElementsFromIndex(edit_amountArray, RowIndex, RowNextIndex + 1);


                $('.HR_' + resposne.data.id).remove();

                $('.modal.fade.show tr[class="' + resposne.data.id + '"]').remove();

                var packageTotal = resposne?.data?.total.replace(/,/g, '');
                var totalWithoutCommas = parseInt(packageTotal, 10);

                if (totalWithoutCommas > 1) {
                    $("#" + type + "package_total_1").val(totalWithoutCommas ?? 0);
                } else {
                    $("#" + type + "package_total_1").val(0);
                }
                
                // Check which modal is open and call appropriate grand total function
                if ($('#modal_edit_bundle').hasClass('show')) {
                    // Update bundle modal totals
                    $("#edit_bundle_package_total_1").val(totalWithoutCommas > 1 ? totalWithoutCommas : 0);
                    if (typeof edit_bundle_keyfunction_grandtotal === 'function') {
                        edit_bundle_keyfunction_grandtotal();
                    }
                } else if (type == 'edit_') {
                    edit_keyfunction_grandtotal();
                } else {
                    keyfunction_grandtotal();
                }


                var rows = $('#plan_services tr.HR_' + $('#random_id_1').val()).length;
                if (rows <= 1) {

                    
                    $("#add_plan_location_id").prop("disabled", false).trigger("change.select2");
                    $("#add_patient_id").prop("disabled", false).trigger("change.select2");
                    $("#edit_plan_location_id").prop("disabled", false);
                }

                // Check if edit bundle modal and re-enable service fields if no bundles remain
                if ($('#modal_edit_bundle').hasClass('show')) {
                    var bundleRows = $('#edit_bundle_plan_services tbody tr[class*="HR_"]').length;
                    if (bundleRows === 0) {
                        $('#edit_bundle_service_id').prop('disabled', false);
                        $('#edit_bundle_net_amount_1').prop('disabled', false);
                        $('#edit_bundle_sold_by').prop('disabled', false);
                        $('#EditBundlePackage').prop('disabled', false);
                    }
                }

            } else {

                if (resposne.data.del == 1) {
                    toastr.error('This service has already been consumed and cannot be deleted.');
                } else {
                    toastr.error('Something went wrong while deleting the service.');
                    $('#' + type + 'wrongMessage').show();
                }

            }
        }
    });

}
function deleteConfPlan(id, type) {

    var package_total = $('#' + type + 'package_total_1').val();

    $.ajax({
        type: 'post',
        url: route('admin.packages.deleteconfpackages_service'),
        data: {
            '_token': $('input[name=_token]').val(),
            'id': id,
            'package_total': package_total
        },
        success: function (resposne) {

            if (resposne.status) {

                $('.HR_' + resposne.data.id).remove();
                if (resposne?.data?.total > 1) {
                    $("#" + type + "package_total_1").val(resposne?.data?.total ?? 0);
                } else {
                    $("#" + type + "package_total_1").val(0);
                }
                if (type == 'edit_') {
                    edit_keyfunction_grandtotal();
                } else {
                    keyfunction_grandtotal();
                }


                var rows = $('#plan_services tr.HR_' + $('#random_id_1').val()).length;
                if (rows <= 1) {
                    $("#add_plan_location_id").prop("disabled", false).trigger("change.select2");
                    $("#add_patient_id").prop("disabled", false).trigger("change.select2");
                    $("#edit_plan_location_id").prop("disabled", false);
                }

            } else {

                if (resposne.data.del == 1) {
                    toastr.error('This service has already been consumed and cannot be deleted.');
                } else {
                    toastr.error('Something went wrong while deleting the service.');
                    $('#' + type + 'wrongMessage').show();
                }

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

var total_amountArray = [];
var edit_amountArray = [];
var ExistingTotal = 0;
jQuery(document).ready(function () {
    patientSearchPlan('search_patient_refund');
    $("#AddPackage").click(function () {
   
        $('.create-plan-error').html('');

        if (!$('#add_plan_location_id').val()) {
            $('#add_plan_location_id_error').html('Please select centre');
            toastr.error('Please select centre');
            return false;
        }

        if (!$('#add_patient_id').val()) {
            $('#add_patient_id_error').html('Please select patient');
            toastr.error('Please select patient');
            return false;
        }

        if (!$('#add_appointment_id').val()) {
            $('#add_appointment_id_error').html('Please select appointment');
            toastr.error('Please select appointment');
            return false;
        }

        if (!$('#add_service_id').val()) {
            $('#add_service_id_error').html('Please select service');
            toastr.error('Please select service');
            return false;
        }
        if (!$('#add_sold_by').val()) {
            $('#add_sold_by_errorr').html('Please select sold by');
            toastr.error('Please select sold by');
            return false;
        }
        // For configurable discounts, skip discount type/value validation
        // Check if configurable preview exists OR if discount type div is hidden (both indicate configurable discount)
        var is_configurable_selected = ($('#configurable_preview').length > 0) || 
            ($('#select_discount_type').css('display') === 'none' && $('#add_discount_id').val());

        if ($('#add_discount_id').val() && !is_configurable_selected) {
            if (!$('#add_discount_type').val()) {
                $('#add_discount_type_error').html('Please select discount type');
                return false;
            } else if (!$('#discount_value_1').val()) {
                $('#add_discount_value_error').html('Please add discount value');
                return false;
            }
        }

        // hideMessages();

        $(this).attr("disabled", true);
        var random_id = $('#random_id_1').val();
        var service_id = $('#add_service_id').val();
        var discount_id = $('#add_discount_id').val();
        var net_amount = $('#net_amount_1').val();
        var discount_type = $('#add_discount_type').val();
        var discount_price = $('#discount_value_1').val();
        var discount_slug = $("#slug_1").val();
        var package_total = $('#package_total_1').val();
        var sold_by = $('#add_sold_by').val();
        var sold_by_name = $('#add_sold_by option:selected').text().trim() || 'N/A';
        var is_exclusive = $('#is_exclusive').val();
        var location_id = $('#add_plan_location_id').val();
        var user_id = $('#add_patient_id').val();

        // Hide any previous error messages before validation
        $('#inputfieldMessage').hide();

        var has_valid_fields = service_id && location_id && (net_amount || is_configurable_selected);

        if (has_valid_fields) {

            showSpinner("-add");
            if (!is_configurable_selected && discount_slug == 'custom' && discount_id != '') {
                if (discount_price == '') {
                    hideSpinner("-add");
                    toastr.error("Please add discount value");
                    $(this).attr("disabled", false);
                    return false;
                }
                if (discount_type == 'Percentage') {
                    if (discount_price > 100) {
                        $('#percentageMessage').show();
                        hideSpinner("-add");
                        $(this).attr("disabled", false);
                        return false;
                    }
                }

                if (window.customDiscountLimits) {
                    var limits = window.customDiscountLimits;
                    var dVal = parseFloat(discount_price) || 0;
                    if (discount_type == 'Percentage' && limits.max_percentage !== undefined && dVal > parseFloat(limits.max_percentage)) {
                        $('#percentageMessage').text('Maximum allowed percentage is ' + limits.max_percentage + '%').show();
                        toastr.error('Maximum allowed percentage is ' + limits.max_percentage + '%');
                        hideSpinner("-add");
                        $("#AddPackage").attr("disabled", false);
                        return false;
                    }
                    if (discount_type == 'Fixed' && limits.max_fixed_amount !== undefined && dVal > parseFloat(limits.max_fixed_amount)) {
                        var maxFixed = parseFloat(limits.max_fixed_amount).toFixed(2);
                        $('#percentageMessage').text('Maximum allowed amount is ' + maxFixed).show();
                        toastr.error('Maximum allowed amount is ' + maxFixed);
                        hideSpinner("-add");
                        $("#AddPackage").attr("disabled", false);
                        return false;
                    }
                }
            }

            // --- CLIENT-SIDE PREVIEW (no DB call) ---
            $('#inputfieldMessage').hide();
            $('#wrongMessage').hide();
            $('#AlreadyExitMessage').hide();
            $('.not_found').remove();

            var svcInfo = window.createPlanServiceInfo || {};
            var discInfo = window.createPlanDiscountInfo || {};
            var svcName = svcInfo.service_name || $('#add_service_id option:selected').text();
            var svcPrice = parseFloat(svcInfo.service_price) || parseFloat(net_amount) || 0;
            var taxType = svcInfo.tax_treatment_type_id;
            var taxPct = parseFloat(svcInfo.location_tax_percentage) || 0;
            var discountName = discount_id ? ($('#add_discount_id option:selected').text() || '-') : '-';
            var rowUid = Date.now(); // unique ID for DOM grouping (no DB id needed)

            if (is_configurable_selected && discInfo.preview_rows && discInfo.preview_rows.length) {
                // --- CONFIGURABLE DISCOUNT: multiple preview rows ---
                var configGroupId = 'cfg_' + rowUid;
                var rowCount = discInfo.preview_rows.length;

                jQuery.each(discInfo.preview_rows, function (i, row) {
                    var rowTaxType = row.tax_treatment_type_id || taxType;
                    var rowTaxPct = parseFloat(row.location_tax_percentage) || taxPct;
                    var taxCalc = calculatePlanTax(row.net_amount, rowTaxPct, rowTaxType, is_exclusive);

                    total_amountArray.push(parseFloat(taxCalc.tax_including_price));

                    var deleteBtn = '';
                    if (i === 0) {
                        deleteBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' data-config-group='" + configGroupId + "' onClick='deleteConfigurablePlanRows(this)'>" + trashBtn() + "</button>";
                    }

                    $('#plan_services').append(buildPlanServiceRow({
                        groupClass: random_id + ' configurable-group-' + configGroupId,
                        serviceName: row.service_name,
                        regularPrice: row.service_price,
                        discountName: row.discount_type || discountName,
                        discountValue: row.discount_price,
                        subtotal: taxCalc.tax_exclusive_net_amount,
                        tax: taxCalc.tax_price,
                        total: taxCalc.tax_including_price,
                        serviceId: row.service_id,
                        discountId: discount_id,
                        taxTreatmentTypeId: rowTaxType,
                        soldBy: sold_by,
                        soldByName: sold_by_name,
                        configGroupId: configGroupId,
                        rowType: row.row_type || '',
                        showEditSoldBy: (i === 0)
                    }, deleteBtn));
                });

                var sum = total_amountArray.reduce((a, b) => a + b, 0);
                $("#package_total_1").val(sum.toFixed(2));

            } else {
                // --- SIMPLE DISCOUNT: single row ---
                var rowNetAmount = parseFloat(net_amount) || 0;
                var taxCalc = calculatePlanTax(rowNetAmount, taxPct, taxType, is_exclusive);

                total_amountArray.push(parseFloat(taxCalc.tax_including_price));

                var sum = total_amountArray.reduce((a, b) => a + b, 0);
                $("#package_total_1").val(sum.toFixed(2));

                var deleteBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRowTem(this)'>" + trashBtn() + "</button>";

                $('#plan_services').append(buildPlanServiceRow({
                    groupClass: random_id + ' plan-row-' + rowUid,
                    serviceName: svcName,
                    regularPrice: svcPrice,
                    discountName: discountName,
                    discountValue: discount_price || 0,
                    subtotal: taxCalc.tax_exclusive_net_amount,
                    tax: taxCalc.tax_price,
                    total: taxCalc.tax_including_price,
                    serviceId: service_id,
                    discountId: discount_id,
                    discountType: discount_type || '',
                    taxTreatmentTypeId: taxType,
                    soldBy: sold_by,
                    soldByName: sold_by_name,
                    configGroupId: ''
                }, deleteBtn));
            }

            keyfunction_grandtotal();
            var rowCount = $('#plan_services tr').length;
            if (rowCount > 0) {
                $("#add_plan_location_id").prop("disabled", true).trigger("change.select2");
                $("#add_patient_id").prop("disabled", true).trigger("change.select2");
            }

            // Reset form fields after successful addition
            $('#configurable_preview').remove();
            $('#add_service_id').val(null).trigger('change');
            $('#add_discount_id').val(null).trigger('change');
            $('#add_discount_type').val(null).trigger('change');
            $('#discount_value_1').val('');
            $('#net_amount_1').val('');
            $('#add_sold_by').val(null).trigger('change');
            window.createPlanDiscountInfo = null;
            window.createPlanServiceInfo = null;

            $("#AddPackage").attr("disabled", false);
            hideSpinner("-add");
        } else {
            $('#inputfieldMessage').show();
            toastr.error('Please fill all required fields (service, location, price)');
            console.log('Validation failed - service_id:', service_id, 'location_id:', location_id, 'net_amount:', net_amount, 'is_configurable_selected:', is_configurable_selected);
            $(this).attr("disabled", false);
            hideSpinner("-add");
        }
    });
    /*End*/




    /*function for final package information save*/
    $("#AddPackageFinal").click(function () {

        $('.create-plan-error').html('');
        if ($('#payment_mode_id_1').val()) {
            if (!$('#cash_amount_1').val()) {
                $('#cash_amount_error').html('Please enter cash mount');
                return false;
            }
        }

        hideMessages();

        var random_id = $('#random_id_1').val();
        var patient_id = $('#add_patient_id').val();
        var total = $('#package_total_1').val();
        var payment_mode_id = $('#payment_mode_id_1').val();
        var cash_amount = $('#cash_amount_1').val();
        var grand_total = $('#grand_total_1').val();
        var location_id = $('#add_plan_location_id').val();
        var is_exclusive = $('#is_exclusive').val();
        var appointment_id = $('#add_appointment_id').val();
        var base_service_id = $('#add_plan_location_id').val();
        var complimentary = $("#is_complimentary").val($('#net_amount_1').val());
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
            'plan_type': 'plan',
            // 'base_service_id':base_service_id,
            package_bundles: []
        };


        $('#plan_services').find('tr:not(.inner_records_hr)').each(function () {
            formData['package_bundles'].push({
                serviceName: $(this).find('td:first-child a').text(),
                RegularPrice: $(this).find('td:nth-child(2)').text(),
                DiscountName: $(this).find('td:nth-child(3)').text(),
                DiscountValue: $(this).find('td:nth-child(4)').text(),
                Amount: $(this).find('td:nth-child(5)').text(),
                Tax: $(this).find('td:nth-child(6)').text(),
                Total: $(this).find('td:nth-child(7)').text(),
                bundleId: $(this).find('td:nth-child(11)').find("input[name='bundle_id']").val(),
                DiscountId: $(this).find('td:nth-child(11)').find("input[name='discount_id']").val(),
                Type: $(this).find('td:nth-child(11)').find("input[name='discount_type']").val() || '',
                config_group_id: $(this).find('td:nth-child(11)').find("input[name='config_group_id']").val() || '',
                row_type: $(this).find('td:nth-child(11)').find("input[name='row_type']").val() || '',
                source_type: $(this).find('td:nth-child(11)').find("input[name='source_type']").val() || '',
                sold_by: $(this).find('td:nth-child(12)').find("input[name='sold_by[]']").val()
            });
        });
       
        var status = 0;
        if (cash_amount > 0) {
            var status = 1;
        }

        if (payment_mode_id == '' && cash_amount > 0) {
            toastr.error("Please select the payment mode");
            return false;
        }

        if (random_id && (patient_id > 0) && total && status == 1 ? payment_mode_id : true && cash_amount >= 0 && grand_total && location_id) {

            showSpinner("-save");

            $.ajax({
                type: 'post',
                url: route('admin.packages.savepackages'),
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (resposne) {

                    if (resposne.status) {

                        $('#successMessage').show();
                        toastr.success(" Plan successfully created")
                        closePopup('modal_appointment_plan_section');
                        reInitTable();
                    } else {

                        $('#wrongMessage').show();
                        if (resposne.message) {
                            toastr.error(resposne.message);
                        }
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

   $('#cash_amount_1').on('input', function () {
    let val = $(this).val();

    // Reset if value starts with 0 but isn't "0" or a decimal like "0.5"
    if (val.length > 1 && val.startsWith("0") && !val.startsWith("0.")) {
        $(this).val('');
        return;
    }

    // Reset if value is negative
    if (parseFloat(val) < 0) {
        $(this).val('');
        return;
    }

    // Trigger your function if valid
    keyfunction_grandtotal();
});

   $("#edit_cash_amount_1").on('input', function () {
    let val = $(this).val();

    // Reset if first character is 0 and length > 1 and doesn't start with "0."
    if (val.length > 1 && val.startsWith("0") && !val.startsWith("0.")) {
        $(this).val('');
        return;
    }

    // Reset if value is negative
    if (parseFloat(val) < 0) {
        $(this).val('');
        return;
    }

    // Call your function if value is valid
    edit_keyfunction_grandtotal();
});


    /*save data for both predefined discounts and keyup trigger*/
    $("#EditPackage").click(function () {

        // Safety check: block adding if config group has out-of-order consumption
        if (window.editPlanLocked) {
            toastr.error('Cannot add new services. A configurable discount group has out-of-order consumption. Please consume the BUY services first or create a new plan.');
            return false;
        }

        $('.error-msg').html('');
        if (!$('#edit_appointment_id').val()) {
            $('#edit_appointment_id_error').html('Please select appointment');
            return false;
        }
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
        var sold_by = $('#edit_sold_by').val();
        var sold_by_name = $('#edit_sold_by option:selected').text().trim() || 'N/A';
        var user_id = $('#edit_parent_id').val();
        if (!service_id) {
            $('#service_id').html('Please select service');
            $(this).attr("disabled", false);
            hideSpinner("-edit-add");
            return false;
        }
        if (!$('#edit_sold_by').val()) {
            $('#edit_sold_by_errorr').html('Please select sold by');
            $(this).attr("disabled", false);
                hideSpinner("-edit-add");
            return false;
        }
        // Check if configurable discount is selected
        var edit_is_configurable = ($('#configurable_preview').length > 0) || 
            ($('#select_edit_discount_type').css('display') === 'none' && discount_id);
        
        if (discount_id && !edit_is_configurable) {
            if (!discount_type) {
                $('#discount_type_error').html('Please select discount type');
                $(this).attr("disabled", false);
                hideSpinner("-edit-add");
                return false;
            } else if (!discount_price) {
                $('#discount_payment_error').html('Please enter discount value');
                $(this).attr("disabled", false);
                hideSpinner("-edit-add");
                return false;
            }
        }

        if (service_id && (net_amount || edit_is_configurable) && location_id) {
            showSpinner("-edit-add");
            if (!edit_is_configurable && discount_slug == 'custom' && discount_id != '') {
                if (discount_price == '') {
                    hideSpinner("-edit-add");
                    $('#discount_payment').html('Please enter discount value');
                    // $('#edit_inputfieldMessage').show();
                    return false;
                }
                
                if (discount_type == 'Percentage') {
                    if (discount_price > 100) {
                        $('#edit_percentageMessage').show();
                        hideSpinner("-edit-add");
                        return false;
                    }
                }

                if (window.editCustomDiscountLimits) {
                    var editLimits = window.editCustomDiscountLimits;
                    var eVal = parseFloat(discount_price) || 0;
                    if (discount_type == 'Percentage' && editLimits.max_percentage !== undefined && eVal > parseFloat(editLimits.max_percentage)) {
                        $('#edit_percentageMessage').text('Maximum allowed percentage is ' + editLimits.max_percentage + '%').show();
                        toastr.error('Maximum allowed percentage is ' + editLimits.max_percentage + '%');
                        hideSpinner("-edit-add");
                        $("#EditPackage").attr("disabled", false);
                        return false;
                    }
                    if (discount_type == 'Fixed' && editLimits.max_fixed_amount !== undefined && eVal > parseFloat(editLimits.max_fixed_amount)) {
                        var editMaxFixed = parseFloat(editLimits.max_fixed_amount).toFixed(2);
                        $('#edit_percentageMessage').text('Maximum allowed amount is ' + editMaxFixed).show();
                        toastr.error('Maximum allowed amount is ' + editMaxFixed);
                        hideSpinner("-edit-add");
                        $("#EditPackage").attr("disabled", false);
                        return false;
                    }
                }
            }
            // --- CLIENT-SIDE PREVIEW (no DB call) ---
            showSpinner("-edit-add");

            var svcInfo = window.editPlanServiceInfo || {};
            var discInfo = window.editPlanDiscountInfo || {};
            var svcName = svcInfo.service_name || $('#edit_service_id option:selected').text();
            var svcPrice = parseFloat(svcInfo.service_price) || parseFloat(net_amount) || 0;
            var taxType = svcInfo.tax_treatment_type_id;
            var taxPct = parseFloat(svcInfo.location_tax_percentage) || 0;
            var discountName = discount_id ? ($('#edit_discount_id option:selected').text() || '-') : '-';
            var rowUid = Date.now();

            if ($('#edit_plan_services').find('tr[class="text-center"]').length) {
                $('#edit_plan_services').empty();
            }

            if (edit_is_configurable && discInfo.preview_rows && discInfo.preview_rows.length) {
                // --- CONFIGURABLE DISCOUNT: multiple preview rows ---
                var configGroupId = 'cfg_' + rowUid;

                jQuery.each(discInfo.preview_rows, function (i, row) {
                    var rowTaxType = row.tax_treatment_type_id || taxType;
                    var rowTaxPct = parseFloat(row.location_tax_percentage) || taxPct;
                    var taxCalc = calculatePlanTax(row.net_amount, rowTaxPct, rowTaxType, is_exclusive);

                    edit_amountArray.push(parseFloat(taxCalc.tax_including_price));

                    var deleteBtn = '';
                    if (i === 0) {
                        deleteBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' data-config-group='" + configGroupId + "' onClick='deleteConfigurablePlanRowsEdit(this)'>" + trashBtn() + "</button>";
                    }

                    $('#edit_plan_services').append(buildPlanServiceRow({
                        groupClass: random_id + ' configurable-group-' + configGroupId,
                        serviceName: row.service_name,
                        regularPrice: row.service_price,
                        discountName: row.discount_type || discountName,
                        discountValue: row.discount_price,
                        subtotal: taxCalc.tax_exclusive_net_amount,
                        tax: taxCalc.tax_price,
                        total: taxCalc.tax_including_price,
                        serviceId: row.service_id,
                        discountId: discount_id,
                        taxTreatmentTypeId: rowTaxType,
                        soldBy: sold_by,
                        soldByName: sold_by_name,
                        configGroupId: configGroupId,
                        rowType: row.row_type || '',
                        showEditSoldBy: (i === 0)
                    }, deleteBtn));
                });

            } else {
                // --- SIMPLE DISCOUNT: single row ---
                var rowNetAmount = parseFloat(net_amount) || 0;
                var taxCalc = calculatePlanTax(rowNetAmount, taxPct, taxType, is_exclusive);

                edit_amountArray.push(parseFloat(taxCalc.tax_including_price));

                var deleteBtn = "<button type='button' class='btn btn-icon btn-sm btn-light btn-hover-danger btn-sm' onClick='deletePlanRowTem(this)'>" + trashBtn() + "</button>";

                $('#edit_plan_services').append(buildPlanServiceRow({
                    groupClass: random_id + ' plan-row-' + rowUid,
                    serviceName: svcName,
                    regularPrice: svcPrice,
                    discountName: discountName,
                    discountValue: discount_price || 0,
                    subtotal: taxCalc.tax_exclusive_net_amount,
                    tax: taxCalc.tax_price,
                    total: taxCalc.tax_including_price,
                    serviceId: service_id,
                    discountId: discount_id,
                    discountType: discount_type || '',
                    taxTreatmentTypeId: taxType,
                    soldBy: sold_by,
                    soldByName: sold_by_name,
                    configGroupId: ''
                }, deleteBtn));
            }

            // Recalculate grand total
            var editsum = 0;
            if (edit_amountArray.length) {
                editsum = edit_amountArray.reduce((partialSum, a) => partialSum + a, 0);
            }
            $("#edit_package_total_1").val((editsum + ExistingTotal).toFixed(2));
            edit_keyfunction_grandtotal();

            // Reset form fields
            $('#configurable_preview').remove();
            $('#edit_service_id').val(null).trigger('change');
            $('#edit_net_amount_1').val('');
            $('#edit_sold_by').val(null).trigger('change');
            window.editPlanDiscountInfo = null;
            window.editPlanServiceInfo = null;

            $("#EditPackage").removeAttr('disabled');
            hideSpinner("-edit-add");
        } else {
            $('#edit_inputfieldMessage').show();
            $(this).attr("disabled", false);
            hideSpinner("-edit-add");
        }
    });
    /*End*/

    /*function for final package information save*/
    $("#EditPackageFinal").click(function () {
        $('.error-msg').html('');
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
            'appointment_id': appointment_id,
            'plan_type': 'plan',
            package_bundles: []
        };

        // Only collect NEWLY ADDED rows (not existing DB rows)
        // Existing DB rows have data-existing="1", new rows added via Add button do not
        $('#edit_plan_services').find('tr[id="table_1"]:not(.inner_records_hr)').each(function () {
            // Skip existing DB rows — they are already in the database
            if ($(this).data('existing')) return true;
            formData['package_bundles'].push({
                serviceName: $(this).find('td:first-child a').text(),
                RegularPrice: $(this).find('td:nth-child(2)').text(),
                DiscountName: $(this).find('td:nth-child(3)').text(),
                DiscountValue: $(this).find('td:nth-child(4)').text(),
                Amount: $(this).find('td:nth-child(5)').text(),
                Tax: $(this).find('td:nth-child(6)').text(),
                Total: $(this).find('td:nth-child(7)').text(),
                bundleId: $(this).find('td:nth-child(11)').find("input[name='bundle_id']").val(),
                DiscountId: $(this).find('td:nth-child(11)').find("input[name='discount_id']").val(),
                Type: $(this).find('td:nth-child(11)').find("input[name='discount_type']").val() || '',
                config_group_id: $(this).find('td:nth-child(11)').find("input[name='config_group_id']").val() || '',
                row_type: $(this).find('td:nth-child(11)').find("input[name='row_type']").val() || '',
                source_type: $(this).find('td:nth-child(11)').find("input[name='source_type']").val() || '',
                sold_by: $(this).find('td:nth-child(12)').find("input[name='sold_by[]']").val()
            });
        });

        var status = 0;
        if (cash_amount > 0) {
            var status = 1;
        }

        if (payment_mode_id == '' && cash_amount > 0) {
            // toastr.error("Please select the payment mode");
            $('#payment_mode_id').html('Please select payment mode');
            return false;
        }

        if (payment_mode_id && cash_amount == '') {
            $('#cash_amount_error').html('Please enter cash amount');
            return false;
        }

        

        if (random_id && (patient_id > 0) && total && status == 1 ? payment_mode_id : true && cash_amount >= 0 && grand_total && location_id) {
            showSpinner("-edit-save");
            $.ajax({
                type: 'get',
                url: route('admin.packages.updatepackages'),
                data: formData,
                success: function (resposne) {
                    if (resposne.status) {
                        toastr.success(resposne.message || 'Plan updated successfully');
                        $("#modal_edit_plan").modal("hide");
                        current_url = window.location.href;
                        if (!window.location.href.includes("view-package")) {
                            reInitTable();
                        }
                    } else {
                        if (resposne.data?.setteled == 1) {
                            $('#casesetteledamount').show();
                        } else {
                            $('#edit_wrongMessage').show();
                            toastr.error(resposne.message)
                        }
                    }

                    hideSpinner("-edit-save");
                },
                error: function (response) {
                    errors = response?.responseJSON?.errors;
                    if (errors) {
                        errors.appointment_id ? $('#appointment_id').html(errors.appointment_id) : $('#appointment_id').html('');
                    }
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

    // Prevent opening/clearing location once treatments have been added to preview
    $('#add_plan_location_id').on('select2:opening', function (e) {
        if ($('#plan_services tr').length > 0) {
            e.preventDefault();
        }
    });

    // Prevent opening/clearing patient search once treatments have been added to preview
    $('#add_patient_id').on('select2:opening', function (e) {
        if ($('#plan_services tr').length > 0) {
            alert('You cannot change the patient once you have added services to the plan. Please remove all services before changing the patient.');
            e.preventDefault();
        }
    });
    $('#add_patient_id').on('select2:unselecting', function (e) {
        if ($('#plan_services tr').length > 0) {
            alert('You cannot clear the patient once you have added services to the plan. Please remove all services before clearing the patient.');
            e.preventDefault();
        }
    });

    // Handle patient selection from Select2 dropdown
    $('#add_patient_id').on('select2:select', function (e) {
        var patientId = $(this).val();
        if (patientId) {
            // Clear appointment dropdown
            $("#add_appointment_id").empty();
            $('#add_appointment_id').append('<option value="">Select Appointment</option>');
            $('#add_appointment_id').val(null).trigger('change');

            // Reset service, discount, price and sold by fields
            $('#add_service_id').val(null).trigger('change');
            $("#add_discount_id").html('<option value="">Select Discount/Voucher Type</option>');
            $("#add_discount_type").val('').change();
            $("#add_discount_type").attr('disabled', true);
            $('#discount_value_1').val('');
            $("#discount_value_1").attr('disabled', true);
            $('#net_amount_1').val('');
            $('#add_sold_by').val(null).trigger('change');
            $('#configurable_preview').remove();
            
            // Load appointments for selected patient
            getAppointments(patientId);
        }
    });

    // Clear appointments when patient is cleared
    $('#add_patient_id').on('select2:clear', function (e) {
        $("#add_appointment_id").empty();
        $('#add_appointment_id').append('<option value="">Select Appointment</option>');
        $('#add_appointment_id').val(null).trigger('change');
        $('#patient_membership').val('No data');

        // Reset service, discount, price and sold by fields
        $('#add_service_id').val(null).trigger('change');
        $("#add_discount_id").html('<option value="">Select Discount/Voucher Type</option>');
        $("#add_discount_type").val('').change();
        $("#add_discount_type").attr('disabled', true);
        $('#discount_value_1').val('');
        $("#discount_value_1").attr('disabled', true);
        $('#net_amount_1').val('');
        $('#add_sold_by').val(null).trigger('change');
        $('#configurable_preview').remove();
    });

    $('.index_search_patient').keyup(function () {
        $('.index_search_patient').val() ? $('.index_search_patient_croxcli').show() : $('.index_search_patient_croxcli').hide();
    })

    $('.index_search_patient_croxcli').on('click', function () {
        $('.index_search_patient').val('');
        $('.suggesstion-box').hide();
        $('.index_search_patient_croxcli').hide();
    })

    $('#payment_mode_id_1').on('change', function () {
        if ($('#payment_mode_id_1').val()) {
            $('#cash_amount_1').prop('disabled', false);
        } else {
            $('#cash_amount_1').val('');
            $('#cash_amount_1').prop('disabled', true);
            keyfunction_grandtotal();
        }
    });

});

function removeElementsFromIndex(arr, index, numElements) {
    arr.splice(index, numElements);
}

// Delete all rows in a configurable discount group (Create Plan - DOM only, no DB)
function deleteConfigurablePlanRows(btn) {
    var configGroup = $(btn).attr('data-config-group');
    
    swal.fire({
        title: 'Are you sure you want to delete?',
        text: 'This will delete all services in this configurable discount group.',
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
            // Just remove rows from DOM - no DB records to delete
            var rowsToRemove = $('.configurable-group-' + configGroup).length;
            $('.configurable-group-' + configGroup).remove();
            
            // Remove corresponding entries from total_amountArray
            for (var i = 0; i < rowsToRemove; i++) {
                if (total_amountArray.length > 0) {
                    total_amountArray.pop();
                }
            }
            
            // Recalculate totals
            var remainingRows = $('#plan_services tr[id="table_1"]').length;
            var sum = 0;
            if (remainingRows > 0 && total_amountArray.length > 0) {
                sum = total_amountArray.reduce((partialSum, a) => partialSum + a, 0);
            } else {
                total_amountArray = [];
                sum = 0;
            }
            
            jQuery('.modal.show #package_total_1').val(sum.toFixed(2));
            jQuery('.modal.show #grand_total_1').val(sum.toFixed(2));
            jQuery('.modal.show #payment_mode_id_1').val('').change();
            jQuery('.modal.show #cash_amount_1').val('');
            
            if (remainingRows === 0) {
                $("#add_plan_location_id").prop("disabled", false).trigger("change.select2");
                $("#add_patient_id").prop("disabled", false).trigger("change.select2");
            }
        }
    });
}

// Delete all rows in a configurable discount group (Edit Plan context)
// Handles both: newly added rows (data-config-group, DOM only) and existing DB rows (data-config-rows, AJAX delete)
function deleteConfigurablePlanRowsEdit(btn) {
    var configGroup = $(btn).attr('data-config-group');
    var configRowIds = $(btn).attr('data-config-rows') ? JSON.parse($(btn).attr('data-config-rows')) : null;
    
    swal.fire({
        title: 'Are you sure you want to delete?',
        text: 'This will delete all services in this configurable discount group.',
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
            // If existing DB rows, delete via AJAX
            if (configRowIds && configRowIds.length) {
                // Send first request to check if deletion is allowed (config group sibling check)
                var firstId = configRowIds[0];
                $.ajax({
                    type: 'post',
                    url: route('admin.plans.deletepackages_service'),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        'id': firstId,
                        'random_id': $('#edit_random_id_1').val(),
                        'package_total': $('#edit_package_total_1').val(),
                    },
                    success: function (response) {
                        if (!response.status) {
                            if (response.data && response.data.del == 1) {
                                toastr.error('Cannot delete this group. A service in this configurable discount group has already been consumed.');
                            } else {
                                toastr.error('Something went wrong while deleting the service.');
                            }
                            return;
                        }
                        // First row deleted successfully, delete remaining rows
                        for (var i = 1; i < configRowIds.length; i++) {
                            $.ajax({
                                type: 'post',
                                url: route('admin.plans.deletepackages_service'),
                                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                                data: {
                                    'id': configRowIds[i],
                                    'random_id': $('#edit_random_id_1').val(),
                                    'package_total': $('#edit_package_total_1').val(),
                                },
                                success: function () {},
                                error: function () {}
                            });
                        }
                        // Only remove DOM rows after backend confirms deletion is allowed
                        var rowsToRemove = $('.configurable-group-' + firstId).length;
                        $('.configurable-group-' + firstId).remove();
                        deleteConfigGroupUpdateTotals(rowsToRemove);
                    },
                    error: function () {
                        toastr.error('Something went wrong while deleting the service.');
                    }
                });
                return; // Exit early — DOM update happens inside the AJAX callback
            } else if (configGroup) {
                // Newly added rows - just remove from DOM
                var rowsToRemove = $('.configurable-group-' + configGroup).length;
                $('.configurable-group-' + configGroup).remove();
                deleteConfigGroupUpdateTotals(rowsToRemove);
            }
        }
    });
}

// Helper: recalculate totals after config group rows are removed from DOM
function deleteConfigGroupUpdateTotals(rowsToRemove) {
    // Remove corresponding entries from edit_amountArray
    for (var i = 0; i < rowsToRemove; i++) {
        if (edit_amountArray.length > 0) {
            edit_amountArray.pop();
        }
    }
    
    // Recalculate totals from remaining rows
    var sum = 0;
    $('#edit_plan_services tr[id="table_1"]').each(function() {
        var rowTotal = parseFloat($(this).find('td:eq(6)').text().replace(/,/g, '')) || 0;
        sum += rowTotal;
    });
    
    if (sum === 0) {
        edit_amountArray = [];
    }
    
    jQuery('.modal.show #edit_package_total_1').val(sum.toFixed(2));
    jQuery('.modal.show #edit_grand_total_1').val(sum.toFixed(2));
    jQuery('.modal.show #edit_payment_mode_id').val('').change();
    jQuery('.modal.show #edit_cash_amount_1').val('');
}

// Delete a single plan service row (Create Plan - DOM only, no DB)
function deletePlanRowTem(btn) {
    var $row = $(btn).closest('tr');
    var rowIndex = $row.index();
    
    // Remove from total_amountArray
    if (rowIndex >= 0 && rowIndex < total_amountArray.length) {
        total_amountArray.splice(rowIndex, 1);
    }
    
    // Remove DOM row
    $row.remove();
    
    // Recalculate totals
    var sum = 0;
    if (total_amountArray.length) {
        sum = total_amountArray.reduce((partialSum, a) => partialSum + a, 0);
    }
    jQuery('.modal.show #package_total_1').val(sum.toFixed(2));
    jQuery('.modal.show #grand_total_1').val(sum.toFixed(2));
    jQuery('.modal.show #payment_mode_id_1').val('').change();
    jQuery('#cash_amount_1').val('');
    
    // Re-enable location and patient if no services remain
    var remainingRows = $('#plan_services tr[id="table_1"]').length;
    if (remainingRows === 0) {
        $("#add_plan_location_id").prop("disabled", false).trigger("change.select2");
        $("#add_patient_id").prop("disabled", false).trigger("change.select2");
    }
}

// Delete a single plan service row (Edit Plan - needs AJAX for DB records)
function deletePlanRowTemEdit(id, type = "") {
    $.ajax({
        type: 'post',
        url: route('admin.plans.deletepackages_service'),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: {
            'id': id,
            'random_id': $('#edit_random_id_1').val(),
            'package_total': $('#edit_package_total_1').val(),
        },
        success: function (response) {},
        error: function (response) {}
    });
    var RowIndex = jQuery('.modal.show #edit_centre_target_location').find('#edit_plan_services').find('tr[id="table_1"][class*="HR_' + id + '"]').index();
    var RowNextIndex = jQuery('.modal.show #edit_centre_target_location').find('#edit_plan_services').find('tr[id="table_1"][class*="HR_' + id + '"] + tr:not([id="table_1"])').length;

    removeElementsFromIndex(edit_amountArray, RowIndex, RowNextIndex + 1);
    var Editsum = 0;
    if (edit_amountArray.length) {
        Editsum = edit_amountArray.reduce((partialSum, a) => partialSum + a, 0);
    }

    var currentRowPrice = jQuery('.modal.show #edit_centre_target_location').find('#edit_plan_services').find('tr[id="table_1"][class*="HR_' + id + '"]').find('td:nth-child(7)').text();
    currentRowPrice = parseFloat((currentRowPrice || '0').replace(/,/g, '')) || 0;

    jQuery('.modal.show #edit_package_total_1').val((Editsum + ExistingTotal).toFixed(2));
    jQuery('.modal.show #edit_centre_target_location').find('#edit_plan_services').find('tr[class*="HR_' + id + '"]').remove();

    if (jQuery('.modal.show #edit_grand_total_1').val()) {
        var CashReceivedRemain = parseFloat(jQuery('.modal.show #edit_grand_total_1').val().replace(/,/g, '')) || 0;
        jQuery('.modal.show #edit_grand_total_1').val(Math.round(CashReceivedRemain - currentRowPrice));
    }

    jQuery('#edit_cash_amount_1').val('');
    if (!jQuery('.modal.show #edit_centre_target_location #edit_plan_services tr').length) {
        jQuery('.modal.show #edit_payment_mode_id').val('').change();
        jQuery('.modal.show #edit_grand_total_1').val('');
        jQuery('.modal.show #edit_cash_amount_1').val('');
    }
}

// Reset and close create plan modal (no DB cleanup needed since Add is client-side only)
function resetVoucherAdd(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Clear client-side state
    window.createPlanServiceInfo = null;
    window.createPlanDiscountInfo = null;
    total_amountArray = [];
    
    $('#modal_add_plan').modal('hide');
    return false;
}
// Reset and close edit plan modal (no DB cleanup needed since Add is client-side only)
function resetVoucherEdit(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Clear client-side state
    window.editPlanServiceInfo = null;
    window.editPlanDiscountInfo = null;
    window.editPlanLocked = false;
    edit_amountArray = [];
    
    $('#modal_edit_plan').modal('hide');
    return false;
}

function getPackageBundlesArray() {
    const packageBundles = [];
    const inputs = document.querySelectorAll('input[name="package_bundles[]"]');
    
    inputs.forEach(function(input, index) {
        if (input.value.trim() !== '') {
            packageBundles.push(input.value);
        }
    });
    return packageBundles;
}

function sendPackageBundlesToLaravelJQuery(packageBundles) {
    try {
        var random_id = $('#edit_random_id_1').val();
        
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        
        $.ajax({
            url: route('admin.packages.resetvoucherpacakgebundles'),
            method: 'POST',
            data: {
                package_bundles: packageBundles,
                random_id: random_id
            },
            dataType: 'json',
            beforeSend: function() {
                
                // Optional: Show loading indicator
                // showLoadingIndicator();
            },
            success: function(response) {
                toastr.success('Plan updated successfully');
                $('#modal_edit_plan').modal('hide');
                if (typeof datatable !== 'undefined') { datatable.reload(); } else { location.reload(); }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                
                // Show error message
                alert('Error processing request: ' + error);
                
                // Hide modal even on error
                $('#modal_edit_plan').modal('hide');
            },
            complete: function() {
                
                // Optional: Hide loading indicator
                // hideLoadingIndicator();
            }
        });
        
    } catch (error) {
        console.error("Error in AJAX function:", error);
        alert("AJAX Setup Error: " + error.message);
        $('#modal_edit_plan').modal('hide');
    }
}
function sendPackageBundlesToLaravel(packageBundles) {
    try {
        var random_id = $('#random_id_1').val();
        
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        
        $.ajax({
            url: route('admin.packages.resetvoucherpacakgebundles'),
            method: 'POST',
            data: {
                package_bundles: packageBundles,
                random_id: random_id
            },
            dataType: 'json',
            beforeSend: function() {
                
                // Optional: Show loading indicator
                // showLoadingIndicator();
            },
            success: function(response) {
                toastr.success('Plan created successfully');
                $('#modal_add_plan').modal('hide');
                if (typeof datatable !== 'undefined') { datatable.reload(); } else { location.reload(); }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response Text:', xhr.responseText);
                
                // Show error message
                alert('Error processing request: ' + error);
                
                // Hide modal even on error
               $('#modal_add_plan').modal('hide');
            },
            complete: function() {
             
                // Optional: Hide loading indicator
                // hideLoadingIndicator();
            }
        });
        
    } catch (error) {
        console.error("Error in AJAX function:", error);
        alert("AJAX Setup Error: " + error.message);
        $('#modal_add_plan').modal('hide');
    }
}
// Function to hide modal - works with different modal types
function hideModal() {
    try {
        // Method 1: Bootstrap 5 Modal
        const modal = document.querySelector('.modal.show');
        if (modal) {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
              
                return;
            }
        }
        
        // Method 2: jQuery Bootstrap Modal
        const $modal = $('.modal:visible');
        if ($modal.length > 0) {
            $modal.modal('hide');
            
            return;
        }
        
        // Method 3: Custom modal with specific ID (adjust as needed)
        const customModal = document.getElementById('kt_modal_edit_voucher') || 
                           document.getElementById('editVoucherModal') ||
                           document.querySelector('[data-kt-users-modal-action="cancel"]').closest('.modal');
        
        if (customModal) {
            // Try Bootstrap instance first
            const modalInstance = bootstrap.Modal.getInstance(customModal);
            if (modalInstance) {
                modalInstance.hide();
            } else {
                // Fallback to hiding manually
                customModal.style.display = 'none';
                customModal.classList.remove('show');
                // Remove backdrop
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
                document.body.classList.remove('modal-open');
            }
           
            return;
        }
        
        // Method 4: If using popup-close class
        const popupClose = document.querySelector('.popup-close');
        if (popupClose) {
            const popup = popupClose.closest('.popup, .modal, .overlay');
            if (popup) {
                popup.style.display = 'none';
                popup.classList.remove('show', 'open');
           
                return;
            }
        }
        
    

    } catch (error) {
        console.error("Error hiding modal:", error);
    }
}

/*
 * Function to edit sold_by for newly-added (non-persisted) plan rows.
 * Updates DOM directly without any AJAX call.
 */
function editNewRowSoldBy(btn) {
    var $row = $(btn).closest('tr');
    var currentSoldBy = $row.find('.package_bundles_sold_by').val();

    // Detect configurable group from row's config_group_id hidden input
    var configGroupId = $row.find('.config_group_id').val() || '';

    // Determine which sold_by dropdown to clone options from
    var $sourceSoldBy = $('#edit_sold_by').length && $('#edit_sold_by option').length > 1
        ? $('#edit_sold_by')
        : $('#add_sold_by');

    // Populate the modal dropdown from the form's sold_by options
    var userOptions = '<option value="">Select</option>';
    $sourceSoldBy.find('option').each(function () {
        if ($(this).val()) {
            var selected = ($(this).val() == currentSoldBy) ? 'selected' : '';
            userOptions += '<option value="' + $(this).val() + '" ' + selected + '>' + $(this).text().trim() + '</option>';
        }
    });
    $('#sold_by_dropdown').html(userOptions);

    // Mark this as a new-row edit so the update handler knows
    $('#package_service_id').data('new-row-edit', true);
    $('#package_service_id').data('target-row', $row);
    $('#package_service_id').data('config-group-id', configGroupId);

    // Show modal
    $('#modal_edit_sold_by').modal({ backdrop: 'static', keyboard: true });
    $('#modal_edit_sold_by').on('shown.bs.modal', function () {
        $(this).css('z-index', parseInt($('.modal-backdrop').css('z-index')) + 10);
    });
}

/*
 * Function to edit sold_by for all services in a package bundle
 */
function editBundleSoldBy(packageBundleId, locationId, configBundleIds) {
    // Build request data - send all bundle IDs for configurable groups
    var requestData = {
        package_bundle_id: packageBundleId,
        location_id: locationId
    };
    if (configBundleIds && Array.isArray(configBundleIds) && configBundleIds.length > 1) {
        requestData.config_bundle_ids = configBundleIds;
    }

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.packages.getsoldbydata'),
        type: 'GET',
        data: requestData,
        success: function(response) {
            if (response.status) {
                // Store all package service IDs
                let serviceIds = response.data.package_services.map(service => service.id);
                $('#package_service_id').val(serviceIds[0] || '');
                $('#package_service_id').data('service-ids', serviceIds);
                // Store bundle IDs for DOM update after save
                $('#package_service_id').data('edit-bundle-id', packageBundleId);
                $('#package_service_id').data('edit-config-bundle-ids', configBundleIds || []);

                // Populate dropdown with users
                let userOptions = '<option value="">Select</option>';
                Object.entries(response.data.users).forEach(function([id, name]) {
                    let selected = (parseInt(id) === parseInt(response.data.current_sold_by)) ? 'selected' : '';
                    userOptions += '<option value="' + id + '" ' + selected + '>' + name + '</option>';
                });
                $('#sold_by_dropdown').html(userOptions);

                // Show modal with proper z-index handling
                $('#modal_edit_sold_by').modal({
                    backdrop: 'static',
                    keyboard: true
                });

                // Fix z-index for nested modal
                $('#modal_edit_sold_by').on('shown.bs.modal', function () {
                    $(this).css('z-index', parseInt($('.modal-backdrop').css('z-index')) + 10);
                });

            } else {
                toastr.error(response.message || 'Failed to load sold by data');
            }
        },
        error: function(xhr) {
            errorMessage(xhr);
        }
    });
}

/*
 * Close edit sold by modal
 */
function closeSoldByModal() {
    $('#modal_edit_sold_by').modal('hide');
    $('#sold_by_error').html('');
    $('#package_service_id').val('');
    $('#package_service_id').removeData('service-ids');
    $('#package_service_id').removeData('new-row-edit');
    $('#package_service_id').removeData('target-row');

    // Ensure parent modal stays visible after closing nested modal
    setTimeout(function() {
        if ($('#modal_edit_plan').hasClass('show') || $('#packages_add').hasClass('show')) {
            $('body').addClass('modal-open');
        }
    }, 500);
}

/*
 * Update sold_by on button click
 */
$(document).on('click', '#update_sold_by_btn', function() {
    // Skip if this is from membership edit modal (handled in create-membership.js)
    if ($('#package_service_id').data('from-membership')) {
        return;
    }

    let soldBy = $('#sold_by_dropdown').val();

    // Validation
    if (!soldBy) {
        $('#sold_by_error').html('Please select sold by');
        return false;
    }

    $('#sold_by_error').html('');

    // --- New-row edit: update DOM directly, no AJAX ---
    if ($('#package_service_id').data('new-row-edit')) {
        var $targetRow = $('#package_service_id').data('target-row');
        var configGroupId = $('#package_service_id').data('config-group-id') || '';
        var soldByName = $('#sold_by_dropdown option:selected').text().trim();

        if (configGroupId) {
            // Propagate to all rows in the same configurable group
            $('tr.configurable-group-' + configGroupId).each(function () {
                $(this).find('.package_bundles_sold_by').val(soldBy);
                $(this).find('.sold-by-display').text(soldByName);
            });
        } else if ($targetRow && $targetRow.length) {
            // Single row (simple discount)
            $targetRow.find('.package_bundles_sold_by').val(soldBy);
            $targetRow.find('.sold-by-display').text(soldByName);
        }

        // Clean up and close
        $('#package_service_id').removeData('new-row-edit');
        $('#package_service_id').removeData('target-row');
        $('#package_service_id').removeData('config-group-id');
        closeSoldByModal();
        toastr.success('Sold by updated');
        return;
    }

    // --- Persisted-row edit: AJAX call ---
    $(this).attr('disabled', true);

    let packageServiceIds = $('#package_service_id').data('service-ids');
    let updateData = {
        sold_by: soldBy
    };

    // If multiple services, send as array
    if (packageServiceIds && Array.isArray(packageServiceIds)) {
        updateData.package_services = packageServiceIds;
    } else {
        updateData.package_service_id = $('#package_service_id').val();
    }

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.packages.updatesoldby'),
        type: 'POST',
        data: updateData,
        success: function(response) {
            $('#update_sold_by_btn').attr('disabled', false);
            if (response.status) {
                closeSoldByModal();
                toastr.success(response.message || 'Sold by updated successfully');
                location.reload();
            } else {
                toastr.error(response.message || 'Failed to update sold by');
            }
        },
        error: function(xhr) {
            $('#update_sold_by_btn').attr('disabled', false);
            errorMessage(xhr);
        }
    });
});
