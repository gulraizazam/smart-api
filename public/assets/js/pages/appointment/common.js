jQuery('#treatment_city_filter').on('change', function(){
    loadLocations($(this).val(), 'treatment');
});
jQuery('#treatment_location_filter').on('change', function(){
    loadDoctors($(this).val(), 'treatment');
});
jQuery('#treatment_doctor_filter').on('change', function(){
    treatmentDoctorListener($(this).val());
});
jQuery('#treatment_resource_filter').on('change', function(){
    machineListener($(this).val());
});


function toggleSection($this, $class) {

    /*$(".menu-item").removeClass('menu-item-active');
    $(".manage-" + $class).addClass('menu-item-active');*/



    if ($class !== 'consultancy') {

        /*setQueryStringParameter('city_id');
        setQueryStringParameter('location_id');
        setQueryStringParameter('doctor_id');
        setQueryStringParameter('machine_id');

        $("#consultancy_city_filter").val('').trigger("change")
        $("#consultancy_location_filter").val('').trigger("change")
        $("#consultancy_doctor_filter").val('').trigger("change")*/
    }

    if ($class == 'appointment') {
        $(".export-appointments").show();
        setQueryStringParameter('tab', $class);
        let url = window.location.href;
        const lastSegment = url.split("/").pop();
        var appointment = 'treatment';
       if (lastSegment == 'treatment?tab=appointment') {
           appointment = 'treatment';
       } else {
           appointment = 'consultancy';
       }
        reInitTable(appointment);
    } else {
        $(".export-appointments").hide();
        setQueryStringParameter('tab', $class);
    }

    $(".appointment").addClass("d-none");
    $("." + $class + "-section").removeClass("d-none");

    $(".change-tab").removeClass("nav-bar-active");
    $this.addClass("nav-bar-active");

    $(".change-label").text($this.text());

    // Show/hide location dropdown in header based on active tab
    if ($class == 'treatment') {
       
        $(".treatment-location-header-dropdown").removeClass("d-none");
        $(".consultancy-location-header-dropdown").addClass("d-none");
        // Initialize select2 and load locations for treatment dropdown
        setTimeout(function() {
         
            $('#treatment_location_filter').select2({ width: '100%' });
            if ($('#treatment_location_filter option').length <= 1) {
                
                loadLocations('', 'treatment');
            } else {
                console.log('Locations already loaded, skipping');
            }
        }, 200);
    } else if ($class == 'consultancy') {
        
        $(".consultancy-location-header-dropdown").removeClass("d-none");
        $(".treatment-location-header-dropdown").addClass("d-none");
        // Initialize select2 and load locations for consultancy dropdown
        setTimeout(function() {
           
            $('#consultancy_location_filter').select2({ width: '100%' });
            if ($('#consultancy_location_filter option').length <= 1) {
                
                loadLocations('', 'consultancy');
            } else {
                console.log('Locations already loaded, skipping');
            }
        }, 200);
    } else {
        $(".treatment-location-header-dropdown").addClass("d-none");
        $(".consultancy-location-header-dropdown").addClass("d-none");
    }

    if ($class != 'appointment') {
        setDashboardFilters();

        getUserCity();
    }

    // Auto-trigger calendar for users with single centre when switching to consultancy tab
    if ($class == 'consultancy' && typeof autoTriggerCalendarForSingleCentre === 'function') {
        setTimeout(function() {
            autoTriggerCalendarForSingleCentre();
        }, 800);
    }

}

