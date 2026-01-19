var table_url = route('admin.leads.datatable');
if (typeof lead_type !== 'undefined' && lead_type != '') {
    table_url = route('admin.leads.datatable', {type: lead_type});
}
var table_columns = [{
    field: 'id',
    sortable: false,
    width: 20,
    title: renderCheckbox(),
    template: function(data) {
        return childCheckbox(data, data.lead_id);
    }
}, {
    field: 'lead_id',
    title: 'ID',
    sortable: false,
    width: 60,
}, {
    field: 'name',
    title: 'Full Name',
    sortable: false,
    width: 110,
}, {
    field: 'phone',
    title: 'Phone',
    sortable: false,
    width: 90,
    template: function (data) {
        return phoneClip(data);
    }
}, {
    field: 'city_id',
    title: 'City',
    sortable: false,
    width: 90,
    className: 'tooltip_wrap',
    template: function (data) {

        let city_id = data.city_id;
        let city = '<span class="text text-danger">Empty</span>';
        if (city_id != '') {
            city = city_id;
        }
        return '<a href="javascript:void(0);" data-city_id="'+data.cityId+'" onclick="editInline(`' + data.lead_id + '`, `'+data.cityId+'`, $(this))" class="lead_city" id="lead-'+data.lead_id+'">'+city+'</a>';
    }
},{
    field: 'location_id',
    title: 'Centre',
    sortable: false,
    width: 110,
    template: function (data) {
        if(data.location != ""){
            return data.location;
        }else{
            return '<span class="text text-danger">Empty</span>';
        }
    }
},{
    field: 'service_id',
    title: 'Service',
    sortable: false,
    width: 110,
    template: function (data) {
        let value = data.service_id.split(",");
        let valueActive = data.service_active.split(",");
        let services = '';
        if(data.service_id != ""){
            value.forEach((item) => {
                if(valueActive[0] == item){
                    services += '<span class="text text-primary">' + item + '</span><br>'
                }

            })
        }else{
            services += '<span class="text text-danger">Empty</span>'
        }
        return services;
    }
},{
    field: 'lead_status_id',
    title: 'Lead Status',
    sortable: false,
    width: 70,
    template: function (data) {
        return '<a href="javascript:void(0);" onclick="editLeadStatus('+data.lead_id+');">'+data.lead_status_id+'</a>';
    }
}, {
    field: 'status',
    title: 'Status',
    sortable: false,
    width: 60,
    template: function(data) {
        let status_url = route('admin.leads.status');
        let id = data.id;
        let active = data.active;
        let status = '';
        if (active) {
            if (permissions.update_status) {
                status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateStatus(`'+status_url+'`, `'+data.lead_id+'`, $(this));" type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
            } else {
                status += '<span class="switch switch-icon">\
            <label>\
                <input disabled type="checkbox" checked="checked" name="select">\
                <span></span>\
            </label>\
            </span>';
            }
        } else {
            status += '<span class="switch switch-icon">\
        <label>\
            <input value="1" onchange="updateStatus(`'+status_url+'`, `'+data.lead_id+'`, $(this));" type="checkbox" name="select">\
            <span></span>\
        </label>\
        </span>';
        }
        return status;
    }
},{
    field: 'gender',
    title: 'Gender',
    sortable: false,
    width: 70,
    template: function (data) {
        return data.gender;
    }
},{
    field: 'created_by',
    title: 'Created By',
    sortable: false,
    width: 70,
},{
    field: 'actions',
    title: 'Actions',
    sortable: false,
    width: 70,
    overflow: 'visible',
    autoHide: false,
    template: function(data) {
        return actions(data);
    }
}, {
    field: 'created_at',
    title: 'Created At',
    sortable: false,
    width: 'auto',
}, {
    field: 'child_service_id',
    title: 'Treatment',
    sortable: false,
    width: 'auto',
    template: function (data) {
        if(data.child_service != ""){
            return data.child_service;
        }else{
            return '<span class="text text-danger">Empty</span>';
        }
    }
}];

