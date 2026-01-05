var table_url = route('admin.patients.datatable');

var table_columns = [
    {
        field: 'id',
        sortable: false,
        width: 80,
        title: renderCheckbox(),
        template: function (data) {

            return childCheckbox(data);
        }
    },
    {
        field: 'patient_id',
        title: 'Patient ID',
        width: 'auto',
        sortable: false,
        template: function (data) {

            return makePatientId(data.id);
        }
    }, {
        field: 'name',
        title: 'Name',
        width: 'auto',
        sortable: false,
    }, {
        field: 'membership',
        title: 'Membership',
        width: 'auto',
        sortable: false,
        template: function (data) {

            if (data.membership == null) {
                return 'No Membership';
            }
            var end_date = moment(data.membership.end_date);
            var isExpired = end_date.isBefore(moment());
            var prefix = data.membership.is_referral == 1 ? 'Ref: ' : '';
            return prefix + data.membership.code + ' - ' + (isExpired ? 'Expired' : (data.membership.active === 1 ? 'Active' : 'Inactive'));
        }
    }, {
        field: 'phone',
        title: 'Phone',
        width: 90,
        sortable: false,
        template: function (data) {
            if (permissions.contact) {
                return data.phone;
            }
            return '***********';
        }
    }, {
        field: 'gender',
        title: 'Gender',
        width: 60,
        sortable: false,
        template: function (data) {
            return getGender(data.gender);
        }
    }, {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        sortable: false,
        template: function (data) {
            return formatDate(data.created_at);
        }
    }, {
        field: 'status',
        title: 'status',
        width: 70,
        sortable: false,
        template: function (data) {
            let status_url = route('admin.patients.status');
            return statuses(data, status_url);
        }
    }, {
        field: 'actions',
        title: 'Actions',
        sortable: false,
        width: 'auto',
        overflow: 'visible',
        autoHide: false,
        template: function (data) {
            return actions(data);
        }
    }];


