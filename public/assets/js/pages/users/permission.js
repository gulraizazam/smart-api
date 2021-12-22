
var table_url = route('admin.permissions.datatable');

var table_columns = [ {
    field: 'checkbox',
    sortable: false,
    title: '<th data-field="RecordID" class="datatable-cell-center datatable-cell datatable-cell-check"><span style="width: 20px;"><label class="checkbox checkbox-single checkbox-all"><input class="select-all-checkboxes" type="checkbox">&nbsp;<span></span></label></span></th>',
    template: function () {
        return '<th data-field="RecordID" class="datatable-cell-center datatable-cell datatable-cell-check"><span style="width: 20px;"><label class="checkbox checkbox-single checkbox-all"><input class="table-checkboxes" type="checkbox">&nbsp;<span></span></label></span></th>';
    }
},
    {
        field: 'title',
        title: 'Title',
        width: 'auto',
    }, {
        field: 'Name',
        title: 'name',
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
        template: function() {
            return '<div class="dropdown dropdown-inline action-dots">\
	                    <a href="javascript:;" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
	                                <i class="fa fa-ellipsis-v" aria-hidden="true"></i>\
	                            </a>\
	                        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
	                                <ul class="navi flex-column navi-hover py-2">\
	                                    <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
	                                        Choose an action:\
	                                    </li>\
	                                    <li class="navi-item">\
	                                        <a href="#" class="navi-link">\
	                                            <span class="navi-icon"><i class="la la-print"></i></span>\
	                                            <span class="navi-text">Edit</span>\
	                                        </a>\
	                                    </li>\
	                                    <li class="navi-item">\
	                                        <a href="#" class="navi-link">\
	                                            <span class="navi-icon"><i class="la la-copy"></i></span>\
	                                            <span class="navi-text">Delete</span>\
	                                        </a>\
	                                    </li>\
	                                </ul>\
	                            </div>\
	                </div>';
        },
    }];