function editLeadStatus(lead_id) {
    $("#modal_change_status").modal("show");
    $("#lead_id").val(lead_id);
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.leads.showleadstatus',{id: lead_id}),
        type: "GET",
        cache: false,
        success: function(response) {
            if (response.status) {
                let statuses = response.data.lead_statuses_Pdata;
                let parent_id = response.data.lead_status_parent?.id;
                let lead_status_chalid = response.data.lead_status_chalid;
                let lead_statuses_Cdata = response.data.lead_statuses_Cdata;
                let lead_status_parent = response.data.lead_status_parent;
                let status_option = '<option value="">Select a Lead Status</option>';
                if (statuses) {
                    Object.entries(statuses).forEach(function (status) {
                        status_option += '<option value="'+status[0]+'">'+status[1]+'</option>';
                    });
                }
                $("#update_status_id").html(status_option);
                $("#update_status_id").val(parent_id);
                $("#update_status_id").attr('name', 'lead_status_parent_id');
                $("#update_status_id").removeClass('d-none');
                $("#lead_status_chalid_id").addClass('d-none');
                if (lead_status_chalid != 'null') {
                    $("#lead_status_chalid_id").html(status_option);
                    $("#lead_status_chalid_id").val(lead_status_chalid.id);
                    $("#lead_status_chalid_id").attr('name', 'lead_status_child_id');
                    $("#lead_status_chalid_id").removeClass('d-none');
                    $("#update_status_id").addClass('d-none');
                }
                if (lead_status_chalid == 'null' && lead_statuses_Cdata != 'nothing') {
                    $("#lead_status_chalid_id").html(status_option);
                    $("#lead_status_chalid_id").attr('name', 'lead_status_child_id');
                    $("#lead_status_chalid_id").removeClass('d-none');
                    $("#update_status_id").addClass('d-none');
                }
                if(lead_status_parent.is_comment == '1') {
                    $("#lead_status_comment1_id").attr('name', 'comment1');
                    $("#lead_status_comment1_id").removeClass('d-none');
                }
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function updateLeadStatus() {
    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.leads.storeleadstatus',{id: $("#lead_id").val()}),
        type: "PUT",
        data: $("#modal_change_status_form").serialize(),
        cache: false,
        success: function(response) {
            if (response.status) {
                $("#modal_change_status").modal("hide");
                toastr.success(response.message);
                hideSpinnerRestForm($("#modal_change_status_form")[0]);
                reInitTable();
            } else {
                toastr.error(response.message);
                hideSpinnerRestForm();
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            hideSpinnerRestForm();
        }
    });
}

function actions(data) {
    if (typeof data.id !== 'undefined') {
        let id = data.lead_id;
        let edit_url = route('admin.leads.edit', { id: id });
        let display_url = route('admin.leads.detail', { id: id });
        let delete_url = route('admin.leads.destroy', { id: id });
        let convert_url = route('admin.leads.convert', { id: id });
        if (permissions.create || permissions.edit) {
            let actions = '<div class="dropdown dropdown-inline action-dots">';
            if (permissions.convert && lead_type === 'junk') {
                actions += '<a title="Remove From Junk" href="javascript:void(0);" onclick="removeFromJunk(`' + id + '`);" class="btn btn-icon btn-success btn-sm">\
                        <span class="navi-icon"><i class="la la-recycle"></i></span>\
                    </a>';
            }
        actions += '<a href="javascript:void(0);" class="btn btn-sm btn-clean btn-icon mr-2" data-toggle="dropdown">\
            <i class="ki ki-bold-more-hor" aria-hidden="true"></i>\
        </a>\
        <div class="dropdown-menu dropdown-menu-sm dropdown-menu-right">\
            <ul class="navi flex-column navi-hover py-2">\
                <li class="navi-header font-weight-bolder text-uppercase font-size-xs text-primary pb-2">\
                    Choose an action: \
                    </li>';
            actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="viewLead(`' + display_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">View</span>\
                    </a>\
                </li>';
            if (permissions.edit) {
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="editRow(`' + edit_url + '`, '+id+');" class="navi-link">\
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

function createLead(url) {
    $(".croxcli").click();
    $('.msg_new_patient').hide();
    $('.new_patient').prop("checked", false);
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            $("#modal_edit_regions").modal("show");
            setLeadData(response);
            setTimeout(function(){
                $("#add_phone").attr("readonly",true);
                $("#add_full_name").attr("readonly",true);
                $("#add_gender_id").attr("readonly",true);
            },500)
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(Validation);
        }
    });

}

function setLeadData(response) {
    try {
        let Services = response.data.Services;
        let cities = response.data.cities;
        let employees = response.data.employees;
        let gender = response.data.gender;
        let leadServices = response.data.leadServices;
        let lead_sources = response.data.lead_sources;
        let lead_statuses = response.data.lead_statuses;
        let lead = response.data.lead;
        let service_options = '<option value="">Select Service</option>';
        let city_options = '<option value="">Select a City</option>';
        let employee_options = '<option value="">Select a Referrer</option>';
        let gender_options = '<option value="">Select a Gender</option>';
        let lead_sources_options = '<option value="">Select a Lead Sources</option>';
        let lead_statuses_options = '<option value="">Select a Lead Status</option>';
        let town_options = '<option value="">Select a Townus</option>';
        if (Services) {
            Object.entries(Services).forEach(function (service) {
                service_options += '<option value="' + service[0] + '">' + service[1] + '</option>';
            });
        }
        if (cities) {
            Object.entries(cities).forEach(function (city) {
                city_options += '<option value="' + city[0] + '">' + city[1] + '</option>';
            });
        }
        if (employees) {
            Object.entries(employees).forEach(function (employee) {
                employee_options += '<option value="' + employee[0] + '">' + employee[1] + '</option>';
            });
        }
        if (gender) {
            Object.entries(gender).forEach(function (gender) {
                gender_options += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
            });
        }
        if (lead_sources) {
            Object.entries(lead_sources).forEach(function (source) {
                lead_sources_options += '<option value="' + source[0] + '">' + source[1] + '</option>';
            });
        }
        if (lead_statuses) {
            Object.entries(lead_statuses).forEach(function (status) {
                lead_statuses_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
            });
        }
        $("#add_service_id").html(service_options);
        $("#add_city_id").html(city_options);
        $("#add_referred_by_id").html(employee_options);
        $("#add_gender_id").html(gender_options);
        $("#add_lead_source_id").html(lead_sources_options);
        $("#add_lead_status_id").html(lead_statuses_options);
        $("#add_child_service_id").val();
        $("#add_location_id").val();
        getUserCity();
    } catch (error) {
        showException(error);
    }
}

function viewLead(url) {
    $("#modal_view_lead").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            $("#modal_edit_regions").modal("show");
            setViewData(response);
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setViewData(response) {
    try {
        let lead = response.data.lead;
        $("#full_name").text(lead?.name)
        let email = 'N/A';
        if(lead?.email) {
            email = lead?.email;
        }
        $("#email").text(email);
        let phone = 'N/A';
        if(lead?.phone) {
            phone = lead.phone;
        }
        $("#phone").text(phone);
        $("#dob").text(lead?.dob);
        let gender = 'N/A';
        if(lead?.gender) {
            gender = lead?.gender;
        }
        $("#gender").text(gender);
        let sms = 'Not Delivered';
        if(lead?.msg_count) {
            sms = 'Delivered';
        }
        $("#sms_status").text(sms);
        let address = 'N/A';
        if(lead?.address) {
            address = lead?.address;
        }
        $("#address").text(address);
        let city = 'N/A';
        if(lead?.city_id) {
            city = lead?.city?.name;
        }
        $("#city").text(city);
        let centre = 'N/A';
        if(lead?.location_id) {
            centre = lead?.towns?.name;
        }
        $("#centre").text(centre);

        let town = 'N/A';
        if(lead?.town_id) {
            town = lead?.towns?.name;
        }
        $("#town").text(town);

        lead_source = 'N/A';
        if(lead?.lead_source_id) {
            lead_source = lead?.lead_source?.name;
        }
        $("#lead_source").text(lead_source);
        let lead_status = 'N/A';
        if(lead?.lead_status_id) {
            lead_status = lead?.lead_status?.name;
        }
        $("#lead_status").text(lead_status);

        let activeservice = 'N/A';
        if(lead?.lead_service?.find(service => service.status == 1)?.service.name) {
            activeservice = lead?.lead_service?.find(service => service.status == 1)?.service.name;
        }
        $("#activeservice").text(activeservice);

        let allservices = 'N/A';
        let services = lead?.lead_service;console.log('services', services)
        let serviceNames = [];
        services.forEach(function(service){
            if(!serviceNames.includes(service.service.name)){
            serviceNames.push(service.service.name);
            }
        })
        allservices = serviceNames.join(", ");
        $("#allservices").text(allservices);

        let child = 'N/A';
        services.forEach(function(service){
            if(lead?.lead_service?.find(service => service.status == 1)?.service.name) {
                child = lead?.lead_service?.find(service => service.status == 1)?.childservice?.name;
            }
        })
        $("#childservice").text(child);
        $.ajax({
            url: route('admin.dashboard.getchild'),
            type: 'GET',
            data: {
                'child_id': child,
            },
            cache: false,
            success: function (response) {
                $("#childservice").text(response.data.child);
            },
        });
        $("#comment_lead_id").val(lead.id)
        setComments(lead);
        setServicesHistory(lead);
    } catch (error) {
        showException(error);
    }
}

function setServicesHistory(lead) {
    let services = lead?.lead_service;
    let history_html = '';
    
    if (services && services.length > 0) {
        let index = 1;
        services.forEach(function(service) {
            let serviceName = service?.service?.name ?? 'N/A';
            let treatmentName = service?.childservice?.name ?? 'N/A';
            let leadStatusName = service?.lead_status?.name ?? 'N/A';
            let status = service?.status == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>';
            let createdAt = service?.created_at ? formatDate(service.created_at, 'ddd MMM, DD yyyy') : 'N/A';
            
            history_html += '<tr>';
            history_html += '<td>' + index + '</td>';
            history_html += '<td>' + serviceName + '</td>';
            history_html += '<td>' + treatmentName + '</td>';
            history_html += '<td>' + leadStatusName + '</td>';
            history_html += '<td>' + status + '</td>';
            history_html += '<td>' + createdAt + '</td>';
            history_html += '</tr>';
            index++;
        });
    } else {
        history_html = '<tr><td colspan="6" class="text-center">No services found</td></tr>';
    }
    
    $("#services_history_table").html(history_html);
}

function viewConvert(url) {
    $("#modal_convert_lead").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            $("#modal_edit_regions").modal("show");
            setConvert(response);
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function removeFromJunk(leadId) {
    Swal.fire({
        title: 'Remove from Junk?',
        text: "This will set the lead status to Open.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.leads.remove_from_junk', { id: leadId }),
                type: "POST",
                cache: false,
                success: function(response) {
                    if (response.status) {
                        Swal.fire('Removed!', 'Lead has been removed from junk.', 'success');
                        datatable.reload();
                    } else {
                        Swal.fire('Error!', response.message || 'Failed to remove lead from junk.', 'error');
                    }
                },
                error: function(xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                }
            });
        }
    });
}

function setConvert(response) {
    try {
        resetDoctors();
        resetLocations();
        let cities = response.data.cities;
        let services = response.data.services;
        let consultancy_types = response.data.consultancy_types;
        let lead = response.data.lead;
        let user_info = response.data.user_info;
        let city_options = '<option value="">Select a City</option>';
        let service_options = '<option value="">Select a Service</option>';
        let consultancy_options = '<option value="">Select Consultancy Type</option>';
        if (cities) {
            Object.entries(cities).forEach(function (city) {
                city_options += '<option value="'+city[0]+'">'+city[1]+'</option>';
            });
        }
        if (services) {
            Object.entries(services).forEach(function (service) {
                service_options += '<option value="'+service[0]+'">'+service[1]+'</option>';
            });
        }
        if (consultancy_types) {
            Object.entries(consultancy_types).forEach(function (consultancy_type) {
                consultancy_options += '<option value="'+consultancy_type[0]+'">'+consultancy_type[1]+'</option>';
            });
        }
        $("#convert_city").html(city_options).val(lead.city_id).change();
        $("#convert_treatment_id").html(service_options).val(lead.service_id).change();
        $("#convert_consultancy_type_id").html(consultancy_options);
        $("#convert_lead_id").val(lead.id);
        $("#convert_patient_id").val(lead.patient_id);
        $("#convert_patient_phone").val(lead.patient?.phone || lead.phone || '');
        $("#convert_patient_name").val(lead.patient?.name || lead.name || '');
        $("#convert_patient_cnic").val(lead.patient?.cnic || '');
        $("#convert_patient_email").val(lead.patient?.email || lead.email || '');
        $("#convert_patient_dob").val(lead.patient?.dob || '');
        $("#convert_patient_address").val(lead.patient?.address || '');
        $("#convert_lead_source_id").val(lead.lead_source_id);
        $("#convert_referred_by").val(user_info?.referred_by || '');
        $("#convert_service_id").val(user_info?.service_id || '');
    } catch (error) {
        showException(error);
    }
}

function setComments(lead) {
    let lead_comments = lead.lead_comments;
    let comment_html = '';
    if (lead_comments.length) {
        Object.values(lead_comments).forEach(function (comment) {
            comment_html += commentData(comment?.user?.name, comment?.created_at, comment?.comment);
        });
    }
    $("#commentsection").html(comment_html);
}

function commentData(user_name, created_at, comment) {
    let comment_html = '';
    comment_html = '<div class="tab-content" id="itemComment">' +
        ' <div class="tab-pane active" id="portlet_comments_1"> ' +
        '<div class="mt-comments"> ' +
        '<div class="mt-comment">' +
        ' <div class="mt-comment-img" id="imgContainer"> ' +
        '<img src="'+asset_url+'assets/media/avatar.jpg" alt="Avatar"> ' +
        '</div><div class="mt-comment-body"> ' +
        '<div class="mt-comment-info"> ' +
        '<span class="mt-comment-author" id="creat_by">';
    comment_html += user_name ?? 'N/A';
    comment_html += '</span> <span class="mt-comment-date" id="datetime">';
     comment_html += formatDate(created_at, 'ddd MMM, DD YYYY hh:mm A');
    comment_html += '</span> </div>' +
        '<div class="mt-comment-text" id="message">';
    comment_html += comment ?? 'N/A';
    comment_html += '</div><div class="mt-comment-details"> </div>' +
        '</div></div></div></div></div>';
    return comment_html;
}

function editRow(url, id) {
    $("#modal_edit_leads").modal("show");
    $("#modal_edit_leads_form").attr("action", route('admin.leads.update', {id: id}));
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {
            setEditData(response);
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditData(response) {
    try {
        let Services = response.data.Services;
        let Childservices = response.data.child_services;
        let services = [];
        let child_services = [];
        let cities = response.data.cities;
        let locations = response.data.locations;
        let employees = response.data.employees;
        let gender = response.data.gender;
        let lead_sources = response.data.lead_sources;
        let lead_statuses = response.data.lead_statuses;
        let lead = response.data.lead;
        let service_option_select = (services == "") ? "selected" : "";
        let service_list = '';
        let child_service_list = '';
        let service_edit = '';
        let service_options = '';
        let child_service_options = '';
        let city_options = '<option value="">Select a City</option>';
        let location_options = '<option value="">Select a Location</option>';
        let employee_options = '<option value="">Select a Referrer</option>';
        let gender_options = '<option value="">Select a Gender</option>';
        let lead_sources_options = '<option value="">Select a Lead Sources</option>';
        let lead_statuses_options = '<option value="">Select a Lead Status</option>';
        if(lead){
            let parentServiceNames = [];
            let parentServiceButton = [];
            lead.lead_service.forEach(function(service) {
                service_list += '<tr>'; // Start a new row
                // Check if service name already exists in parentServiceNames array
                if (!parentServiceNames.includes(service.service.name)) {
                  parentServiceNames.push(service.service.name); // Add service name to the array
                  service_list += '<td>' + service.service.name + '</td>';
                } else {
                    service_list += '<td></td>';
                }

                if (service.child_service_id == null) {
                  service_list += '<td>N/A</td>';
                } else {
                  service_list += '<td>' + service.childservice?.name + '</td>';
                }
                // Check if service name already exists in parentServiceButton array
                if (!parentServiceButton.includes(service.service.name)) {
                    parentServiceButton.push(service.service.name); // Add service name to the array
                    if (service.consultancy_id == '' || service.consultancy_id == null) {
                        service_list += '<td><a href="javascript:void(0);" onclick="editService(' + lead.id + ', ' + service.service_id + ');" class="btn btn-primary btn-sm"><i class="la la-pencil"></i></span></a></td>';
                    } else {
                        service_list += '<td></td>'; // Empty column for services without an edit button
                    }
                } else {
                    service_list += '<td></td>';
                }
                service_list += '</tr>'; // End the row
            });

        }
        if (cities) {
            Object.entries(cities).forEach(function(city) {
                city_options += '<option value="' + city[0] + '">' + city[1] + '</option>';
            });
        }
        if (locations) {
            Object.entries(locations).forEach(function(location) {
                location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
            });
        }
        if (employees) {
            Object.entries(employees).forEach(function(employee) {
                employee_options += '<option value="' + employee[0] + '">' + employee[1] + '</option>';
            });
        }
        if (gender) {
            Object.entries(gender).forEach(function(gender) {
                gender_options += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
            });
        }
        if (lead_sources) {
            Object.entries(lead_sources).forEach(function(source) {
                lead_sources_options += '<option value="' + source[0] + '">' + source[1] + '</option>';
            });
        }
        if (lead_statuses) {
            Object.entries(lead_statuses).forEach(function(status) {
                lead_statuses_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
            });
        }
        $("#edit_service_id").html(service_options);
        $("#edit_child_service_id").html(child_service_options);
        $("#service_list_table").html(service_list);
        $("#service_edit").html(service_edit);
        $("#edit_city_id").html(city_options);
        $("#edit_location_id").html(location_options);
        $("#edit_referred_by_id").html(employee_options);
        $("#edit_gender_id").html(gender_options);
        $("#edit_lead_source_id").html(lead_sources_options);
        $("#edit_lead_status_id").html(lead_statuses_options);

        $("#edit_city_id").val(lead.city_id);
        if (lead?.location_id && lead?.location_id != 0) {
            $("#edit_location_id").val(lead?.location_id).change();
        }
        if (lead?.referred_by && lead?.referred_by != 0) {
            $("#edit_referred_by_id").val(lead?.referred_by);
        }
        if (lead?.gender && lead?.gender != 0) {
            $("#edit_gender_id").val(lead.gender);
        }
        if (lead?.lead_source_id && lead?.lead_source_id != 0) {
            $("#edit_lead_source_id").val(lead?.lead_source_id);
        }
        if (lead?.lead_status_id && lead?.lead_status_id != 0) {
            $("#edit_lead_status_id").val(lead.lead_status_id);
        }
        $("#edit_full_name").val(lead.name);
        $("#edit_lead_id").val(lead.id);
        $("#edit_old_phone").val(lead.phone).attr("readonly", true);
        if (permissions.contact) {
            $("#edit_phone").val(lead.phone).attr("readonly", true);
        } else {
            $("#edit_phone").val("***********").attr("readonly", true);
        }
    } catch (error) {
        showException(error);
    }
}
function editService(id, service_id){
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.leads.edit.service', {id: id, service_id: service_id}),
        type: "GET",
        cache: false,
        success: function(response) {
            setEditService(response.data, service_id);
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}
function setEditService(data, service_id){
    let Services = data.Services;
    let ChildService = data.Child_service;
    let Lead_service = data.lead_service;
    let service_options = '';
    let child_service_options = '';
    let serviceIDs = [];
    let childServiceIDs = [];
    let save_service = '';

    if (Lead_service) {
        Lead_service.forEach((service) => {
            const serviceID = service.service.id;
            if (!serviceIDs.includes(serviceID)) {
                serviceIDs.push(serviceID);
            }

            if (service.childservice != null) {
                childServiceIDs.push(service.childservice?.id)
            }
        });
    }

    if (Services) {
        Object.entries(Services).forEach(function(service) {
            if(jQuery.inArray(Number(service[0]), serviceIDs) != -1){
                service_options += '<option value="' + service[0] + '" selected>' + service[1] + '</option>';
            } else {
                service_options += '<option value="' + service[0] + '">' + service[1] + '</option>';
            }
        });
    }

    if (ChildService) {
        Object.entries(ChildService).forEach(function(service) {
            if(jQuery.inArray(Number(service[0]), childServiceIDs) != -1){
                child_service_options += '<option value="' + service[0] + '" selected>' + service[1] + '</option>';
            } else {
                child_service_options += '<option value="' + service[0] + '">' + service[1] + '</option>';
            }
        });
    }

    $("#edit_service_id").html(service_options);
    $("#edit_child_service_id").html(child_service_options);
    $("#edit_old_service").val(service_id);
}

function applyFilters(datatable) {
    $('#apply-filters').on('click', function() {
        let filters = {
            delete: '',
            lead_id: $("#search_id").val(),
            name: $("#search_full_name").val(),
            phone: $("#search_phone").val(),
            city_id: $("#search_city_id").val(),
            location_id: $("#search_location_id").val(),
            region_id: $("#search_region_id").val(),
            service_id: $("#search_service_id").val(),
            gender_id: $("#search_gender_id").val(),
            created_by: $("#search_created_by").val(),
            created_at: $("#date_range").val(),
            lead_status_id: $("#search_status_id").val(),
            filter: 'filter',
        }
        datatable.search(filters, 'search');
    });
}

function resetAllFilters(datatable) {
    $('#reset-filters').on('click', function() {
        let filters = {
            delete: '',
            lead_id: '',
            name: '',
            phone: '',
            city_id: '',
            region_id: '',
            service_id: '',
            created_by: '',
            date_at: '',
            lead_status_id: '',
            gender_id:'',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });
}

function setFilters(filter_values, active_filters) {
    try {
        let cities = filter_values.cities;
        let locations = filter_values.locations;
        let genders = filter_values.genders;
        let regions = filter_values.regions;
        let lead_statuses = filter_values.lead_statuses;
        let services = filter_values.Services;
        let users = filter_values.users;
        let city_options = '<option value="">All</option>';
        let location_options = '<option value="">All</option>';
        let gender_options = '<option value="">All</option>';
        let region_options = '<option value="">All</option>';
        let status_options = '<option value="">All</option>';
        let service_options = '<option value="">All</option>';
        let user_options = '<option value="">All</option>';
        if (cities) {
            Object.entries(cities).forEach(function(city) {
                city_options += '<option value="' + city[0] + '">' + city[1] + '</option>';
            });
        }
        if (locations) {
            Object.entries(locations).forEach(function(location) {
                location_options += '<option value="' + location[0] + '">' + location[1] + '</option>';
            });
        }
        if (genders) {
            Object.entries(genders).forEach(function(gender) {
                gender_options += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
            });
        }
        if (regions) {
            Object.entries(regions).forEach(function(region) {
                region_options += '<option value="' + region[0] + '">' + region[1] + '</option>';
            });
        }
        if (lead_statuses) {
            Object.entries(lead_statuses).forEach(function(status) {
                status_options += '<option value="' + status[0] + '">' + status[1] + '</option>';
            });
        }
        if (services) {
            Object.entries(services).forEach(function(service) {

                service_options += '<option value="' + service[0] + '">' + service[1] + '</option>';
            });
        }
        if (users) {
            Object.entries(users).forEach(function(user) {

                user_options += '<option value="' + user[0] + '">' + user[1] + '</option>';
            });
        }
        if (lead_type == 'junk') {
            $("#search_status_id").html('<option value="">All</option><option value="'+junk+'">Junk</option>');
        } else {
            $("#search_status_id").html(status_options);
        }
        $("#search_city_id").html(city_options);
        $("#search_region_id").html(region_options);
        $("#search_service_id").html(service_options);
        $("#search_created_by").html(user_options);
        $("#search_id").val(active_filters.lead_id);
        $("#search_full_name").val(active_filters.name);
        $("#search_phone").val(active_filters.phone);
        $("#search_city_id").val(active_filters.city_id);
        
        // Handle location dropdown based on city selection
        if (active_filters.city_id) {
            // City is selected - load city-specific locations
            // Store the location_id to restore after AJAX completes
            let savedLocationId = active_filters.location_id;
            
            // Check if locations are already loaded for this city
            let currentCityId = $("#search_city_id").val();
            if (currentCityId == active_filters.city_id && $("#search_location_id option").length > 1) {
                // Locations already loaded, just set the value
                if (savedLocationId) {
                    $("#search_location_id").val(savedLocationId);
                }
            } else {
                // Need to load locations via AJAX
                loadLocations(active_filters.city_id, '#search_location_id', false);
                // Set value after AJAX completes
                if (savedLocationId) {
                    setTimeout(function() {
                        $("#search_location_id").val(savedLocationId);
                    }, 500);
                }
            }
        } else {
            // No city selected - show all locations
            $("#search_location_id").html(location_options);
            if (active_filters.location_id) {
                $("#search_location_id").val(active_filters.location_id);
            }
        }
        
        $("#search_region_id").val(active_filters.region_id);
        $("#search_status_id").val(active_filters.lead_status_id);
        $("#search_service_id").val(active_filters.service_id);
        $("#date_range").val(active_filters.created_at);
        $("#search_created_by").val(active_filters.created_by);
        hideShowAdvanceFilters(active_filters);
        getUserCity();
    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {
    // Only check filters that are actually in the advance filters section
    // (service_id for non-junk, gender_id, created_at, created_by)
    let hasAdvanceFilter = (typeof active_filters.created_at !== 'undefined' && active_filters.created_at != '')
        || (typeof active_filters.created_by !== 'undefined' && active_filters.created_by != '')
        || (typeof active_filters.gender_id !== 'undefined' && active_filters.gender_id != '');
    
    // Service is in advance filters only for non-junk leads
    if (lead_type != 'junk') {
        hasAdvanceFilter = hasAdvanceFilter || (typeof active_filters.service_id !== 'undefined' && active_filters.service_id != '');
    }
    
    if (hasAdvanceFilter) {
        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }
}

function newLead() {

    $('.new_lead').change(function () {

        if ($(this).is(":checked")) {
            $('.lead_search_id').attr('readonly',true);
            $('.new_lead').val('1');
            $('.msg_new_lead').show();
            $("#add_phone").removeAttr("readonly");
            $("#add_full_name").removeAttr("readonly");
            if ($("#add_phone").val() != '') {
                $(".select2").val(null).trigger("change");
                $('.lead_search_id').val('');
                $("#add_phone").val('');
                $("#add_full_name").val('');

            }
            $("#add_phone").attr("readonly",false);
            $("#add_full_name").attr("readonly",false);
            $("#add_gender_id").attr("readonly",false);
        } else {
            $('.lead_search_id').attr('readonly',false);
            $('.new_lead').val('0');
            $('.msg_new_lead').hide();
            $("#add_phone").val("");
            $("#add_full_name").val("");
            $("#add_gender_id").val("");
            $("#add_phone").prop("readonly", true);
            $("#add_full_name").prop("readonly", true);
            $("#add_gender_id").attr("readonly",true);
        }
    });
}

function getLeadDetail($this) {
    $.ajax({
        type: 'get',
        url: route('admin.leads.get_lead_number'),
        data: {
            'lead_id': $this.val()
        },
        success: function (resposne) {
            if (resposne.status && resposne.data.lead) {
                lead = resposne.data.lead;
          
                $('#add_phone').val(lead?.phone);

                if (permissions.contact) {
                    $('#add_phone').val(lead?.phone);
                } else {
                    $('#add_phone').val("***********");
                }
                $('#add_full_name').val(lead?.name);
                $('#add_gender_id').val(lead?.gender).change();
                $('#add_city_id').val(lead?.city_id).change();
                if (isExist(lead?.referred_by)) {
                    $('#add_referred_by_id').val(lead?.referred_by).change();
                }
            }
        },
    });
}

function loadLead(lead) {
    if (typeof lead !== "undefined" && lead !== null) {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'post',
            url: route('admin.appointments.load_lead'),
            data: {
                'referred_by': lead.referred_by,
                'service_id': $("#create_consultancy_service").val(),
                'lead_id': lead.id,
                'phone': lead.phone,
            },
            success: function (resposne) {
                if (resposne.status) {
                    let lead_source_id = resposne.data.lead_source_id;

                    if (isExist(lead_source_id)) {
                        $('#create_consultancy_lead').val(lead_source_id).change();
                    }
                }
            },
        });
    }
}

function editInline($lead_id, city_id, $this) {
    let cities = filter_values.cities;
    let city_options = '<option value="">Select City</option>';
    Object.entries(cities).forEach( function (city) {
        city_options += '<option value="'+city[0]+'">'+city[1]+'</option>';
    });
    $(".city-editable-" + $lead_id).remove();
    let editable = '<div class="city_editable custom_tooltip form-group city-editable-'+$lead_id+'"> ' +
        '<div class="row">' +
        '<div class="city-popup">' +
        '<span class="city-title">Change City</span> ' +
        '<select class="form-control city-select"> ' +
            city_options +
        '</select> ' +
        '<div class="float-right city-edit-btn"> ' +
        '<button type="button" class="btn btn-sm btn-success spinner-button" onclick="saveCity('+$lead_id+');"><i class="fa fa-check"></i></button> ' +
        '<button type="button" class="btn btn-sm btn-danger" onclick="closeEditable('+$lead_id+')"><i class="fa fa-times"></i></button> ' +
        '</div>' +
        '<div class="arrow"></div>' +
        '</div>' +
        '</div>' +
        '</div>';
    $("#lead-" + $lead_id).parents('.datatable-row').css("margin-top", "130px");
    $("#lead-" + $lead_id).parents('td').append(editable);
    $(".city-editable-" + $lead_id).find(".city-select").val(city_id);
    $(".city-select").val($this.data("city_id"))
}

function saveCity(lead_id) {
    showSpinner();
    let city_id = $(".city-editable-" + lead_id).find('.city-select').val();
    $("#lead-" + lead_id).data("city_id", city_id);
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.leads.save_city'),
        type: "PUT",
        data: {value: city_id, pk: lead_id},
        cache: false,
        success: function(response) {
            if (response.status) {
                let city = response.data.city;
                toastr.success(response.message);
                $("#lead-" + lead_id).text(city);
                $("#lead-" + lead_id).animate({backgroundColor: "#dd0000"}, 'slow').fadeOut(500).fadeIn(500).fadeOut(500).fadeIn(500);
                closeEditable(lead_id)
            } else {
                toastr.error(response.message);
            }
            hideSpinnerRestForm();
        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            hideSpinnerRestForm();
        }
    });
}

function closeEditable($lead_id) {
    $(".city-editable-" + $lead_id).remove();
    $(".datatable-row").css("margin-top", 0);
}

$(function () {
    $(document).mouseup(function(e) {
        var container = $(".city_editable");
        if (!container.is(e.target) && container.has(e.target).length === 0) {
            container.remove();
            $(".datatable-row").css("margin-top", 0);
        }
    });
        $("#lead-1").mouseover(
            function() {
                $(this).animate({backgroundColor: "#fff"}, 'slow');
            }, function() {
                $(this).animate({backgroundColor:"#000"},'slow');
        });
    $("#Add_comment").click(function(){
        $.ajax({
            type: 'POST',
            url: route('admin.leads.storecomment'),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                'comment': $('input[name=comment]').val(),
                'lead_id': $('#comment_lead_id').val(),
            },
            success: function(data) {
                $('#commentsection').prepend(commentData(data.username, data.leadCommentDate, data.lead.comment));
            },
        });
        $('#cment')[0].reset();
    });
})


let loadLocations = function (cityId, targetSelector = '#convert_location_id', resetDoctorsFlag = true) {
    if(cityId != '') {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_locations'),
            type: 'POST',
            data: {
                city_id: cityId
            },
            cache: false,
            success: function(response) {
                if(response.status) {

                    let dropdowns =  response.data.dropdown;
                    let defaultText = targetSelector === '#search_location_id' ? 'All' : 'Select a Location';
                    let dropdown_options =  '<option value="">' + defaultText + '</option>';

                    Object.entries(dropdowns).forEach(function (dropdown) {
                        dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                    });

                    $(targetSelector).html(dropdown_options);
                    $('.select2').select2({ width: '100%' });
                    if (resetDoctorsFlag) {
                        resetDoctors();
                    }
                } else {
                    if (resetDoctorsFlag) {
                        resetDropdowns();
                    } else {
                        $(targetSelector).html('<option value="">All</option>');
                    }
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                if (resetDoctorsFlag) {
                    resetDropdowns();
                } else {
                    $(targetSelector).html('<option value="">All</option>');
                }
            }
        });
    } else {
        if (resetDoctorsFlag) {
            resetDropdowns();
        } else {
            $(targetSelector).html('<option value="">All</option>');
        }
    }
}

// Search City change handler - load centres dynamically
$(document).on('change', '#search_city_id', function() {
    let cityId = $(this).val();
    loadLocations(cityId, '#search_location_id', false);
});

// Edit City change handler - load centres dynamically
$(document).on('change', '#edit_city_id', function() {
    let cityId = $(this).val();
    loadLocations(cityId, '#edit_location_id', false);
});

// Add City change handler - load centres dynamically
$(document).on('change', '#add_city_id', function() {
    let cityId = $(this).val();
    loadLocations(cityId, '#add_location_id', false);
});

let resetLocations = function () {
    var locationDropdown = '<select id="location_id" class="form-control select2 required" name="location_id"><option value="" selected="selected">Select a Location</option></select>';
    $('#convert_location_id').html(locationDropdown);
    $('.select2').select2({ width: '100%' });
}

let resetDoctors = function () {
    var doctorDropdown = '<select id="doctor_id" class="form-control select2 required" name="doctor_id"><option value="" selected="selected">Select a Doctor</option></select>';
    $('#convert_doctor_id').html(doctorDropdown);
    $('.select2').select2({ width: '100%' });
}


let resetDropdowns = function () {
    resetLocations();
    resetDoctors();
}

let loadDoctors = function (locationId) {
    if (locationId != '' && locationId != null) {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_doctors'),
            type: 'POST',
            data: {
                location_id: locationId
            },
            cache: false,
            success: function(response) {
                if(response.status) {
                    let dropdowns =  response.data.dropdown;
                    let dropdown_options =  '<option value="">Select a Doctor</option>';
                    Object.entries(dropdowns).forEach(function (dropdown) {
                        dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                    });
                    $('#convert_doctor_id').html(dropdown_options);
                    $('.select2').select2({ width: '100%' });
                } else {
                    resetDoctors();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDoctors();
            }
        });
    } else {
        resetDoctors();
    }
}

 function importLead() {
     let form_id = 'modal_import_leads_form';
     let form = document.getElementById(form_id);
     if ($(".leads_file").val() == '') {
         addValidation($(".leads_file"))
         return false;
     }
     submitFileForm($(form).attr('action'), $(form).attr('method'), form_id, function (response) {
         if (response.status) {
             toastr.success(response.message);
             closePopup("modal_import_leads_form");
             reInitTable();
         } else {
             toastr.error(response.message);
         }
     });
}

function skipStatus($this) {
    if ($this.is(":checked")) {
        if($("#update_statuss").val() != 0){
            $("#skip_lead_statuses").attr("checked", false);
            $("#skip_lead_statuses").prop("disabled", false);
            $(".skip_lead_status").css("opacity", 1);
        }
    } else {
        $("#skip_lead_statuses").prop("disabled", true);
        $("#skip_lead_statuses").prop("checked", false);
        $(".skip_lead_status").css("opacity", 0.7);
    }
}

function skipUpdateStatus($this){
    if ($this.is(":checked")) {
        $("#update_statuss").prop("disabled", true);
        $(".update_statuss").css("opacity", 0.7);
    } else {
        $("#update_statuss").prop("disabled", false);
        $(".update_statuss").css("opacity", 1);
    }
}

function addValidation(elem) {
    if (elem.val() == '') {
        elem.addClass("is-invalid");
        $(".lead_file_msg").removeClass("d-none");
    } else {
        elem.removeClass("is-invalid");
        $(".lead_file_msg").addClass("d-none");
    }
}

function cencleLead($this) {
    $(".msg_new_lead").hide();
    $(".new_lead").prop("checked", false);
    $('.lead_search_id').attr('readonly',false);
}

function cencleImport($this) {
    $("#skip_lead_statuses").prop("disabled", true);
    $("#skip_lead_statuses").prop("checked", false);
    $(".skip_lead_status").css("opacity", 0.7);
}

$("#export-pdf-leads").on("click",function(){
    let id =$('#search_id').val();
    let name =$('#search_full_name').val();
    let phone =$("#search_phone").val()
    let city_id =$("#search_city_id").val()
    let location_id =$("#search_location_id").val()
    let region_id =$("#search_region_id").val()
    let lead_status_id =$("#search_status_id").val()
    let service_id =$("#search_service_id").val()
    let created_at =$("#date_range").val()
    let created_by =$("#search_created_by").val();
    let url = $(this).data('href');
    window.location.href =  url+'?id='+cleanId(id)+'&name='+name+'&phone='+phone+'&city_id='+city_id+'&location_id='+location_id+'&region_id='+region_id+'&lead_status_id='+lead_status_id+'&service_id='+service_id+'&created_at='+created_at+'&created_by='+created_by;
});

$("#export-leads").on("click",function(){
    let id =$('#search_id').val();
    let name =$('#search_full_name').val();
    let phone =$("#search_phone").val()
    let city_id =$("#search_city_id").val()
    let location_id =$("#search_location_id").val()
    let region_id =$("#search_region_id").val()
    let lead_status_id =$("#search_status_id").val()
    let service_id =$("#search_service_id").val()
    let created_at =$("#date_range").val()
    let created_by =$("#search_created_by").val();
    let url = $(this).data('href');
    window.location.href =  url+'?id='+cleanId(id)+'&name='+name+'&phone='+phone+'&city_id='+city_id+'&location_id='+location_id+'&region_id='+region_id+'&lead_status_id='+lead_status_id+'&service_id='+service_id+'&created_at='+created_at+'&created_by='+created_by+'&ext=xlsx';
});

$("#csv-leads").on("click",function(){
    let id =$('#search_id').val();
    let name =$('#search_full_name').val();
    let phone =$("#search_phone").val()
    let city_id =$("#search_city_id").val()
    let location_id =$("#search_location_id").val()
    let region_id =$("#search_region_id").val()
    let lead_status_id =$("#search_status_id").val()
    let service_id =$("#search_service_id").val()
    let start_date =$("#search_created_from").val()
    let end_date =$("#search_created_to").val()
    let created_by =$("#search_created_by").val();
    let url = $(this).data('href');
    window.location.href =  url+'?id='+cleanId(id)+'&name='+name+'&phone='+phone+'&city_id='+city_id+'&location_id='+location_id+'&region_id='+region_id+'&lead_status_id='+lead_status_id+'&service_id='+service_id+'&start_date='+start_date+'&end_date='+end_date+'&created_by='+created_by+'&ext=csv';
});

function cleanId(id){
    if (!id) return '';
    if (id.indexOf('c-') > -1)
    {
      return id.replace('c-','');
    }
    if (id.indexOf('C-') > -1)
    {
      return id.replace('C-','');
    }
    return id;
}
function LoadLoc()
{
    cityId = $("#search_city_id").val();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.load_locations'),
        type: 'POST',
        data: {
            city_id: cityId
        },
        cache: false,
        success: function(response) {
            if(response.status) {
                let dropdowns =  response.data.dropdown;
                let dropdown_options =  '<option value="">All</option>';
                Object.entries(dropdowns).forEach(function (dropdown) {
                    dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                });
                $('#search_location_id').html(dropdown_options);
            } else {
                $('#search_location_id').html('<option value="">All</option>');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            $('#search_location_id').html('<option value="">All</option>');
        }
    });
}

jQuery(document).ready( function () {
    $(".leads_file").change( function () {
        addValidation($(this))
    });
    $(document).on("click", ".croxcli", function () {
        $('.search_field').val('').change();
        setTimeout( function () {
            $("#add_phone").removeAttr("readonly");
            $("#add_full_name").removeAttr("readonly");
        },300);
    });
    $(document).on( "click", ".popup-close", function () {
        $(this).parents(".modal").modal("toggle");
    });
    $("#date_range").val("");
});
