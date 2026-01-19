
var table_url = route('admin.patients.documentdatatable', {id: patientCardID});

var table_columns = [
    {
        field: 'thumbnail',
        title: 'Preview',
        width: 80,
        sortable: false,
        textAlign: 'center',
        template: function(data) {
            return getFileThumbnail(data.url, data.full_url);
        }
    },{
        field: 'document_type',
        title: 'Document Type',
        width: 150,
        sortable: false,
        template: function(data) {
            return formatDocumentType(data.document_type);
        }
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

// Format document type for display
function formatDocumentType(type) {
    if (!type) return '<span class="text-muted">-</span>';
    
    let typeMap = {
        'consent_form': { label: 'Consent Form', class: 'badge-primary' },
        'consultation_form': { label: 'Consultation Form', class: 'badge-info' },
        'others': { label: 'Others', class: 'badge-secondary' }
    };
    
    let typeInfo = typeMap[type] || { label: type, class: 'badge-secondary' };
    return '<span class="badge ' + typeInfo.class + '">' + typeInfo.label + '</span>';
}

// Get file thumbnail based on file extension - shows image preview for images, icons for others
function getFileThumbnail(fileUrl, fullUrl) {
    if (!fileUrl) return '<i class="la la-file text-muted" style="font-size: 24px;"></i>';
    
    let ext = fileUrl.split('.').pop().toLowerCase();
    let downloadUrl = fullUrl || (base_route + "/storage/" + fileUrl);
    let html = '';
    
    // Image files - show actual thumbnail
    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
        html = '<a href="' + downloadUrl + '" target="_blank" title="View Image">' +
            '<img src="' + downloadUrl + '" alt="Preview" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #eee;">' +
            '</a>';
    }
    // PDF files
    else if (ext === 'pdf') {
        html = '<a href="' + downloadUrl + '" target="_blank" class="file-icon-link" title="View PDF">' +
            '<i class="la la-file-pdf text-danger" style="font-size: 32px;"></i>' +
            '</a>';
    }
    // Word documents
    else if (['doc', 'docx'].includes(ext)) {
        html = '<a href="' + downloadUrl + '" download class="file-icon-link" title="Download Word Document">' +
            '<i class="la la-file-word text-primary" style="font-size: 32px;"></i>' +
            '</a>';
    }
    // Excel files
    else if (['xls', 'xlsx'].includes(ext)) {
        html = '<a href="' + downloadUrl + '" download class="file-icon-link" title="Download Excel">' +
            '<i class="la la-file-excel text-success" style="font-size: 32px;"></i>' +
            '</a>';
    }
    // SVG files
    else if (ext === 'svg') {
        html = '<a href="' + downloadUrl + '" target="_blank" title="View SVG">' +
            '<img src="' + downloadUrl + '" alt="Preview" style="width: 50px; height: 50px; object-fit: contain; border-radius: 4px; border: 1px solid #eee;">' +
            '</a>';
    }
    // Other files
    else {
        html = '<a href="' + downloadUrl + '" download class="file-icon-link" title="Download File">' +
            '<i class="la la-file-alt text-muted" style="font-size: 32px;"></i>' +
            '</a>';
    }
    
    return html;
}

// Get file type icon based on file extension (kept for backward compatibility)
function getFileTypeIcon(fileUrl, fullUrl) {
    return getFileThumbnail(fileUrl, fullUrl);
}


function actions(data) {

    if (typeof data.id !== 'undefined') {

        let id = data.id;
        let documentType = data.document_type || '';
        let file = data.url;

        let edit_url = route('admin.patients.updatedocuments', {id: id});
        // Use full_url from backend if available, otherwise construct manually
        let view_url = data.full_url ? data.full_url : (base_route + "/storage/" + file);
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
                    <a href="javascript:void(0);" onclick="editRow(`'+edit_url+'`, `'+documentType+'`, `'+file+'`, '+id+')" class="navi-link">\
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

function editRow(url, documentType, fileUrl, documentId) {

    $("#modal_edit_document_form").modal("show");
    $("#modal_edit_documents_form").attr("action", url);
    
    // Store document ID for API call
    currentEditDocumentId = documentId;

    $("#edit_patient_id").val(patientCardID);
    $("#edit_document_type").val(documentType);
    
    // Reset file input
    $("#edit_document_file").val('');
    $("#edit_document_file").next('.custom-file-label').text('Choose file');
    
    // Show current file preview
    if (fileUrl) {
        // fileUrl might be full URL or relative path
        let fullUrl = fileUrl.startsWith('http') ? fileUrl : (base_route + "/storage/" + fileUrl);
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
    clearFileInput();
}

// Clear file input and reset dropzone
function clearFileInput() {
    $("#document_file").val('');
    $(".dropzone-content").removeClass('d-none');
    $(".dropzone-preview").addClass('d-none');
}

// Update dropzone preview when file is selected
function updateDropzonePreview(file) {
    if (file) {
        let ext = file.name.split('.').pop().toLowerCase();
        let iconClass = 'la-file';
        let iconColor = '#6c757d';
        
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
            iconClass = 'la-file-image';
            iconColor = '#17a2b8';
        } else if (ext === 'pdf') {
            iconClass = 'la-file-pdf';
            iconColor = '#dc3545';
        } else if (['doc', 'docx'].includes(ext)) {
            iconClass = 'la-file-word';
            iconColor = '#007bff';
        } else if (['xls', 'xlsx'].includes(ext)) {
            iconClass = 'la-file-excel';
            iconColor = '#28a745';
        }
        
        $("#preview_icon").removeClass().addClass('la ' + iconClass).css('color', iconColor);
        $("#preview_filename").text(file.name);
        $(".dropzone-content").addClass('d-none');
        $(".dropzone-preview").removeClass('d-none');
    }
}

// Clear edit file input and reset dropzone
function clearEditFileInput() {
    $("#edit_document_file").val('');
    $(".edit-dropzone-content").removeClass('d-none');
    $(".edit-dropzone-preview").addClass('d-none');
}

// Update edit dropzone preview when file is selected
function updateEditDropzonePreview(file) {
    if (file) {
        let ext = file.name.split('.').pop().toLowerCase();
        let iconClass = 'la-file';
        let iconColor = '#6c757d';
        
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'].includes(ext)) {
            iconClass = 'la-file-image';
            iconColor = '#17a2b8';
        } else if (ext === 'pdf') {
            iconClass = 'la-file-pdf';
            iconColor = '#dc3545';
        } else if (['doc', 'docx'].includes(ext)) {
            iconClass = 'la-file-word';
            iconColor = '#007bff';
        } else if (['xls', 'xlsx'].includes(ext)) {
            iconClass = 'la-file-excel';
            iconColor = '#28a745';
        }
        
        $("#edit_preview_icon").removeClass().addClass('la ' + iconClass).css('color', iconColor);
        $("#edit_preview_filename").text(file.name);
        $(".edit-dropzone-content").addClass('d-none');
        $(".edit-dropzone-preview").removeClass('d-none');
    }
}

// Initialize drag and drop
$(document).ready(function() {
    // Create dropzone - prevent default drag behaviors on all dropzones
    $(document).on('dragenter dragover dragleave drop', '.dropzone-area', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });
    
    // Highlight dropzone on drag
    $(document).on('dragenter dragover', '.dropzone-area', function() {
        $(this).addClass('dragover');
    });
    
    $(document).on('dragleave drop', '.dropzone-area', function() {
        $(this).removeClass('dragover');
    });
    
    // Handle dropped files for create form
    $(document).on('drop', '.dropzone-area:not(.edit-dropzone)', function(e) {
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            var fileInput = document.getElementById('document_file');
            fileInput.files = files;
            updateDropzonePreview(files[0]);
        }
    });
    
    // Handle dropped files for edit form
    $(document).on('drop', '.edit-dropzone', function(e) {
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            var fileInput = document.getElementById('edit_document_file');
            fileInput.files = files;
            updateEditDropzonePreview(files[0]);
        }
    });
    
    // Handle file input change for create form
    $(document).on('change', '#document_file', function() {
        if (this.files.length > 0) {
            updateDropzonePreview(this.files[0]);
        }
    });
    
    // Handle file input change for edit form
    $(document).on('change', '#edit_document_file', function() {
        if (this.files.length > 0) {
            updateEditDropzonePreview(this.files[0]);
        }
    });
});

// Optimized API-based document update
function updatePatientDocument(patientId, documentId) {
    let form = document.getElementById('modal_edit_documents_form');
    let formData = new FormData(form);
    
    let documentType = document.getElementById('edit_document_type').value;
    if (!documentType || documentType.trim() === '') {
        toastr.error('Please select a document type');
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
    
    let documentType = document.getElementById('add_document_type').value;
    if (!documentType || documentType.trim() === '') {
        toastr.error('Please select a document type');
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
                    document_type: {
                        validators: {
                            notEmpty: {
                                message: 'Please select a document type'
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
                    document_type: {
                        validators: {
                            notEmpty: {
                                message: 'Please select a document type'
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

