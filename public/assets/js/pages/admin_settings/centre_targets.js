
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

loadActiveLocation = function () {

    $('.centre_require_field').addClass("d-none");

    var year = $('#add_year').val();
    var month = $('#add_month').val();

    if(year == '' || month == '') {
        $('.centre_require_field').removeClass("d-none");
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
                setTargets(response);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {

        }
    });
}

function setTargets(response) {

    let center_target_working_days = response.data.center_target_working_days;
    let locations = response.data.target_location;
    let location_options = '';

    Object.values(locations).forEach( function (value) {
        location_options += getTable(value.location_name, value.location_id, value.target_amount);
    });

    $("#centre_target_location").append(location_options);
    $(".center_target_table").removeClass("d-none");

}

function getTable(location_name, id, target_amount) {
    return ' <tr> <td>'+location_name+'</td><td> <input class="form-control" type="number" value="'+target_amount+'" name="target_amount['+id+']"> </td></tr>';
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

    $("#modal_edit_discounts").modal("show");

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

        let discount = response.data.discount;

        $("#modal_edit_discounts_form").attr("action", route('admin.discounts.update', {id: discount.id}));

        if (discount.discount_type == 'Treatment') {
            $(".treatment").prop("checked", true);
        }
        if (discount.discount_type == 'Consultancy') {
            $(".consultancy").prop("checked", true);
        }

        if (discount.slug == 'default') {
            $(".default").prop("checked", true);
            $(".edit_birthday_range").addClass("d-none");
        }
        if (discount.slug == 'custom') {
            $(".custom").prop("checked", true);
            $(".edit_birthday_range").addClass("d-none");
        }
        if (discount.slug == 'birthday') {
            $(".birthday").prop("checked", true);
            $(".edit_birthday_range").removeClass("d-none");

        }

        $("#edit_name").val(discount.name);
        $("#edit_amount_type").val(discount.type);
        $("#edit_amount").val(discount.amount);
        $("#edit_pre_days").val(discount.pre_days);
        $("#edit_post_days").val(discount.post_days);
        $("#edit_start").val(discount.start);
        $("#edit_end").val(discount.end);

        $("#edit_active").prop("checked", discount.active);

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
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
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
            created_from: '',
            created_to: '',
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
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);

    } catch (err) {
        showException(error);
    }
}
