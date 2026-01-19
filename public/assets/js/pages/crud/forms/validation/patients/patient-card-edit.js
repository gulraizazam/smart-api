var patientCardPermissions = {
    edit: true,
    contact: true
};

window.editPatientFromProfile = function() {
    let id = patientCardID;
    let url = route('admin.patients.edit', { id: id });
    
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
            setEditDataFromProfile(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function setEditDataFromProfile(response) {
    let genders = response.data.gender;
    let patient = response.data.patient;
    let permission = response.data.permissions || patientCardPermissions;

    let gender_option = '<option value="">Select Gender</option>';

    Object.entries(genders).forEach(function (gender) {
        gender_option += '<option value="' + gender[0] + '">' + gender[1] + '</option>';
    });

    $("#edit_gender_id").html(gender_option);

    $("#edit_name").val(patient.name);
    $("#edit_email").val(patient.email);
    $("#edit_old_phone").val(patient.phone);

    if (permission.contact) {
        $("#edit_phone").val(patient.phone).attr("readonly", false);
    } else {
        $("#edit_phone").val("***********").attr("readonly", true);
    }

    $("#edit_gender_id").val(patient.gender);
}

var PatientCardEditValidation = function () {
    var Validation = function () {
        let modal_id = 'modal_edit_patients_form';
        let form = document.getElementById(modal_id);
        
        if (!form) return;
        
        let validate = FormValidation.formValidation(
            form,
            {
                fields: {
                    name: {
                        validators: {
                            notEmpty: {
                                message: 'The name field is required'
                            }
                        }
                    },
                    phone: {
                        validators: {
                            notEmpty: {
                                message: 'The phone field is required'
                            },
                            stringLength: {
                                min: 10,
                                max: 12,
                                message: 'The phone number must be between 10 and 12 characters'
                            },
                            regexp: {
                                regexp: /^\d+$/,
                                message: 'The phone number must contain only digits (0-9)'
                            }
                        }
                    },
                    gender: {
                        validators: {
                            notEmpty: {
                                message: 'The gender field is required'
                            }
                        }
                    },
                },

                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap(),
                    submitButton: new FormValidation.plugins.SubmitButton(),
                }
            }
        );
        
        validate.on('core.form.invalid', function (e) {
            if (typeof select2Validation === 'function') {
                select2Validation();
            }
        });
        
        validate.on('core.form.valid', function(event) {
            submitForm($(form).attr('action'), $(form).attr('method'), $(form).serialize(), function (response) {
                if (response.status) {
                    toastr.success(response.message);
                    closePopup(modal_id);
                    // Refresh patient data on the profile page
                    getPatient();
                } else {
                    toastr.error(response.message);
                }
            }, form);
        });
    }

    return {
        init: function() {
            Validation();
        }
    };
}();

jQuery(document).ready(function() {
    PatientCardEditValidation.init();
});
