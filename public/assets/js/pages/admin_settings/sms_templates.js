var table_url = route('admin.sms_templates.datatable');

var table_columns = [

    {
        field: 'name',
        title: 'Name',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'content',
        sortable: false,
        width: 'auto',
        title: 'content',
        template: function (data) {
            return data.content.substring(0,70)+' ...';
        }
    },
    {
        field: 'slug',
        sortable: false,
        width: 'auto',
        title: 'Slug',
    },

    {
        field: 'status',
        title: 'Status',
        width: 'auto',
        sortable: false,
        template: function (data) {
            let status_url = route('admin.sms_templates.status');
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
    let url = route('admin.sms_templates.edit', {id: id});
    if (permissions.edit) {
        return '<a href="javascript:void(0);" onclick="editRow(`' + url + '`)" class="btn btn-sm btn-primary">\
        <span class="navi-icon"><i class="la la-pencil"></i></span>\
        <span class="navi-text">Edit</span>\
        </a>';
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
            $("#modal_edit_sms_templates").modal("show");
            setEditData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(TownValidation);
        }
    });
}

function setEditData(response) {
    let sms_templates = response.data;
    let action = route('admin.sms_templates.update', {id: sms_templates.id});

    $("#modal_edit_sms_templates_form").attr("action", action);
    $("#edit_sms_templates_content").html(sms_templates.content);

    let variables=sms_templates.variables;
    let variable_options='';
    let options;
    Object.entries(variables).forEach(function (value, index) {
        variable_options += '<optgroup label="' + value[0] + '">';
        options=value[1];
        Object.entries(options).forEach(function (value2, index) {
            variable_options += '<option value="' + value2[0] + '">' + value2[1] + '</option>';
        });
        variable_options +='</optgroup>';
    });
    $('#edit_sms_templates_name').val(sms_templates.name);
    $("#edit_sms_templates_variables").html(variable_options);
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function () {
        let filters = {
            delete: '',
            name: $("#search_name").val(),
            slug: $("#search_slug").val(),
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
            slug: '',
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
    $("#search_status").val(active_filters.status);
    $("#search_slug").val(active_filters.slug);
}


function applyVariable() {
    var selected_var = $('#edit_sms_templates_variables option:selected').val();
    insertAtCaret('edit_sms_templates_content', selected_var);
}

function insertAtCaret(areaId, text) {
    var txtarea = document.getElementById(areaId);
    if (!txtarea) { return; }

    var scrollPos = txtarea.scrollTop;
    var strPos = 0;
    var br = ((txtarea.selectionStart || txtarea.selectionStart == '0') ?
        "ff" : (document.selection ? "ie" : false ) );
    if (br == "ie") {
        txtarea.focus();
        var range = document.selection.createRange();
        range.moveStart ('character', -txtarea.value.length);
        strPos = range.text.length;
    } else if (br == "ff") {
        strPos = txtarea.selectionStart;
    }

    var front = (txtarea.value).substring(0, strPos);
    var back = (txtarea.value).substring(strPos, txtarea.value.length);
    txtarea.value = front + text + back;
    strPos = strPos + text.length;
    if (br == "ie") {
        txtarea.focus();
        var ieRange = document.selection.createRange();
        ieRange.moveStart ('character', -txtarea.value.length);
        ieRange.moveStart ('character', strPos);
        ieRange.moveEnd ('character', 0);
        ieRange.select();
    } else if (br == "ff") {
        txtarea.selectionStart = strPos;
        txtarea.selectionEnd = strPos;
        txtarea.focus();
    }

    txtarea.scrollTop = scrollPos;
}
