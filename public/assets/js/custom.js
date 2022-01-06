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

               reloadDataTable();
           } else {
             toastr.error(response.message);   
           }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function updateStatus(route) {

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
                type: "PATCH",
                cache: false,
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);

                        reloadDataTable();
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    errorMessage(xhr);
                }
            });

        }
    });
}

/*functions*/

function reloadDataTable() {
    $('#kt_datatable').KTDatatable('reload');
}

function errorMessage(xhr) {
    if (xhr.status == '401') {
        toastr.error("You are not authorized to access this resource");
    } else {
        toastr.error("Unable to process your request, please try again later.");
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
        $('#kt_datatable').KTDatatable('destroy');
        KTDatatable.init();
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
