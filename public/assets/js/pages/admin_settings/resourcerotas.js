//To manage the functionality of Week days
$(document).ready(function () {

    $('#mondayElement_1').on('change', function () {
        if ($('#mondayElement_1').is(':unchecked')) {
            $('#mondayOperation_1 :input').attr('disabled', true);
            $('.mondaytime_1').val('', '');
            $('.monday_breake_time').val('', '');
        } else {
            $('#mondayOperation_1 :input').removeAttr('disabled');
            $('.mondaytime_1').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());
            $('.monday_breake_time').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null);
        }
    });
    $('#tuesdayElement_1').on('change', function () {
        if ($('#tuesdayElement_1').is(':unchecked')) {
            $('#tuesdayOperation_1 :input').attr('disabled', true);
            $('.tuesdaytime_1').val('', '');
            $('.tuesdaytime_break').val('', '');
        } else {
            $('#tuesdayOperation_1 :input').removeAttr('disabled');
            $('.tuesdaytime_1').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());
            $('.tuesdaytime_break').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null);
        }
    });
    $('#wednesdayElement_1').on('change', function () {
        if ($('#wednesdayElement_1').is(':unchecked')) {
            $('#wednesdayOperation_1 :input').attr('disabled', true);
            $('.wednesdaytime_1').val('', '');
            $('.wednesdaytime_break').val('', '');
        } else {
            $('#wednesdayOperation_1 :input').removeAttr('disabled');
            $('.wednesdaytime_1').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());
            $('.wednesdaytime_break').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null);
        }
    });
    $('#thursdayElement_1').on('change', function () {
        if ($('#thursdayElement_1').is(':unchecked')) {
            $('#thursdayOperation_1 :input').attr('disabled', true);
            $('.thursdaytime_1').val('', '');
            $('.thursdaytime_break').val('', '');
        } else {
            $('#thursdayOperation_1 :input').removeAttr('disabled');
            $('.thursdaytime_1').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());
            $('.thursdaytime_break').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null);
        }
    });
    $('#fridayElement_1').on('change', function () {
        if ($('#fridayElement_1').is(':unchecked')) {
            $('#fridayOperation_1 :input').attr('disabled', true);
            $('.fridaytime_1').val('', '');
            $('.fridaytime_break').val('', '');
        } else {
            $('#fridayOperation_1 :input').removeAttr('disabled');
            $('.fridaytime_1').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());
            $('.fridaytime_break').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null);
        }
    });
    $('#saturdayElement_1').on('change', function () {
        if ($('#saturdayElement_1').is(':unchecked')) {
            $('#saturdayOperation_1 :input').attr('disabled', true);
            $('.saturdaytime_1').val('', '');
            $('.saturdaytime_break').val('', '');
        } else {
            $('#saturdayOperation_1 :input').removeAttr('disabled');
            $('.saturdaytime_1').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());
            $('.saturdaytime_break').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null);
        }
    });
    $('#sundayElement_1').on('change', function () {
        if ($('#sundayElement_1').is(':unchecked')) {
            $('#sundayOperation_1 :input').attr('disabled', true);
            $('.sundaytime_1').val('', '');
            $('.sundaytime_break').val('', '');
        } else {
            $('#sundayOperation_1 :input').removeAttr('disabled');
            $('.sundaytime_1').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());
            $('.sundaytime_break').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null);
        }
    });
    $('#copy_all_1').change(function () {

        if ($(this).is(":checked")) {
            $('#copy_all_1').val('1');

            $('#mondayElement_1').prop('checked', true);

            $('#tuesdayOperation_1 :input').attr('disabled', true);
            $('#tuesdayElement_1').prop('checked', true);

            $('#wednesdayOperation_1 :input').attr('disabled', true);
            $('#wednesdayElement_1').prop('checked', true);

            $('#thursdayOperation_1 :input').attr('disabled', true);
            $('#thursdayElement_1').prop('checked', true);

            $('#fridayOperation_1 :input').attr('disabled', true);
            $('#fridayElement_1').prop('checked', true);

            $('#saturdayOperation_1 :input').attr('disabled', true);
            $('#saturdayElement_1').prop('checked', true);

            $('#sundayOperation_1 :input').attr('disabled', true);
            $('#sundayElement_1').prop('checked', true);

            $('.check_final_1').hide();

            /*get the monday break timing*/
            var frombreakvalue = $('.break_mondayfrom').val();
            var tobreakValue = $('.break_mondayto').val();

            /*set the monday break timing in all days*/
            $(".f_time_break").val(frombreakvalue);
            $(".t_time_break").val(tobreakValue);

            /*get the monday form and to value*/
            var fromValue = $('.mondayfrom_1').val();
            var toValue = $('.mondayto_1').val();

            //set the monday to and from value to all other days
            $(".ftime_1").val(fromValue);
            $(".ttime_1").val(toValue);
        }
        else {
            $('.check_final_1').show();
            $('#copy_all_1').val('0');
            $('#mondayOperation_1 :input').attr('disabled', false);
            $('#tuesdayOperation_1 :input').attr('disabled', false);
            $('#wednesdayOperation_1 :input').attr('disabled', false);
            $('#thursdayOperation_1 :input').attr('disabled', false);
            $('#fridayOperation_1 :input').attr('disabled', false);
            $('#saturdayOperation_1 :input').attr('disabled', false);
            $('#sundayOperation_1 :input').attr('disabled', false);
            $('.check_final_1').show();
            $(".ftime_1").timepicker('setTime', new Date());
            $(".ttime_1").timepicker('setTime', new Date());
            $(".f_time_break").timepicker('setTime', null);
            $(".t_time_break").timepicker('setTime', null);
        }
    });
});
/*End*/

