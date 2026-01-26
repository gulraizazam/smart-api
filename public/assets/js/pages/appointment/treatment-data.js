// Load all child services (parent_id != 0) function
window.loadAllChildServices = function () {
    resource_id = $("#treatment_resource_id").val();

   
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.appointments.load_all_child_services'),
        type: 'POST',
        data: {
            resource_id: resource_id
        },
        cache: false,
        success: function(response) {
            
            if(response.status) {
                let services = response.data.services;
                let service_option = '<option value="">Select a Service</option>';

                let serviceCount = 0;
                Object.entries(services).forEach(function (service) {
                    service_option += '<option value="'+service[0]+'">'+service[1]+'</option>';
                    serviceCount++;
                });

        
                $('#create_treatment_service').html(service_option);

                // Reinitialize select2 if it exists
                if ($('#create_treatment_service').hasClass('select2-hidden-accessible')) {
                    $('#create_treatment_service').select2('destroy');
                }
                $('#create_treatment_service').select2();
            } else {
                console.error('Failed to load services:', response.message);
                toastr.error(response.message || 'Failed to load services');
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.error('Error loading child services:', thrownError);
            console.error('Response:', xhr.responseText);
            toastr.error('Failed to load services. Please try again.');
        }
    });
}

// Define loadEndServices globally before anything else to ensure it's available immediately
window.loadEndServices = function (baseServiceId) {
    resource_id = $("#treatment_resource_id").val();

    if(baseServiceId != '') {
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: route('admin.appointments.load_node_service'),
            type: 'POST',
            data: {
                service_id: baseServiceId,
                resource_id:resource_id
            },
            cache: false,
            success: function(response) {
                if(response.status) {
                    let services = response.data.services;
                    let service_option = '<option value="">Select a Child Service</option>';

                    Object.entries(services).forEach( function (service) {
                        service_option += '<option value="'+service[0]+'">'+service[1]+'</option>';
                    });

                    $('#create_treatment_service').html(service_option);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {

            }
        });
    } else {
        if (typeof resetNodeServices === 'function') {
            resetNodeServices();
        }
        if (typeof CreateFormValidation !== 'undefined' && typeof CreateFormValidation.loadLead === 'function') {
            CreateFormValidation.loadLead();
        }
    }
}

