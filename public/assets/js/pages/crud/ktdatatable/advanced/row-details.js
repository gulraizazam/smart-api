"use strict";
// Class definition

var KTDatatableAutoColumnHideDemo = function() {
	// Private functions

	// basic demo
	var table = function() {

		var datatable = $('#kt_datatable').KTDatatable({
			data: {
				type: 'remote',
				source: {
					read: {
						url: table_url,
					},
				},
				pageSize: 10,
				saveState: false,
				serverPaging: true,
				serverFiltering: true,
				serverSorting: true,
			},

			layout: {
				scroll: false
			},

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

    $(document).on("click", ".select-all-checkboxes", function () {
        if ($(this).is(":checked")) {
            $(".table-checkboxes").prop('checked', true);
            $(".delete-records").removeClass('d-none');
           $(".checkbox-count").text($(".table-checkboxes:checked").length)
        } else {
            $(".table-checkboxes").prop('checked', false);
            $(".delete-records").addClass('d-none');
        }
    });

    $(document).on("click", ".table-checkboxes", function () {
        $(".table-checkboxes").each(function () {
            if ($(".table-checkboxes").is(":checked")) {
                $(".delete-records").removeClass('d-none');
                $(".checkbox-count").text($(".table-checkboxes:checked").length)
            } else {
                $(".delete-records").addClass('d-none');
            }
        })

    });
});
