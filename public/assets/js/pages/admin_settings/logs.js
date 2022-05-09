var table_url = route('admin.logs.datatable');

var table_columns = [

    {
        field: 'id',
        title: 'ID',
        width: '80',
        sortable: false,
    },
    {
        field: 'created_at',
        title: 'Date Time',
        width: 'auto',
        sortable: false,
    },
    {
        field: 'audit_trail_table_name',
        title: 'Screen',
        width: 'auto',
        sortable: false,
        template: function (data) {
            return data?.audit_table?.screen ?? 'N/A';
        }
    },
    {
        field: 'user_id',
        title: 'User',
        width: 'auto',
        sortable: false,
        template: function (data) {
            return data.user.name;
        }
    },
    {
        field: 'audit_trail_action_name',
        title: 'Action',
        width: 'auto',
        sortable: false,
        template: function (data) {
            return '<b>'+data.audit_action.name+'</b>';
        }
    },
];


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

