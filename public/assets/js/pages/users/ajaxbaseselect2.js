$(document).ready(function () {

    $(".patient_id").select2({
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
            processResults: function (data, params) {
                params.page = params.page || 1;

                return {
                    results: $.map(data, function (item) {
                        return {
                            text: item.name + ' - ' + item.phone,
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

    function formatRepo(item) {
        if (item.loading) {
            return item.text;
        }
        markup = item.text;
        return markup;
    }

    function formatRepoSelection(item) {
        if (item.id) {
            return item.text + " <span type='button' onclick='addUsers()' class='croxcli' style='float: right;border: 0; background: none;padding: 0 0 0;'><i class='fa fa-times' aria-hidden='true'></i></span>";
        } else {
            return 'Select Patient';
        }
    }
});

function addUsers() {
    $('.patient_id').val(null).trigger('change');
}

function addLeads() {
    $('.lead_id').val(null).trigger('change');
}
