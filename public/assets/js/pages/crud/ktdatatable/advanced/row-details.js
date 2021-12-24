"use strict";
// Class definition

let row_ids = [];

var KTDatatableAutoColumnHideDemo = function() {

	var table = function() {

		var datatable = $('#kt_datatable').KTDatatable({
			data: {
				type: 'remote',
				source: {
					read: {
                        url:  typeof table_url !== 'undefined' ? table_url : '',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        map: function (data) { /*to get response, we can remove this */

                           if (typeof data.status !== 'undefined') {
                               deleteSuccessAndReset(data, datatable);
                           }
                            var response = data;
                            if (typeof response.data !== 'undefined') {
                                response = response.data;
                            }

                            return response;
                        },
					},
				},
				pageSize: 20,
				saveState: false,
				serverPaging: true,
				serverFiltering: true,
				serverSorting: true,
			},

            layout: {
                scroll: false,
               // height: 550,
                footer: false,
                /*spinner: {
                    message: "Loading wait.."
                }*/
            },
            /*rows: {
                autoHide: false,
            },*/

			// column sorting
			sortable: true,

			pagination: true,

			search: {
				input: $('#kt_datatable_search_query'),
				key: 'generalSearch'
			},

			// columns definition
			columns: typeof table_columns !== 'undefined' ? table_columns : [],

		});

		$('#delete-table-rows').on('click', function() {
            deleteConfirm(datatable);
		});

        $('#kt_datatable_search_status').on('change', function() {
			datatable.search($(this).val().toLowerCase(), 'Status');
		});

		$('#kt_datatable_search_type').on('change', function() {
			datatable.search($(this).val().toLowerCase(), 'Type');
		});

		$('#kt_datatable_search_status, #kt_datatable_search_type').selectpicker();
	};

	return {
		// public functions
		init: function() {
            table();
		},
	};
}();

jQuery(document).ready(function() {

	KTDatatableAutoColumnHideDemo.init();

    /*To get selected row ids for deletion*/
    $(document).on("click", ".select-all-checkboxes", function () {
        if ($(this).is(":checked")) {
            $(".table-checkboxes").prop('checked', true);
            $(".delete-records").removeClass('d-none');
           $(".checkbox-count").text($(".table-checkboxes:checked").length);
        } else {
            $(".table-checkboxes").prop('checked', false);
            $(".delete-records").addClass('d-none');
        }
        setRowIds($(".table-checkboxes:checked"));
    });

    $(document).on("click", ".table-checkboxes", function () {
        $(".table-checkboxes").each(function () {
            if ($(".table-checkboxes").is(":checked")) {
                $(".delete-records").removeClass('d-none');
                $(".checkbox-count").text($(".table-checkboxes:checked").length)
            } else {
                $(".delete-records").addClass('d-none');
            }
        });

        setRowIds($(".table-checkboxes:checked"));

    });
});

function setRowIds($rows) {
    row_ids = [];
    $rows.each(function () {
        row_ids.push($(this).val())
    });
}

function deleteSuccessAndReset(data, datatable) {
    $(".delete-records").addClass("d-none");
    toastr.info(data.message);
    datatable.search([], 'delete');

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