jQuery(document).ready(function() {

    var result = get_query();

    if (typeof result.tab !== 'undefined') {
        $("." + result.tab+ '-tab').click();
    } else {
        $(".appointment-tab").addClass("nav-bar-active")
    }

    if (typeof result.city_id !== "undefined"
        && typeof result.location_id !== "undefined"
        && typeof result.doctor_id !== "undefined"
        && typeof result.machine_id !== "undefined"
        && typeof result.tab !== 'undefined' && result.tab == 'treatment') {

        setTimeout( function () {
            $("#treatment_city_filter").val(result.city_id).change();
        }, 200);
        setTimeout( function () {
            $("#treatment_resource_filter").val(result.machine_id).change();
        },1200);
    }
    $("#Add_comment").click(function () {

        if ($('#consultancy_comment').val() !== '') {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'get',
                url: route('admin.appointments.storecomment'),
                data: {
                    'comment': $('#consultancy_comment').val(),
                    'appointment_id': $('#comment_appointment_id').val(),
                },
                success: function (data) {
                    $('#commentsection').prepend(commentData(data.username, data.appointmentCommentDate, data.appointment.comment));
                },

            });
        } else {
            toastr.error("Please fill out the comment field");
        }
        $('#cment')[0].reset();
    });


    // Handle treatment form submission with AJAX
    $(document).on('submit', '#modal_create_treatment_form', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        
        var form = $(this);
        var formData = form.serialize();
        var submitButton = form.find('[type="submit"]');
        
        // Disable submit button
        submitButton.prop('disabled', true);
        
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            success: function(response) {
           
                if (response.status) {
                    toastr.success(response.message || 'Treatment created successfully');
                    
                    // Close modal
                    $('#modal_create_treatment').modal('hide');
                    
                    // Reload calendar after a short delay to ensure modal is closed
                    setTimeout(function() {
                     
                        // Reload resource calendar if it's visible
                        if ($('#custom_treatment_resource_calendar').is(':visible')) {
                            if (typeof TreatmentResourceCalendar !== 'undefined') {
                       
                                TreatmentResourceCalendar.reload();
                            } else {
                                console.error('✗ TreatmentResourceCalendar is not defined!');
                            }
                        }
                        // Otherwise reload regular calendar
                        else if (typeof treatment_calendar !== 'undefined') {
                            console.log('✓ Calling treatment_calendar.refetchEvents()');
                            treatment_calendar.refetchEvents();
                        } else {
                            console.error('✗ No calendar found to reload!');
                        }
                    }, 500);
                } else {
                    toastr.error(response.message || 'Error creating treatment');
                }
                
                // Re-enable submit button
                submitButton.prop('disabled', false);
            },
            error: function(xhr) {
                var errorMessage = 'Error creating treatment';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                toastr.error(errorMessage);
                
                // Re-enable submit button
                submitButton.prop('disabled', false);
            }
        });
        
        return false;
    });

});
    var counter = 0;
    window.treatmentDoctorListener = function (doctorId) {
    setQueryStringParameter('doctor_id', doctorId);
    $("#treatment_doctor_filter").val(doctorId);
    if (typeof treatment_calendar !== "undefined") { /*if already initiate then destroy first*/
        treatment_calendar.destroy();
    }
    var result = get_query();
    if ($("#treatment_location_filter").val() !== "" && $("#treatment_doctor_filter").val() !== "" && typeof result.tab !== 'undefined' && result.tab == 'treatment') {
        window.eventData = {}
        window.eventData.city_id = $("#treatment_city_filter").val()
        window.eventData.location_id = $("#treatment_location_filter").val()
        window.eventData.doctor_id = $("#treatment_doctor_filter").val();
        window.eventData.id = null;
        window.eventData.firstTime = true;
        setTimeout( function () {
            TreatmentCalendar.init();
        }, 500);
    }
    counter = counter+1;
}

window.machineListener = function (machineId) {

    setQueryStringParameter('machine_id', machineId);

    if (machineId != '' && machineId != null) {

        loadCalendar();
        counter = counter +1;
    }

}


function checkingtest() {
    $("#patient_search_selector").select2({
        ajax: {
        type: "GET",
        url: route('admin.users.getpatient.id'),
        dataType: 'json',
        delay: 250,
        data: function (params) {
        return {
            search: params.term // search term
        };
        },
        processResults: function (response) {
        return {
            results: response.data.patients,
        };
        },
        cache: true
        },
        placeholder: 'Search for a repository',
        templateResult:  formatRepo,
        templateSelection: formatRepoSelection

    });

    $("#patient_search_selector").on("select2:select", function (e) {
        var thisID = $(this).val();
        $(this).parent().parent('div').find('.search_field').val(thisID).change();
    });

    function formatRepo (repo) {
        var $container, search_id = 'patient_search_selector', flag = 1;
        if (repo.loading) {
            $container = $(
                "<div class='select2-result-repository__avatar'>Searching</div>"
            );
        } else{
            $container = $(
                '<div class="select2-result-repository__avatar tst">' + repo.name + " - C " + repo.id +"</div>"
            );
        }
        return $container;
    }

    function formatRepoSelection (repo) {
        return repo.name || repo.text;
    }
}



function loadCalendar() {
     checkingtest();
    if (typeof treatment_calendar !== "undefined") { /*if already initiate then destroy first*/
        treatment_calendar.destroy();
    }

    var result = get_query();

    if ($("#treatment_city_filter").val() !== ""
        && $("#treatment_location_filter").val() !== ""
        && $("#treatment_doctor_filter").val() !== ""
        && $("#treatment_resource_filter").val() !== ""
        && typeof result.tab !== 'undefined' && result.tab == 'treatment') {

        window.eventData = {}
        window.eventData.city_id = $("#treatment_city_filter").val()
        window.eventData.location_id = $("#treatment_location_filter").val()
        window.eventData.doctor_id = $("#treatment_doctor_filter").val();
        window.eventData.id = null;
        window.eventData.firstTime = true;

        setTimeout( function () {
            TreatmentCalendar.init();
        }, 500);
    }
}



