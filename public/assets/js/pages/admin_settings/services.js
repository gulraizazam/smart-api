
var table_url = route('admin.services.datatable');
let changePages = 1000;
var table_columns = [
    {
        field: 'name',
        title: 'Name',
        sortable: false,
        width:350,
        template: function (data) {
            if (data.parent_id == 0) {
                return '<b class="text text-dark" style="font-size: 12px; white-space: nowrap;">'+data.name+'</b>';
            }
            // Child service with clickable name and instruction icon
            return '<span class="ml-3" style="white-space: nowrap;">' +
                '<a href="javascript:void(0);" onclick="showInstructions(' + data.id + ', \'' + data.name.replace(/'/g, "\\'") + '\');" title="View Instructions" style="cursor: pointer; text-decoration: none; color: #3F4254;">'+data.name+'</a>' +
                '<a href="javascript:void(0);" onclick="showInstructions(' + data.id + ', \'' + data.name.replace(/'/g, "\\'") + '\');" title="View Instructions" style="cursor: pointer; margin-left: 8px;">' +
                    '<i class="la la-file-text text-primary" style="font-size: 16px;"></i>' +
                '</a>' +
            '</span>';
        }
    },{
        field: 'duration',
        title: 'Duration',
        sortable: false,
        width: 80,
        template: function (data) {
            if (typeof data.price !== 'undefined') {
                return '<span>'+data.duration+' mins</span>';
            }
            return '00.00';
        }
    },{
        field: 'color',
        title: 'Color',
        sortable: false,
        width: 80,
        template: function (data) {
            return '<span class="badge" style="background-color: '+data.color+' !important; color: #fff; font-size: 12px;">'+data.color+'</span>';
        }
    },{
        field: 'price',
        title: 'Price',
        sortable: false,
        width: 80,
        template: function (data) {
            if (data.slug == 'all') {
                return '-';
            }
            if (typeof data.price !== 'undefined') {
                return '<span>'+data.price.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",")+'</span>';
            } else {
                return '00.00';
            }
        }
    },
    // {
    //     field: 'complimentory',
    //     title: 'Complimentory',
    //     sortable: false,
    //     width: 120,
    //     template: function (data) {
    //         if (data.parent_id == 0) {
    //             return '-';
    //         }
    //         if (typeof data.complimentory !== 'undefined') {
    //             let status = data.complimentory == 1 ? 'Yes' : 'No';
    //             return '<span>'+status+'</span>';
    //         }
    //         return 'No';
    //     }
    // }, 
    {
        field: 'status',
        title: 'status',
        width: 60,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.services.status');
            return serviceStatuses(data, status_url);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 70,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];


function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let url = route('admin.services.edit', {id: id});
        let delete_url = route('admin.services.destroy', {id: id});
        let duplicate_url = route('admin.services.duplicate', {id: id});

        
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
                    <a href="javascript:void(0);" onclick="editRow(`' + url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Edit</span>\
                    </a>\
                </li>';
            }
            // Add instructions option only for child services
            if (permissions.detail && data.parent_id != 0) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="showInstructions(' + id + ', \'' + data.name.replace(/'/g, "\\'") + '\');" class="navi-link">\
                        <span class="navi-icon"><i class="la la-file-text"></i></span>\
                        <span class="navi-text">Instructions</span>\
                    </a>\
                </li>';
            }
            // Add duplicate option only for child services
            if (permissions.duplicate && data.parent_id != 0) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="duplicateRow(`' + duplicate_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-copy"></i></span>\
                        <span class="navi-text">Duplicate</span>\
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

            actions += '</ul>\
        </div>\
    </div>';

            return actions;
        
    }
    return '';
}