window.loadLocations = function (cityId, appointment = null) {
    let url = window.location.href;
    const lastSegment = url.split("/").pop();
    if (lastSegment.includes('treatment')) {
        appointment = 'treatment';
    } else {
        appointment = 'consultancy';
    }
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
                let dropdown_options =  '<option selected="selected" disabled value="">Select a Location</option>';

                Object.entries(dropdowns).forEach(function (dropdown) {
                    dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                });

                let result = get_query();

                if (appointment && appointment == 'consultancy') {
                    $('#consultancy_location_filter').html(dropdown_options);
                    setQueryStringParameter('city_id', cityId);

                    if (typeof result.location_id !== "undefined") {
                        $("#consultancy_location_filter").val(result.location_id).change();
                    }
                } else  if (appointment && appointment == 'treatment') {
                    $('#treatment_location_filter').html(dropdown_options);
                    setQueryStringParameter('city_id', cityId);

                    if (typeof result.location_id !== "undefined") {
                        $("#treatment_location_filter").val(result.location_id).change();
                    }
                } else {
                    $('#edit_location').html(dropdown_options);
                }
                // $('.select2').select2({ width: '100%' });
                resetDoctors();
            } else {
                resetDropdowns();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            resetDropdowns();
        }
    });
}
window.loadEditTreatmentLocations = function (cityId, appointment = null) {
    if(cityId != '' ) {
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
                    let dropdown_options =  '<option value="">Select a Location</option>';
                    Object.entries(dropdowns).forEach(function (dropdown) {
                        dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                    });
                    $('#edit_treatment_location_id').html(dropdown_options);
                    setQueryStringParameter('city_id', cityId);
                    resetDoctors();
                } else {
                    resetDropdowns();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDropdowns();
            }
        });
    } else {
        resetDropdowns();
    }
}
window.loadEditConsultancyLocations = function (cityId, appointment = null) {
    if(cityId != '' ) {
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
                    let dropdown_options =  '<option value="">Select a Location</option>';
                    Object.entries(dropdowns).forEach(function (dropdown) {
                        dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                    });
                    $('#edit_location').html(dropdown_options);
                    setQueryStringParameter('city_id', cityId);
                    resetDoctors();
                } else {
                    resetDropdowns();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDropdowns();
            }
        });
    } else {
        resetDropdowns();
    }
}
var something = (function() {
    var executed = false;
    return function() {
        if (!executed) {
            executed = true;
            setTimeout( function () {
                TreatmentCalendar.init();
            }, 500);
        }
    };
})();
window.loadDoctors = function (locationId, appointment = null) {
   
  
    if (locationId != '' && locationId != null) {
        $('#treatment_doctor_filter').removeAttr('disabled');
        $('#treatment_resource_filter').removeAttr('disabled');
        $('#consultancy_doctor_filter').removeAttr('disabled');
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

                    let result = get_query();
                    if (appointment && appointment == 'consultancy') {
                        $('#consultancy_doctor_filter').html(dropdown_options);
                        setQueryStringParameter('location_id', locationId);
                        if (typeof calendar !== "undefined") { /*if already initiate then destroy first*/
                            calendar.destroy();
                        }
                        var result02 = get_query();

                        if ($("#consultancy_location_filter").val() !== ""
                            && typeof result02.tab !== 'undefined' && result02.tab == 'consultancy') {
                            window.eventData = {};
                            window.eventData.id = null;
                            window.eventData.firstTime = true;
                            ConsultancyCalendar.init();
                        }
                        if (typeof result.doctor_id !== "undefined") {
                            $("#consultancy_doctor_filter").val(result.doctor_id).change();
                        }

                        var result3 = get_query();

                        if (
                             $("#consultancy_location_filter").val() !== ""
                            && typeof result3.tab !== 'undefined' && result3.tab == 'treatment') {
                            window.eventData = {}
                            window.eventData.location_id = $("#consultancy_location_filter").val()
                            window.eventData.doctor_id = $("#consultancy_doctor_filter").val();
                            window.eventData.id = null;
                            window.eventData.firstTime = true;
                            if($('#consultancy_location_filter option').length == 2){
                                something();
                            } else{
                                setTimeout( function () {
                                    ConsultancyCalendar.init();
                                }, 500);
                            }
                        }
                    } else if (appointment && appointment == 'treatment') {
                        if (typeof treatment_calendar !== "undefined" && $('#treatment_location_filter option').length > 2) { /*if already initiate then destroy first*/
                            treatment_calendar.destroy();
                        }
                        $('#treatment_doctor_filter').html(dropdown_options);
                        if(typeof result.machine_id == "undefined"){
                            setQueryStringParameter('doctor_id', '');
                        }

                        if(result.location_id !== jQuery('#treatment_location_filter').val()){
                            setQueryStringParameter('doctor_id', '');
                            setQueryStringParameter('machine_id', '');
                        }
                        setQueryStringParameter('location_id', locationId);
                        loadMachine(locationId);
                        var result3 = get_query();
                        if (
                             $("#treatment_location_filter").val() !== ""
                            && typeof result3.tab !== 'undefined' && result3.tab == 'treatment') {
                            
                            // Create doctors list for resource view
                            var doctorsList = [];
                            Object.entries(dropdowns).forEach(function (dropdown) {
                                doctorsList.push({
                                    id: dropdown[0],
                                    name: dropdown[1]
                                });
                            });
                            
                            window.eventData = {}
                            window.eventData.location_id = $("#treatment_location_filter").val()
                            window.eventData.doctor_id = $("#treatment_doctor_filter").val();
                            window.eventData.id = null;
                            window.eventData.firstTime = true;
                            
                            // Initialize treatment calendar with resource view (doctors as resources)
                            if (typeof TreatmentResourceCalendar !== 'undefined') {
                                TreatmentResourceCalendar.init(doctorsList);
                            } else {
                                // Fallback to regular calendar if resource calendar not available
                                if($('#treatment_location_filter option').length == 2){
                                    something();
                                } else{
                                    setTimeout( function () {
                                        TreatmentCalendar.init();
                                    }, 500);
                                }
                            }
                        }
                        if (typeof result.doctor_id !== "undefined" && typeof result.reload === "undefined") {
                        }
                    } else {
                        $('#edit_doctor').html(dropdown_options);
                    }

                } else {
                    resetDoctors();
                }
                setQueryStringParameter('reload');
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDoctors();
            }
        });
    } else {
        resetDoctors();
    }

}

