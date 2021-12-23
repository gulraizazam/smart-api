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
                        url: table_url,
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
			columns: table_columns ?? [],

		});

		$('#delete-table-rows').on('click', function() {
            swal.fire({
                title: 'Are you sure you want to delete?',
                type: 'danger',
                icon: 'info',
                buttonsStyling: false,
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                showCancelButton: true,
                cancelButtonClass: 'btn btn-danger font-weight-bold',
                confirmButtonClass: 'btn btn-primary font-weight-bold'
            }).then(function(result) {
                if (result.value) {
                    datatable.search(row_ids.join(','), 'delete');
                }
            });
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

function deleteRow($this) {
    console.log($($this).data('route'));
   // window.location.href = route;
}
