
var table_url = route('admin.nonplansrefunds.datatable');

var table_columns = [
   {
        field: 'patient_id',
        title: 'Patient ID',
        sortable: false,
        width: 'auto',
    },{
        field: 'name',
        title: 'Patient',
        sortable: false,
        width: 'auto',
    },{
        field: 'doctor',
        title: 'Doctor',
        sortable: false,
        width: 'auto',
    },{
        field: 'region',
        title: 'Region',
        sortable: false,
        width: 'auto',
    },{
        field: 'city',
        title: 'City',
        sortable: false,
        width: 'auto',
    },{
        field: 'location',
        title: 'Centre',
        sortable: false,
        width: 'auto',
    },{
        field: 'service',
        title: 'Service',
        sortable: false,
        width: 'auto',
    },{
        field: 'type',
        title: 'Type',
        sortable: false,
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

        let refund_url = route('admin.nonprefunds.refund_create', {id: id});

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
                    <a href="javascript:void(0);" onclick="refund(`' + refund_url + '`);" class="navi-link">\
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
            id: $("#search_patient_id").val(),
            patient_id: $("#search_patient").val(),
            patient_name: $("#search_patient").text(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetCustomFilters() {

    $(".filter-field").val('');
    addUsers();
    $('.select2').val(null).trigger('change');
}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            id: '',
            patient_id: '',
            patient_name: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
   
    try {

        $("#search_patient_id").val(active_filters.id);

        if (active_filters.patient_name !== 'undefined' && active_filters.patient_name != 'undefined') {
            $("#search_patient").html('<option value="'+active_filters.patient_id+'">'+active_filters.patient_name+'</option>');
            $("#search_patient").val(active_filters.patient_id);
        }
       
    } catch (err) {
        showException(err);
    }
}
