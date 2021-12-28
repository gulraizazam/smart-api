
var table_url = route('admin.roles.datatable');

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
        field: 'name',
        title: 'Name',
        width: 700,
    }, {
        field: 'commission',
        title: 'Commission',
        width: 200,
    },  {
        field: 'Actions',
        title: 'Actions',
        sortable: false,
        width: 80,
        overflow: 'visible',
        autoHide: false,
        template: function(data) {
            let modal = 'modal_add_permission';
            let id = data.id;
            let delete_route = route('admin.permissions.destroy', {id: id});
            let csrf = $('meta[name="csrf-token"]').attr('content');

            return '<div class="dropdown dropdown-inline action-dots">'+
                '<a href="javascript:;" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">'+
                    '<i class="ki ki-bold-more-hor" aria-hidden="true"></i>'+
                '</a>'+
                '<div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">'+
                    '<ul class="navi flex-column navi-hover py-2">'+
                        '<li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">'+
                        'Choose an action:'+
                        '</li>'+
                        '<li class="navi-item">'+
                            '<a href="javascript:void(0);" onclick="editRow('+id+', '+modal+');" class="navi-link">'+
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


function editRow( id, modal) {

    $(modal).modal("show");

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.permissions.edit', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            $("#permission-create").html(response);
            reInitSelect2("#kt_select2_8", "Select an Parent Group");
            reInitValidation(KTPermissionValidation);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {
                toastr.error("You are not authorized to access this resource");
            } else {
                toastr.error("Unable to process your request, please try again later.");
            }
            reInitValidation(KTPermissionValidation);
        }
    });


}
