
var table_url = route('admin.towns.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: renderCheckbox(),
        template: function (data) {
            let id = data.id;
            return childCheckbox(data);
        }
    },
     {
        field: 'name',
        title: 'Name',
        width: 'auto',
    },{
        field: 'city.name',
        title: 'City',
        width: 'auto',
        sortable: false,
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.towns.status');
            return statuses(data, status_url);
        }
    }, {
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

        let id = data.id;

        let url = route('admin.towns.edit', {id: id});
        let delete_url = route('admin.towns.destroy', {id: id});

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

    function createTown($route) {

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
            reInitValidation(Validation);
        }
    });
    }

    function setCreateData(response) {

        let cities = response.data.cities;
        let cities_options = '<option value="">Select a City</option>';

        Object.entries(cities).forEach(function(value, index) {
            cities_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        $("#add_town_city_id").html(cities_options);

}

    function editRow(url) {

        $("#modal_edit_towns").modal("show");

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: "GET",
            cache: false,
            success: function (response) {

                setEditData(response);

                reInitSelect2(".select2", "");

            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);

                reInitValidation(TownValidation);
            }
        });


    }

    function setEditData(response) {


        let town = response.data.town;
        let cities = response.data.cities;

        let action = route('admin.towns.update', {id: town.id});
        $("#modal_edit_towns_form").attr("action", action);

        let options = '<option value="">Select</option>';

        Object.entries(cities).forEach(function(value, index) {

            options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        $("#edit_town_city_id").html(options);

        $("#edit_town_name").val(town.name);
        $("#edit_town_city_id").val(town.city_id);

    }

    function applyFilters(datatable) {

        $('#apply-filters').on('click', function() {

            let filters =  {
                delete: '',
                name: $("#search_name").val(),
                city_id: $("#search_city").val(),
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
                city_id: '',
                status: '',
                filter: 'filter_cancel',
            }
            datatable.search(filters, 'search');
        });

    }

    function setFilters(filter_values, active_filters) {

        let cities = filter_values.cities;
        let status = filter_values.status;
        let city_options = '';
        let status_options = '<option value="">All</option>';

        Object.entries(status).forEach(function(value, index) {
            status_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(cities).forEach(function(value, index) {

            city_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        $("#search_city").html(city_options);

        $("#search_status").html(status_options);

        $("#search_name").val(active_filters.name);

        $("#search_status").val(active_filters.status);
        $("#search_city").val(active_filters.city_id);
    }
