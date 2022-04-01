$(document).ready(function () {

    $(document).on("change", ".select2", function () {

       if ($(this).val() != '') {
            $(this).parents(".fv-row").find(".fv-plugins-message-container").find(".fv-help-block").hide();
            $(this).parent(".fv-row").find(".select2-selection").removeClass("select2-is-invalid");
       } else {
            $(this).parents(".fv-row").find(".fv-plugins-message-container").find(".fv-help-block").show();
            $(this).parent(".fv-row").find(".select2-selection").addClass("select2-is-invalid");
       }
   });

    $(document).on( "click", ".popup-close", function () {
        $(this).parents(".modal").modal("toggle");
    })

    $('.select2').select2();

    $('.to-from-datepicker').datepicker({
        todayHighlight: true,
        format: 'yyyy-mm-dd',
        orientation: 'bottom',
        templates: {
            leftArrow: '<i class="la la-angle-left"></i>',
            rightArrow: '<i class="la la-angle-right"></i>',
        },
    });

    customDatePicker();

    $('.current-datepicker').datepicker({
        todayHighlight: true,
        orientation: 'bottom',
        startDate: new Date(),
        format: 'yyyy-mm-dd',
        templates: {
            leftArrow: '<i class="la la-angle-left"></i>',
            rightArrow: '<i class="la la-angle-right"></i>',
        },
    }).datepicker("setDate", new Date());

    $('.timepicker').timepicker({timeFormat: 'h:mm:ss p'}).timepicker("setTime", new Date());


    /*for percentage amount*/

    $(".group_slug").click( function () {
        if ($(this).val() === 'birthday') {
            $(".birthday_range").removeClass("d-none");
        } else {
            $(".birthday_range").addClass("d-none");
        }
    });

    $(".edit_group_slug").click( function () {
        if ($(this).val() === 'birthday') {
            $(".edit_birthday_range").removeClass("d-none");
        } else {
            $(".edit_birthday_range").addClass("d-none");
        }
    });

    $("#add_amount_type").change( function () {

        if ($(this).val() === 'Percentage') {
            $("#add_amount").attr("max", 100);
            if ($("#add_amount").val() > 100) {
                $("#add_amount").val("");
            }
            $("#add_amount").attr("max", 100);
        } else {
            $("#add_amount").removeAttr("max");
        }
    });

    $("#add_amount").on("keyup", function() {

        if ($(this).attr("max") == 100) {
            var val = parseInt(this.value);
            if(val > 100 || val < 0)
            {
                this.value ='';
                toastr.error("For percentage type, amount is not allowed greater than 100");
            }
        }

    })

    $("#edit_amount_type").change( function () {

        if ($(this).val() === 'Percentage') {
            $("#edit_amount").attr("max", 100);
            if ($("#edit_amount").val() > 100) {
                $("#edit_amount").val("");
            }
            $("#edit_amount").attr("max", 100);
        } else {
            $("#edit_amount").removeAttr("max");
        }
    });

    $("#edit_amount").on("keyup", function() {

        if ($(this).attr("max") == 100) {
            var val = parseInt(this.value);
            if(val > 100 || val < 0)
            {
                this.value ='';
                toastr.error("For percentage type, amount is not allowed greater than 100");
            }
        }

    });

    patientSearch();

    $(".package_id").select2({
        width: '100%',
        placeholder: 'Select Plan',
        ajax: {
            url: route('admin.packages.getpackage'),
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;

                return {
                    results: $.map(data, function (item) {
                        return {
                            text: item.name,
                            id: item.id
                        }
                    }),
                };
            },
            cache: true
        },
        escapeMarkup: function (markup) {
            return markup;
        },
        minimumInputLength: 1,
        templateResult: formatRepo,
        templateSelection: formatRepoSelection
    });

    /*input mask*/
    $(".cnic-mask").inputmask("99999-9999999-9", {
        placeholder: "XXXXX-XXXXXXX-X",
        clearMaskOnLostFocus: true
    });

    /*Copy to clipboard*/
    var clipboard = new ClipboardJS('.clipboard');
    clipboard.on('success', function(e) {
        e.clearSelection();
        toastr.info("phone is copied to clipboard.")
    });

    $("body").click(function () {
        $(".modal_consultancy_popup").hide();
    });

    $('.default-timepicker').timepicker();
    $('#edit_scheduled_time').on('click', function() {

        $('.default-timepicker').text($("#edit_scheduled_time").val());
    });

});

