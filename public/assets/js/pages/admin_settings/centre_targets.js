
var table_url = route('admin.centre_targets.datatable');

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
        field: 'year',
        title: 'Year',
        sortable: false,
        width: 300,
    },{
        field: 'month',
        title: 'Month',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
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

        let url = route('admin.centre_targets.edit', {id: id});
        let delete_url = route('admin.centre_targets.destroy', {id: id});
        let display_url = route('admin.centre_targets.display', {id: id});

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
            if (permissions.manage) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="display(`' + display_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">Display</span>\
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

function createCentreTarget($route) {

    resetFormAndData("add_");
    $("#modal_add_centre_targets_form")[0].reset();

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

loadActiveLocation = function (prefix = "add_") {

    resetFormAndData(prefix);

    if (prefix == "edit_") {
        $("#edit_working_days").val('0')
        $(".edit_target_amount").val('0')
    }

    var year = $('#'+prefix+'year').val();
    var month = $('#'+prefix+'month').val();

    if(year == '' || month == '') {
        $('#'+prefix+'centre_require_field').removeClass("d-none");
        return false;
    }

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.centre_targets.load_target_centre'),
        type: 'POST',
        data: {
            year: year,
            month: month,
        },
        cache: false,
        success: function(response) {

            if(response.status) {
                setTargets(response, prefix);

                if(response.data.center_target_status == 0){
                    $('#'+prefix+'centre_edit_perform').addClass("d-none");
                } else {
                    $('#'+prefix+'centre_edit_perform').removeClass("d-none");
                }
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {

        }
    });
}

function setTargets(response, prefix) {

    try {

        let center_target_working_days = response.data.center_target_working_days;
        let locations = response.data.target_location;
        let location_options = '';

        Object.values(locations).forEach(function (value) {
            location_options += getTable(value.location_name, value.location_id, value.target_amount, prefix);
        });

        $("#" + prefix + "centre_target_location").append(location_options);
        $("." + prefix + "center_target_table").removeClass("d-none");

        $("#" + prefix + "working_days").val(center_target_working_days);
    } catch (error) {
        showException(error);
    }

}

function resetFormAndData(prefix) {

    $('#'+prefix+'centre_edit_perform').addClass("d-none");
    $('#'+prefix+'centre_require_field').addClass("d-none");
    $("."+prefix+"target-row").remove();
}

function getTable(location_name, id, target_amount, prefix = "add_") {
    return ' <tr class="'+prefix+'target-row"> <td>'+location_name+'</td><td> <input class="form-control '+prefix+'target_amount" type="number" value="'+target_amount+'" name="target_amount['+id+']"> </td></tr>';
}

function setCreateData(response) {

    try {

        let years = response.data.years;
        let months = response.data.months;

        let months_options = '<option value="">Select a Year</option>';
        let years_options = '<option value="">Select a Month</option>';

        Object.entries(months).forEach(function (value, index) {
            months_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(years).forEach(function (value, index) {
            years_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });


        $("#add_month").html(months_options);
        $("#add_year").html(years_options);

    } catch (error) {
        showException(error);
    }
}

function editRow(url) {

    $("#modal_edit_centre_targets").modal("show");

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

        let years = response.data.years;

        let months = response.data.months;

        let center_target = response.data.center_target;

        $("#modal_edit_centre_targets_form").attr("action", route('admin.centre_targets.update', {id: center_target.id}));

        let months_options = '<option value="">Select a Year</option>';
        let years_options = '<option value="">Select a Month</option>';

        Object.entries(months).forEach(function (value, index) {
            months_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(years).forEach(function (value, index) {
            years_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });


        $("#edit_month").html(months_options);
        $("#edit_year").html(years_options);
        $("#edit_working_days").val(center_target.working_days);
        $("#edit_month").val(center_target.month);
        $("#edit_year").val(center_target.year);

        loadActiveLocation("edit_");

    } catch (error) {
        showException(error);
    }

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            month: $("#search_month").val(),
            year: $("#search_year").val(),
            created_at: $("#date_range").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        let filters =  {
            delete: '',
            month: '',
            year: '',
            created_at: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let months = filter_values.months;
        let years = filter_values.years;

        let months_options = '<option value="">All</option>';
        let years_options = '<option value="">All</option>';

        Object.entries(months).forEach(function (value, index) {
            months_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(years).forEach(function (value, index) {
            years_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });


        $("#search_month").html(months_options);
        $("#search_year").html(years_options);

        $("#search_month").val(active_filters.month);
        $("#search_year").val(active_filters.year);
        $("#date_range").val(active_filters.created_at);

    } catch (error) {
        showException(error);
    }
}

function display($route) {

    $(".display-rows").remove();
    $("#modal_display_centre_targets").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setDisplayData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setDisplayData(response) {

    try {

        let rows = '';
        let center_target = response.data.center_target;

        Object.values(center_target.center_target_meta).forEach(function (value, index) {

            rows += displayRows(value.location.name, value.target_amount, index);
        });

        $(".month_value").text(center_target.month);
        $(".year_value").text(center_target.year);

        $("#display-target").append(rows);
    } catch (error) {
        showException(error);
    }
}

function displayRows(location_name, amount, sr) {
    return '<tr class="display-rows"> <td>'+sr+'</td><td>'+location_name+'</td><td align="right">'+amount+'</td></tr>';
}

jQuery(document).ready( function () {
    $("#date_range").val("");
})
