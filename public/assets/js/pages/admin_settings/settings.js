let modal_id = 'modal_settings_form';
let form = document.getElementById(modal_id);
var validate = FormValidation.formValidation(
    form,
    {
        fields: {
            data: {
                validators: {
                    notEmpty: {
                        message: 'The data field is required'
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
        if (response.status == true) {
            toastr.success(response.message);
            closePopup(modal_id);
            reInitTable('globalSetting');
        } else {
            toastr.error(response.message);
        }
    });
});

var table_url = route('admin.settings.datatable');

var table_columns = [
    {
        field: 'name',
        title: 'Name',
        width: 600,
    }, {
        field: 'data',
        title: 'Data',
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

    let id = data.id;

    if (permissions.edit) {
        return '<a href="javascript:void(0);" onclick="editRow(' + id + ')" class="btn btn-sm btn-primary">\
        <span class="navi-icon"><i class="la la-pencil"></i></span>\
        <span class="navi-text">Edit</span>\
        </a>';
    }

    return '';
}

const preValidators = {
    validators: {
        notEmpty: {
            message: 'The Pre is required',
        },
    },
};
const postValidators = {
    validators: {
        notEmpty: {
            message: 'The Post is required',
        },
    },
};
const minValidators = {
    validators: {
        notEmpty: {
            message: 'The Min is required',
        },
        lessThan: {
            message: "The min value is greater than Max",
            max: $('#max').val(),
        }
    },
};

const maxValidators = {
    validators: {
        notEmpty: {
            message: 'The Max is required',
        }
    },
};

const dataValidators = {
    validators: {
        notEmpty: {
            message: 'The data is required',
        }
    },
};

function editRow(id, modal) {

    $("#min-msg").hide();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.settings.edit', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            if (response.status) {
                $("#change_modal").modal("show");
                let fields = validate.getFields();
                if (fields.data) {
                    validate.removeField('data');
                }
                if (fields.min) {
                    validate.removeField('min');
                }
                if (fields.min) {
                    validate.removeField('max');
                }
                if (fields.min) {
                    validate.removeField('pre');
                }
                if (fields.min) {
                    validate.removeField('post');
                }
                let data;
                data = '<input type="hidden" name="data" value="' + response.data.data + '" id="form_data" class="form-control form-control-lg form-control-solid mb-2">';
                if (response.data.field_type === 'text') {
                    data = '<input type="text" name="data" value="' + response.data.data + '" id="form_data" class="form-control form-control-lg form-control-solid mb-2">';
                } else if (response.data.field_type === 'select') {
                    data = data + '<select class="form-control form-control-lg mb-2" name="data" >';
                    Object.entries(response.data.list).forEach(function (value, index) {
                        if (response.data.data === value[0]) {
                            data = data + '<option selected value="' + value[0] + '">' + value[1] + '</option>';
                        } else {
                            data = data + '<option value="' + value[0] + '">' + value[1] + '</option>';
                        }
                    });
                    data = data + '</select>';
                } else if (response.data.field_type === 'minmax') {
                    data = data + '<div class="row">' +
                        '<div class="col-md-6"><input oninput="phoneField(this);"  placeholder="Min" type="text"  name="min" id="min" value="' + response.data.min + '" id="form_data" required class="form-control form-control-lg form-control-solid mb-2"><span style="display: none;" id="min-msg" class="text text-danger"></span></div>' +
                        '<div class="col-md-6"><input oninput="phoneField(this);"  placeholder="Max" type="text" name="max" id="max" value="' + response.data.max + '" id="form_data" required class="form-control form-control-lg form-control-solid mb-2"></div>' +
                        '</div>';
                } else if (response.data.field_type === 'prepost') {
                    data = data + '<div class="row">' +
                        '<div class="col-md-6"><input oninput="phoneField(this);"  placeholder="Pre" type="text" name="pre" value="' + response.data.pre + '" id="form_data" required class="form-control form-control-lg form-control-solid mb-2 mr-1"></div>' +
                        '<div class="col-md-6"><input oninput="phoneField(this);"  placeholder="Post" type="text" name="post" value="' + response.data.post + '" id="form_data" required class=" form-control form-control-lg form-control-solid mb-2 ml-1"></div>' +
                        '</div>';
                }
                $('#modal_settings_form').attr('action', route('admin.settings.update', response.data.id));
                $('#form_name').html(response.data.name);
                $('#form_name_field').val(response.data.name);
                $('#field_data').html(data);
                validate.addField('data', dataValidators);
                if (response.data.field_type === 'minmax') {
                    validate.addField('max', maxValidators);
                } else if (response.data.field_type === 'prepost') {
                    validate.addField('pre', preValidators);
                    validate.addField('post', postValidators);
                }
            } else {
                toastr.error(response.message);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

$(document).on('keydown', '#min', function () {
  //  setMinValidation();

});

$(document).on('keyup', '#max', function () {
   // setMinValidation();
    validate.revalidateField('min');
});

function setMinValidation() {
    validate.setFieldOptions('min', {
        validators: {
            notEmpty: {
                message: 'The Min is required',
            },
            lessThan: {
                message: "The min value is greater than Max",
                max: $('#max').val(),
            }
        },
    });
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            name: $("#search_name").val(),
            data: $("#search_data").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function () {
        let filters = {
            delete: '',
            name: '',
            data: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function submitForm(action, method, data, callback) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: action,
        type: method,
        data: data,
        cache: false,
        success: function (response) {
            if (response.status == true) {
                $("#min-msg").hide();
                callback({
                    'status': response.status,
                    'message': response.message,
                });
            } else {
                $("#min-msg").text(response.message).show();
                callback({
                    'status': response.status,
                    'message': response.message,
                });
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {
                callback({
                    'status': 0,
                    'message': 'You are not authorized to access this resource',
                });
            } else {
                callback({
                    'status': 0,
                    'message': 'Unable to process your request, please try again later.',
                });
            }
        }
    });
}



