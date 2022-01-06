
var table_url = route('admin.settings.datatable');

var table_columns = [
    {
        field: 'name',
        title: 'Name',
        width: 600,
    },{
        field: 'data',
        title: 'Data',
        width: 'auto',
    },  {
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

    if (permissions.edit) {
        return  '<a href="javascript:void(0);" onclick="editRow('+id+')" class="btn btn-sm btn-primary">\
        <span class="navi-icon"><i class="la la-pencil"></i></span>\
        <span class="navi-text">Edit</span>\
        </a>';
    }

    return '';
}

function editRow(id, modal) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.settings.edit', {id: id}),
        type: "GET",
        cache: false,
        success: function (response) {
            // $("#user_type_edit").html(response);
            if(response.status){
                $("#change_modal").modal("show");
                let data;
                data = '<input type="hidden" name="data" value="'+ response.data.data +'" id="form_data" class="form-control form-control-lg form-control-solid mb-2">';
                if(response.data.field_type ==='text'){
                    data = '<input type="text" name="data" value="'+ response.data.data +'" id="form_data" class="form-control form-control-lg form-control-solid mb-2">';
                }
                else if(response.data.field_type ==='select'){
                    data= data+'<select class="form-control form-control-lg mb-2" name="data" >';
                        Object.entries(response.data.list).forEach(function (value, index){
                            if(response.data.data===value[0]){
                                data = data + '<option selected value="'+ value[0] +'">'+ value[1] +'</option>';
                            }
                            else{
                                data = data + '<option value="'+ value[0] +'">'+ value[1] +'</option>';
                            }
                        });
                    data = data + '</select>';
                }
                else if(response.data.field_type === 'minmax'){
                    data=data+'<div class="row">' +
                    '<div class="col-md-6"><input placeholder="Min" type="text"  name="min" value="'+ response.data.min +'" id="form_data" required class="form-control form-control-lg form-control-solid mb-2"></div>' +
                    '<div class="col-md-6"><input placeholder="Max" type="text" name="max" value="'+ response.data.max +'" id="form_data" required class="form-control form-control-lg form-control-solid mb-2"></div>' +
                    '</div>';
                }
                else if(response.data.field_type === 'prepost'){
                    data=data+'<div class="row">' +
                        '<div class="col-md-6"><input placeholder="Pre" type="text" name="pre" value="'+ response.data.pre +'" id="form_data" required class="form-control form-control-lg form-control-solid mb-2 mr-1"></div>' +
                        '<div class="col-md-6"><input placeholder="Post" type="text" name="post" value="'+ response.data.post +'" id="form_data" required class=" form-control form-control-lg form-control-solid mb-2 ml-1"></div>' +
                        '</div>';
                }
                $('#modal_settings_form').attr('action',route('admin.settings.update',response.data.id));
                $('#form_name').html(response.data.name);
                $('#form_name_field').val(response.data.name);
                $('#field_data').html(data);
            }
            else{
                toastr.error(response.message);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters =  {
            delete: '',
            name: $("#search_name").val(),
            data: $("#search_data").val(),
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
            data: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}