// Load machines for treatment appointments
window.loadMachine = function(locationId) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.center_machines', {
            location_id: locationId,
        }),
        type: 'GET',
        data: {
            location_id: locationId
        },
        cache: false,
        success: function(response) {
            if(response.status) {
                let dropdowns =  response.data.dropdown;
                let dropdown_options =  '<option value="">Select a Machine</option>';

                Object.entries(dropdowns).forEach(function (dropdown) {
                    dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                });

                let result = get_query();

                $('#treatment_resource_filter').html(dropdown_options);

                if (typeof result.doctor_id !== "undefined" && $("#treatment_doctor_filter").val() === '') {
                    $("#treatment_doctor_filter").val(result.doctor_id).select2();
                }

                if (typeof result.machine_id !== "undefined" && $("#treatment_resource_filter").val() === '') {
                    $("#treatment_resource_filter").val(result.machine_id).select2();
                }
            } else {
                resetDoctors();
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            resetDoctors();
        }
    });
}

window.loadConsultantDoctors = function (locationId, appointment = null) {

    if (locationId != '' && locationId != null) {
        $('#treatment_doctor_filter').removeAttr('disabled');
        $('#treatment_resource_filter').removeAttr('disabled');
        $('#consultancy_doctor_filter').removeAttr('disabled');
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_consultant_doctors'),
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

                    let result = get_query();
                    if (appointment && appointment == 'consultancy') {
                        $('#consultancy_doctor_filter').html(dropdown_options);
                        setQueryStringParameter('location_id', locationId);
                        if (typeof calendar !== "undefined") { /*if already initiate then destroy first*/
                            calendar.destroy();
                        }
                        var result02 = get_query();

                        if ($("#consultancy_location_filter").val() !== ""
                            && typeof result02.tab !== 'undefined' && result02.tab == 'consultancy') {

                            // Create doctors list for custom resource view
                            var doctorsList = [];
                            Object.entries(dropdowns).forEach(function (dropdown) {
                                doctorsList.push({
                                    id: dropdown[0],
                                    name: dropdown[1]
                                });
                            });

                            window.eventData = {};
                            window.eventData.id = null;
                            window.eventData.firstTime = true;
                            window.eventData.location_id = $("#consultancy_location_filter").val();

                            // Initialize custom resource calendar with vertical doctor columns
                            if (typeof CustomResourceCalendar !== 'undefined') {
                                CustomResourceCalendar.init(doctorsList);
                            }
                        }
                        if (typeof result.doctor_id !== "undefined") {
                            $("#consultancy_doctor_filter").val(result.doctor_id).change();
                        }
                    } else if (appointment && appointment == 'treatment') {
                        if (typeof treatment_calendar !== "undefined" && $('#treatment_location_filter option').length > 2) { /*if already initiate then destroy first*/
                            treatment_calendar.destroy();
                        }
                        $('#treatment_doctor_filter').html(dropdown_options);
                        if(typeof result.machine_id == "undefined"){
                            setQueryStringParameter('doctor_id', '');
                        }

                        if(result.location_id !== jQuery('#treatment_location_filter').val()){
                            setQueryStringParameter('doctor_id', '');
                            setQueryStringParameter('machine_id', '');
                        }
                        setQueryStringParameter('location_id', locationId);
                        loadMachine(locationId);
                        var result3 = get_query();
                        if (
                             $("#treatment_location_filter").val() !== ""
                            && typeof result3.tab !== 'undefined' && result3.tab == 'treatment') {
                            window.eventData = {}
                            window.eventData.location_id = $("#treatment_location_filter").val()
                            window.eventData.doctor_id = $("#treatment_doctor_filter").val();
                            window.eventData.id = null;
                            window.eventData.firstTime = true;
                            if($('#treatment_location_filter option').length == 2){
                                something();
                            } else{
                                setTimeout( function () {
                                    TreatmentCalendar.init();
                                }, 500);
                            }
                        }
                        if (typeof result.doctor_id !== "undefined" && typeof result.reload === "undefined") {
                        }
                    } else {
                        $('#edit_doctor').html(dropdown_options);
                    }

                } else {
                    resetDoctors();
                }
                setQueryStringParameter('reload');
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDoctors();
            }
        });
    } else {
        resetDoctors();
    }

}
window.loadEditTreatmentDoctors = function (locationId, appointment = null) {
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
                    $('#edit_treatment_doctor_id').html(dropdown_options);
                    setQueryStringParameter('location_id', locationId);
                    loadMachine(locationId);
                } else {
                    resetDoctors();
                }
                setQueryStringParameter('reload');
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDoctors();
            }
        });
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.center_machines', {
                location_id: locationId,
            }),
            type: 'GET',
            data: {
                location_id: locationId
            },
            cache: false,
            success: function(response) {
                if(response.status) {
                    let dropdowns =  response.data.dropdown;
                    let dropdown_options =  '<option value="">Select a Machine</option>';
                    Object.entries(dropdowns).forEach(function (dropdown) {
                        dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                    });
                    $('#edit_treatment_machine_id').html(dropdown_options);
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
window.loadEditConsultancyDoctors = function (locationId, appointment = null) {
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

                    $('#edit_doctor').html(dropdown_options).select2();
                    setQueryStringParameter('location_id', locationId);
                } else {
                    resetDoctors();
                }
                setQueryStringParameter('reload');
            },
            error: function (xhr, ajaxOptions, thrownError) {
                resetDoctors();
            }
        });
    } else {
        resetDoctors();
    }

}
window.resetDoctors = function () {
    var doctorDropdown = '<select id="doctor_id" class="form-control select2 required" name="doctor_id"><option value="" selected="selected">Select a Doctor</option></select>';
    $('#convert_doctor_id').html(doctorDropdown);
   // $('.select2').select2({ width: '100%' });
}