function editRow(url) {

    $("#modal_edit_services").modal("show");

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

        let service = response.data.service;

        $("#modal_edit_services_form").attr("action", route('admin.services.update', {id: service.id}));
        // Reset form method to PUT for edit
        $("#modal_edit_services_form").find('input[name="_method"]').val('put');

        let services = response.data.parent_services;
        let durations = response.data.durations;
        let tax_treatment_types = response.data.tax_treatment_types;
        let select_tax_treatment_type = response.data.select_tax_treatment_type;
        let services_options = '<option value="0">Parent Service</option>';
        let duration_options = '<option value="">Select a Duration</option>';
        let radios = '';

        Object.entries(tax_treatment_types).forEach(function (value, index) {

            if (typeof value[1].id !== 'undefined') {
                radios += '<label class="radio">\
            <input type="radio" name="tax_treatment_type_id" value="' + value[1].id + '">\
            <span></span>\
        ' + value[1].name + '\
        </label>';
            }

        });

        Object.entries(services).forEach(function (value, index) {
            services_options += '<option value="' + value[1].id + '">' + value[1].name + '</option>';
        });

        Object.entries(durations).forEach(function (value, index) {
            duration_options += '<option value="' + value[1] + '">' + value[1] + '</option>';
        });

        $("#edit_duration").html(duration_options);

        $("#edit_parent_service").html(services_options);

        if (radios != '') {
            $(".tax-radios").html(radios);
        }

        $(".tax-radios").find("input").each(function () {
            if ($(this).val() == select_tax_treatment_type) {
                $(this).prop("checked", true);
            }
        });

        $("#edit_parent_service").val(service.parent_id);
        $("#edit_service_name").val(service.name);
        $("#edit_duration").val(service.duration);
        $("#edit_color").val(service.color);
        $("#edit_price").val(service.price);

        // Set Trix editor content
        $("#edit_description").val(service.description || '');
        let trixEditor = document.querySelector("trix-editor[input='edit_description']");
        if (trixEditor && trixEditor.editor) {
            trixEditor.editor.loadHTML(service.description || '');
        }

        if (service.end_node == 1) {
            $("#edit_end_node").prop("checked", true);
        } else {
            $("#edit_end_node").prop("checked", false);
        }

        if (service.complimentory == 1) {
            $("#edit_complimentory").prop("checked", true);
        } else {
            $("#edit_complimentory").prop("checked", false);
        }

    } catch (error) {
        showException(error);
    }

}

function duplicateRow(url) {

    $("#modal_edit_services").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {

            setDuplicateData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });

}

function setDuplicateData(response) {

    try {

        let service = response.data.service;

        $("#modal_edit_services_form").attr("action", route('admin.services.duplicate.store'));
        // Change form method to POST for duplicate
        $("#modal_edit_services_form").find('input[name="_method"]').val('post');

        let services = response.data.parent_services;
        let durations = response.data.durations;
        let tax_treatment_types = response.data.tax_treatment_types;
        let select_tax_treatment_type = response.data.select_tax_treatment_type;
        let services_options = '<option value="0">Parent Service</option>';
        let duration_options = '<option value="">Select a Duration</option>';
        let radios = '';

        Object.entries(tax_treatment_types).forEach(function (value, index) {

            if (typeof value[1].id !== 'undefined') {
                radios += '<label class="radio">\
            <input type="radio" name="tax_treatment_type_id" value="' + value[1].id + '">\
            <span></span>\
        ' + value[1].name + '\
        </label>';
            }

        });

        Object.entries(services).forEach(function (value, index) {
            services_options += '<option value="' + value[1].id + '">' + value[1].name + '</option>';
        });

        Object.entries(durations).forEach(function (value, index) {
            duration_options += '<option value="' + value[1] + '">' + value[1] + '</option>';
        });

        $("#edit_duration").html(duration_options);

        $("#edit_parent_service").html(services_options);

        if (radios != '') {
            $(".tax-radios").html(radios);
        }

        $(".tax-radios").find("input").each(function () {
            if ($(this).val() == select_tax_treatment_type) {
                $(this).prop("checked", true);
            }
        });

        $("#edit_parent_service").val(service.parent_id);
        $("#edit_service_name").val(service.name);
        $("#edit_duration").val(service.duration);
        $("#edit_color").val(service.color);
        $("#edit_price").val(service.price);

        // Set Trix editor content
        $("#edit_description").val(service.description || '');
        let trixEditor = document.querySelector("trix-editor[input='edit_description']");
        if (trixEditor && trixEditor.editor) {
            trixEditor.editor.loadHTML(service.description || '');
        }

        if (service.end_node == 1) {
            $("#edit_end_node").prop("checked", true);
        } else {
            $("#edit_end_node").prop("checked", false);
        }

        if (service.complimentory == 1) {
            $("#edit_complimentory").prop("checked", true);
        } else {
            $("#edit_complimentory").prop("checked", false);
        }

    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: $("#search_name").val(),
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
            name: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    let status = filter_values.status;

    let status_options = '<option value="">All</option>';

    Object.entries(status).forEach(function(value, index) {
        status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });


    $("#search_status").html(status_options);

    $("#search_name").val(active_filters.name);
    $("#search_fdo_name").val(active_filters.fdo_name);
    $("#search_fdo_phone").val(active_filters.fdo_phone);
    $("#search_address").val(active_filters.address);
    $("#search_created_from").val(active_filters.created_from);
    $("#search_created_to").val(active_filters.created_to);

    $("#search_status").val(active_filters.status);
    $("#search_city").val(active_filters.city_id);
    $("#service_region").val(active_filters.service_id);
}

function createService($route) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setCreateData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });
}

