/**
 * Dashboard JavaScript
 * Handles lazy loading of dashboard charts using Intersection Observer
 * This improves performance by only loading charts when they become visible
 */

(function() {
    'use strict';

    // Track which charts have been loaded
    const loadedCharts = {
        collectionByCentre: false,
        revenueByCentre: false,
        revenueByServiceCategory: false,
        revenueByService: false,
        collectionByServiceCategory: false,
        consultancyByStatus: false,
        treatmentByStatus: false,
        patientFollowUp: false,
        patientFollowUpOneMonth: false
    };

    // Chart loading functions mapping
    const chartLoaders = {
        'collection-by-centre-section': {
            key: 'collectionByCentre',
            load: function() {
                const val = $('#collection_centre').val();
                if (typeof initCollectionByCentre === 'function') {
                    initCollectionByCentre(val);
                }
            }
        },
        'revenue-by-centre-section': {
            key: 'revenueByCentre',
            load: function() {
                const val = $('#revenue_centre').val();
                if (typeof initRevenueByCentre === 'function') {
                    initRevenueByCentre(val);
                }
            }
        },
        'revenue-service-category-section': {
            key: 'revenueByServiceCategory',
            load: function() {
                if (!window.dashboardConfig.isCSR && typeof InitRevenueByServiceCategory === 'function') {
                    InitRevenueByServiceCategory($('#revenue_service_cate').val());
                }
            }
        },
        'revenue-service-section': {
            key: 'revenueByService',
            load: function() {
                if (!window.dashboardConfig.isCSR && typeof initRevenueByService === 'function') {
                    initRevenueByService($('#revenue_service').val());
                }
            }
        },
        'collection-service-category-section': {
            key: 'collectionByServiceCategory',
            load: function() {
                loadCollectionByServiceCategory();
            }
        },
        'consultancy-status-section': {
            key: 'consultancyByStatus',
            load: function() {
                loadConsultancyByStatus();
            }
        },
        'treatment-status-section': {
            key: 'treatmentByStatus',
            load: function() {
                loadTreatmentByStatus();
            }
        },
        'patient-followup-section': {
            key: 'patientFollowUp',
            load: function() {
                if (typeof initPatientFollowUp === 'function') {
                    initPatientFollowUp('thismonth', '');
                }
            }
        },
        'patient-followup-onemonth-section': {
            key: 'patientFollowUpOneMonth',
            load: function() {
                if (typeof initPatientFollowUpOneMonth === 'function') {
                    initPatientFollowUpOneMonth();
                }
            }
        }
    };

    /**
     * Load Collection By Service Category chart
     */
    function loadCollectionByServiceCategory() {
        const requestType = window.dashboardConfig.requestType || '';
        
        $.ajax({
            url: route('admin.home.CollectionByServiceCategory'),
            type: "GET",
            data: { 'type': requestType },
            cache: false,
            success: function(response) {
                const colors = response.data.colors;
                const total = response.data.total;
                let pie;
                
                // Get pie data based on request type
                const pieData = response.data.pie;
                if (requestType === 'today' || requestType === '') {
                    pie = pieData.today;
                } else if (requestType === 'yesterday') {
                    pie = pieData.yesterday;
                } else if (requestType === 'week') {
                    pie = pieData.week;
                } else if (requestType === 'thismonth') {
                    pie = pieData.thismonth || pieData.month;
                } else if (requestType === 'lastmonth') {
                    pie = pieData.lastmonth;
                } else if (requestType === 'last7days') {
                    pie = pieData.last7days;
                }
                
                if (typeof CollectionByServiceCategory === 'function') {
                    CollectionByServiceCategory(pie, colors);
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                if (typeof errorMessage === 'function') {
                    errorMessage(xhr);
                }
            }
        });
    }

    /**
     * Load Consultancy By Status chart
     */
    function loadConsultancyByStatus() {
        const period = $('#consultancy_status').val() || window.dashboardConfig.requestType || 'today';
        
        $.ajax({
            url: route('admin.dashboard.appointment_by_status'),
            type: "GET",
            data: {
                'period': period,
                'type': '1'
            },
            cache: false,
            success: function(response) {
                $("#consultancy_status1 .loader-img-attended").css('display', 'none');
                $("#consultancy_status1 #consultancy_by_status").css('display', '');
                
                const colors = response.data.colors;
                const pie = getPieDataByPeriod(response.data.pie, period);
                
                setTimeout(function() {
                    if (typeof ConsultancyByStatus === 'function') {
                        ConsultancyByStatus(pie, colors);
                    }
                }, 500);
            },
            error: function(xhr, ajaxOptions, thrownError) {
                if (typeof errorMessage === 'function') {
                    errorMessage(xhr);
                }
            }
        });
    }

    /**
     * Load Treatment By Status chart
     */
    function loadTreatmentByStatus() {
        const period = $('#treatment_status').val() || window.dashboardConfig.requestType || 'today';
        
        $.ajax({
            url: route('admin.dashboard.appointment_by_status'),
            type: "GET",
            data: {
                'period': period,
                'type': '2'
            },
            cache: false,
            success: function(response) {
                $("#treatment_status1 .loader-img-attended").css('display', 'none');
                $("#treatment_status1 #treatment_by_status").css('display', '');
                
                const colors = response.data.colors;
                const pie = getPieDataByPeriod(response.data.pie, period);
                
                setTimeout(function() {
                    if (typeof TreatmentByStatus === 'function') {
                        TreatmentByStatus(pie, colors);
                    }
                }, 500);
            },
            error: function(xhr, ajaxOptions, thrownError) {
                if (typeof errorMessage === 'function') {
                    errorMessage(xhr);
                }
            }
        });
    }

    /**
     * Get pie data based on period
     */
    function getPieDataByPeriod(pieData, period) {
        if (!pieData) return [];
        
        switch(period) {
            case 'yesterday':
                return pieData.yesterday;
            case 'week':
                return pieData.week;
            case 'thismonth':
                return pieData.thismonth || pieData.month;
            case 'lastmonth':
                return pieData.lastmonth;
            case 'last7days':
                return pieData.last7days;
            case 'today':
            case '':
            default:
                return pieData.today;
        }
    }

    /**
     * Initialize Intersection Observer for lazy loading
     */
    function initLazyLoading() {
        // Check if Intersection Observer is supported
        if (!('IntersectionObserver' in window)) {
            // Fallback: load all charts immediately
            console.warn('IntersectionObserver not supported, loading all charts');
            loadAllCharts();
            return;
        }

        const observerOptions = {
            root: null, // viewport
            rootMargin: '100px', // Start loading 100px before element is visible
            threshold: 0.1 // Trigger when 10% of element is visible
        };

        const observer = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const sectionId = entry.target.id;
                    const chartConfig = chartLoaders[sectionId];
                    
                    if (chartConfig && !loadedCharts[chartConfig.key]) {
                        loadedCharts[chartConfig.key] = true;
                        
                        // Add small delay to prevent too many simultaneous requests
                        setTimeout(function() {
                            chartConfig.load();
                        }, 100);
                        
                        // Stop observing this element
                        observer.unobserve(entry.target);
                    }
                }
            });
        }, observerOptions);

        // Observe all chart sections
        Object.keys(chartLoaders).forEach(function(sectionId) {
            const element = document.getElementById(sectionId);
            if (element) {
                observer.observe(element);
            }
        });
    }

    /**
     * Fallback: Load all charts (for browsers without IntersectionObserver)
     */
    function loadAllCharts() {
        Object.keys(chartLoaders).forEach(function(sectionId) {
            const chartConfig = chartLoaders[sectionId];
            if (!loadedCharts[chartConfig.key]) {
                loadedCharts[chartConfig.key] = true;
                chartConfig.load();
            }
        });
    }

    /**
     * Initialize dashboard on document ready
     */
    function initDashboard() {
        const period = "today";
        
        // Load activity
        $.ajax({
            url: route('admin.home.getactivity'),
            type: "GET",
            data: { 'type': period },
            cache: false,
            success: function(response) {
                $('.loader-img').css('display', "none");
                $("#activitydiv").html(response);
            }
        });

        // Initialize role-specific charts
        const centreId = $(".doctorwiseconversion").attr('data-id');
        
        if (window.dashboardConfig.isCSRSupervisor || window.dashboardConfig.isSocialLead) {
            if (typeof initUserWiseArrival === 'function') {
                initUserWiseArrival('today', '', 'firsttime');
            }
            if (!window.dashboardConfig.isCSR && typeof initDoctorWiseConversion === 'function') {
                initDoctorWiseConversion('today', centreId, 'firsttime');
            }
        } else {
            if (typeof initCentreWiseArrival === 'function') {
                initCentreWiseArrival('yesterday', '', 'firsttime');
            }
            if (!window.dashboardConfig.isCSR && typeof initDoctorWiseConversion === 'function') {
                initDoctorWiseConversion('today', centreId, 'firsttime');
            }
        }

        // Initialize doctor wise feedback
        const feedbackCentreId = $(".doctorwisefeedback").attr('data-id');
        if (!window.dashboardConfig.isCSR && typeof initDoctorWiseFeedback === 'function') {
            initDoctorWiseFeedback('today', feedbackCentreId, 'firsttime');
        }

        // Initialize lazy loading for other charts
        initLazyLoading();
    }

    /**
     * Initialize dropdown handlers
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
     * Initialize plan ID copy functionality
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
            setTimeout(() => {
                $(this).attr('data-original-title', 'Click to copy');
            }, 5000);
        });
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initDropdownHandlers();
        initDashboard();
        initPlanIdCopy();
    });

    // Expose functions globally for backward compatibility
    window.DashboardLazyLoader = {
        loadChart: function(chartKey) {
            Object.keys(chartLoaders).forEach(function(sectionId) {
                const config = chartLoaders[sectionId];
                if (config.key === chartKey && !loadedCharts[chartKey]) {
                    loadedCharts[chartKey] = true;
                    config.load();
                }
            });
        },
        reloadChart: function(chartKey) {
            loadedCharts[chartKey] = false;
            this.loadChart(chartKey);
        }
    };

})();