window.ConsultancyDoctorListener = function (doctorId) {

    // Set doctor_id in query string
    setQueryStringParameter('doctor_id', doctorId);

    // In custom resource view, doctor filter is optional
    // All doctors are already visible in columns
    // You can add filtering logic here if needed to highlight selected doctor
    if (doctorId != '' && doctorId != null) {
        // Optional: Highlight the selected doctor column
        $('.resource-doctor-column').css('opacity', '0.5');
        $('.resource-doctor-column[data-doctor-id="' + doctorId + '"]').css('opacity', '1');
        $('.resource-doctor-header').css('opacity', '0.5');
        $('.resource-doctor-header[data-doctor-id="' + doctorId + '"]').css('opacity', '1');
    } else {
        // Reset all columns to full opacity if no doctor selected
        $('.resource-doctor-column').css('opacity', '1');
        $('.resource-doctor-header').css('opacity', '1');
    }
}

function detailActions(appointment, invoice, invoiceid, permissions, $class = 'detail-actions') {

    let id = appointment.id;

    let edit_url = route('admin.appointments.edit', {id: appointment.id});
    let edit_service_url = route('admin.treatments.edit', {id: appointment.id});
    let detail_url = route('admin.appointments.detail', {id: appointment.id});
    let sms_logs_url = route('admin.appointments.sms_logs', {id: appointment.id});
    let patient_url = route('admin.patients.card', { id: appointment.patient_id });
    let service_invoice_url = route('admin.appointments.invoicecreate', {id: appointment.id});
    let consultancy_invoice_url = route('admin.appointments.invoice-create-consultancy', {id: appointment.id, type: 'appointment'});
    let image_url = route('admin.appointmentsimage.imageindex', {id: appointment.id});
    let measurement_url = route('admin.appointmentsmeasurement.measurements', {id: appointment.id});
    let medical_url = route('admin.appointmentsmedical.medicals', {id: appointment.id});
    let plan_create_url = route('admin.appointmentplans.create', {id: appointment.id});
    let log_url = route('admin.appointments.loadPage', {id: appointment.id, type: 'web'});

    let buttons = '';

    if (permissions.edit) {
        if (appointment.appointment_type_id == 1) {
            buttons += '<li><a class="text text-primary" href="javascript:void(0);" onclick="editRow(`' + edit_url + '`, `' + id + '`);" >\
            <i class="la la-pencil"></i> Edit\
            </a></li>';
        } else {
            buttons += '<li><a class="text text-primary" href="javascript:void(0);" onclick="editRow(`' + edit_service_url + '`, `' + id + '`, `' + $class + '`);" >\
            <i class="la la-pencil"></i> Edit\
            </a></li>';
        }
    }

    buttons += '<li><a href="javascript:void(0);" onclick="viewSmsLogs(`' + sms_logs_url + '`);" class="text text-primary" >\
        <i class="la la-sms" data-toggle="tooltip" title="SMS Logs"></i> SMS Logs\
        </a></li>';
    if (permissions.invoice) {
        if (!invoice) {
            if (appointment.appointment_type_id == 2) {
                buttons += '<li><a class="text text-primary" href="javascript:void(0);" onclick="createTreatmentInvoice(`' + service_invoice_url + '`);">\
                <i class="la la-file" title="Generate Invoice"></i> Generate Invoice\
                </a></li>';
            }

            if (appointment.appointment_type_id == 1) {
                buttons += '<li><a class="text text-primary" href="javascript:void(0);" onclick="createConsultancyInvoice(`' + consultancy_invoice_url + '`);" >\
                <i class="la la-file" title="Generate Invoice"></i> Generate Invoice\
                </a></li>';
            }
        }
        if (permissions.invoice_display) {
            if (invoice) {
                let invoice_url = route('admin.appointments.InvoiceDisplay', {id: invoiceid});
                buttons += '<li><a class="text text-primary" href="javascript:void(0);" onclick="displayInvoice(`' + invoice_url + '`);" >\
                <i class="la la-file-invoice-dollar" title="Invoice Display"></i> Invoice Display\
                </a></li>';
            }
        }
    }

    if (appointment.appointment_type_id == 2) {
        if (permissions.image_manage) {
            
        }

        if (permissions.measurement_manage) {
            
        }
    }

    if(permissions.patient_card) {
        buttons += '<li><a class="text text-primary" target="_blank" href="'+patient_url+'">\
        <i class="la la-user" title="Patient Card"></i>Patient Card\
        </a></li>';
    }

    if (permissions.delete) {
        let delete_url = route('admin.appointments.destroy', {id: appointment.id});
        buttons += '<li id="delete-appointment-action" style="background-color: #F64E60; border-radius: 4px; margin-top: 5px;"><a style="color: white !important; " href="javascript:void(0);" onclick="deleteRow(`' + delete_url + '`);">\
        <i class="la la-trash" title="Delete"></i> Delete\
        </a></li>';
    }

    $("." + $class).html(buttons);

}

