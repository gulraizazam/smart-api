$(document).ready(function () {
    // Initialize Select2 for Lead Search in Add Lead form
    // Using OPTIMIZED API endpoint - same as consultation modal
    $("#add_lead_patient_search").select2({
        width: '100%',
        placeholder: 'Search Patient by Name or Phone',
        allowClear: true,
        ajax: {
            url: route('admin.users.getpatient.optimized'),
            dataType: 'json',
            delay: 150,
            data: function (params) {
                return {
                    search: params.term
                };
            },
            processResults: function (response, params) {
                params.page = params.page || 1;

                // The API returns {data: {patients: [...]}}
                let patients = response.data.patients || [];
                
                return {
                    results: $.map(patients, function (patient) {
                        return {
                            text: patient.name + ' - ' + patient.phone,
                            id: patient.id,
                            patient: patient
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
        templateResult: formatPatientRepo,
        templateSelection: formatPatientRepoSelection
    });

    // Handle patient selection
    $("#add_lead_patient_search").on("select2:select", function (e) {
        var data = e.params.data;
        if (data.patient) {
            // Set the hidden lead_id field to trigger getLeadDetail
            $("#create_lead_search").val(data.id).trigger('change');
            
            // Also populate phone field if it exists
            if ($('.lead_search_id').length) {
                $('.lead_search_id').val(data.patient.phone);
            }
        }
    });

    // Handle clearing selection
    $("#add_lead_patient_search").on("select2:clear", function () {
        $("#create_lead_search").val('').trigger('change');
        if ($('.lead_search_id').length) {
            $('.lead_search_id').val('');
        }
        addLeads(); // Call the clear function
    });

    function formatPatientRepo(item) {
        if (item.loading) {
            return item.text;
        }
        return item.text;
    }

    function formatPatientRepoSelection(item) {
        if (item.id) {
            return item.text;
        } else {
            return 'Search Patient by Name or Phone';
        }
    }
});
