
var table_url = route('admin.refunds.datatable');

var table_columns = [
    {
        field: 'patient_id',
        title: 'ID',
        sortable: false,
        width: 70,
    },{
        field: 'name',
        title: 'Name',
        sortable: false,
        width: 70,
    },{
        field: 'package_id',
        title: 'Plans',
        sortable: false,
        width: 70,
        template: function (data) {
            if (typeof data.package_id === 'undefined' || data.package_id === null) {
                return '-';
            }
            return '<a href="javascript:void(0);" onclick="filterByPlanId(' + data.package_id + ');" class="text-primary">' + data.package_id + '</a>';
        }
    },{
        field: 'total',
        title: 'Plan Amount',
        sortable: false,
        width: 80,
    },{
        field: 'cash_receive',
        title: 'Cash receive',
        sortable: false,
        width: 80,
    },{
        field: 'settle_amount',
        title: 'Settled Amount',
        sortable: false,
        width: 80,
    },{
        field: 'refunded',
        title: 'Refund Amount',
        sortable: false,
        width: 70,
    },{
        field: 'case_setteled',
        title: 'Case Setteled',
        sortable: false,
        width: 70,
    },{
        field: 'location_id',
        title: 'Centres',
        sortable: false,
        width: 170,
    },{
        field: 'created_at',
        title: 'Created at',
        width: 'auto',
    },{
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

        let edit_url = route('admin.refunds.edit', {id: id});
        let history = route('admin.packages.display', {id: id});
        if (permissions.refund) {
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
                    <a href="javascript:void(0);" onclick="edit(`' + edit_url + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-pencil"></i></span>\
                        <span class="navi-text">edit</span>\
                    </a>\
                </li>';
            }
                actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="history(`' + history + '`);" class="navi-link">\
                        <span class="navi-icon"><i class="la la-eye"></i></span>\
                        <span class="navi-text">View History</span>\
                    </a>\
                </li>';

            actions += '</ul>\
        </div>\
    </div>';

            return actions;
        }
    }
    return '-';
}