function deleteAppointmentFromModal(button) {
    // Get appointment ID from the modal's hidden input field
    let appointmentId = $("#comment_appointment_id").val() || $("#treatment_comment_appointment_id").val();
    
    if (!appointmentId) {
        toastr.error('Appointment ID not found');
        return;
    }
    
    let delete_url = route('admin.appointments.destroy', {id: appointmentId});
    
    swal.fire({
        title: 'Are you sure you want to delete this appointment?',
        text: "You won't be able to revert this!",
        type: 'warning',
        icon: 'warning',
        buttonsStyling: false,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel',
        showCancelButton: true,
        cancelButtonClass: 'btn btn-secondary font-weight-bold',
        confirmButtonClass: 'btn btn-danger font-weight-bold'
    }).then(function (result) {
        if (result.value) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: delete_url,
                type: 'DELETE',
                cache: false,
                success: function (response) {
                    if (response.status) {
                        toastr.success(response.message);
                        // Close both modals (consultancy and treatment)
                        $("#modal_consultancy_detail").modal("hide");
                        $("#modal_treatment_detail").modal("hide");
                        // Reload the calendar/datatable
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
    });
}

var patient;

function getPatientDetail($this) {
    $.ajax({
        type: 'get',
        url: route('admin.users.get_patient_number'),
        data: {
            'patient_id': $this.val()
        },
        success: function (resposne) {
            if (resposne.status && resposne.data.patient) {
                patient = resposne.data.patient;
                $('#create_old_consultancy_phone').val(patient?.phone);

                if (permissions.contact) {
                    $('#create_consultancy_phone').val(patient?.phone);
                } else {
                    $('#create_consultancy_phone').val("***********");
                }

                $('#create_patient_name').val(patient?.name);
                $('#create_consultancy_gender').val(patient?.gender).change();

                if (isExist(patient?.referred_by)) {
                    $('#create_consultancy_referred_by').val(patient?.referred_by).change();
                }

                if (patient?.phone != '') {
                    $("#create_consultancy_phone").removeClass("is-invalid")
                    $("#create_consultancy_phone").parent("div").find(".fv-help-block").remove();
                }

                if (patient?.gender != '') {
                    $("#create_consultancy_gender").removeClass("is-invalid")
                    $("#create_consultancy_gender").parent("div").find(".fv-help-block").remove();
                }

                if (patient?.name != '') {
                    $("#create_patient_name").removeClass("is-invalid")
                    $("#create_patient_name").parent("div").find(".fv-help-block").remove();
                }

                if ($("#create_consultancy_service").val() != '') {
                    loadPatient(patient);
                }
            }

        },
    });

    $("#consultancy_patient_id").val($this.val() != '' ? $this.val() : '0');
}

var patient;
function loadPatient(patient) {
    if (typeof patient !== "undefined" && patient !== null) {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type: 'post',
            url: route('admin.appointments.load_lead'),
            data: {
                'referred_by': patient.referred_by,
                'service_id': $("#create_consultancy_service").val(),
                'patient_id': patient.id,
                'phone': patient.phone,
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
                $('#create_old_consultancy_phone').val(lead?.phone);

                // Set phone in the lead_search_id field (which is now the phone field)
                if (permissions.contact) {
                    $('.lead_search_id').val(lead?.phone);
                } else {
                    $('.lead_search_id').val("***********");
                }
                $('#create_patient_name').val(lead?.name);
                $('#create_patient_name').attr('readonly', true);
                
                $('#create_consultancy_gender').val(lead?.gender);
                $('#create_consultancy_gender').attr('disabled', true);
                $('#create_consultancy_gender').css("pointer-events","none");

                $('#create_consultancy_lead').val(lead?.lead_source_id).change();
                $('#create_consultancy_referred_by').val(lead?.referred_by);
                if (isExist(lead?.referred_by)) {
                    $('#create_consultancy_referred_by').val(lead?.referred_by).change();
                }
                
                // Hide new patient message since patient exists
                $('.new_patient_message').hide();
                $('#new_patient').val('0');
                
                if (lead?.phone != '') {
                    $(".lead_search_id").removeClass("is-invalid")
                    $(".lead_search_id").parent("div").find(".fv-help-block").remove();
                }
                if (lead?.gender != '') {
                    $("#create_consultancy_gender").removeClass("is-invalid")
                    $("#create_consultancy_gender").parent("div").find(".fv-help-block").remove();
                }
                if (lead?.name != '') {
                    $("#create_patient_name").removeClass("is-invalid")
                    $("#create_patient_name").parent("div").find(".fv-help-block").remove();
                }
                if ($("#create_consultancy_service").val() != '') {
                    loadLead(lead);
                }
            }
        },
    });

    $("#consultancy_lead_id").val($this.val() != '' ? $this.val() : '0');
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

// newPatient function removed - now handled automatically by lead search

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
    comment_html += formatDate(created_at, 'ddd MMM DD, yyyy hh:mm A');
    comment_html += '</span> </div>' +
        '<div class="mt-comment-text" id="message">';
    comment_html += comment ?? 'N/A';
    comment_html += '</div><div class="mt-comment-details"> </div>' +
        '</div></div></div></div></div>';

    return comment_html;
}

