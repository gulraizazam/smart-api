
var table_url = route('admin.refunds.datatable');

var table_columns = [
    {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 80,
    },{
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 'auto',
    },{
        field: 'phone',
        title: 'Phone',
        sortable: false,
        width: 'auto',
    },{
        field: 'package_id',
        title: 'Plans',
        sortable: false,
        width: 70,
    },{
        field: 'session_count',
        title: 'Session count',
        sortable: false,
        width: 'auto',
    },{
        field: 'total',
        title: 'Total',
        sortable: false,
        width: 80,
    },{
        field: 'cash_receive',
        title: 'Cash receive',
        sortable: false,
        width: 100,
    },{
        field: 'location_id',
        title: 'Centres',
        sortable: false,
        width: 170,
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
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

        let url = route('admin.refunds.refund_create', {id: id});

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

                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="refund(`' + url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Refund</span>\
                    </a>\
                </li>';

            actions += '</ul>\
        </div>\
    </div>';

            return actions;
        }
    }
    return '-';
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

        if (refund.refundable_amount == 0) {
            $("#modal_edit_refunds").modal("hide");
            toastr.error("Insufficient amount to refund");
            return false;
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

    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            id: $("#search_id").val(),
            patient_id: $("#search_patient").val(),
            location_id: $("#search_centres").val(),
            package_id: $("#search_plans").val(),
            created_at: $("#date_range").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            delete: '',
            patient_id: '',
            package_id: '',
            location_id: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
    try {

        let patients = filter_values.patient;
        let locations = filter_values.locations;
        let package = filter_values.package;

        let patients_options = '<option value="">All</option>';
        let location_options = '<option value="">All</option>';
        let package_options = '<option value="">All</option>';

        Object.entries(package).forEach(function (value, index) {
            package_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(patients).forEach(function (value, index) {
            patients_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value, index) {
            location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });


        $("#search_plans").html(package_options);
        $("#search_id").html(patients_options);

        $("#search_id").val(active_filters.patient_id);
        $("#date_range").val(active_filters.created_at);
        $("#search_centres").html(location_options);
        $("#search_centres").val(active_filters.location_id);

        hideShowAdvanceFilters(active_filters);

        getUserCentre();

    } catch (err) {

    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}

$(document).ready( function () {

    patientSearch('search_patient');

    $(document).on("click", ".croxcli", function () {
        $('.search_field').val('').change();
        $('.search_patient').val(null).trigger('change');
    });
    $("#date_range").val("");
});
