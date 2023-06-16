//To manage the functionality of Week days
$(document).ready(function () {
    $('#mondayElement_1').on('change', function () {
        if ($('#mondayElement_1').is(':checked') == true) {
            $('.mondaytime_1').attr('disabled', false);
            $('.monday_breake_time').attr('disabled', false);
            $('.mondaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.monday_breake_time').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.mondaytime_1').attr('disabled', true);
            $('.monday_breake_time').attr('disabled', true);
            $('.mondaytime_1').val('', '');
            $('.monday_breake_time').val('', '');
        }
    });

    $('#edit_mondayElement_1').on('change', function () {
        if ($('#edit_mondayElement_1').is(':checked')) {
            $('.edit_mondaytime_1').attr('disabled', false);
            $('.edit_monday_breake_time').attr('disabled', false);
            $('.edit_mondaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.edit_monday_breake_time').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.edit_mondaytime_1').attr('disabled', true);
            $('.edit_monday_breake_time').attr('disabled', true);
            $('.edit_mondaytime_1').val('', '');
            $('.edit_monday_breake_time').val('', '');
        }
    });


    $('#tuesdayElement_1').on('change', function () {
        if ($('#tuesdayElement_1').is(':checked')) {
            $('.tuesdaytime_1').attr('disabled', false);
            $('.tuesdaytime_break').attr('disabled', false);
            $('.tuesdaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.tuesdaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.tuesdaytime_1').attr('disabled', true);
            $('.tuesdaytime_break').attr('disabled', true);
            $('.tuesdaytime_1').val('', '');
            $('.tuesdaytime_break').val('', '');
        }
    });

    $('#edit_tuesdayElement_1').on('change', function () {
        if ($('#edit_tuesdayElement_1').is(':checked')) {
            $('.edit_tuesdaytime_1').attr('disabled', false);
            $('.edit_tuesdaytime_break').attr('disabled', false);
            $('.edit_tuesdaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.edit_tuesdaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.edit_tuesdaytime_1').attr('disabled', true);
            $('.edit_tuesdaytime_break').attr('disabled', true);
            $('.edit_tuesdaytime_1').val('', '');
            $('.edit_tuesdaytime_break').val('', '');
        }
    });

    $('#wednesdayElement_1').on('change', function () {
        if ($('#wednesdayElement_1').is(':checked')) {
            $('.wednesdaytime_1').attr('disabled', false);
            $('.wednesdaytime_break').attr('disabled', false);
            $('.wednesdaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.wednesdaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.wednesdaytime_1').attr('disabled', true);
            $('.wednesdaytime_break').attr('disabled', true);
            $('.wednesdaytime_1').val('', '');
            $('.wednesdaytime_break').val('', '');

        }
    });

    $('#edit_wednesdayElement_1').on('change', function () {
        if ($('#edit_wednesdayElement_1').is(':checked')) {
            $('.edit_wednesdaytime_1').attr('disabled', false);
            $('.edit_wednesdaytime_break').attr('disabled', false);
            $('.edit_wednesdaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.edit_wednesdaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.edit_wednesdaytime_1').attr('disabled', true);
            $('.edit_wednesdaytime_break').attr('disabled', true);
            $('.edit_wednesdaytime_1').val('', '');
            $('.edit_wednesdaytime_break').val('', '');
        }
    });


    $('#thursdayElement_1').on('change', function () {
        if ($('#thursdayElement_1').is(':checked')) {
            $('.thursdaytime_1').attr('disabled', false);
            $('.thursdaytime_break').attr('disabled', false);
            $('.thursdaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.thursdaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);

        } else {
            $('.thursdaytime_1').attr('disabled', true);
            $('.thursdaytime_break').attr('disabled', true);
            $('.thursdaytime_1').val('', '');
            $('.thursdaytime_break').val('', '');
        }
    });

    $('#edit_thursdayElement_1').on('change', function () {
        if ($('#edit_thursdayElement_1').is(':checked')) {
            $('.edit_thursdaytime_1').attr('disabled', false);
            $('.edit_thursdaytime_break').attr('disabled', false);
            $('.edit_thursdaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.edit_thursdaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.edit_thursdaytime_1').attr('disabled', true);
            $('.edit_thursdaytime_break').attr('disabled', true);
            $('.edit_thursdaytime_1').val('', '');
            $('.edit_thursdaytime_break').val('', '');
        }
    });

    $('#fridayElement_1').on('change', function () {
        if ($('#fridayElement_1').is(':checked')) {
            $('.fridaytime_1').attr('disabled', false);
            $('.fridaytime_break').attr('disabled', false);
            $('.fridaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.fridaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.fridaytime_1').attr('disabled', true);
            $('.fridaytime_break').attr('disabled', true);
            $('.fridaytime_1').val('', '');
            $('.fridaytime_break').val('', '');
        }
    });

    $('#edit_fridayElement_1').on('change', function () {
        if ($('#edit_fridayElement_1').is(':checked')) {
            $('.edit_fridaytime_1').attr('disabled', false);
            $('.edit_fridaytime_break').attr('disabled', false);
            $('.edit_fridaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.edit_fridaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.edit_fridaytime_1').attr('disabled', true);
            $('.edit_fridaytime_break').attr('disabled', true);
            $('.edit_fridaytime_1').val('', '');
            $('.edit_fridaytime_break').val('', '');
        }
    });

    $('#saturdayElement_1').on('change', function () {
        if ($('#saturdayElement_1').is(':checked')) {
            $('.saturdaytime_1').attr('disabled', false);
            $('.saturdaytime_break').attr('disabled', false);
            $('.saturdaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.saturdaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.saturdaytime_1').attr('disabled', true);
            $('.saturdaytime_break').attr('disabled', true);
            $('.saturdaytime_1').val('', '');
            $('.saturdaytime_break').val('', '');
        }
    });

    $('#edit_saturdayElement_1').on('change', function () {
        if ($('#edit_saturdayElement_1').is(':checked')) {
            $('.edit_saturdaytime_1').attr('disabled', false);
            $('.edit_saturdaytime_break').attr('disabled', false);
            $('.edit_saturdaytime_1').val('', '');
            $('.edit_saturdaytime_break').val('', '');
        } else {
            $('.edit_saturdaytime_1').attr('disabled', true);
            $('.edit_saturdaytime_break').attr('disabled', true);
            $('.edit_saturdaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.edit_saturdaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        }
    });

    $('#sundayElement_1').on('change', function () {
        if ($('#sundayElement_1').is(':checked')) {
            $('.sundaytime_1').attr('disabled', false);
            $('.sundaytime_break').attr('disabled', false);
            $('.sundaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.sundaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('.sundaytime_1').attr('disabled', true);
            $('.sundaytime_break').attr('disabled', true);
            $('.sundaytime_1').val('', '');
            $('.sundaytime_break').val('', '');
        }
    });

    $('#edit_sundayElement_1').on('change', function () {
        if ($('#edit_sundayElement_1').is(':checked')) {
            $('.edit_sundaytime_1').attr('disabled', false);
            $('.edit_sundaytime_break').attr('disabled', false);
            $('.edit_sundaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
            $('.edit_sundaytime_break').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);
        } else {
            $('#edit_copy_all_1').prop('checked', false);
            $('.edit_sundaytime_1').attr('disabled', true);
            $('.edit_sundaytime_break').attr('disabled', true);
            $('.edit_sundaytime_1').val('', '');
            $('.edit_sundaytime_break').val('', '');
        }
    });

    /*for add*/
    $('#copy_all_1').change(function () {
        if ($(this).is(":checked") == true) {
            $('#copy_all_1').prop('checked', true);
            $('#copy_all_1').val('1');

            $('#mondayElement_1').prop('checked', true);
            $('#mondayElement_1').attr('disabled', true);

            $('#tuesdayElement_1').prop('checked', true);
            $('#tuesdayOperation_1').find("input").prop('disabled', true);

            $('#wednesdayElement_1').prop('checked', true);
            $('#wednesdayOperation_1').find("input").prop('disabled', true);

            $('#thursdayElement_1').prop('checked', true);
            $('#thursdayOperation_1').find("input").prop('disabled', true);

            $('#fridayElement_1').prop('checked', true);
            $('#fridayOperation_1').find("input").prop('disabled', true);

            $('#saturdayElement_1').prop('checked', true);
            $('#saturdayOperation_1').find("input").prop('disabled', true);

            $('#sundayElement_1').prop('checked', true);
            $('#sundayOperation_1').find("input").prop('disabled', true);

            /*get the monday break timing*/
            var frombreakvalue = $('#break_monday_from').val();
            var tobreakValue = $('#break_monday_to').val();

            /*set the monday break timing in all days*/
            $(".f_time_break").val(frombreakvalue);
            $(".t_time_break").val(tobreakValue);

            if ($('#monday_from').val() == '' && $('#monday_to').val() == '') {
                /*get the monday form and to value*/
                $('.mondaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
                var fromValue = $('#monday_from').val();
                var toValue = $('#monday_to').val();
            } else {
                /*get the monday form and to value*/
                var fromValue = $('#monday_from').val();
                var toValue = $('#monday_to').val();
            }
            //set the monday to and from value to all other days
            $(".ftime_1").val(fromValue);
            $(".ttime_1").val(toValue);

        } else {
            $('#copy_all_1').prop('checked', false);
            $('#copy_all_1').val('0');

            $('#mondayElement_1').prop('checked', false);
            $('#mondayElement_1').attr('disabled', false);

            $('#tuesdayElement_1').prop('checked', false);
            $('#tuesdayOperation_1').find("input").prop('disabled', false);

            $('#wednesdayElement_1').prop('checked', false);
            $('#wednesdayOperation_1').find("input").prop('disabled', false);

            $('#thursdayElement_1').prop('checked', false);
            $('#thursdayOperation_1').find("input").prop('disabled', false);

            $('#fridayElement_1').prop('checked', false);
            $('#fridayOperation_1').find("input").prop('disabled', false);

            $('#saturdayElement_1').prop('checked', false);
            $('#saturdayOperation_1').find("input").prop('disabled', false);

            $('#sundayElement_1').prop('checked', false);
            $('#sundayOperation_1').find("input").prop('disabled', false);

            $('.mondaytime_1').val('', '');
            $('.monday_breake_time').val('', '');
            $(".ftime_1").timepicker('setTime', null);
            $(".ttime_1").timepicker('setTime', null);
            $(".f_time_break").timepicker('setTime', null);
            $(".t_time_break").timepicker('setTime', null);
        }
    });

    /*for edit*/
    $('#edit_copy_all_1').change(function () {
        if ($(this).is(":checked") == true) {
            $('#edit_copy_all_1').prop('checked', true);
            $('#edit_copy_all_1').val('1');

            $('#edit_mondayElement_1').prop('checked', true);
            $('#edit_mondayElement_1').attr('disabled', true);

            $('#edit_tuesdayElement_1').prop('checked', true);
            $('#edit_tuesdayOperation_1').find("input").prop('disabled', true);

            $('#edit_wednesdayElement_1').prop('checked', true);
            $('#edit_wednesdayOperation_1').find("input").prop('disabled', true);

            $('#edit_thursdayElement_1').prop('checked', true);
            $('#edit_thursdayOperation_1').find("input").prop('disabled', true);

            $('#edit_fridayElement_1').prop('checked', true);
            $('#edit_fridayOperation_1').find("input").prop('disabled', true);

            $('#edit_saturdayElement_1').prop('checked', true);
            $('#edit_saturdayOperation_1').find("input").prop('disabled', true);

            $('#edit_sundayElement_1').prop('checked', true);
            $('#edit_sundayOperation_1').find("input").prop('disabled', true);

            /*get the monday break timing*/
            var frombreakvalue = $('#edit_break_monday_from').val();
            var tobreakValue = $('#edit_break_monday_to').val();

            /*set the monday break timing in all days*/
            $(".f_time_break").val(frombreakvalue);
            $(".t_time_break").val(tobreakValue);

            if ($('#edit_monday_from').val() == '' && $('#edit_monday_to').val() == '') {
                /*get the monday form and to value*/
                $('.edit_mondaytime_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());
                var fromValue = $('#edit_monday_from').val();
                var toValue = $('#edit_monday_to').val();
            } else {
                /*get the monday form and to value*/
                var fromValue = $('#edit_monday_from').val();
                var toValue = $('#edit_monday_to').val();
            }
            //set the monday to and from value to all other days
            $(".ftime_1").val(fromValue);
            $(".ttime_1").val(toValue);
        } else {
            $('#edit_copy_all_1').prop('checked', false);
            $('#edit_copy_all_1').val('0');

            $('#edit_mondayElement_1').prop('checked', false);
            $('#edit_mondayElement_1').attr('disabled', false);

            $('#edit_tuesdayElement_1').prop('checked', false);
            $('#edit_tuesdayOperation_1').find("input").prop('disabled', false);

            $('#edit_wednesdayElement_1').prop('checked', false);
            $('#edit_wednesdayOperation_1').find("input").prop('disabled', false);

            $('#edit_thursdayElement_1').prop('checked', false);
            $('#edit_thursdayOperation_1').find("input").prop('disabled', false);

            $('#edit_fridayElement_1').prop('checked', false);
            $('#edit_fridayOperation_1').find("input").prop('disabled', false);

            $('#edit_saturdayElement_1').prop('checked', false);
            $('#edit_saturdayOperation_1').find("input").prop('disabled', false);

            $('#edit_sundayElement_1').prop('checked', false);
            $('#edit_sundayOperation_1').find("input").prop('disabled', false);

            $('.mondaytime_1').val('', '');
            $('.monday_breake_time').val('', '');
            $(".ftime_1").timepicker('setTime', null);
            $(".ttime_1").timepicker('setTime', null);
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

    $('.time_to_Rota_1').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());

    $('.breaktime').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null);

    $('.monday_breake_time').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null).on('change', function () {

        if ($('#copy_all_1').is(":checked")) {
            $('#copy_all_1').trigger('change');
        }
        return true;
    });


    $('.edit_monday_breake_time').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", null).on('change', function () {

        if ($('#edit_copy_all_1').is(":checked")) {
            $('#edit_copy_all_1').trigger('change');
        }
        return true;
    });

    $('#monday_from').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date()).on('change', function () {

        if ($('#copy_all_1').is(":checked")) {
            $('#copy_all_1').trigger('change');
        }
        return true;
    });

    $('#edit_monday_from').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date()).on('change', function () {

        if ($('#edit_copy_all_1').is(":checked")) {
            $('#edit_copy_all_1').trigger('change');
        }
        return true;
    });


    $('#monday_to').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date()).on('change', function () {

        if ($('#copy_all_1').is(":checked")) {
            $('#copy_all_1').trigger('change');
        }
        return true;
    });

    $('#edit_monday_to').timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date()).on('change', function () {

        if ($('#edit_copy_all_1').is(":checked")) {
            $('#edit_copy_all_1').trigger('change');
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
        width: 30,
        title: renderCheckbox(),
        template: function (data) {
            return childCheckbox(data);
        }
    }, {
        field: 'name',
        title: 'Resource Name',
        sortable: false,
        width: 130,
    }, {
        field: 'type',
        title: 'Type',
        sortable: false,
        width: 70,
    }, {
        field: 'location',
        title: 'Centre',
        sortable: false,
        width: 'auto',
    }, {
        field: 'city',
        title: 'City',
        sortable: false,
        width: 70,
    }, {
        field: 'from',
        title: 'From',
        sortable: false,
        width: 120,
    }, {
        field: 'to',
        title: 'To',
        sortable: false,
        width: 120,
    }, {
        field: 'active',
        title: 'Status',
        width: 70,
        template: function (data) {
            return statuses(data, route('admin.resourcerotas.status'));
        }
    }, {
        field: 'created_at',
        title: 'Created at',
        width: 140,
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 140,
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }, {
        field: 'region',
        title: 'Regions',
        sortable: false,
        width: 90,
    }];

function actions(data) {

    if (typeof data.id !== 'undefined') {
        let id = data.id;

        let url = route('admin.resourcerotas.edit', { id: id });
        let delete_url = route('admin.resourcerotas.destroy', { id: id });
        let calender_url = route('admin.resourcerotas.calender-view', { id: id });

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

            if (permissions.calender) {
                actions += '<li class="navi-item">\
                    <a href="'+ calender_url + '" class="navi-link">\
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
            setCreateEditData(response);

            $("#mondayElement_1").val('on');
            $("#tuesdayElement_1").val('on');
            $("#wednesdayElement_1").val('on');
            $("#thursdayElement_1").val('on');
            $("#fridayElement_1").val('on');
            $("#saturdayElement_1").val('on');
            $("#sundayElement_1").val('on');
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });
}

function setCreateData(response) {

    try {
        $(".rota-title").text("Add Rota");
        $(".common-section").addClass("d-none");
        $(".add-section").removeClass("d-none");

        let form = $("#modal_add_resourcerotas_form");

        form[0].reset();

        $(".doctor_section").addClass("d-none");
        $(".check_final_1").show();
        $("#is_consultancy_1").attr("checked", true);
        $("#is_treatment_1").attr("checked", true);

        setDefaultValues(form);

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

function setDefaultValues(form) {
    let data = new Date().toISOString().split('T')[0];
    $("#start").val(data)
    $("#end").val(data)

    $(".current-timepicker").timepicker({ timeFormat: 'h:mm:ss p' }).timepicker("setTime", new Date());

    form.find(".f_time_break").val('');
    form.find(".t_time_break").val('');
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

            if (response.status != false) {
                setLocations(response);
            } else {
                let location_options = '<option>Select a Centre</option>';
                $("#location_id").html(location_options);
            }

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
            if (response.status != false) {
                setResources(response);
            } else {
                let doctors_options = '<option value="">Select a Doctor</option>';
                let machine_options = '<option value="">Select a Machine</option>';
                $("#machine_id").html(machine_options);
                $("#doctor_id").html(doctors_options);
            }
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

        let doctors_options = '<option value="">Select a Doctor</option>';
        let machine_options = '<option value="">Select a Machine</option>';

        Object.entries(doctors).forEach(function (value) {
            doctors_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.values(machine).forEach(function (value) {
            machine_options += '<option value="' + value.id + '">' + value.name + '</option>';
        });

        $("#machine_id").html(machine_options);
        $("#doctor_id").html(doctors_options);

    } catch (error) {
        showException(error);
    }
}

function toggleResource($this) {

    $(".resource_fields").addClass("d-none");
    $(".doctor_section").addClass("d-none");
    if ($this.val() == '1') {
        $("#machine_field").removeClass("d-none");
        $("#Rota_type_operation").addClass("d-none");
    } else {
        $("#doctor_field").removeClass("d-none");
        $("#Rota_type_operation").removeClass("d-none");
    }
    $("#is_consultancy_name").val(0);
    $("#is_consultancy_1").val(1);

    $("#is_treatment_name").val(0);
    $("#is_treatment_1").val(1);
}

function editRow(url) {

    $("#modal_edit_resourcerotas").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {

            setEditData(response);
            setCreateEditData(response, true);

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

        $(".rota-title").text("Edit Rota");
        $(".common-section").addClass("d-none");
        $(".edit-section").removeClass("d-none");

        let city = response.data.city;
        let citi = response.data.citi;
        let location = response.data.location;
        let resourceRota = response.data.resourceRota;
        let resource_name = response.data.resource_name;

        $("#modal_edit_resourcerotas_form").attr("action", route('admin.resourcerotas.update', { id: resourceRota.id }));

        let fullCity = citi.region_id ? citi.region.name + ' - ' + city : city;

        $("#city-name").text(fullCity);
        $("#resource-name").text(resource_name.name);
        $("#centre-name").text(location);
        $("#rota-start-date").text(resourceRota.start);
        $("#edit_start").val(resourceRota.start);
        $("#edit_end").val(resourceRota.end);


        if (resourceRota.resource_type_id == 1) {
            $(".doctor_section").addClass("d-none");
        } else {
            $(".doctor_section").removeClass("d-none");
        }

        if (resourceRota.is_treatment == 1) {
            $("#edit_is_treatment_1").attr("checked", true);
        } else {
            $("#edit_is_treatment_1").attr("checked", false);
        }

        if (resourceRota.is_consultancy == 1) {
            $("#edit_is_consultancy_1").attr("checked", true);
        } else {
            $("#edit_is_consultancy_1").attr("checked", false);
        }

        /*Monday*/
        $("#edit_monday_from").val(resourceRota.time_f_monday);
        $("#edit_monday_to").val(resourceRota.time_to_monday);
        $("#edit_break_monday_from").val(resourceRota.break_from_monday);
        $("#edit_break_monday_to").val(resourceRota.break_to_monday);
        if (response.data.resourceRota.mondaychecked == "_off") {
            $('#edit_mondayElement_1').attr('checked', false);
        } else {
            $('#edit_mondayElement_1').attr('checked', true);
        }
        /*Tuesday*/
        $("#edit_time_f_tuesday").val(resourceRota.time_f_tuesday);
        $("#edit_time_to_tuesday").val(resourceRota.time_to_tuesday);
        $("#edit_break_from_tuesday").val(resourceRota.break_from_tuesday);
        $("#edit_break_to_tuesday").val(resourceRota.break_to_tuesday);
        if (response.data.resourceRota.tuesdaychecked == "_off") {
            $('#edit_tuesdayElement_1').attr('checked', false);
        } else {
            $('#edit_tuesdayElement_1').attr('checked', true);
        }
        /*Wednesday*/
        $("#edit_time_f_wednesday").val(resourceRota.time_f_wednesday);
        $("#edit_time_to_wednesday").val(resourceRota.time_to_wednesday);
        $("#edit_break_from_wednesday").val(resourceRota.break_from_wednesday);
        $("#edit_break_to_wednesday").val(resourceRota.break_to_wednesday);
        if (response.data.resourceRota.wednesdaychecked == "_off") {
            $('#edit_wednesdayElement_1').attr('checked', false);
        } else {
            $('#edit_wednesdayElement_1').attr('checked', true);
        }
        /*Thursday*/
        $("#edit_time_f_thursday").val(resourceRota.time_f_thursday);
        $("#edit_time_to_thursday").val(resourceRota.time_to_thursday);
        $("#edit_break_from_thursday").val(resourceRota.break_from_thursday);
        $("#edit_break_to_thursday").val(resourceRota.break_to_thursday);
        if (response.data.resourceRota.thursdaychecked == "_off") {
            $('#edit_thursdayElement_1').attr('checked', false);
        } else {
            $('#edit_thursdayElement_1').attr('checked', true);
        }
        /*Friday*/
        $("#edit_time_f_friday").val(resourceRota.time_f_friday);
        $("#edit_time_to_friday").val(resourceRota.time_to_friday);
        $("#edit_break_from_friday").val(resourceRota.break_from_friday);
        $("#edit_break_to_friday").val(resourceRota.break_to_friday);
        if (response.data.resourceRota.fridaychecked == "_off") {
            $('#edit_fridayElement_1').attr('checked', false);
        } else {
            $('#edit_fridayElement_1').attr('checked', true);
        }
        /*Saturday*/
        $("#edit_time_f_saturday").val(resourceRota.time_f_saturday);
        $("#edit_time_to_saturday").val(resourceRota.time_to_saturday);
        $("#edit_break_from_saturday").val(resourceRota.break_from_saturday);
        $("#edit_break_to_saturday").val(resourceRota.break_to_saturday);
        if (response.data.resourceRota.saturdaychecked == "_off") {
            $('#edit_saturdayElement_1').attr('checked', false);
        } else {
            $('#edit_saturdayElement_1').attr('checked', true);
        }
        /*Sunday*/
        $("#edit_time_f_sunday").val(resourceRota.time_f_sunday);
        $("#edit_time_to_sunday").val(resourceRota.time_to_sunday);
        $("#edit_break_from_sunday").val(resourceRota.break_from_sunday);
        $("#edit_break_to_sunday").val(resourceRota.break_to_sunday);
        if (response.data.resourceRota.sundaychecked == "_off") {
            $('#edit_sundayElement_1').attr('checked', false);
        } else {
            $('#edit_sundayElement_1').attr('checked', true);
        }
    } catch (error) {
        showException(error);
    }

}

function setCreateEditData(response, edit = false) {

    let citi = response.data.citi;
    let resourceRota = response.data.resourceRota;
    let resource_name = response.data.resource_name;


    if (edit) {
        $("#city_id").removeAttr('name');
        $("#location_id").removeAttr('name');
        $("#resource_type_id").removeAttr('name');
        $("#machine_id").removeAttr('name');
        $("#doctor_id").removeAttr('name');

        $("#edit_city_id").attr('name', 'city_id').val(citi.id);
        $("#edit_location_id").attr('name', 'location_id').val(resourceRota.location_id);
        $("#edit_resource_type_id").attr('name', 'resource_type_id').val(resource_name.resource_type_id);
        $("#edit_machine_id").attr('name', 'resource_machine').val();
        $("#edit_doctor_id").attr('name', 'resource_doctor').val();
    } else {
        $("#city_id").attr('name', 'city_id');
        $("#location_id").attr('name', 'location_id');
        $("#resource_type_id").attr('name', 'resource_type_id');
        $("#machine_id").attr('name', 'resource_machine');
        $("#doctor_id").attr('name', 'resource_doctor');

        $("#edit_city_id").removeAttr('name');
        $("#edit_location_id").removeAttr('name');
        $("#edit_resource_type_id").removeAttr('name');
        $("#edit_machine_id").removeAttr('name');
        $("#edit_doctor_id").removeAttr('name');
    }
}


function applyFilters(datatable) {

    $('#apply-filters').on('click', function () {

        let filters = {
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

    $('#reset-filters').on('click', function () {
        let filters = {
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

        Object.entries(resource_types).forEach(function (value) {
            type_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(cities).forEach(function (value) {
            cities_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(regions).forEach(function (value) {
            regions_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value) {
            locations_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(status).forEach(function (value) {
            status_option += '<option value="' + value[0] + '">' + value[1] + '</option>';
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

        getUserCity();

    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.region_id !== 'undefined' && active_filters.region_id != '')
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


/*Define query for define rota type for consultancy*/
$('#is_consultancy_1').change(function () {
    if ($(this).is(":checked")) {
        $('#is_consultancy_1').val('1');
    }
    else {
        $('#is_consultancy_1').val('0');
    }
});
$('#is_consultancy_1').change();
/*End*/
/*Define query for define rota type for treatment*/
$('#is_treatment_1').change(function () {
    if ($(this).is(":checked")) {
        $('#is_treatment_1').val('1');
    }
    else {
        $('#is_treatment_1').val('0');
    }
});
$('#is_treatment_1').change();
/*End*/

