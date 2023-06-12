
var table_url = route('admin.services.datatable');
let changePages = 1000;
var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 30,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 230,
        template: function (data) {
            if (data.parent_id == 0) {
                return '<b class="text text-dark" style="font-size: 12px;">'+data.name+'</b>';
            }
            return '<span class="ml-3">'+data.name+'</span>';
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
    },{
        field: 'complimentory',
        title: 'Complimentory',
        sortable: false,
        width: 120,
        template: function (data) {
            if (data.parent_id == 0) {
                return '-';
            }
            if (typeof data.complimentory !== 'undefined') {
                let status = data.complimentory == 1 ? 'Yes' : 'No';
                return '<span>'+status+'</span>';
            }
            return 'No';
        }
    }, {
        field: 'status',
        title: 'status',
        width: 60,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.services.status');
            return statuses(data, status_url);
        }
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

        let url = route('admin.services.edit', {id: id});
        let delete_url = route('admin.services.destroy', {id: id});

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
                    <a href="javascript:void(0);" onclick="editRow(`' + url + '`);" class="navi-link">\
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

            actions += '</ul>\
        </div>\
    </div>';

            return actions;
        }
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

    } catch (error) {
        showException(error);
    }
}
