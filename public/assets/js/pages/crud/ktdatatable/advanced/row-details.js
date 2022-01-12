"use strict";

let perPage = 20;
if (typeof changePages !== 'undefined') {
    perPage = changePages;
}
let row_ids = [];
let permissions = [];
let active_filters = [];
let filter_values = [];
var datatable;

var KTDatatable = function() {

	var table = function() {

		 datatable = $('#kt_datatable').KTDatatable({
			data: {
				type: 'remote',
				source: {
					read: {
                        url:  typeof table_url !== 'undefined' ? table_url : '',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        map: function (data) { /*to get response, we can remove this */
                            /* get permissions array for actions */
                            permissions = data.permissions;

                            if (typeof setFilters === 'function') {
                                setFilters(data.filter_values, data.active_filters);
                            }


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
				pageSize: perPage,
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

			// columns definition
			columns: typeof table_columns !== 'undefined' ? table_columns : [],

		});

		$('#delete-table-rows').on('click', function() {
            deleteConfirm(datatable);
		});

        $('#reset-search').on('click', function() {
            let filters =  {
                filter: 'filter_cancel',
            }
            datatable.search(filters, 'search');
        });

        $('#apply-search').on('click', function() {
            let filters =  {
                filter: 'filter',
                search: $("#datatable_search_query").val().toLowerCase(),
            }
			datatable.search(filters, 'search');
		});

        /*reset all table filters*/
        if(typeof resetAllFilters === "function") {
            resetAllFilters(datatable);
        }

        /*apply table filters*/
        if(typeof applyFilters === "function") {
            applyFilters(datatable);
        }

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

    KTDatatable.init();

    /*To get selected row ids for deletion*/
    $(document).on("click", ".select-all-checkboxes", function () {

        if ($(this).is(":checked") && $(".table-checkboxes").length > 0) {
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
