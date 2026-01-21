$(document).ready(function () {
    // Initialize Select2 for Referred By field (Patient Search)
    // Using OPTIMIZED API endpoint - 50-100X faster than old endpoint
    $(".select2-patient-search").select2({
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
                            id: patient.id
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