//Date picker initilize function
$(document).ready(function () {
    var date = new Date();
    date.setDate(date.getDate());
    $('.date_to_rota_1').datepicker({
        format: 'yyyy-mm-dd',
        startDate: date
    }).on('changeDate', function (ev) {
        $(this).datepicker('hide');
    });

    $('.time_to_Rota_1').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());

    $('.breaktime').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null);

    $('.monday_breake_time').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", null).on('change', function(){

        if ($('#copy_all_1').is(":checked")) {
            $('#copy_all_1').trigger('change');
        }
        return true;
    });

    $('#monday_from').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date()).on('change', function(){

        if ($('#copy_all_1').is(":checked")) {
            $('#copy_all_1').trigger('change');
        }
        return true;
    });
    $('#monday_to').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date()).on('change', function(){

        if ($('#copy_all_1').is(":checked")) {
            $('#copy_all_1').trigger('change');
        }
        return true;
    });
});
/*End*/


var table_url = route('admin.resourcerotas.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 'auto',
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'name',
        title: 'Resource Name',
        sortable: false,
        width: 300,
    },{
        field: 'type',
        title: 'Type',
        sortable: false,
        width: 'auto',
    },{
        field: 'region',
        title: 'Regions',
        sortable: false,
        width: 'auto',
        /*template: function (data) {
            let cityName = '';
            if (typeof data.location.city !== 'undefined') {
                cityName = data.location.city.name + '-';
            }
           return cityName + data.location.name;
        }*/
    },{
        field: 'city',
        title: 'City',
        sortable: false,
        width: 'auto',
    },{
        field: 'location',
        title: 'Centre',
        sortable: false,
        width: 'auto',
    },{
        field: 'from',
        title: 'From',
        sortable: false,
        width: 'auto',
    },{
        field: 'to',
        title: 'To',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    },{
        field: 'status',
        title: 'Status',
        width: 'auto',
        template: function (data) {
            return statuses(data, route('admin.resourcerotas.status'));
        }
    }];

function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let url = route('admin.resourcerotas.edit', {id: id});
        let delete_url = route('admin.resourcerotas.destroy', {id: id});

        if (permissions.edit && permissions.delete) {
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

            if (permissions.edit) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">Calendar</span>\
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

function createRota($route) {

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

        let cities = response.data.cities;
        let resource_types = response.data.resource_types;

        let cities_options = '<option value="">Select</option>';
        let resource_options = '<option value="">Select</option>';

        Object.entries(cities).forEach(function (value, index) {
            cities_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.values(resource_types).forEach(function (value, index) {
            resource_options += '<option value="' + value.id + '">' + value.name + '</option>';
        });

        $("#city_id").html(cities_options);
        $("#resource_type_id").html(resource_options);

    } catch (error) {
        showException(error);
    }
}

function getLocations($this) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.resourcerotas.load_location'),
        type: "GET",
        data: { city_id: $this.val() },
        cache: false,
        success: function (response) {

            setLocations(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });
}

function setLocations(response) {

    try {

        let locations = response.data.locations;

        let location_options = '<option>Select a Centre</option>';

        Object.values(locations).forEach(function (value) {
            location_options += '<option value="' + value.id + '">' + value.name + '</option>';
        });

        $("#location_id").html(location_options);

    } catch (error) {
        showException(error);
    }
}

function getResource($this) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.resourcerotas.load_doctor_and_Machine'),
        type: "GET",
        data: { location_id: $this.val() },
        cache: false,
        success: function (response) {

            setResources(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });

}