function customDatePicker() {

    $('.custom-datepicker').datepicker({
        todayHighlight: true,
        orientation: 'bottom',
        format: 'yyyy-mm-dd',
        templates: {
            leftArrow: '<i class="la la-angle-left"></i>',
            rightArrow: '<i class="la la-angle-right"></i>',
        },
    });
}

function addUsers() {
    $('.patient_id').val(null).trigger('change');
    $('.patient_search_id').val(null).trigger('change');
}

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

function deleteRow(route, method = "DELETE", tableClass = null) {
    deleteConfirm(null, route, method, tableClass);
}

function deleteConfirm(datatable = null, route = null, method, tableClass = null) {

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
            if (tableClass) {
                patientDatatable[tableClass].search({ datatable_reload: 'reload' }, 'search');
            }
            if (route) {
                sendDeleteRequest(route, method)
            }
        }
    });
}

function sendDeleteRequest(route, method) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route,
        type: method,
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

function closeAllPopup(modal) {
    $(modal).parents(".modal").modal("hide");
}

function reInitTable() {

    setTimeout(function () {
        /**
        /*@reload has bug so we can't use this
         */
        //$('#kt_datatable').KTDatatable('reload');

        /*this is for reload datatable*/
        if (typeof datatable !== 'undefined') {
            datatable.search({ datatable_reload: 'reload' }, 'search');
        }

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

function inputSpinner(show = true, elem = 'AddPackage') {
    if (show) {
        $("#" + elem ).prop("disabled", true).addClass("disabled-btn");
        $(".input-spinner").show();
    } else {
        $("#" + elem ).prop("disabled", false).removeClass("disabled-btn");
        $(".input-spinner").hide();
    }
}

function showSpinner(suffix = '') {
    $(".spinner-button" + suffix).addClass("spinner spinner-white spinner-right mr-3").prop('disabled', true);
}

function hideSpinner(suffix = '') {
    $(".spinner-button" + suffix).removeClass("spinner spinner-white spinner-right mr-3").prop('disabled', false);
}

function hideSpinnerRestForm(form = null, imageReset = false) {
    $(".spinner-button").removeClass("spinner spinner-white spinner-right mr-3").prop('disabled', false);
   if (form) {
       form.reset();
   }
   if (!imageReset) {
       $(".image-input-wrapper").css('background-image', "url()");
       $(".image-input-wrapper").parent(".image-input").find("span").removeClass("btn-shadow");
   }
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
                 hideSpinnerRestForm();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {
                callback({
                    'status': 0,
                    'message': 'You are not authorized to access this resource',
                });
                hideSpinnerRestForm();
            } else {
                callback({
                    'status': 0,
                    'message': 'Unable to process your request, please try again later.',
                });
                hideSpinnerRestForm();
            }
        }
    });
}

