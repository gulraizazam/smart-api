$(document).ready(function () {

    $(document).on("change", ".select2", function () {

       if ($(this).val() != '') {
            $(this).parents(".fv-row").find(".fv-plugins-message-container").hide();
            $(this).parent(".fv-row").find(".select2-selection").removeClass("select2-is-invalid");
       } else {
            $(this).parents(".fv-row").find(".fv-plugins-message-container").show();
            $(this).parent(".fv-row").find(".select2-selection").addClass("select2-is-invalid");
       }
   });

    $(document).on( "click", ".popup-close", function () {
        $(this).parents(".modal").modal("toggle");
    })

    $('.select2').select2();

    $('.to-from-datepicker').datepicker({
        todayHighlight: true,
        templates: {
            leftArrow: '<i class="la la-angle-left"></i>',
            rightArrow: '<i class="la la-angle-right"></i>',
        },
    });


});

// not working
function resetFielsValidation() {
    $("input").parents(".fv-row").find(".fv-help-block").hide();
    $("input").parents(".fv-row").removeClass(".is-invalid");

    $("input").parents(".fv-row").find(".fv-help-block").hide();
    $("input").parent(".fv-row").find(".select2-selection").removeClass("select2-is-invalid");
}


function deleteSuccessAndReset(data, datatable) {
    $(".delete-records").addClass("d-none");
   if (data.status) {
       toastr.success(data.message);
   } else {
       toastr.error(data.message);
   }
}

function deleteRow(route) {
    deleteConfirm(null, route);
}

function deleteConfirm(datatable = null, route = null) {
    swal.fire({
        title: 'Are you sure you want to delete?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function(result) {
        if (result.value) {
            if (datatable) {
                let filters =  {
                    delete: row_ids.join(','),
                }
                datatable.search(filters, 'search');
            }
            if (route) {
                sendDeleteRequest(route)
            }
        }
    });
}

function sendDeleteRequest(route) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route,
        type: "DELETE",
        cache: false,
        success: function (response) {
           if (response.status) {
               toastr.success(response.message);

               reInitTable();
           } else {
             toastr.error(response.message);
           }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function statuses(data, status_url) {

    let id = data.id;
    let active = data.active;
    let status = '';

    if (active) {
        if (permissions.active && permissions.inactive) {
            status += '<span class="switch switch-icon">\
            <label>\
                <input value="1" onchange="updateStatus(`'+status_url+'`, `'+id+'`, $(this));" type="checkbox" checked="checked" name="select">\
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
            <input value="1" onchange="updateStatus(`'+status_url+'`, `'+id+'`, $(this));" type="checkbox" name="select">\
            <span></span>\
        </label>\
        </span>';
    }

    return status;
}

function updateStatus(route, id, $this) {

    swal.fire({
        title: 'Are you sure you want to change?',
        type: 'danger',
        icon: 'info',
        buttonsStyling: false,
        confirmButtonText: 'Yes, change!',
        cancelButtonText: 'No',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-primary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function(result) {
        if (result.value) {

           $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route,
                data: {id: id, status: $this.is(":checked") ? '1' : '0'},
                type: "POST",
                cache: false,
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                }
            });

        } else {
            if ($this.is(":checked")) {
                $this.prop("checked", false);
            } else {
                $this.prop("checked", true);
            }

        }
    });
}

/*functions*/


function errorMessage(xhr) {
    if (xhr.status == '401') {
        toastr.error("You are not authorized to access this resource");
    } else {
        toastr.error(xhr.responseJSON.message);
    }
}

function reInitSelect2(elem, title = 'Select') {
    $(elem).select2({
        placeholder: 'Select'
    });
}

function autoFocusFields(validate) {
    var fields = validate.getFields();
    fields = Object.keys(fields).reverse();
    $(fields).each(function(index, field) {
        $("input[name='"+field+"']").focus();
        return false;
    });
}

function reInitValidation(validate) {
    validate.init();
}

function select2Validation() {
    $(".is-invalid").parent(".fv-row").find(".select2-selection").addClass("select2-is-invalid");
}

function closePopup(modal) {
    $("#" + modal).parents(".modal").modal("hide");
}

function reInitTable() {

    setTimeout(function () {
        /**
        /*@reload has bug so we can't use this
         */
        //$('#kt_datatable').KTDatatable('reload');

        /*this is for reload datatable*/
        datatable.search({ datatable_reload: 'reload' }, 'search');

    }, 400);
}

function getAllFilterValues() {

}

function resetFilters() {
    $(".filter-field").val('');
    $(".select2").select2({
        placeholder: 'Select'
    });
}

function advanceFilters() {
    $(".advance-filters").slideToggle();
    $(".advance-arrow").toggleClass("fa-caret-right").toggleClass("fa-caret-down")
}

function phoneReset(className) {
    $("." + className).val('');
}

function showPreLoader(){
    $('.page-loader-base').show();
}

function hidePreLoader(){
    $('.page-loader-base').hide();
}

function showSpinner() {
    $(".spinner-button").addClass("spinner spinner-white spinner-right mr-3").prop('disabled', true);
}

function hideSpinnerRestForm(form = null) {
    $(".spinner-button").removeClass("spinner spinner-white spinner-right mr-3").prop('disabled', false);
   if (form) {
       form.reset();
   }
    $(".image-input-wrapper").css('background-image', "url()");
    $(".image-input-wrapper").parent(".image-input").find("span").removeClass("btn-shadow");
    $("#complimentary").addClass("d-none");
}

function submitForm(action, method, data, callback, form = '') {

    showSpinner();
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: action,
        type: method,
        data: data,
        cache: false,
        success: function (response) {
            if (response.status == true) {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm(form);
            } else {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                // hideSpinnerRestForm(form);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {
                callback({
                    'status': 0,
                    'message': 'You are not authorized to access this resource',
                });
                hideSpinnerRestForm(form);
            } else {
                callback({
                    'status': 0,
                    'message': 'Unable to process your request, please try again later.',
                });
                hideSpinnerRestForm(form);
            }
        }
    });
}

function submitFileForm(action, method, form_id, callback) {

    showSpinner();

    var form = $('#' + form_id)[0];

    var data = new FormData(form);

    let files = $('#file')[0].files;
    if(files.length){
        data.append('file',files[0]);
    }

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: action,
        type: method,
        data: data,
        contentType: false,
        processData: false,
        cache: false,
        success: function (response) {
            if (response.status == true) {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm(form);
            } else {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm(form);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {
                callback({
                    'status': 0,
                    'message': 'You are not authorized to access this resource',
                });
                hideSpinnerRestForm(form);
            } else {
                callback({
                    'status': 0,
                    'message': 'Unable to process your request, please try again later.',
                });
                hideSpinnerRestForm(form);
            }
        }
    });
}

function renderCheckbox() {
    return '<label class="custom_checkbox checkbox-all"><input class="select-all-checkboxes" type="checkbox"><strong></strong></label>';
}

function childCheckbox(data) {
    return '<label class="checkbox checkbox-single checkbox-all"><input value="'+data.id+'" class="table-checkboxes" type="checkbox">&nbsp;<span></span></label>';
}


function switchComplimentary($id) {
    $("#" + $id).toggleClass("d-none");
}

function showException(error) {
    if (debug) {
        toastr.error(error);
        console.log(error);
    }
}
