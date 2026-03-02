"use strict";

let perPage = 30;
let paginate = true;
if (typeof changePaginate !== 'undefined') {
    paginate = changePaginate;
}
if (typeof changePages !== 'undefined') {
    perPage = changePages;
}
let row_ids = [];
let permissions = [];
let metaData = [];
let active_filters = [];
let filter_values = [];
var datatable;
var patientDatatable = [];

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
                        map: function (data) { /*to get response*/
                            /* get permissions array for actions */
                            permissions = data.permissions;
                            filter_values = data.filter_values;

                            if (typeof setFilters === 'function') {
                                
                                setFilters(data.filter_values, data.active_filters);
                            }


                           if (typeof data.status !== 'undefined') {
                               deleteSuccessAndReset(data, datatable);
                           }
                            if (typeof data.meta !== 'undefined') {
                                metaData = data.meta;
                            }
                            
                            if (typeof setTotal === 'function') {
                                
                                setTotal(metaData);
                                
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
             fixedColumns: true,
			sortable: true,

			pagination: paginate,

			// columns definition
			columns: typeof table_columns !== 'undefined' ? table_columns : [],

		});

        datatable.on('datatable-on-ajax-fail',function (event,error){
            if (error && error.responseJSON && error.responseJSON.message) {
                toastr.error(error.responseJSON.message);
            }
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


/*this is for patient card*/
var KTPatientDatatable = function() {

    var patientTable = function(table_class) {

        patientDatatable[table_class] = $(table_class).KTDatatable({
            data: {
                type: 'remote',
                source: {
                    read: {
                        url:  typeof table_url !== 'undefined' ? table_url : '',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        map: function (data) { /*to get response*/
                            /* get permissions array for actions */
                            permissions = data.permissions;
                            filter_values = data.filter_values;

                            if (typeof setFilters === 'function') {
                                setFilters(data.filter_values, data.active_filters);
                            }


                            if (typeof data.status !== 'undefined') {
                                deleteSuccessAndReset(data, patientDatatable[table_class]);
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
            fixedColumns: true,
            sortable: true,

            pagination: paginate,

            // columns definition
            columns: typeof table_columns !== 'undefined' ? table_columns : [],

        });

        patientDatatable[table_class].on('datatable-on-ajax-fail',function (event,error){
            if (error && error.responseJSON && error.responseJSON.message) {
                toastr.error(error.responseJSON.message);
            }
        });

        $('#delete-table-rows').on('click', function() {
            deleteConfirm(patientDatatable[table_class]);
        });

        $('#reset-search').on('click', function() {
            let filters =  {
                filter: 'filter_cancel',
            }
            patientDatatable[table_class].search(filters, 'search');
        });

        $('#apply-search').on('click', function() {
            let filters =  {
                filter: 'filter',
                search: $("#datatable_search_query").val().toLowerCase(),
            }
            patientDatatable[table_class].search(filters, 'search');
        });

        /*reset all table filters*/
        if(typeof resetAllFilters === "function") {
            resetAllFilters(patientDatatable[table_class]);
        }

        /*apply table filters*/
        if(typeof applyFilters === "function") {
            applyFilters(patientDatatable[table_class]);
        }

        $('#kt_datatable_search_status, #kt_datatable_search_type').selectpicker();
    };

    return {
        // public functions
        init: function(table_class = '#kt_datatable') {
            patientTable(table_class);
        },
    };
}();

/*end for patient card*/

jQuery(document).ready(function() {

    if (typeof table_url !== 'undefined'
        && typeof patientCardID === 'undefined') { /*this is for overall datatable*/
        KTDatatable.init();
    }

    if (typeof patientCardID !== 'undefined') { /*this is for patient card*/
        KTPatientDatatable.init();
    }

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

        if ($(".table-checkboxes:checked").length == $(".table-checkboxes").length) {
            $(".select-all-checkboxes").prop('checked', true);
        } else {
            $(".select-all-checkboxes").prop('checked', false);
        }

        setRowIds($(".table-checkboxes:checked"));
    });

    $(document).on("click", ".table-checkboxes", function () {
        $(".table-checkboxes").each(function () {
            if ($(".table-checkboxes").is(":checked")) {
                $(".delete-records").removeClass('d-none');
                $(".checkbox-count").text($(".table-checkboxes:checked").length);
            } else {
                $(".delete-records").addClass('d-none');
            }

            if ($(".table-checkboxes:checked").length == $(".table-checkboxes").length) {
                $(".select-all-checkboxes").prop('checked', true);
            } else {
                $(".select-all-checkboxes").prop('checked', false);
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