function loadTodayAppointments(today, appointment) {
    //$("#appoint_search_doctor").reset();
    $('.appointment_patient_id').val(null).trigger('change');
    $(".filter-field").val('');

    setQueryStringParameter('type');
    setQueryStringParameter('from');
    setQueryStringParameter('to');
    setQueryStringParameter('center_id');
    $("#appoint_search_start").val(today);
    $("#appoint_appoint_end").val(today);
    $("#filter_date_to").val(today);
    $("#filter_date_from").val(today);
    $("#treatment_search_start").val(today);
    $("#treatment_appoint_end").val(today);
    $("#filter_patient_id").val('');
    $("#filter_phone").val('');
    $("#filter_doctor_id").val('');
    $("#filter_center_id").val('');
    $("#filter_status_id").val('');
    $("#filter_city_id").val('');
    $("#filter_service_id").val('');
    $("#filter_region_id").val('');
    $("#filter_consultancytype_id").val('');
    $("#filter_updated_by_id").val('');
    $("#filter_created_from_id").val('');
    $("#filter_created_to_id").val('');
    $("#filter_rescheduled_by_id").val('');
    if (typeof datatable !== 'undefined') {
        reInitTable(appointment);
    }

}
$("#modal_create_consultancy").on('hide.bs.modal', function(){
    $('#create_consultancy_phone').attr("readonly",true);
    $('#create_consultancy_gender').attr("readonly",true);
});
$(document).ready(function() {
    $("#treatment_search_service,#treatment_search_centre,#treatment_search_status,#appoint_search_service,#appoint_search_centre,#appoint_search_status").select2({dropdownCssClass : 'bigdrop'});
    loadLocations(cityId='');
});

/**
 * Edit Schedule - shared function for consultations and treatments datatable
 */
function editSchedule(id, doc_id, loc_id) {

    $("#modal_change_appointment_schedule").modal("show");
    $("#schedule_appointment_id").val(id)
    $("#schedule_doctor_id").val(doc_id)
    $("#schedule_location_id").val(loc_id)

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.get_schedule'),
        type: "GET",
        data: { id: id },
        cache: false,
        success: function (response) {
            if (response) {
                $("#schedule_date").val(response.scheduled_date);
                // Update datepicker to show the correct month
                if (response.scheduled_date) {
                    $("#schedule_date").datepicker('update', response.scheduled_date);
                }
                $("#schedule_time").val(response.scheduled_time);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}



