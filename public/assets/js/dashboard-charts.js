/**
 * Dashboard Charts JavaScript
 * Contains all chart rendering functions and UI handlers for the dashboard
 */

(function() {
    'use strict';

    // Global variable for centre wise arrival chart
    var centre_wise_arrival = null;

    /**
     * Doctor Upselling Functions
     */
    function initDoctorUpselling() {
        // Auto-load data on page load if a centre is pre-selected
        const initialCentreId = $('#doctor_upselling_centre_select').val();
        const initialPeriod = $('#dr_wise_upselling_period').val();
        
        if (initialCentreId) {
            loadDoctorUpsellingData(initialCentreId, initialPeriod);
        }
        
        // Handle centre selection change
        $('#doctor_upselling_centre_select').on('change', function() {
            const centreId = $(this).val();
            const period = $('#dr_wise_upselling_period').val();
            
            if (centreId) {
                loadDoctorUpsellingData(centreId, period);
            } else {
                resetUpsellingTable();
            }
        });
        
        // Handle period change
        $('#dr_wise_upselling_period').on('change', function() {
            const centreId = $('#doctor_upselling_centre_select').val();
            const period = $(this).val();
            
            if (centreId) {
                loadDoctorUpsellingData(centreId, period);
            }
        });
    }

    function loadDoctorUpsellingData(centreId, period) {
        // Show loader and hide chart area
        $('.loader-img-upselling').show();
        $('#doctor_upselling_chart').hide();
        
        // Hide table content
        $('#doctor_upselling_tbody').html('');
        $('#doctor_upselling_tfoot').hide();
        
        $.ajax({
            url: window.dashboardConfig.routes.doctorUpsellingData,
            method: 'GET',
            global: false,
            data: {
                centre_id: centreId,
                period: period
            },
            success: function(response) {
                $('.loader-img-upselling').hide();
                $('#doctor_upselling_chart').show();
                
                if (response.status && response.data.length > 0) {
                    populateUpsellingTable(response.data);
                } else {
                    showUpsellingNoDataMessage();
                }
            },
            error: function(xhr, status, error) {
                $('.loader-img-upselling').hide();
                $('#doctor_upselling_chart').show();
                console.error('Error loading doctor upselling data:', error);
                showUpsellingErrorMessage();
            }
        });
    }

    function populateUpsellingTable(data) {
        let tbody = '';
        let totalUpsellingAmount = 0;
        
        data.forEach(function(doctor) {
            totalUpsellingAmount += parseFloat(doctor.total_upselling_amount || 0);
            
            tbody += '<tr>' +
                '<td class="font-weight-bold">' + doctor.doctor_name + '</td>' +
                '<td class="text-right">' + formatCurrency(doctor.total_upselling_amount) + '</td>' +
            '</tr>';
        });
        
        $('#doctor_upselling_tbody').html(tbody);
        $('#total_upselling_amount').text(formatCurrency(totalUpsellingAmount));
        $('#doctor_upselling_tfoot').show();
        
        // Generate chart
        generateDoctorUpsellingChart(data);
    }

    function generateDoctorUpsellingChart(data) {
        const primary = '#6993FF';
        
        let doctorNames = data.map(function(doctor) { return doctor.doctor_name; });
        let upsellingAmounts = data.map(function(doctor) { return parseFloat(doctor.total_upselling_amount || 0); });
        
        // Hide placeholder and show chart
        $('#doctor_upselling_placeholder').hide();
        
        // Calculate dynamic width based on number of doctors (min 800px, 60px per doctor)
        let dynamicWidth = Math.max(800, doctorNames.length * 60);
        
        // Calculate dynamic column width - narrower bars when fewer doctors
        let columnWidthPercent = doctorNames.length <= 5 ? '35%' : doctorNames.length <= 10 ? '50%' : '60%';
        
        // Dynamic font size - larger for fewer doctors, smaller for many
        let dynamicFontSize = doctorNames.length <= 5 ? 14 : doctorNames.length <= 10 ? 12 : 10;
        
        // Calculate total for series name
        let totalAmount = upsellingAmounts.reduce((a, b) => a + b, 0);
        
        var options = {
            series: [{
                name: 'Upselling Amount (' + formatCurrency(totalAmount) + ')',
                data: upsellingAmounts
            }],
            chart: {
                type: 'bar',
                height: 350,
                width: dynamicWidth,
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: columnWidthPercent,
                    endingShape: 'rounded',
                    dataLabels: {
                        position: 'top',
                        orientation: 'vertical'
                    }
                }
            },
            dataLabels: {
                enabled: true,
                formatter: function(val) { return formatCurrency(val); },
                style: {
                    fontSize: dynamicFontSize + 'px',
                    colors: ['#304758'],
                    fontWeight: 600
                },
                offsetY: -5
            },
            stroke: {
                show: true,
                width: 1,
                colors: ['transparent']
            },
            xaxis: {
                categories: doctorNames,
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    style: { fontSize: '11px' },
                    trim: true,
                    maxHeight: 100
                }
            },
            yaxis: {
                title: { text: 'Upselling Amount' },
                labels: {
                    formatter: function(val) { return formatCurrency(val); }
                }
            },
            colors: [primary],
            fill: { opacity: 1 },
            tooltip: {
                y: {
                    formatter: function(val) { return formatCurrency(val); }
                }
            },
            legend: { show: false }
        };
        
        // Clear any existing chart and add horizontal scroll only (no vertical scroll)
        $('#doctor_upselling_chart').empty();
        $('#doctor_upselling_chart').css({'overflow-x': 'auto', 'overflow-y': 'hidden'});
        
        var chart = new ApexCharts(document.querySelector("#doctor_upselling_chart"), options);
        chart.render();
    }

    function showUpsellingNoDataMessage() {
        $('#doctor_upselling_tbody').html(
            '<tr><td colspan="3" class="text-center text-muted py-5">' +
            '<i class="fas fa-exclamation-triangle mb-2"></i><br>' +
            'No upselling data found for the selected criteria</td></tr>'
        );
        $('#doctor_upselling_tfoot').hide();
        $('#doctor_upselling_chart').empty();
        $('#doctor_upselling_placeholder').show();
        $('#doctor_upselling_placeholder .text-center').html(
            '<i class="fas fa-exclamation-triangle mb-2"></i><br>' +
            'No upselling data found for the selected criteria'
        );
    }

    function showUpsellingErrorMessage() {
        $('#doctor_upselling_tbody').html(
            '<tr><td colspan="3" class="text-center text-danger py-5">' +
            '<i class="fas fa-exclamation-circle mb-2"></i><br>' +
            'Error loading data. Please try again.</td></tr>'
        );
        $('#doctor_upselling_tfoot').hide();
        $('#doctor_upselling_chart').empty();
        $('#doctor_upselling_placeholder').show();
        $('#doctor_upselling_placeholder .text-center').html(
            '<i class="fas fa-exclamation-circle mb-2"></i><br>' +
            'Error loading data. Please try again.'
        );
    }

    function resetUpsellingTable() {
        $('#doctor_upselling_tbody').html(
            '<tr id="no_data_row"><td colspan="3" class="text-center text-muted py-5">' +
            '<i class="fas fa-info-circle mb-2"></i><br>' +
            'Select a centre to view doctor upselling data</td></tr>'
        );
        $('#doctor_upselling_tfoot').hide();
        $('#doctor_upselling_chart').empty();
        $('#doctor_upselling_placeholder').show();
        $('#doctor_upselling_placeholder .text-center').html(
            '<i class="fas fa-info-circle mb-2"></i><br>' +
            'Select a centre to view doctor upselling data'
        );
    }

    /**
     * Dropdown Handlers
     */
    function initDropdownHandlers() {
        jQuery('.btn.arrivalbtn + .dropdown-menu li a').on('click', function() {
            var dataID = jQuery(this).attr('data-id');
            var dataText = jQuery(this).text();
            jQuery('.btn.arrivalbtn').attr('data-id', dataID);
            jQuery('.btn.arrivalbtn').html(dataText + '<i class="fa fa-angle-down"></i>');
            jQuery('.wise_arrival_ul li a').removeClass('active');
            jQuery('.wise_arrival_ul li.thismonth a').addClass('active');
        });

        jQuery('.btn.doctorwiseconversion + .dropdown-menu li a').on('click', function() {
            var dataID = jQuery(this).attr('data-id');
            var dataText = jQuery(this).text();
            jQuery('.btn.doctorwiseconversion').attr('data-id', dataID);
            jQuery('.btn.doctorwiseconversion').html(dataText + '<i class="fa fa-angle-down"></i>');
            jQuery('.doc_wise_arrival_ul li a').removeClass('active');
            jQuery('.doc_wise_arrival_ul li.thismonth a').addClass('active');
        });

        jQuery('.btn.doctorwisefeedback + .dropdown-menu li a').on('click', function() {
            var dataID = jQuery(this).attr('data-id');
            var dataText = jQuery(this).text();
            jQuery('.btn.doctorwisefeedback').attr('data-id', dataID);
            jQuery('.btn.doctorwisefeedback').html(dataText + '<i class="fa fa-angle-down"></i>');
            jQuery('.doc_wise_arrival_ul li a').removeClass('active');
            jQuery('.doc_wise_arrival_ul li.thismonth a').addClass('active');
        });
    }

    /**
     * Plan ID Copy Handler
     */
    function initPlanIdCopy() {
        $(document).on('click', '.planIdText', function() {
            $('.planIdText').tooltip();
            var planId = $(this).text();
            var tempInput = $('<input>');
            $('body').append(tempInput);
            tempInput.val(planId).select();
            document.execCommand('copy');
            tempInput.remove();

            $(this).attr('data-original-title', 'Copied! ' + planId).tooltip('show');
            var self = this;
            setTimeout(function() {
                $(self).attr('data-original-title', 'Click to copy');
            }, 5000);
        });
    }

    /**
     * Google Chart Functions
     */
    window.TreatmentByStatus = function(pie, colors) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function() {
            var data = google.visualization.arrayToDataTable(pie);
            var options = { colors: colors };
            var chart = new google.visualization.PieChart(document.getElementById('treatment_by_status'));
            chart.draw(data, options);
        });
        if (pie && pie.length > 1) {
            $("#treatment_by_status").css("height", "500px");
        }
    };

    window.ConsultancyByStatus = function(pie, colors) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function() {
            var data = google.visualization.arrayToDataTable(pie);
            var options = { colors: colors };
            var chart = new google.visualization.PieChart(document.getElementById('consultancy_by_status'));
            chart.draw(data, options);
        });
        if (pie && pie.length > 1) {
            $("#consultancy_by_status").css("height", "500px");
        }
    };

    window.collectionCentreChart = function(pie) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function() {
            var data = google.visualization.arrayToDataTable(pie);
            var chart = new google.visualization.PieChart(document.getElementById('collection-by-centre'));
            chart.draw(data);
        });
        if (pie && pie.length > 1) {
            $("#collection-by-centre").css("height", "500px");
        }
    };

    window.revenueCentreChart = function(pie) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function() {
            var data = google.visualization.arrayToDataTable(pie);
            var chart = new google.visualization.PieChart(document.getElementById('revenue-centre'));
            chart.draw(data);
        });
        if (pie && pie.length > 1) {
            $("#revenue-centre").css("height", "500px");
        }
    };

    window.revenueByService = function(service, colors) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function() {
            var data = google.visualization.arrayToDataTable(service);
            var options = { colors: colors };
            var chart = new google.visualization.PieChart(document.getElementById('revenue-service'));
            chart.draw(data, options);
        });
        if (typeof service !== 'undefined' && service.length > 1) {
            $("#revenue-service").css("height", "500px");
        }
    };

    window.CollectionByServiceCategory = function(service) {
        google.load('visualization', '1', {
            packages: ['corechart', 'bar', 'line']
        });
        google.setOnLoadCallback(function() {
            var data = google.visualization.arrayToDataTable(service);
            var chart = new google.visualization.PieChart(document.getElementById('revenue-service-collection'));
            chart.draw(data);
        });
        if (typeof service !== 'undefined' && service.length > 1) {
            $("#revenue-service-collection").css("height", "500px");
        }
    };

    window.BarChart = function(service) {
        const primary = '#6993FF';
        const success = '#1BC5BD';
        const warning = '#FFA800';
        
        // Process locations
        var locations = service.data.bar;
        var modifiedLocations;
        
        if (locations && locations.length > 0) {
            if (locations.some(function(str) {
                return str.includes('ALLURA,') || str.includes('CUTERA,');
            })) {
                modifiedLocations = locations.map(function(location) {
                    return location.replace(/^(ALLURA|CUTERA), /i, '');
                });
            } else {
                modifiedLocations = locations;
            }
        } else {
            modifiedLocations = ['Bahadurabad Karachi', 'Gulshan Johar', 'DHA Karachi', 
                                'Johar Town Lahore', 'Gulberg Lahore', 'DHA Lahore'];
        }

        // Create copies of arrays
        var totalData = (service.data.total || []).slice();
        var arrivedData = (service.data.arrived || []).slice();
        var walkinData = (service.data.walkin || []).slice();
        
        // Ensure all arrays have the same length
        var maxLength = Math.max(totalData.length, arrivedData.length, walkinData.length);
        while (totalData.length < maxLength) totalData.push(0);
        while (arrivedData.length < maxLength) arrivedData.push(0);
        while (walkinData.length < maxLength) walkinData.push(0);
        
        // Adjust data: subtract walkin from total and arrived
        for (var i = 0; i < walkinData.length; i++) {
            totalData[i] = Math.max(0, totalData[i] - walkinData[i]);
            arrivedData[i] = Math.max(0, arrivedData[i] - walkinData[i]);
        }

        // Clear any existing chart
        if (centre_wise_arrival) {
            centre_wise_arrival.destroy();
        }

        var options = {
            series: [{
                name: 'Total Appointments',
                data: totalData
            }, {
                name: 'Arrived',
                data: arrivedData
            }, {
                name: 'Walk-in',
                data: walkinData
            }],
            chart: {
                type: 'bar',
                height: 400,
                stacked: false,
                toolbar: { show: true }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '70%',
                    endingShape: 'rounded',
                    dataLabels: { position: 'top' }
                }
            },
            stroke: {
                show: true,
                width: 1,
                colors: ['transparent']
            },
            xaxis: {
                categories: modifiedLocations,
                labels: {
                    rotate: -45,
                    rotateAlways: true,
                    style: { fontSize: '11px' }
                }
            },
            colors: [primary, success, warning],
            dataLabels: { enabled: false },
            legend: {
                show: true,
                position: 'top'
            },
            tooltip: {
                shared: true,
                intersect: false
            },
            yaxis: {
                title: { text: 'Count' }
            }
        };
        
        // Create new chart instance
        centre_wise_arrival = new ApexCharts(document.querySelector("#centre_wise_arrival"), options);
        centre_wise_arrival.render();
    };

    /**
     * Utility Functions
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount || 0);
    }

    // Expose formatCurrency globally
    window.formatCurrency = formatCurrency;

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initDropdownHandlers();
        initPlanIdCopy();
        initDoctorUpselling();
    });

})();