function refund(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            refundData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(RefundValidation);
        }
    });


}
function edit(url) {
    $("#edit_refunds_modal").modal("show");
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

            reInitValidation(EditRefundValidation);
        }
    });


}
function history(url) {
    $("#edit_history_modal").modal("show");
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setHistoryData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            reInitValidation(EditRefundValidation);
        }
    });


}
function setEditData(response) {
   
    let refund = response.data;

        $("#edit_refunds_form").attr("action", route('admin.refunds.update'));


        if (refund.document) {
            $("#edit_document-label").text('Documentation Charges Already Taken');
            $("#edit_documentationcharges").hide();
        } else {
            $("#edit_document-label").text('Documentation Charges');
            $("#edit_documentationcharges").show();
        }
        $("#edit_refund_amount").val(refund.refundable_amount);
        $("#edit_documentationcharges").val(refund.documentationcharges.data);
        $("#edit_balance").val(refund.refundable_amount);
        $("#edit_refund_amount").val(refund.refunded_amount);
        $("#edit_received_amount").val(refund.cash_amount);
        $("#edit_package_id").val(refund.id);
        $("#edit_is_adjustment_amount").val(refund.is_adjustment_amount);
        $("#edit_return_tax_amount").val(refund.return_tax_amount);
        $("#patient_info").val(refund.patient_name);
        $("#edit_patients_id").val(refund.patient_id);
        $("#plan_id_1").val(refund.plan);
        $("#edit_plan_id_1").val(refund.plan);
        $("#edit_date_backend").val(refund.date_backend);
        $("#edit_created_at").val(refund.created_date);
        $("#edit_refund_note").val(refund.refund_note);
        $("#record_id").val(response.data.record_id);
        let paymentmodes = response.data.paymentmodes;
        
        let payment_options = '<option value="">Select Payment Mode</option>';
        var selected;
        if (paymentmodes) {
            
            Object.entries(paymentmodes).forEach(function (paymentmode) {
                var selected = '';  
                
                if (refund.payment_method_id == paymentmode[0]) {
                    selected = 'selected';  
                }
                
                payment_options += '<option value="' + paymentmode[0] + '" ' + selected + '>' + paymentmode[1] + '</option>';
            });
        }
        $("#edit_refund_payment_mode_id").html(payment_options);
        if(refund.package_setteled_amount > 0){
            $("#edit_case_setteled").prop('checked',true);
        }else{
            $("#edit_case_setteled").prop('checked',false);
        }

}
function setHistoryData(response){
    try {

        let packageadvances = response.data.packageadvances;
        let package = response.data.package;
        let packagebundles = response.data.packagebundles;
       

        $("#package_pdf").attr("href", route('admin.packages.package_pdf', package.id))

        let history_options = noRecordFoundTable(4);

        if (Object(packageadvances).length) {

            history_options = '';
            Object.values(packageadvances).forEach(function (packageadvance) {

                if (packageadvance.cash_amount != '0' && packageadvance.is_tax == 0) {
                    history_options += '<tr>';
                    history_options += '<td>' + packageadvance.paymentmode.name + '</td>';
                    if(packageadvance.is_refund==1){
                        history_options += '<td>out / refund</td>';
                    }else if(packageadvance.is_setteled==1){
                        history_options += '<td>out / settled</td>';
                    }
                    else{
                        history_options += '<td>' + packageadvance.cash_flow + '</td>';
                    }
                    history_options += '<td>' + packageadvance.cash_amount + '</td>';
                    history_options += '<td>' + formatDate(packageadvance.created_at, 'MMM, DD yyyy hh:mm A') + '</td>';
                    history_options += '<tr>';
                }
            });
        }
        let service_options = noRecordFoundTable(9);
        $(".plan_history").html(history_options);
        $("#user_name").text(package.user.name)
        $("#location_name").text(package.location.name)
        


    } catch (error) {
        showException(error);
    }
}
$(document).ready(function () {
   
    $("#add_patient_id_selector").on("select2:select", function (e) {
        $("#add_plan_id").empty();
        $('#add_plan_id').val(null).trigger('change');
        getplans($(this).val());
        
    });

    patientSearchRefund('search_patient_refund');

    $(document).on("click", ".croxcli", function () {
        $('.search_field').val('').change();
        $('.package_id').val(null).trigger('change');

        $('.search_patient_refund').val(null).trigger('change');
    });

});
$("#add_patients_id").on('change',function(){
    var patient_id = $('#add_patients_id').val();
    let url = route('admin.refunds.getplans');
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "post",
        data:{'patient_id':patient_id},
        cache: false,
        success: function (response) {
            let plans_options = '<option value=""> Select Plan </option>';
            Object.values(response.plans).forEach(function (value) {
                plans_options += '<option value="' + value + '"> ' + value + ' </option>';
            });
            $("#add_plan_id").html(plans_options);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);

            
        }
    });
});
function GetPlanDetail(){
    var planId = $('#add_plan_id').val();
    if(planId !== ""){
        let refund_url = route('admin.refunds.refund_create', { id: planId });
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: refund_url,
            type: "get",
            
            cache: false,
            success: function (response) {

            let refund = response.data;
              if(response.status==404){
                toastr.error("No balance available against this plan.");
                $("#received_amount").val(0);
                $("#balance").val(0);
                $("#package_id").val(refund);
              }else{
                
                let paymentmodes = response.data.paymentmodes;
                let payment_options = '<option value="">Select Payment Mode</option>';
                if (paymentmodes) {
                    Object.entries(paymentmodes).forEach(function (paymentmode) {
                        payment_options += '<option value="' + paymentmode[0] + '">' + paymentmode[1] + '</option>';
                    });
                }
                $("#modal_edit_refunds").modal("show");
        
                $("#modal_edit_refunds_form").attr("action", route('admin.refunds.store'));
        
        
                if (refund.document) {
                    $("#document-label").text('Documentation Charges Already Taken');
                    $("#documentationcharges").hide();
                } else {
                    $("#document-label").text('Documentation Charges');
                    $("#documentationcharges").show();
                }
                $("#refund_amount").html(refund.refundable_amount);
                $("#documentationcharges").val(refund.documentationcharges.data);
                $("#balance").val(refund.refundable_amount);
                $("#refund_amount").attr('max', refund.refundable_amount);
                $("#package_id").val(refund.id);
                $("#is_adjustment_amount").val(refund.is_adjustment_amount);
                $("#return_tax_amount").val(refund.return_tax_amount);
                $("#date_backend").val(refund.date_backend);
                $("#refund_payment_mode_id").html(payment_options);
                $("#received_amount").val(refund.cash_amount);
              }
                
    
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
    
                
            }
        });
    }
    
}
function refundData(response) {

    try {

        let refund = response.data;

        // if (refund.refundable_amount == 0) {
        //     $("#modal_edit_refunds").modal("hide");
        //     toastr.error("Insufficient amount to refund");
        //     return false;
        // }

        $("#modal_edit_refunds").modal("show");

        $("#modal_edit_refunds_form").attr("action", route('admin.refunds.store'));


        if (refund.document) {
            $("#document-label").text('Documentation Charges Already Taken');
            $("#documentationcharges").hide();
        } else {
            $("#document-label").text('Documentation Charges');
            $("#documentationcharges").show();
        }
        $("#refund_amount").html(refund.refundable_amount);
        $("#documentationcharges").val(refund.documentationcharges.data);
        $("#balance").val(refund.refundable_amount);
        $("#refund_amount").attr('max', refund.refundable_amount);

        $("#package_id").val(refund.id);
        $("#is_adjustment_amount").val(refund.is_adjustment_amount);
        $("#return_tax_amount").val(refund.return_tax_amount);
        $("#date_backend").val(refund.date_backend);

    } catch (error) {
        showException(error);
    }

}