function actions(data) {

    let id = data.id;
    let url = route('admin.patients.edit', { id: id });
    let delete_url = route('admin.patients.destroy', { id: id });
    let view_url = route('admin.patients.preview', { id: id });
    let assign_membership_url = route('admin.patients.preview', { id: id });
    let assign_voucher_url = route('admin.patients.preview', { id: id });
    let cancel_url = route('admin.memberships.cancel', { id: id });
    if (permissions.edit || permissions.delete || permissions.add_referrals || permissions.manage) {
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
                        <a href="javascript:void(0);" onclick="assignMembership(`'+ assign_membership_url + '`, `' + id + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Assign Membership</span>\
                        </a>\
                    </li>';
            actions += '<li class="navi-item">\
                    <a href="javascript:void(0);" onclick="cancelMembership(`' + cancel_url + '`);" class="navi-link">\
                    <span class="navi-icon"><i class="la la-cross"></i></span>\
                    <span class="navi-text">Cancel Membership</span>\
                    </a>\
                </li>';
                // actions += '<li class="navi-item">\
                //         <a href="javascript:void(0);" onclick="addVoucher(`'+ assign_voucher_url + '`, `' + id + '`);" class="navi-link">\
                //             <span class="navi-icon"><i class="la la-pencil"></i></span>\
                //             <span class="navi-text">Add Voucher</span>\
                //         </a>\
                //     </li>';
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="editRow(`'+ url + '`, `' + id + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-pencil"></i></span>\
                            <span class="navi-text">Edit</span>\
                        </a>\
                    </li>';
        }
        if (permissions.add_referrals) {
            actions += '<li class="navi-item">\
                        <a href="javascript:void(0);" onclick="addReferral(`' + id + '`);" class="navi-link">\
                            <span class="navi-icon"><i class="la la-user-plus"></i></span>\
                            <span class="navi-text">Add Referral</span>\
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

        if (permissions.manage) {
            actions += '<li class="navi-item">\
                            <a href="'+ view_url + '" class="navi-link">\
                            <span class="navi-icon"><i class="la la-eye"></i></span>\
                            <span class="navi-text">View</span>\
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

function editRow(url, id) {

    $("#modal_edit_patients").modal("show");
    $("#modal_edit_patients_form").attr("action", route('admin.patients.update', { id: id }));

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
            reInitValidation(EditValidation);
        }
    });


}
function assignMembership(url, id) {
    $("#modal_edit_memberships").modal("show");
    $("#modal_edit_memberships_form").attr("action", route('admin.patients.assignmembership'));
    $("#assign_membership_patient_id").val(id);
}
function addVoucher(url, id) {
    $('#edit_voucher_id').empty();
    $("#edit_amount").val('');
    var getVouchersUrl = route('admin.voucherTypes.getListing');
    $('#edit_voucher_id').append('<option value="">Select a Voucher</option>');
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: getVouchersUrl,
        type: "GET",
        cache: false,
        success: function (response) {
            Object.keys(response.data).forEach(function(voucherName) {
                var voucherId = response.data[voucherName];
                $('#edit_voucher_id').append(
                    `<option value="${voucherId}">${voucherName}</option>`
                );
            });

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(EditValidation);
        }
    });
    $("#modal_edit_vouchers").modal("show");
    $("#modal_edit_vouchers_form").attr("action", route('admin.patients.assignvoucher'));

}
function addReferral(id) {
    $("#modal_add_referral").modal("show");
    $("#modal_add_referral_form").attr("action", route('admin.patients.addreferral', { id: id }));
    $("#referral_patient_id").val(id);
    
    // Clear previous form data
    $("#referral_membership_code").val('');
    
    // Handle form submission
    $("#modal_add_referral_form").off('submit').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var url = form.attr('action');
        var formData = form.serialize();
        
        // Show loading state
        var submitBtn = form.find('button[type="submit"]');
        var originalText = submitBtn.find('.indicator-label').text();
        submitBtn.prop('disabled', true);
        submitBtn.find('.indicator-label').text('Processing...');
        
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: url,
            type: "POST",
            data: formData,
            cache: false,
            success: function (response) {
                if (response.status) {
                    toastr.success(response.message);
                    $("#modal_add_referral").modal("hide");
                    // Optionally refresh the datatable
                    reInitTable();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            },
            complete: function() {
                // Reset button state
                submitBtn.prop('disabled', false);
                submitBtn.find('.indicator-label').text(originalText);
            }
        });
    });
}
function cancelMembership(url) {

    swal.fire({
        title: 'Are you sure you want to cancel?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, Cancel!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {

        if (result.value) {

            sendCancelMembershipRequest(url)

        }
    });
}
function sendCancelMembershipRequest(route) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route,
        type: 'post',
        cache: false,
        success: function (response) {
      
            if (response.status) {
                toastr.success(response.message);

                reInitTable();
            } else {
                toastr.error(response.message);
            }
        },
        error: function (xhr) {
            errorMessage(xhr);
        }
    });
}

function setEditData(response) {

    let genders = response.data.gender;
    let patient = response.data.patient;

    let gender_option = '<option value="">All</option>';

    Object.entries(genders).forEach(function (gender) {
        gender_option += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
    });


    $("#edit_gender_id").html(gender_option);

    $("#edit_name").val(patient.name);
    $("#edit_email").val(patient.email);
    $("#edit_old_phone").val(patient.phone);

    if (permissions.contact) {
        $("#edit_phone").val(patient.phone);
    } else {
        $("#edit_phone").val("***********").attr("readonly", true);
    }

    $("#edit_gender_id").val(patient.gender);

}


function createPatient(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function (response) {
            setPatientData(response);

        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
            reInitValidation(AddValidation);
        }
    });


}

function setPatientData(response) {

    let genders = response.data.gender;
    let gender_option = '<option value="">All</option>';

    Object.entries(genders).forEach(function (gender) {
        gender_option += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
    });
    $("#add_gender_id").html(gender_option);

}

function applyFilters(datatable) {

    $('#apply-filters').on('click', function () {

        let filters = {
            delete: '',
            patient_id: $("#search_patient_id").val(),
            name: $("#search_name").val(),
            gender: $("#search_gender").val(),
            membership: $("#search_membership").val(),
            created_at: $("#date_range").val(),
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
            patient_id: '',
            name: '',
            membership: '',
            gender: '',
            created_at: '',
            status: '',
            filter: 'filter_cancel',
        }
        datatable.search(filters, 'search');
    });

}

