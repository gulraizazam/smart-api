function addCustomForm(url) {

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: url,
        type: "GET",
        cache: false,
        success: function(response) {

            setCustomFormData(response);

        },
        error: function(xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });

}

function setCustomFormData(response) {

    try {

        let forms = response.data.CustomForms;
        let id = response.data.id;

        let rows = '';

        if (forms) {

            Object.values(forms).forEach(function (form) {
                let url = route('admin.customformfeedbackspatient.fill_form', {
                    id: form.id,
                    patient_id: id
                });
                rows += '<tr>\
                    <td>'+form.name+'</td>\
                    <td><a class="btn btn-sm btn-primary" href="'+url+'">Submit</a></td>\
                </tr>';
            });
        }

        $(".custom_form_submit").html(rows !== '' ? rows : noRecordFoundTable(2));


    } catch (error) {
        showException(error);
    }

}
