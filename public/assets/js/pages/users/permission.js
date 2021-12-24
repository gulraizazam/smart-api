
var table_url = route('admin.permissions.datatable');

var table_columns = [ {
    field: 'id',
    sortable: false,
    width: 25,
    title: '<th data-field="RecordID" class="datatable-cell-center datatable-cell datatable-cell-check"><span style="width: 20px;"><label class="checkbox checkbox-single checkbox-all"><input class="select-all-checkboxes" type="checkbox">&nbsp;<span></span></label></span></th>',
        template: function (data) {
            let id = data.id;
            return '<th data-field="RecordID" class="datatable-cell-center datatable-cell datatable-cell-check"><span style="width: 20px;"><label class="checkbox checkbox-single checkbox-all"><input value="'+id+'" class="table-checkboxes" type="checkbox">&nbsp;<span></span></label></span></th>';
        }
    }, {
        field: 'title',
        title: 'Title',
        width: 'auto',
    }, {
        field: 'name',
        title: 'Name',
        width: 300,
    }, {
        field: 'parent_id',
        title: 'Parent Permission',
        width: 300,
    },  {
        field: 'Actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
        template: function(data) {
            let id = data.id;
            let delete_route = route('admin.permissions.destroy', {id: id});
            let csrf = $('meta[name="csrf-token"]').attr('content');

            return '<div class="dropdown dropdown-inline action-dots">'+
                '<a href="javascript:;" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">'+
                    '<i class="fa fa-ellipsis-v" aria-hidden="true"></i>'+
                '</a>'+
                '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">'+
                    '<ul class="navi flex-column navi-hover py-2">'+
                        '<li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">'+
                        'Choose an action:'+
                        '</li>'+
                        '<li class="navi-item">'+
                            '<a href="javascript:void(0);" class="navi-link">'+
                                '<span class="navi-icon"><i class="la la-pencil"></i></span>'+
                                '<span class="navi-text">Edit</span>'+
                            '</a>'+
                        '</li>'+
                        '<li class="navi-item">'+
                            '<a href="javascript:void(0);" onclick="deleteRow('+id+');" class="navi-link">'+
                                '<span class="navi-icon"><i class="la la-trash"></i></span>'+
                                '<span class="navi-text">Delete</span>'+
                            '</a>'+
                            '<form id="delete-row-form-'+id+'" action="'+delete_route+'" method="post">' +
                                '<input type="hidden" name="_token" value="'+csrf+'">'+
                                '<input type="hidden" name="_method" value="delete">'+
                            '</form>'+
                        '</li>'+
                    '</ul>'+
                '</div>'+
                '</div>';
        },
    }];


function createPermission($route) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: $route,
        type: "GET",
        cache: false,
        success: function (response) {
            $("#permission-create").html(response);
            reInit("#kt_select2_8", "Select an Parent Group");
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {

            } else {
            }
            reInit("#kt_select2_8", "Select an Parent Group");
        }
    });
}
