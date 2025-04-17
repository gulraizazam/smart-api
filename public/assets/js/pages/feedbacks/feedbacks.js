var table_url = route('admin.feedbacks.datatable');

var table_columns = [{
    field: 'paient_name',
    title: 'Patient Name',
    sortable: false,
    width: 110,
}, {
    field: 'phone',
    title: 'Phone',
    sortable: false,
    width: 90,
    template: function (data) {
        return phoneClip(data);
    }
},{
    field: 'doctor',
    title: 'Doctor',
    sortable: false,
    width: 110,
    template: function (data) {
        if(data.doctor != ""){
            return data.doctor;
        }else{
            return '<span class="text text-danger">Empty</span>';
        }
    }
},{
    field: 'service',
    title: 'Service',
    sortable: false,
    width: 110,

},{
    field: 'treatment',
    title: 'Treatment',
    sortable: false,
    width: 110,

},{
    field: 'rating',
    title: 'Rating',
    sortable: false,
    width: 70,
    template: function (data) {
        if(data.rating != ""){
            return data.rating+'/10';
        }else{
            return '<span class="text text-danger">Empty</span>';
        }
    }

}, {
    field: 'created_at',
    title: 'Created At',
    sortable: false,
    width: 'auto',
},{
    field: 'actions',
    title: 'Actions',
    sortable: false,
    width: 70,
    overflow: 'visible',
    autoHide: false,
    template: function(data) {
        return actions(data);
    }
}];
function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let edit_url = route('admin.feedbacks.edit', {id: id});
        let delete_url = route('admin.feedbacks.destroy', { id: id });
        if (permissions.edit) {
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
                    <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';

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
    return '-';
}
function editRow(url) {

    $("#modal_edit_feedbacks").modal("show");

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

            reInitValidation(EditValidation);
        }
    });


}
function setEditData(response) {

    try {

        let feedback = response;

        $("#modal_edit_feedbacks_form").attr("action", route('admin.feedbacks.update', {id: feedback.id}));
        $("#edit_rating").val(feedback.rating).change();

    } catch (error) {
        showException(error);
    }

}
function openFeedbackForm(){
    $("#add_treatment_id").trigger('change');
    $(".search_patient_refund").val('');
}
$("#add_patients_id").on('change',function(){
    var patient_id = $('#add_patients_id').val();
    let url = route('admin.feedbacks.gettreatments');
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "post",
        data:{'patient_id':patient_id},
        cache: false,
        success: function (response) {
            let plans_options = '';

            if (response.treatments.length === 0) {
                plans_options = '<option value="">No Treatment Found</option>';
            } else {
                plans_options = '<option value="">Select Treatment</option>';
                Object.values(response.treatments).forEach(function (value) {
                    plans_options += '<option value="' + value.id + '">' + value.service.name + '</option>';
                });
            }

            $("#add_treatment_id").html(plans_options);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);


        }
    });
});
patientSearchRefund('search_patient_refund');
function GetTreatmentDetail(){
    var treatmentId = $('#add_treatment_id').val();
    let url = route('admin.feedbacks.gettreatmentsinfo');
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "post",
        data:{'treatment_id':treatmentId},
        cache: false,
        success: function (response) {
            $("#patient_name").val(response.treatments.name);
            $("#scheduled_date").val(response.treatments.scheduled_date);
            $("#doctor_name").val(response.treatments.doctor.name);
            $("#location").val(response.treatments.location.name);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);


        }
    });

}

