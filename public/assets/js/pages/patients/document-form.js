
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
                    <a href="javascript:void(0);" onclick="editRow(`'+edit_url+'`, `'+name+'`, `'+file+'`, '+id+')" class="navi-link">\
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

var currentEditDocumentId = null;

function editRow(url, name, fileUrl, documentId) {

    $("#modal_edit_document_form").modal("show");
    $("#modal_edit_documents_form").attr("action", url);
    
    // Store document ID for API call
    currentEditDocumentId = documentId;

    $("#edit_patient_id").val(patientCardID);
    $("#edit_document_name").val(name);
    
    // Reset file input
    $("#edit_document_file").val('');
    $("#edit_document_file").next('.custom-file-label').text('Choose file');
    
    // Show current file preview
    if (fileUrl) {
        let fullUrl = asset_url + "storage/" + fileUrl;
        let fileName = fileUrl.split('/').pop();
        let ext = fileName.split('.').pop().toLowerCase();
        
        $("#current_file_link").attr("href", fullUrl);
        $("#current_file_name").text(fileName);
        
        // Check if it's an image file
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
            $("#current_file_image").attr("src", fullUrl);
            $("#current_file_link_img").attr("href", fullUrl);
            $("#image_preview_container").show();
            $("#file_icon").removeClass("la-file-alt").addClass("la-image");
        } else {
            $("#image_preview_container").hide();
            $("#file_icon").removeClass("la-image").addClass("la-file-alt");
            // Set icon based on file type
            if (ext === 'pdf') {
                $("#file_icon").removeClass("la-file-alt").addClass("la-file-pdf");
            } else if (['doc', 'docx'].includes(ext)) {
                $("#file_icon").removeClass("la-file-alt").addClass("la-file-word");
            } else if (['xls', 'xlsx'].includes(ext)) {
                $("#file_icon").removeClass("la-file-alt").addClass("la-file-excel");
            }
        }
    } else {
        $("#current_file_link").attr("href", "#");
        $("#current_file_name").text("No file");
        $("#image_preview_container").hide();
    }

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

function addDocumentForm(patientId) {
    $("#patientId").val(patientId);
    // Reset form
    $("#modal_add_documents_form")[0].reset();
    $(".custom-file-label").text("Choose file");
}

// Optimized API-based document update
function updatePatientDocument(patientId, documentId) {
    let form = document.getElementById('modal_edit_documents_form');
    let formData = new FormData(form);
    
    let name = document.getElementById('edit_document_name').value;
    if (!name || name.trim() === '') {
        toastr.error('Please enter a document name');
        return;
    }

    showSpinner();
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/patients/' + patientId + '/update-document/' + documentId,
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        cache: false,
        success: function(response) {
            hideSpinner();
            if (response.status) {
                toastr.success(response.message);
                closePopup('modal_edit_documents_form');
                $("#modal_edit_document_form").modal("hide");
                reloadTable('.document-form');
                // Reset form
                form.reset();
                $("#edit_document_file").next('.custom-file-label').text('Choose file');
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            hideSpinner();
            let message = 'An error occurred';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toastr.error(message);
        }
    });
}

// Optimized API-based document upload
function uploadPatientDocument(patientId) {
    let form = document.getElementById('modal_add_documents_form');
    let formData = new FormData(form);
    
    let fileInput = document.getElementById('document_file');
    if (!fileInput.files.length) {
        toastr.error('Please select a file');
        return;
    }
    
    let name = document.getElementById('add_document_name').value;
    if (!name || name.trim() === '') {
        toastr.error('Please enter a document name');
        return;
    }

    showSpinner();
    
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: '/api/patients/' + patientId + '/upload-document',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        cache: false,
        success: function(response) {
            hideSpinner();
            if (response.status) {
                toastr.success(response.message);
                closePopup('modal_add_documents_form');
                $("#modal_add_document_form").modal("hide");
                reloadTable('.document-form');
                // Reset form
                form.reset();
                $(".custom-file-label").text("Choose file");
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            hideSpinner();
            let message = 'An error occurred';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toastr.error(message);
        }
    });
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
            // Use optimized API upload
            let patientId = $("#patientId").val();
            uploadPatientDocument(patientId);
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
            // Use optimized API update
            let patientId = $("#edit_patient_id").val();
            updatePatientDocument(patientId, currentEditDocumentId);
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

