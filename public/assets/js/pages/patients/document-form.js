
var table_url = route('admin.patients.documentdatatable', {id: patientCardID});

var table_columns = [
    {
        field: 'name',
        title: 'Name',
        width: 'auto',
    },{
        field: 'patient.name',
        title: 'Patient Name',
        width: 'auto',
        sortable: false,
    },{
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        template: function (data) {
            return formatDate(data.date)
        }
    },{
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 100,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];


function actions(data) {

    if (typeof data.id !== 'undefined') {

        let id = data.id;
        let name = data.name;
        let file = data.url;

        let edit_url = route('admin.patients.updatedocuments', {id: id});
        let view_url = asset_url + "storage/" + file;
        let delete_url = route('admin.patients.documentsdestroy', {id: id});

        if (permissions.edit || permissions.delete || permissions.manage) {
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
                    <a href="javascript:void(0);" onclick="editRow(`'+edit_url+'`, `'+name+'`)" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
            }
            if (permissions.manage) {
                actions += '<li class="navi-item">\
                    <a href="'+view_url+'" target="_blank" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">view</span>\
                    </a>\
                </li>';
            }

            if (permissions.delete) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`, `DELETE`, `.document-form`);" class="navi-link">\
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

function editRow(url, name) {

    $("#modal_edit_document_form").modal("show");
    $("#modal_edit_documents_form").attr("action", url);

    $("#edit_patient_id").val(patientCardID);
    $("#edit_document_name").val(name);

}

function applyFilters(datatable) {

    $('#document-search').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#document_search_name").val(),
            created_from: $("#document_search_created_from").val(),
            created_to: $("#document_search_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $(".page-document-form").find('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        $("#search_name").val(active_filters.name);
        $("#search_patient_name").val(active_filters.patient_name);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);

    } catch (error) {
        showException(error);
    }
}

function addDocumentForm(patientCardID) {
    $("#patientId").val(patientCardID);
}

/*For validation*/

var DocumentValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_add_documents_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The name field is required'
                            }
                        }
                    },
                    file: {
                        validators: {
                            notEmpty: {
                                message: 'The file field is required'
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
            submitFileForm($(form).attr('action'), $(form).attr('method'), modal_id, function (response) {
                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    reloadTable('.document-form');

                } else {
                    toastr.error(response.message);
                }
            });
        });
    }

    return {
        // public functions
        init: function() {
            AddValidation();
        }
    };
}();

var EditDocumentValidation = function () {
    // Private functions
    var AddValidation = function () {
        let modal_id = 'modal_edit_documents_form';
        let form = document.getElementById(modal_id);
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The name field is required'
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
                    reloadTable('.document-form');
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        // public functions
        init: function() {
            AddValidation();
        }
    };
}();


jQuery(document).ready(function() {
    DocumentValidation.init();
    EditDocumentValidation.init();
});

/*End For validation*/

