
var table_url = route('admin.discounts.datatable');

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
        title: 'Name',
        sortable: false,
        width: 300,
    },{
        field: 'type',
        title: 'Type',
        sortable: false,
        width: 'auto',
    },{
        field: 'amount',
        title: 'Amount',
        sortable: false,
        width: 'auto'
    },{
        field: 'discount_type',
        title: 'Discount Type',
        sortable: false,
        width: 'auto',
    },{
        field: 'start',
        title: 'From',
        sortable: false,
        width: 'auto',
    },{
        field: 'end',
        title: 'To',
        sortable: false,
        width: 'auto',
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        sortable: false,
        template: function (data) {
            let status_url = route('admin.discounts.status');
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

        let url = route('admin.discounts.edit', {id: id});
        let allocate_url = route('admin.discounts.location_manage', {id: id});
        let delete_url = route('admin.discounts.destroy', {id: id});

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
            if (permissions.allocate) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="allocateRow(`' + allocate_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">Allocate</span>\
                    </a>\
                </li>';
            }
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

function allocateRow(url) {

    $("#modal_allocate_discounts").modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {

            setAllocateData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });


}

function setAllocateData(response) {

    try {

        let discount = response.data.discount;
        let locations = response.data.location;
        let discount_locations = response.data.discount_has_location;

        let location_options = '<option value="">Select Centre</option>';
        let location_services = '';

        Object.values(locations).forEach(function(value, index) {

            location_options += '<option value="">Select</option>\
            <optgroup label="'+value.name+'">';
            Object.values(value.children).forEach(function(child, index) {
                location_options += '<option value="'+child.id+'">'+child.name+'</option>';
            });

            location_options += '</optgroup>';
        });

        Object.values(discount_locations).forEach(function(value, index) {
            let location_name = value.location.city.name +"-"+ value.location.name;
            location_services += serviceLocation(value.id, location_name, value.service.name);
        });

        $('.HR_SERVICES').remove()
        $('#allocate_services').append(location_services)

        $("#discount_id").val(discount.id);

        $("#locations").html(location_options);

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

function getDesrvice($this) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route("admin.discounts.get_Dservice"),
        type: "GET",
        data: {discount_id:  $("#discount_id").val(), id: $this.val()},
        cache: false,
        success: function (response) {

            setServicesData(response);

            reInitSelect2(".select2", "");

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditValidation);
        }
    });
}

function setServicesData(response) {

    let services = response.data.services;
    let locaiton_id = response.data.locaiton_id_1;

    let service_options = '<option value="">Select</option>';

    Object.values(services).forEach(function(value, index) {

        service_options += '<option value="'+value.id+'">'+value.name+'</option>';

    });

    $("#services").html(service_options);

}

function deleteModel(id) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        type: 'post',
        url: route('admin.discounts.delete_service'),
        data: {'id': id
        },
        success: function (response) {

            $('.HR_' + response.data.id).remove();
        }
    });
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
            name: $("#search_name").val(),
            type: $("#search_type").val(),
            amount: $("#search_amount").val(),
            discount_type: $("#search_discount_type").val(),
            startdate: $("#search_start").val(),
            enddate: $("#search_end").val(),
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
            name: '',
            type: '',
            amount: '',
            discount_type: '',
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

        let status = filter_values.status;

        let status_options = '<option value="">All</option>';

        Object.entries(status).forEach(function (value, index) {
            status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });


        $("#search_status").html(status_options);

        $("#search_name").val(active_filters.name);
        $("#search_type").val(active_filters.type);
        $("#search_amount").val(active_filters.amount);
        $("#search_discount_type").val(active_filters.discount_type);
        $("#search_start").val(active_filters.startdate);
        $("#search_end").val(active_filters.enddate);
        $("#search_created_from").val(active_filters.created_from);
        $("#search_created_to").val(active_filters.created_to);
        $("#search_status").val(active_filters.status);

        hideShowAdvanceFilters(active_filters);

    } catch (err) {

    }
}

function createDiscount($route) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            //setDiscountData(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });
}

function setDiscountData(response) {

    try {
    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')
        || (typeof active_filters.startdate !== 'undefined' && active_filters.startdate != '')
        || (typeof active_filters.enddate !== 'undefined' && active_filters.enddate != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}
