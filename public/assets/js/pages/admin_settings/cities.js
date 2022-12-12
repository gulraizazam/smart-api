
var table_url = route('admin.cities.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
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
        field: 'is_featured',
        title: 'Featured',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'region_id',
        title: 'Region',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'status',
        title: 'status',
        width: 80,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.cities.status');
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
    let url = route('admin.cities.edit', {id: id});
    let delete_url = route('admin.cities.destroy', {id: id});

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
                        <a href="javascript:void(0);" onclick="editRow(`'+url+'`);" class="navi-link">\
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
            $("#modal_edit_cities").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(TownValidation);
        }
    });


}

function setEditData(response) {
    let city = response.data;
    let action = route('admin.cities.update', {id: city.id});
    $("#modal_edit_cities_form").attr("action", action);
    $("#edit_cities_name").val(city.name);
    $("#edit_cities_region_id").val(city.region_id).trigger('change');
    $("#edit_cities_is_featured").val(city.is_featured).trigger('change');
}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            is_featured: $("#search_is_featured").val(),
            region_id: $("#search_region_id").val(),
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
            is_featured: '',
            region_id: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
    let status = filter_values.status;
    let is_featured = filter_values.is_featured;
    let regions = filter_values.regions;
    let status_options = '<option value="">All</option>';
    Object.entries(status).forEach(function(value, index) {
        status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    //let regions_options = '<option value="">Select Region</option>';
    let regions_options = '<option value="">All</option>';
    Object.entries(regions).forEach(function(value, index) {
        regions_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    let is_featured_options = '<option value="">All</option>';
    Object.entries(is_featured).forEach(function(value, index) {
        is_featured_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
    });

    $("#search_status").html(status_options);
    $("#search_region_id").html(regions_options);
    $("#search_is_featured").html(is_featured_options);

    $("#add_cities_region").html(regions_options);
    $("#add_cities_is_featured").html(is_featured_options);

    $("#edit_cities_region_id").html(regions_options);
    $("#edit_cities_is_featured").html(is_featured_options);

    $("#search_name").val(active_filters.name);
    $("#search_status").val(active_filters.status);
    $("#search_region_id").val(active_filters.region_id);
    $("#search_is_featured").val(active_filters.is_featured);

}