function setFilters(filter_values, active_filters) {

    try {

        let status = filter_values.status;
        let genders = filter_values.gender;
        let memberships = filter_values.memberships;
        let status_options = '<option value="">All</option>';
        let gender_options = '<option value="">All</option>';
        let membership_options = '<option value="">All</option>';
        Object.entries(genders).forEach(function (gender, index) {
            gender_options += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
        });
        Object.entries(memberships).forEach(function (membership, index) {

            membership_options += '<option value="' + membership[1] + '">' + membership[0] + '</option>';
        });
        Object.entries(status).forEach(function (value, index) {
            status_options += '<option value="' + value[0] + '">' + value[1] + '</option>';
        });

        $("#search_status").html(status_options);
        $("#search_gender").html(gender_options);
        $("#search_membership").html(membership_options);
        $("#search_patient_id").val(active_filters.patient_id);
        $(".patient_id").val(active_filters.patient_id);
        if (active_filters.patient_id) {
            $(".croxcli").show();
        }
        $("#search_membership").val(active_filters.memberships);
        $("#search_name").val(active_filters.name);
        $("#search_status").val(active_filters.status);
        $("#search_gender").val(active_filters.gender);
        $("#date_range").val(active_filters.created_at);

        hideShowAdvanceFilters(active_filters);
    } catch (error) {
        showException(error);
    }
}

function hideShowAdvanceFilters(active_filters) {

    if ((typeof active_filters.created_at !== 'undefined' && active_filters.created_at != '')
        || (typeof active_filters.status !== 'undefined' && active_filters.status != '')
        || (typeof active_filters.gender !== 'undefined' && active_filters.gender != '')
    ) {

        $(".advance-filters").show();
        $(".advance-arrow").removeClass("fa fa-caret-right").addClass("fa fa-caret-down");
    }

}


jQuery(document).ready(function () {
    $("#date_range").val("");
    // Initialize patient search autocomplete
    initPatientSearchAutocomplete();
})

function searchPatient() {
    $('#search_patient_id').val($('.patient_id').val());
    $('.patient_id').val() ? $('.croxcli').show() : $('.croxcli').hide();
}

function initPatientSearchAutocomplete() {
    let debounceTimer;
    
    $(".patient_id").off("keyup").on("keyup", function () {
        // Update hidden field
        $('#search_patient_id').val($(this).val());
        $(this).val() ? $('.croxcli').show() : $('.croxcli').hide();
        
        $(".suggestion-list").html('<li>Searching...</li>');
        $(".suggesstion-box").show();
        
        if ($(this).val().length < 2) {
            $(".suggesstion-box").hide();
            return false;
        }
        
        var that = $(this);
        if ($(this).val() != '') {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                $.ajax({
                    type: "GET",
                    url: route('admin.patients.search'),
                    dataType: 'json',
                    data: { search: that.val() },
                    success: function (response) {
                        let html = '';
                        $(".suggestion-list").html(html);
                        let patients = response.data.patients || response.patients || [];
                        if (patients.length) {
                            patients.forEach(function (patient) {
                                html += '<li onClick="selectPatientFilter(`' + patient.name + '`, `' + patient.id + '`);">' + patient.name + ' - ' + makePatientId(patient.id) + '</li>';
                            });
                            $(".suggestion-list").html(html);
                            $(".suggesstion-box").show();
                            $(".croxcli").show();
                        } else {
                            $(".suggesstion-box").hide();
                        }
                    },
                    error: function() {
                        $(".suggesstion-box").hide();
                    }
                });
            }, 500);
        } else {
            $(".suggesstion-box").hide();
            $(".croxcli").hide();
        }
    });
}

function selectPatientFilter(name, id) {
    $(".patient_id").val(name);
    $("#search_patient_id").val(id);
    $(".suggesstion-box").hide();
    $(".croxcli").show();
}

function addUsers() {
    $(".patient_id").val('');
    $("#search_patient_id").val('');
    $(".croxcli").hide();
    $(".suggesstion-box").hide();
}