function submitFileForm(action, method, form_id, callback, no_reset = false) {

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
            if (response.status) {
                callback({
                    'status': response.status,
                    'message': response.message,
                    'data': response?.data ?? null,
                });

                if (no_reset) {
                    hideSpinnerRestForm(null, true);
                } else {
                    hideSpinnerRestForm(form);
                }
            } else {
                callback({
                    'status': response.status,
                    'message': response.message,
                });
                hideSpinnerRestForm();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (xhr.status == '401') {
                callback({
                    'status': 0,
                    'message': 'You are not authorized to access this resource',
                });
                hideSpinnerRestForm();
            } else {
                callback({
                    'status': 0,
                    'message': 'Unable to process your request, please try again later.',
                });
                hideSpinnerRestForm();
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

function noRecordFoundTable(colspan) {
    return '<tr class="text-center"><td colspan="'+colspan+'">No record found</td></tr>';
}

function phoneField($this) {
   return $this.value = $this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
}

function formatDate(date, format = 'ddd MMM, DD yyyy HH:mm A') {
    return moment(date).format(format);
}

function getGender(gender_id) {

    try {

        if (typeof filter_values.gender !== 'undefined' && typeof filter_values.gender !== 'undefined') {
            return Object(filter_values.gender)[gender_id];
        }

        return gender_id == 1 ? 'Male' : 'Female';

    } catch (e) {
       return gender_id == 1 ? 'Male' : 'Female';
    }


}

function makePatientId(id) {

    return "C-" + id;
}

function makeArray(object) {

    let array = [];

   Object.entries(object).forEach(function (value) {
       array[value[0]] = value[1];
   });

  return array

}

function phoneClip(data) {
    return '<a title="Click to Copy" href="javascript:void(0);" class="clipboard" data-toggle="tooltip" title="" data-clipboard-text="'+data.phone+'" data-original-title="Click to Copy" aria-describedby="tooltip'+data.id+'">'+data.phone+'</a>';
}

function makePhoneNumber(phoneNo, permission, type = 0) {

    if (typeof phoneNo !== "undefined") {

        if (!permission) {
            return '***********';
        } else {
            if (phoneNo[0] == '3' && phoneNo.length == 10 && type == 0) {
                return '+92' + phoneNo;
            } else if (phoneNo[0] == '3' && phoneNo.length == 10 && type == 1) {
                return '0' + phoneNo;
            } else {
                return phoneNo;
            }
        }
    }

    return phoneNo;
}

function setQueryStringParameter(name, value = null) {

    const params = new URLSearchParams(window.location.search);
    if (value) {
        params.set(name, value);
    } else {
        params.delete(name);
    }
    window.history.replaceState({}, "", decodeURIComponent(`${window.location.pathname}?${params}`));
}

function get_query(){
    var url = document.location.href;
    var qs = url.substring(url.indexOf('?') + 1).split('&');
    for(var i = 0, result = {}; i < qs.length; i++){
        qs[i] = qs[i].split('=');
        result[qs[i][0]] = decodeURIComponent(qs[i][1]);
    }
    return result;
}

function patientSearch(search_id = 'patient_id') {

    $("." + search_id).select2({
        width: '100%',
        placeholder: 'Select Patient',
        ajax: {
            url: route('admin.users.getpatient.id'),
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page
                };
            },
            processResults: function (response, params) {

                try {
                    let data = response.data.patients;

                    params.page = params.page || 1;
                    return {
                        results: $.map(data, function (item) {

                            return {
                                text: item.name + ' - ' + item.id,
                                id: item.id
                            }
                        }),
                    };

                } catch (error) {
                    showException(error);
                }
            },
            cache: true
        },
        escapeMarkup: function (markup) {
            return markup;
        },
        minimumInputLength: 3,
        templateResult: formatRepo,
        templateSelection: formatRepoSelection
    });

}

function formatRepo(item) {
    if (item.loading) {
        return item.text;
    }
    markup = item.text;
    return markup;
}

function formatRepoSelection(item) {
    if (item.id) {
        return item.text + " <span onclick='addUsers()' class='croxcli' style='float: right;border: 0; background: none;padding: 0 0 0;'><i class='fa fa-times' aria-hidden='true'></i></span>";
    } else {
        return 'Select Patient';
    }
}

function reInitCalendar(start, calendarInit, calendarInstance) {
    if (typeof calendarInit !== "undefined") { /*if already initiate then destroy first*/
        calendarInit.destroy();
        calendarInstance.init(start);
    }
}


function trashBtn() {
    return '<span class="svg-icon svg-icon-md svg-icon-danger"> ' +
        '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"> <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <rect x="0" y="0" width="24" height="24"></rect>' +
        ' <path d="M6,8 L6,20.5 C6,21.3284271 6.67157288,22 7.5,22 L16.5,22 C17.3284271,22 18,21.3284271 18,20.5 L18,8 L6,8 Z" fill="#000000" fill-rule="nonzero"></path> ' +
        '<path d="M14,4.5 L14,4 C14,3.44771525 13.5522847,3 13,3 L11,3 C10.4477153,3 10,3.44771525 10,4 L10,4.5 L5.5,4.5 C5.22385763,4.5 5,4.72385763 5,5 L5,5.5 C5,5.77614237 5.22385763,6 5.5,6 L18.5,6 C18.7761424,6 19,5.77614237 19,5.5 L19,5 C19,4.72385763 18.7761424,4.5 18.5,4.5 L14,4.5 Z" fill="#000000" opacity="0.3"></path>' +
        ' </g> ' +
        '</svg> ' +
        '</span>';
}

function toggleText($this) {
    $this.find(".full_text").toggle();
    $this.find(".short_text").toggle();
}

function resendSMS(smsId, url, method = 'PUT') {

    showSpinner();

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: method,
        data: {
            id: smsId
        },
        cache: false,
        success: function (response) {
            if (response.status) {
                $('#spanRow' + smsId).text('Yes');
                $('#clickRow' + smsId).hide();
                toastr.success(response.message)
            } else {
                $('#clickRow' + smsId).show();
                $('#spanRow' + smsId).text('No');
                toastr.error(response.message)
            }
            hideSpinnerRestForm();
        }
    });
}

function removeExtraSelect2(elem = 'create_treatment_patient_search') {
    $("#" + elem).parent("div").find(".selection").remove();
}