function getTreatmentPatientDetail($this) {
    if ($this.val() != '') {
        $this.parent("div").find(".select2-selection").removeClass("select2-is-invalid");
        $this.parent("div").find(".fv-help-block").text("");
    }

    var patientId = $this.val();

    $.ajax({
        type: 'get',
        url: route('admin.users.get_patient_number'),
        data: {
            'patient_id': patientId
        },
        success: function (resposne) {
            if (resposne.status && resposne.data.patient) {
                let patient = resposne.data.patient;

                $('#create_old_treatment_phone').val(patient?.phone);
                if (permissions.contact) {
                    $('#create_treatment_phone').val(patient?.phone);
                } else {
                    $('#create_treatment_phone').val("***********");
                }

                $('#create_treatment_patient_name').val(patient?.name);
                if (patient?.id) {
                    $('#create_treatment_c_id').val(makePatientId(patient?.id));
                }
                $('#create_treatment_gender').val(patient?.gender).trigger("change");

                if (patient?.phone != '') {
                    $("#create_treatment_phone").removeClass("is-invalid")
                    $("#create_treatment_phone").parent("div").find(".fv-help-block").remove();
                }

                if (patient?.name != '') {
                    $("#create_treatment_patient_name").removeClass("is-invalid")
                    $("#create_treatment_patient_name").parent("div").find(".fv-help-block").remove();
                }

                // Check patient's last treatment
                checkPatientLastTreatment(patientId);
            }

        },
    });

    $("#treatment_patient_id").val($this.val() != '' ? $this.val() : '0');
}

function checkPatientLastTreatment(patientId) {
    // Disable submit button initially
    $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', true);

    // Hide warning div
    $('#treatment_doctor_warning').addClass('d-none');

    // Get current service and doctor
    var currentServiceId = $('#create_treatment_service').val();
    var currentDoctorId = $('#treatment_doctor_id').val();
    var currentLocationId = $('#treatment_location_id').val();
    var currentStart = $('#treatment_start').val(); // Get the selected date/time

    // If service is not selected yet, just enable submit
    if (!currentServiceId) {
        $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', false);
        return;
    }

    $.ajax({
        type: 'GET',
        url: route('admin.treatments.check_patient_last_treatment'),
        data: {
            patient_id: patientId,
            service_id: currentServiceId,
            location_id: currentLocationId,
            start: currentStart
        },
        success: function(response) {
            if (response.status && response.data.last_treatment) {
                var lastTreatment = response.data.last_treatment;
                var lastDoctorId = lastTreatment.doctor_id;
                var lastDoctorName = lastTreatment.doctor_name;

                // Check if service matches
                if (lastTreatment.service_id == currentServiceId) {
                    // Service matches, check doctor
                    if (lastDoctorId == currentDoctorId) {
                        // Both match, enable submit button
                        $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', false);
                        $('#treatment_doctor_warning').addClass('d-none');
                    } else {
                        // Service matches but doctor is different
                        // Check if previous doctor has rota for the selected date/time
                        var hasDoctorRota = lastTreatment.has_doctor_rota;
                        
                        // Show warning
                        $('#warning_message').html('The last session for this treatment was performed by ' + lastDoctorName + '.');
                        
                        if (hasDoctorRota) {
                            // Doctor has rota, enable option 1 and auto-select it
                            $('#previous_doctor_option').html('<strong>Schedule the treatment with ' + lastDoctorName + '</strong>');
                            $('#use_previous_doctor').prop('disabled', false);
                            $('#use_previous_doctor').prop('checked', true);
                            
                            // Update doctor ID to previous doctor
                            $('#treatment_doctor_id').val(lastDoctorId);
                            if ($('#create_treatment_doctor').length) {
                                $('#create_treatment_doctor').val(lastDoctorId).trigger('change');
                            }
                            
                            // Enable submit button
                            $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', false);
                        } else {
                            // Doctor doesn't have rota, disable option 1 with message
                            $('#previous_doctor_option').html('<strong>Schedule the treatment with ' + lastDoctorName + '</strong> <span class="text-danger">(Doctor is not available in this time slot)</span>');
                            $('#use_previous_doctor').prop('disabled', true);
                            $('#use_previous_doctor').prop('checked', false);
                            
                            // Keep submit disabled - user cannot proceed
                            $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', true);
                        }
                        
                        $('#treatment_doctor_warning').removeClass('d-none');

                        // Store previous doctor ID
                        $('#treatment_doctor_warning').data('previous-doctor-id', lastDoctorId);
                        $('#treatment_doctor_warning').data('previous-doctor-name', lastDoctorName);
                        $('#treatment_doctor_warning').data('has-doctor-rota', hasDoctorRota);
                    }
                } else {
                    // Service doesn't match, enable submit button
                    $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', false);
                    $('#treatment_doctor_warning').addClass('d-none');
                }
            } else {
                // No previous treatment, enable submit button
                $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', false);
                $('#treatment_doctor_warning').addClass('d-none');
            }
        },
        error: function() {
            // On error, enable submit button
            $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', false);
        }
    });
}