function filterByPlanId(planId) {
    $("#search_plans").val(String(planId)).trigger('change');
    $('#apply-filters').trigger('click');
}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function() {

        let filters =  {
            delete: '',
            patient_id: $("#search_patient").val(),
            location_id: $("#search_centres").val(),
            package_id: $("#search_plans").val(),
            created_from: $("#search_created_from").val(),
            created_to: $("#search_created_to").val(),
            filter: 'filter',
        }

        datatable.search(filters, 'search');

    });

}

function resetAllFilters(datatable) {

    $('#reset-filters').on('click', function() {
        $("#search_plans").val('').trigger('change');
        $("#search_centres").val('').trigger('change');
        $("#search_patient").val('');
        $(".search_patient").val('');
        $("#search_created_from").val('');
        $("#search_created_to").val('');

        let filters =  {
            delete: '',
            patient_id: '',
            package_id: '',
            location_id: '',
            created_from: '',
            created_to: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {
    try {

        let patients = filter_values.patient || {};
        let locations = filter_values.locations || {};
        let pkg = filter_values.package || {};

        let location_options = '<option value="">All</option>';
        let package_options = '<option value="">All</option>';

        Object.entries(pkg).forEach(function (value) {
            package_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        Object.entries(locations).forEach(function (value) {
            location_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        $("#search_centres").html(location_options);
        $("#search_centres").val(active_filters.location_id || '').trigger('change');

        $("#search_plans").html(package_options);
        $("#search_plans").val(active_filters.package_id || '').trigger('change');

        $("#search_patient").val(active_filters.patient_id || '');
        if (active_filters.patient_id && patients[active_filters.patient_id]) {
            $(".search_patient").val(patients[active_filters.patient_id]);
        } else if (!active_filters.patient_id) {
            $(".search_patient").val('');
        }

        let createdFrom = active_filters.created_from ? String(active_filters.created_from).substring(0, 10) : '';
        let createdTo = active_filters.created_to ? String(active_filters.created_to).substring(0, 10) : '';
        $("#search_created_from").val(createdFrom);
        $("#search_created_to").val(createdTo);

        hideShowAdvanceFilters(active_filters);

        if (!active_filters.location_id) {
            getUserCentre();
        }

    } catch (err) {

    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_from !== 'undefined' && active_filters.created_from != '')
        || (typeof active_filters.created_to !== 'undefined' && active_filters.created_to != '')) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}

$(document).ready( function () {

    patientSearch('search_patient');

    $(document).on("click", ".croxcli", function () {
        $('.search_field').val('').change();
        $('.search_patient').val(null).trigger('change');
    });
    $("#date_range").val("");
});
