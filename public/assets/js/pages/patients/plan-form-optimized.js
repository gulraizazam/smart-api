/**
 * Optimized Plans Datatable Integration
 * 
 * This file demonstrates how to integrate the new optimized plans API
 * Copy the relevant sections to your existing plan-form.js when ready to migrate
 */

// NEW OPTIMIZED URL - Use this instead of the old route
var table_url = route('admin.plans.optimized.datatable', { patient_id: patientCardID });

// Column definitions remain the same
var table_columns = [
    {
        field: 'package_id',
        title: 'Plan ID',
        width: 70,
    }, 
    {
        field: 'location_id',
        title: 'Centres',
        width: 'auto',
        sortable: false,
    }, 
    {
        field: 'total',
        title: 'Total',
        width: 80,
        sortable: false,
    }, 
    {
        field: 'cash_receive',
        title: 'Cash In',
        width: 80,
        sortable: false,
    }, 
    {
        field: 'settle_amount',
        title: 'Settled',
        width: 80,
        sortable: false,
        // Data is now pre-formatted from API
    }, 
    {
        field: 'refund',
        title: 'Refund',
        width: 'auto',
        sortable: false,
    }, 
    {
        field: 'created_at',
        title: 'Created At',
        width: 'auto',
        // No template needed - already formatted
    }, 
    {
        field: 'status',
        title: 'Status',
        width: 'auto',
        template: function (data) {
            if (data.active == 1) {
                return '<span class="badge badge-success">Active</span>';
            } else {
                return '<span class="badge badge-danger">Inactive</span>';
            }
        }
    }
];

/**
 * Load filter options from optimized API
 */
function loadFilterOptions() {
    $.ajax({
        url: route('admin.plans.optimized.lookup', { patient_id: patientCardID }),
        type: 'GET',
        success: function(response) {
            if (response.status) {
                setFilters(response.data, {});
            }
        },
        error: function(xhr) {
            console.error('Failed to load filter options:', xhr);
        }
    });
}

/**
 * Load plan statistics
 */
function loadPlanStatistics() {
    $.ajax({
        url: route('admin.plans.optimized.statistics', { patient_id: patientCardID }),
        type: 'GET',
        success: function(response) {
            if (response.status) {
                displayStatistics(response.data);
            }
        },
        error: function(xhr) {
            console.error('Failed to load statistics:', xhr);
        }
    });
}

/**
 * Display statistics on page
 */
function displayStatistics(stats) {
    // Example: Update dashboard widgets
    $('#total-plans').text(stats.total_plans);
    $('#active-plans').text(stats.active_plans);
    $('#total-amount').text(stats.total_amount);
    $('#cash-received').text(stats.cash_received);
    $('#refunded-plans').text(stats.refunded_plans);
}

/**
 * Initialize optimized datatable
 * 
 * Usage: Call this function instead of the old initialization
 */
function initOptimizedPlansDatatable() {
    // Load filter options
    loadFilterOptions();
    
    // Load statistics
    loadPlanStatistics();
    
    // Initialize datatable with optimized URL
    var datatable = $('.plan-form').KTDatatable({
        data: {
            type: 'remote',
            source: {
                read: {
                    url: table_url,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    map: function(raw) {
                        var dataSet = raw;
                        if (typeof raw.data !== 'undefined') {
                            dataSet = raw.data;
                        }
                        return dataSet;
                    },
                }
            },
            pageSize: 10,
            serverPaging: true,
            serverFiltering: true,
            serverSorting: true,
        },
        layout: {
            scroll: true,
            height: 550,
            footer: false,
        },
        sortable: true,
        pagination: true,
        columns: table_columns,
    });

    // Apply filters
    applyFilters(datatable);
    
    // Reset filters
    resetAllFilters(datatable);
    
    return datatable;
}

/**
 * MIGRATION NOTES:
 * 
 * 1. Change the table_url to use the new optimized route:
 *    var table_url = route('admin.plans.optimized.datatable', { patient_id: patientCardID });
 * 
 * 2. The API now returns pre-formatted data, so you can remove some templates:
 *    - 'created_at' is already formatted
 *    - 'settle_amount' is already formatted
 *    - Financial values are already formatted with number_format
 * 
 * 3. Load filter options using the new lookup endpoint:
 *    route('admin.plans.optimized.lookup', { patient_id: patientCardID })
 * 
 * 4. Optionally load statistics:
 *    route('admin.plans.optimized.statistics', { patient_id: patientCardID })
 * 
 * 5. All existing filter functions (applyFilters, resetAllFilters) work as-is
 * 
 * 6. All existing action functions (editRow, deleteRow, viewPlan) work as-is
 * 
 * 7. Performance improvements are automatic - no code changes needed
 */

/**
 * Example: How to test the new API manually
 */
function testOptimizedAPI() {
    console.log('Testing optimized plans API...');
    
    // Test datatable endpoint
    $.ajax({
        url: route('admin.plans.optimized.datatable', { patient_id: patientCardID }),
        type: 'POST',
        data: {
            start: 0,
            length: 10,
            draw: 1
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log('Datatable response:', response);
            console.log('Total records:', response.recordsTotal);
            console.log('First record:', response.data[0]);
        },
        error: function(xhr) {
            console.error('Datatable error:', xhr);
        }
    });
    
    // Test lookup endpoint
    $.ajax({
        url: route('admin.plans.optimized.lookup', { patient_id: patientCardID }),
        type: 'GET',
        success: function(response) {
            console.log('Lookup data:', response);
        },
        error: function(xhr) {
            console.error('Lookup error:', xhr);
        }
    });
    
    // Test statistics endpoint
    $.ajax({
        url: route('admin.plans.optimized.statistics', { patient_id: patientCardID }),
        type: 'GET',
        success: function(response) {
            console.log('Statistics:', response);
        },
        error: function(xhr) {
            console.error('Statistics error:', xhr);
        }
    });
}

// Uncomment to test the API in browser console
// $(document).ready(function() {
//     testOptimizedAPI();
// });
