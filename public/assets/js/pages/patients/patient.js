
var table_url = route('admin.patients.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 80,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    },
    {
        field: 'patient_id',
        title: 'Patient ID',
        width: 'auto',
        sortable: false,
        template: function (data) {
            return makePatientId(data.id);
        }
    },{
        field: 'name',
        title: 'Name',
        width: 'auto',
        sortable: false,
    },{
        field: 'email',
        title: 'Email',
        width: 'auto',
        sortable: false,
    },{
        field: 'phone',
        title: 'Phone',
        width: 90,
        sortable: false,
        template: function (data) {
            if (permissions.contact) {
                return data.phone;
            }
            return '***********';
        }
    },{
        field: 'gender',
        title: 'Gender',
        width: 60,
        sortable: false,
        template: function (data) {
            return getGender(data.gender);
        }
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        sortable: false,
        template: function (data) {
            return formatDate(data.created_at);
        }
    }, {
        field: 'status',
        title: 'status',
        width: 70,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.patients.status');
            return statuses(data, status_url);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 'auto',
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];


function actions(data) {

    let id = data.id;

    let url = route('admin.patients.edit', {id: id});
    let delete_url = route('admin.patients.destroy', {id: id});
    let view_url = route('admin.patients.preview', {id: id});

    if (permissions.edit || permissions.delete) {
        let actions = '<div class="dropdown dropdown-inline action-dots">\
            <a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
                <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
            </a>\
            <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
                <ul class="navi flex-column navi-hover py-2">\
                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                        Choose an action: \
                        </li>';
        if (permissions.edit) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`'+url+'`, `'+id+'`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
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

        if (permissions.manage) {
            actions += '<li class="navi-item">\
                            <a href="'+view_url+'" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">View</span>\
                            </a>\
                        </li>';
        }

        actions += '</ul>\
            </div>\
        </div>';

        return actions;
    }
    return '';
}

function editRow(url, id) {

    $("#modal_edit_patients").modal("show");
    $("#modal_edit_patients_form").attr("action", route('admin.patients.update', {id: id}));

    $.ajax({
        // headers: {
        //     'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        // },
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

    let genders = response.data.gender;
    let patient = response.data.patient;
    let gender_option = '<option value="">All</option>';

    Object.entries(genders).forEach(function (gender) {
        gender_option += '<option value="'+gender[0]+'">'+gender[1]+'</option>';
    });

    $("#edit_gender_id").html(gender_option);
    $("#edit_name").val(patient.name);
    $("#edit_email").val(patient.email);
    $("#edit_old_phone").val(patient.phone);

    if (permissions.contact) {
        $("#edit_phone").val(patient.phone);
    } else {
        $("#edit_phone").val("***********").attr("readonly", true);
    }

    $("#edit_gender_id").val(patient.gender);

}


function createPatient(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setPatientData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });


}

function setPatientData(response) {

    let genders = response.data.gender;
    let gender_option = '<option value="">All</option>';

    Object.entries(genders).forEach(function (gender) {
        gender_option += '<option value="'+gender[0]+'">'+gender[1]+'</option>';
    });
    $("#add_gender_id").html(gender_option);

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            patient_id: $("#search_patient_id").val(),
            name: $("#search_name").val(),
            email: $("#search_email").val(),
            phone: $("#search_phone").val(),
            gender: $("#search_gender").val(),
            created_at: $("#date_range").val(),
            status: $("#search_status").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            patient_id: '',
            name: '',
            email: '',
            phone: '',
            gender: '',
            created_at: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let status = filter_values.status;
        let genders = filter_values.gender;

        let status_options = '<option value="">All</option>';
        let gender_options = '<option value="">All</option>';

        Object.entries(genders).forEach(function (gender, index) {
            gender_options += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
        });

        Object.entries(status).forEach(function (value, index) {
            status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        $("#search_status").html(status_options);
        $("#search_gender").html(gender_options);

        $("#search_name").val(active_filters.name);
        $("#search_status").val(active_filters.status);
        $("#search_gender").val(active_filters.gender);
        $("#search_phone").val(active_filters.phone);
        $("#search_email").val(active_filters.email);
        $("#search_created_at").val(active_filters.created_at);

        hideShowAdvanceFilters(active_filters);
    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')
        || (typeof active_filters.gender !== 'undefined' && active_filters.gender != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}


jQuery(document).ready( function () {
    $("#date_range").val("");
})

