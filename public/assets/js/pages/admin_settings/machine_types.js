var table_url = route('admin.machine_types.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 30,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    },
    {
        field: 'name',
        title: 'Name',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'services',
        sortable: false,
        width: 450,
        title: 'Services',
        template: function (data) {
            let badge='';
            data.services.forEach(function (value){
                badge=badge + '<span class="badge badge-primary mr-2 mb-2">'+  value.name + '</span>' ;
            });
            return badge;
        }
    },
    {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        sortable: false,
    },

    {
        field: 'status',
        title: 'Status',
        width: 60,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.machine_types.status');
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

    let id = data.id;

    let csrf = $('meta[name="csrf-token"]').attr('content');
    let url = route('admin.machine_types.edit', {id: id});
    let delete_url = route('admin.machine_types.destroy', {id: id});

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
    return '';
}

function editRow(url) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#modal_edit_machine_types").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(TownValidation);
        }
    });
}

function setEditData(response) {

    let machine_type = response.data.machine_type;
    let services = response.data.services;
    let service_machine_type = response.data.service_machine_type;
    let action = route('admin.machine_types.update', {id: machine_type.id});

    $("#modal_edit_machine_types_form").attr("action", action);
    $("#edit_machine_types_name").val(machine_type.name);
    let service_options = '';
    let service_value;
    Object.entries(services).forEach(function (value, index) {

        if(value[1].parent_id == 0){
            service_value=value[1].name;
        }
        else{
            service_value='\t&nbsp; \t&nbsp; \t&nbsp;'+value[1].name;
        }

        service_options += '<option value="' + (value[1].id) + '">' + service_value + '</option>';
    });
    $("#edit_machine_types_services").html(service_options);
    $("#edit_machine_types_services").val(service_machine_type).change();

    setServiceScroll();

}

function setServiceScroll() {

    setTimeout( function () {
        let elem = $("#modal_edit_machine_types_form").find(".selection").find("ul");
        height = elem.height();
        if (height > 28.57) {
            elem.css("height", "150px");
            elem.css("overflow-y", "scroll");
        }
    }, 400);

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            name: $("#search_name").val(),
            service: $("#search_service").val(),
            created_at: $("#date_range").val(),
            status: $("#search_status").val(),
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
            service: '',
            created_at: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {

    let status = filter_values.status;
    let status_options = '<option value="">All</option>';
    let services = filter_values.services;
    let services_options = '<option value="">All Services</option>';

    Object.entries(status).forEach(function (value, index) {
        status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });

    let service_value = '';

    Object.entries(services).forEach(function (value, index) {
        if(value[1].parent_id == 0){
            service_value=value[1].name;
        }
        else{
            service_value='\t&nbsp; \t&nbsp; \t&nbsp;'+value[1].name;
        }
        if (service_value == 'All Services') {
            services_options += '<option value="">' + service_value + '</option>';
        } else {
            services_options += '<option value="' + value[1].id + '">' + service_value + '</option>';
        }
    });

    $("#search_status").html(status_options);
    $("#search_service").html(services_options);
    $("#add_machine_types_services").html(services_options);

    $("#search_name").val(active_filters.name);
    $("#search_status").val(active_filters.status);
    $("#date_range").val(active_filters.created_at);
    if (active_filters.service) {
        $("#search_service").val(active_filters.service).change();
    }

}
jQuery(document).ready( function () {
    $("#date_range").val("");
})
