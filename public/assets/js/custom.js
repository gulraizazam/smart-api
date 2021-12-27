$(document).ready(function () {

    $(document).on("change", ".select2", function () {

       if ($(this).val() != '') {
           $(".is-invalid").parents(".form-group").find(".fv-plugins-message-container").hide();
           $(".is-invalid").parent(".fv-row").find(".select2-selection").removeClass("select2-is-invalid");
       } else {
           $(".is-invalid").parents(".form-group").find(".fv-plugins-message-container").hide();
           $(".is-invalid").parent(".fv-row").find(".select2-selection").addClass("select2-is-invalid");
       }
   });

    $(document).on( "click", ".popup-close", function () {
        $(this).parents(".modal").modal("toggle");
    })
});

function deleteSuccessAndReset(data, datatable) {
    $(".delete-records").addClass("d-none");
    toastr.info(data.message);
    //datatable.search([], 'delete');
    window.location.reload();

}

function deleteRow(id) {
    deleteConfirm(null, $("#delete-row-form-" + id));
}

function deleteConfirm(datatable = null, $form = null) {
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
                datatable.search(row_ids.join(','), 'delete');
            }
            if ($form) {
                $form.submit();
            }
        }
    });
}


/*functions*/
function reInitSelect2(elem, title) {
    $(elem).select2({
        placeholder: title
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
