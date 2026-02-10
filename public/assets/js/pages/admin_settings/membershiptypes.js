var table_url = route('admin.membershiptypes.datatable');
var table_columns = [

    {
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 'auto',
    }, {
        field: 'parent_name',
        title: 'Parent',
        sortable: false,
        width: 'auto',
    }, {
        field: 'period',
        title: 'Period (Days)',
        sortable: false,
        width: 'auto',
    }, {
        field: 'amount',
        title: 'Price',
        sortable: false,
        width: 'auto',
    }, {
        field: 'status',
        title: 'Status',
        width: 60,
        template: function (data) {
            let status_url = route('admin.membershiptypes.status');
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
        let csrf = $('meta[name="csrf-token"]').attr('content');
        let url = route('admin.membershiptypes.edit', { id: id });
        let delete_url = route('admin.membershiptypes.destroy', { id: id });
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
    $("#modal_edit_membershiptypes").modal("show");
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
            reInitValidation(TownValidation);
        }
    });
}
function setEditData(response) {
    let membershipType = response.data.membershipType;
    let parentMemberships = response.data.parentMemberships;
    
    $("#modal_edit_membershiptypes_form").attr("action", route('admin.membershiptypes.update', { id: membershipType.id }));

    $("#edit_name").val(membershipType.name);
    $("#edit_period").val(membershipType.period);
    $("#edit_membership_amount").val(membershipType.amount);

    // Populate parent membership dropdown
    let parentOptions = '<option value="">None (Main Membership)</option>';
    if (parentMemberships) {
        Object.entries(parentMemberships).forEach(function([id, name]) {
            let selected = (membershipType.parent_id == id) ? 'selected' : '';
            parentOptions += '<option value="' + id + '" ' + selected + '>' + name + '</option>';
        });
    }
    $("#edit_parent_id").html(parentOptions);
}
function applyFilters(datatable) {

    $('#apply-filters').on('click', function () {

        let filters = {

            name: $("#search_name").val(),

            status: $("#search_status").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}
function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function () {
        let filters = {

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

    Object.entries(status).forEach(function (value, index) {
        status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
    });


    $("#search_status").html(status_options);

    $("#search_name").val(active_filters.name);


    hideShowAdvanceFilters(active_filters);

}
function createMembershipType($route) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {

            setCreateData(response);

            //reInitSelect2(".select2", "Select");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(Validation);
        }
    });
}
function setCreateData(response) {

}
function makeServiceOptions(response) {

    let services = response.data.services;
    let service_value = '';
    let service_child_value = '';
    let service_options = '';

    Object.values(services).forEach(function (value, index) {
        service_value = value.name;
        if (service_value == 'All Services') {
            service_options += '<option value="' + value.id + '">' + service_value + '</option>';
        } else {
            service_options += '<option value="' + value.id + '">' + service_value + '</option>';
            Object.values(value.children).forEach(function (child, index) {
                service_child_value = '\t&nbsp; \t&nbsp; \t&nbsp;' + child.name;
                service_options += '<option value="' + child.id + '">' + service_child_value + '</option>';
            });
        }
    });
    return service_options;
}
function hideShowAdvanceFilters(active_filters) {
    if ((typeof active_filters.city_id !== 'undefined' && active_filters.city_id != '')
        || (typeof active_filters.region_id !== 'undefined' && active_filters.region_id != '')
        || (typeof active_filters.address !== 'undefined' && active_filters.address != '')
        || (typeof active_filters.email !== 'undefined' && active_filters.email != '')
        || (typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").addClass("fa fa-caret-down");
    }
}

jQuery(document).ready(function () {
    $("#date_range").val("");
})