function setResourceValue(value) {
    $("#treatment_resource_id").val(value);
}


jQuery(document).ready(function () {

    $("#Add_treatment_comment").click(function () {
        if ($('#treatment_comment').val() !== '') {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'get',
                url: route('admin.appointments.storecomment'),
                data: {
                    'comment': $('#treatment_comment').val(),
                    'appointment_id': $('#treatment_comment_appointment_id').val(),
                },
                success: function (data) {
                    $('#treatment_commentsection').prepend(treatmentCommentData(data.username, data.appointmentCommentDate, data.appointment.comment));
                },

            });
        } else {
            toastr.error("Please fill out the comment field");
        }
        $('#treatment_cment')[0].reset();
    });



    $(document).on("click", ".croxcli", function () {
        $('.search_field').val('').change();
        $('.treatment_patient_search_id').val(null).trigger('change');

        $("#create_treatment_patient_search").parent("div").find(".select2-selection").addClass("select2-is-invalid");
        $("#create_treatment_patient_search").parent("div").find(".fv-help-block").text("The patient field is required");
    });

    // Handle doctor choice radio buttons
    $(document).on('change', 'input[name="doctor_choice"]', function() {
        var selectedValue = $('input[name="doctor_choice"]:checked').val();

        // Enable submit button since a radio button is selected
        $('#modal_create_treatment_form').find('[type="submit"]').prop('disabled', false);

        // If previous doctor is selected, update the hidden doctor field
        if (selectedValue === 'previous') {
            var previousDoctorId = $('#treatment_doctor_warning').data('previous-doctor-id');
            $('#treatment_doctor_id').val(previousDoctorId);

            // Also update the visible doctor select if it exists
            if ($('#create_treatment_doctor').length) {
                $('#create_treatment_doctor').val(previousDoctorId).trigger('change');
            }
        }
        // If selected doctor is chosen, the doctor_id is already set in the field, no change needed
    });

    // Re-check when service changes (using select2:select for better compatibility)
    $(document).on('change select2:select', '#create_treatment_service', function() {
        var patientId = $('#treatment_patient_id').val();
        // Also check the select2 patient dropdown value as fallback
        if (!patientId || patientId == '0') {
            patientId = $('#create_treatment_patient_id').val();
        }
        if (patientId && patientId != '0') {
            checkPatientLastTreatment(patientId);
        }
    });

    // Reset warning when modal is closed
    $('#modal_create_treatment').on('hidden.bs.modal', function() {
        $('#treatment_doctor_warning').addClass('d-none');
        $('input[name="doctor_choice"]').prop('checked', false);
    });
})
