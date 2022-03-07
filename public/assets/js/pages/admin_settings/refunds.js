
var table_url = route('admin.refunds.datatable');

var table_columns = [
    {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 50,
    },{
        field: 'patient_id',
        title: 'Patient',
        sortable: false,
        width: 'auto',
    },{
        field: 'phone',
        title: 'Phone',
        sortable: false,
        width: 'auto',
    },{
        field: 'package_ide',
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
            $("#modal_edit_refunds").modal("hide");
            toastr.error("Insufficient amount to refund");
            return false;
        }

        $("#modal_edit_refunds").modal("show");

        $("#modal_edit_refunds_form").attr("action", route('admin.refunds.update', {id: refund.id}));


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

    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            patient_id: $("#search_id").val(),
            package_id: $("#search_plans").val(),
            location_id: $("#search_centres").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
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
            created_from: '',
            created_to: '',
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
        $("#search_centres").html(location_options);
        $("#search_id").html(patients_options);

        $("#search_id").val(active_filters.patient_id);
        $("#search_plans").val(active_filters.package_id);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);
        $("#search_centres").val(active_filters.location_id);
        //$("#search_phone").val(active_filters.phone);

        hideShowAdvanceFilters(active_filters);

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
