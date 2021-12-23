
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
        width: 'auto',
    }, {
        field: 'parent_id',
        title: 'Parent Permission',
        width: 200,
    },  {
        field: 'Actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
        template: function(data) {
            let delete_route = route('admin.permissions.destroy', {id: data.id});
            return '<div class="dropdown dropdown-inline action-dots">'+
                '<a href="javascript:;" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">'+
                    '<i class="fa fa-ellipsis-v" aria-hidden="true"></i>'+
                '</a>'+
                '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">'+
                    '<ul class="navi flex-column navi-hover py-2">'+
                        '<li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">'+
                        'Choose aaction:'+
                        '</li>'+
                        '<li class="navi-item">'+
                            '<a href="javascript:void(0);" class="navi-link">'+
                                '<span class="navi-icon"><i class="la la-print"></i></span>'+
                                '<span class="navi-text">Edit</span>'+
                            '</a>'+
                        '</li>'+
                        '<li class="navi-item">'+
                            '<a href="javascript:void(0);" data-route="'+delete_route+'" onclick="deleteRow(this)" class="navi-link">'+
                                '<span class="navi-icon"><i class="la la-copy"></i></span>'+
                                '<span class="navi-text">Delete</span>'+
                            '</a>'+
                        '</li>'+
                    '</ul>'+
                '</div>'+
                '</div>';
        },
    }];