function setCreateData(response) {

    try {

        let services = response.data.parent_services;
        let durations = response.data.durations;
        let tax_treatment_types = response.data.tax_treatment_types;
        let select_tax_treatment_type = response.data.select_tax_treatment_type;
        let services_options = '<option value="0">Parent Service</option>';
        let duration_options = '<option value="">Select a Duration</option>';
        let radios = '';

        Object.entries(tax_treatment_types).forEach(function (value, index) {
            if (typeof value[1].id !== 'undefined') {
                radios += '<label class="radio">\
            <input type="radio" name="tax_treatment_type_id" value="' + value[1].id + '">\
            <span></span>\
        ' + value[1].name + '\
        </label>';
            }
        });

        Object.entries(services).forEach(function (value, index) {
            services_options += '<option value="' + value[1].id + '">' + value[1].name + '</option>';
        });

        Object.entries(durations).forEach(function (value, index) {
            duration_options += '<option value="' + value[1] + '">' + value[1] + '</option>';
        });

        $("#add_duration").html(duration_options);

        $("#add_parent_service").html(services_options);

        if (radios != '') {
            $(".tax-radios").html(radios);
        }

        $(".tax-radios").find("input").each(function () {
            if ($(this).val() == select_tax_treatment_type) {
                $(this).prop("checked", true);
            }
        });

        // Reset form fields to default state
        $("#add_parent_service").val(0);
        $("#add_service_name").val('');
        $("#add_duration").val('');
        $("#service_color").val('#000000');
        $("input[name='price']").val('');
        $("#add_description").val('');
        // Reset Trix editor content
        let trixEditor = document.querySelector("trix-editor[input='add_description']");
        if (trixEditor && trixEditor.editor) {
            trixEditor.editor.loadHTML('');
        }
        $("#endnode").prop("checked", false);
        $("input[name='complimentory']").prop("checked", false);
        
        // Hide child service fields (show only parent service fields by default)
        $('.servicefield').hide();

    } catch (error) {
        showException(error);
    }
}

// Custom status function for services - shows warning when deactivating parent with children
function serviceStatuses(data, status_url) {
    let id = data.id;
    let active = data.active;
    let isParent = data.parent_id == 0;
    let status = '';

    if (active) {
        if (permissions.active || permissions.inactive) {
            status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateServiceStatus(`'+ status_url + '`, `' + id + '`, $(this), ' + isParent + ');" type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
        } else {
            status += '<span class="switch switch-icon">\
            <label>\
                <input disabled type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
        }
    } else {
        status += '<span class="switch switch-icon">\
        <label>\
            <input value="1" onchange="updateServiceStatus(`'+ status_url + '`, `' + id + '`, $(this), ' + isParent + ');" type="checkbox" name="select">\
            <span></span>\
        </label>\
        </span>';
    }

    return status;
}

function updateServiceStatus(route, id, $this, isParent) {
    let isDeactivating = !$this.is(":checked");
    let title = 'Are you sure you want to change?';
    let text = '';
    let icon = 'info';

    // Show warning when deactivating or activating a parent service
    if (isParent) {
        if (isDeactivating) {
            title = 'Deactivate Parent Service?';
            text = 'All child services under this parent will also be deactivated.';
            icon = 'warning';
        } else {
            title = 'Activate Parent Service?';
            text = 'All child services under this parent will also be activated.';
            icon = 'info';
        }
    }

    swal.fire({
        title: title,
        text: text,
        type: 'danger',
        icon: icon,
        buttonsStyling: false,
        confirmButtonText: 'Yes, change!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {
        if (result.value) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route,
                data: { id: id, status: $this.is(":checked") ? '1' : '0' },
                type: "POST",
                cache: false,
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                        // Reload datatable to reflect child status changes
                        if (isParent) {
                            datatable.reload();
                        }
                    } else {
                        toastr.error(response.message);
                        // Revert checkbox state
                        if ($this.is(":checked")) {
                            $this.prop("checked", false);
                        } else {
                            $this.prop("checked", true);
                        }
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                    // Revert checkbox state on error
                    if ($this.is(":checked")) {
                        $this.prop("checked", false);
                    } else {
                        $this.prop("checked", true);
                    }
                }
            });
        } else {
            // User cancelled - revert checkbox state
            if ($this.is(":checked")) {
                $this.prop("checked", false);
            } else {
                $this.prop("checked", true);
            }
        }
    });
}

function showInstructions(serviceId, serviceName) {
    // Update modal title with service name
    $("#modal_service_instructions .modal-header h2").text("Service Instructions - " + serviceName);
    $("#modal_service_instructions").modal("show");
    $("#service_instructions_content").html('<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.services.show', {id: serviceId}),
        type: "GET",
        cache: false,
        dataType: 'json',
        success: function (response) {
            if (response.status && response.data && response.data.description) {
                $("#service_instructions_content").html(response.data.description);
            } else {
                $("#service_instructions_content").html('<div class="alert alert-info">No instructions available for this service.</div>');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            $("#service_instructions_content").html('<div class="alert alert-danger">Failed to load instructions.</div>');
            errorMessage(xhr);
        }
    });
}
