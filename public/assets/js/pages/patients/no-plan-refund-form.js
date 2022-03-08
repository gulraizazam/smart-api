
var table_url = route('admin.nonplansrefundpatient.datatable', {id: patient_id});

var table_columns = [
    {
        field: 'name',
        title: 'Patient',
        sortable: false,
        width: 80,
    },{
        field: 'phone',
        title: 'Phone',
        sortable: false,
        width: 'auto',
    },{
        field: 'package_id',
        title: 'Plans',
        sortable: false,
        width: 'auto',
    },{
        field: 'location_id',
        title: 'Centre',
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
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
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

        let refund_url = route('admin.nonplansrefundpatient.refund_create', {id: id});

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

            reInitValidation(NoPlanRefundValidation);
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

var NoPlanRefundValidation = function () {
    // Private functions
    var validation = function () {
        let modal_id = 'modal_no_plan_refund_refunds_form';
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
    NoPlanRefundValidation.init();
});
