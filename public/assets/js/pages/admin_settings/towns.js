
var table_url = route('admin.towns.datatable');

var table_columns = [
     {
        field: 'name',
        title: 'Name',
        width: 600,
    },{
        field: 'city',
        title: 'City',
        width: 'auto',
    }, {
        field: 'status',
        title: 'status',
        width: 'auto',
        template: function (data) {
            let status_url = route('admin.towns.status');
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
    
        let csrf = $('meta[name="csrf-token"]').attr('content');
        let url = route('admin.towns.edit', {id: id});
        let delete_url = route('admin.towns.destroy', {id: id});
    
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
                        <a href="'+url+'" class="navi-link">\
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

    function editRow(id, modal) {

        $("#modal_add_user_type").modal("show");

        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.user_types.edit', {id: id}),
            type: "GET",
            cache: false,
            success: function (response) {
            
                setEditData(response);

                reInitSelect2(".select2", "");
            
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);

                reInitValidation(UserTypeValidation);
            }
        });


    }

    function setEditData(response) {


        let types = response.data.types;
        let usertype = response.data.usertype;

        let action = route('admin.user_types.update', {id: usertype.id});
        $("#modal_user_type_form").attr("action", action);

        let options = '<option value="">Select</option>';

        Object.entries(types).forEach(function(value, index) {
            
            options += '<option value="'+value[0]+'">'+value[1]+'</option>';
        });
        
        console.log(usertype.type);
        $("#user_type").html(options);
        
        $("#user_type_name").val(usertype.name);
        $("#user_type").val(usertype.type);

    }

    function applyFilters(datatable) {

        $('#apply-filters').on('click', function() {

            let filters =  {
                delete: '',
                name: $("#search_name").val(),
                type: $("#search_type").val(),
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
                filter: 'filter_cancel',
            }
            datatable.search(filters, 'search');
        });

    }
