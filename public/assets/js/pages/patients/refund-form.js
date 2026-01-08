
var table_url = route('admin.refundpatient.datatable', {id: patientCardID});

var table_columns = [
    {
        field: 'plan_id',
        title: 'Plan ID',
        sortable: false,
        width: 70,
    }, {
        field: 'total',
        title: 'Plan Total',
        sortable: false,
        width: 90,
    }, {
        field: 'cash_in',
        title: 'Cash In',
        sortable: false,
        width: 80,
    }, {
        field: 'cash_out',
        title: 'Cash Out',
        sortable: false,
        width: 80,
    }, {
        field: 'refunded_amount',
        title: 'Refunded',
        sortable: false,
        width: 80,
    }, {
        field: 'case_setteled',
        title: 'Case Settled',
        sortable: false,
        width: 80,
    }, {
        field: 'created_at',
        title: 'Created At',
        sortable: false,
        width: 120,
    }, {
        field: 'location',
        title: 'Location',
        sortable: false,
        width: 'auto',
    }];


function actions(data) {

    if (typeof data.id !== 'undefined') {

        let id = data.id;

        let refund_url = route('admin.refundpatient.refund_create', {id: id});

        if (permissions.refund) {
            let actions = '<div class="dropdown dropdown-inline action-dots">\
        <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';

            if (permissions.refund) {
                actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="refundPlan(`' + refund_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-trash"></i></span>\
                        <span class="navi-text">Refund</span>\
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

function refundPlan(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {

            refundData(response);

            reInitSelect2(".select2", "");

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

        if (refund.refundable_amount == 0) {
            $("#modal_refund_refund").modal("hide");
            toastr.error("Insufficient amount to refund");
            return false;
        }

        $("#modal_refund_refund").modal("show");

        if (refund.document) {
            $("#document-label").text('Documentation Charges Already Taken');
            $("#documentationcharges").hide();
        } else {
            $("#document-label").text('Documentation Charges');
            $("#documentationcharges").show();
        }
        $("#refund_amount").html(refund.refundable_amount);
        $("#documentationcharges").val(refund.documentationcharges.data);
        $("#balance").attr('max', refund.refundable_amount);

        $("#package_id").val(refund.id);
        $("#is_adjustment_amount").val(refund.is_adjustment_amount);
        $("#return_tax_amount").val(refund.return_tax_amount);
        $("#date_backend").val(refund.date_backend);
        $("#balance").val(refund.refundable_amount);

    } catch (error) {
        showException(error);
    }

}

/*Actions*/

function applyFilters(datatable) {

    $('#refund-search').on('click', function() {

        let filters =  {
            delete: '',
            plan_id: $("#search_refund_plan_id").val(),
            location_id: $("#search_refund_location_id").val(),
            created_from: $("#search_refund_created_from").val(),
            created_to: $("#search_refund_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $(".page-refund-form").find('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            plan_id: '',
            location_id: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let locations = filter_values?.locations;
        let packages = filter_values?.package;

        let package_options = '<option value="">Select Plan</option>';

        if (packages) {
            Object.entries(packages).forEach(function (package) {
                package_options += '<option value="'+package[0]+'">'+package[1]+'</option>';
            });
        }

        let location_options = '<option value="">Select Location</option>';

        if (locations) {
            Object.entries(locations).forEach(function (location) {
                location_options += '<option value="'+location[0]+'">'+location[1]+'</option>';
            });
        }

        $("#search_refund_plan_id").html(package_options);
        $("#search_refund_location_id").html(location_options);
        $("#search_refund_location_id").val(active_filters?.location_id);
        $("#search_refund_plan_id").val(active_filters?.plan_id);
        $("#search_refund_created_from").val(active_filters?.created_from);
        $("#search_refund_created_to").val(active_filters?.created_to);

    } catch (error) {
        showException(error);
    }
}


var RefundValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_refund_refunds_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    refund_note: {
                        validators: {
                            notEmpty: {
                                message: 'The refund note field is required'
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
        validate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reInitTable();
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        init: function() {
            validation();
        }
    };
}();

jQuery(document).ready(function() {
    RefundValidation.init();
});