function setResources(response) {

    try {

        let doctors = response.data.doctors;
        let machine = response.data.machine;

        let doctors_options = '<option>Select a Doctor</option>';
        let machine_options = '<option>Select a Machine</option>';

        Object.entries(doctors).forEach(function (value) {
            doctors_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.values(machine).forEach(function (value) {
            machine_options += '<option value="' + value.id + '">' + value.name + '</option>';
        });

        $("#machine_id").html(machine_options);
        $("#doctor_id").html(doctors_options);

    }  catch (error) {
        showException(error);
    }
}

function toggleResource($this) {

    $(".resource_fields").addClass("d-none");
    if ($this.val() == '1') {
        $("#machine_field").removeClass("d-none");
    } else {
        $("#doctor_field").removeClass("d-none");
    }
}

function editRow(url) {

    $("#modal_edit_resources").modal("show");

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

            reInitValidation(EditValidation);
        }
    });


}

function setEditData(response) {

    try {

        let resource = response.data.resource;
        let machine_types = response.data.machine_types;
        let resource_types = response.data.resource_types;
        let locations = response.data.locations;

        $("#modal_edit_resources_form").attr("action", route('admin.resources.update', {id: resource.id}));


        let machine_options = '<option value="">Select</option>';
        let location_options = '<option value="">Select</option>';
        let resource_options = '<option value="">Select</option>';

        Object.entries(machine_types).forEach(function (value, index) {
            machine_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value, index) {
            location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(resource_types).forEach(function (value, index) {
            resource_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        $("#edit_machine_type_id").html(machine_options);
        $("#edit_location_id").html(location_options);
        $("#edit_resource_type_id").html(resource_options);

        $("#edit_name").val(resource.name);
        $("#edit_location_id").val(resource.location_id);
        $("#edit_machine_type_id").val(resource.machine_type_id);
        $("#edit_resource_type_id").val(resource.resource_type_id);

    } catch (error) {
        showException(error);
    }

}


function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            resourcename: $("#search_resource_name").val(),
            resource_type_id: $("#search_type_id").val(),
            region_id: $("#search_region_id").val(),
            city_id: $("#search_city_id").val(),
            location_id: $("#search_location_id").val(),
            startdate: $("#search_from").val(),
            enddate: $("#search_to").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
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
            resourcename: '',
            resource_type_id: '',
            region_id: '',
            city_id: '',
            location_id: '',
            startdate: '',
            enddate: '',
            created_from: '',
            created_to: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let resource_types = filter_values.resource_type;
        let cities = filter_values.city;
        let regions = filter_values.regions;
        let locations = filter_values.location;
        let status = filter_values.status;

        let status_option = "<option value=''>All</option>";
        let type_options = "<option value=''>All</option>";
        let cities_options = "<option value=''>All</option>";
        let regions_options = "<option value=''>Select a Region</option>";
        let locations_options = "<option value=''>All</option>";

        Object.entries(resource_types).forEach( function (value) {
            type_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(cities).forEach( function (value) {
            cities_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(regions).forEach( function (value) {
            regions_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(locations).forEach( function (value) {
            locations_options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        Object.entries(status).forEach( function (value) {
            status_option += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });

        $("#search_type_id").html(type_options);
        $("#search_region_id").html(regions_options);
        $("#search_city_id").html(cities_options);
        $("#search_location_id").html(locations_options);
        $("#search_status").html(status_option);

        $("#search_resource_name").html(active_filters.resourcename);
        $("#search_from").val(active_filters.startdate);
        $("#search_to").val(active_filters.enddate);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);
        $("#search_status").val(active_filters.status).change();

        $("#search_type_id").val(active_filters.resource_type_id);
        $("#search_region_id").val(active_filters.region_id);
        $("#search_city_id").val(active_filters.city_id);
        $("#search_location_id").val(active_filters.location_id);

        hideShowAdvanceFilters(active_filters);

    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.location_id !== 'undefined' && active_filters.location_id != '')
        || (typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.startdate !== 'undefined' && active_filters.startdate != '')
        || (typeof active_filters.enddate !== 'undefined' && active_filters.enddate != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}

