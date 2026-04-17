/**
 * Dashboard JavaScript
 * Handles lazy loading of dashboard charts using Intersection Observer
 * and API-based data loading for improved performance
 */

(function() {
    'use strict';

    // Load stats via API on page load
    function loadDashboardStats() {
        const requestType = window.dashboardConfig?.requestType || 'today';
        const locationIds = window.dashboardConfig?.locationIds || [];
        
        $.ajax({
            url: '/api/dashboard/stats',
            type: 'GET',
            data: { type: requestType },
            success: function(response) {
                if (response.status && response.data) {
                    const data = response.data;
                    
                    // Update Sales
                    if (data.todaycollection && data.todaycollection[0] !== false) {
                        $('#allleads').html('PKR: ' + data.todaycollection[0]);
                    } else {
                        $('#allleads').html('Your are not authorized');
                    }
                    
                    // Update Revenue
                    if (data.revenue !== null) {
                        const roundedRevenue = Math.round(data.revenue);
                        $('#allrevenue').html('PKR: ' + numberFormat(roundedRevenue, 0));
                    } else {
                        $('#allrevenue').html('Your are not authorized');
                    }
                    
                    // Update Consultancies
                    if (data.done_consultancies !== null && data.all_consultancies !== null) {
                        $('#allconsult').html(data.done_consultancies + '/' + data.all_consultancies);
                        $('#allconsultantdate').attr('href', route('admin.consultancy.index', {
                            type: '1',
                            from: data.start_date || window.dashboardConfig?.startDate,
                            to: data.end_date || window.dashboardConfig?.endDate,
                            center_id: locationIds.join(',')
                        }));
                    } else {
                        $('#allconsult').html('Your are not authorized');
                    }
                    
                    // Update Treatments
                    if (data.done_treatments !== null && data.all_treatments !== null) {
                        $('#alltreat').html(data.done_treatments + '/' + data.all_treatments);
                        $('#alltreatmentdate').attr('href', route('admin.treatment.index', {
                            type: '2',
                            from: data.start_date || window.dashboardConfig?.startDate,
                            to: data.end_date || window.dashboardConfig?.endDate,
                            center_id: locationIds.join(',')
                        }));
                    } else {
                        $('#alltreat').html('Your are not authorized');
                    }
                }
            },
            error: function() {
                $('#allleads, #allrevenue, #allconsult, #alltreat').html('Error loading data');
            }
        });
    }
    
    function numberFormat(num, decimals = 2) {
        if (num === null || num === undefined) return '0';
        // Handle decimal numbers properly - format with specified decimal places
        var parts = parseFloat(num).toFixed(decimals).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        // If decimals is 0, return only the integer part
        return decimals === 0 ? parts[0] : parts.join('.');
    }

    // Activities pagination state
    let activitiesPage = 1;
    let activitiesHasMore = false;
    let activitiesLoading = false;

    /**
     * Load activities via API with infinite scroll support
     */
    function loadActivities(page = 1, append = false) {
        if (activitiesLoading) return;
        activitiesLoading = true;
        
        if (page === 1) {
            $('#activities-loader').show();
            $('#activities-timeline').hide();
            $('#activities-empty').hide();
            $('#activities-unauthorized').hide();
        } else {
            $('#load-more-spinner').show();
        }
        
        $.ajax({
            url: '/api/dashboard/activities',
            type: 'GET',
            data: { page: page, per_page: 10 },
            success: function(response) {
                $('#activities-loader').hide();
                $('#load-more-spinner').hide();
                activitiesLoading = false;
                
                if (!response.status) {
                    if (response.message === 'Unauthorized') {
                        $('#activities-unauthorized').show();
                    }
                    return;
                }

                const activitiesData = response.data;
                const activities = activitiesData.data;
                activitiesHasMore = activitiesData.has_more;
                activitiesPage = activitiesData.current_page;

                // Update total count
                $('#totalactivities').text(activitiesData.total + ' activities');

                if (!activities || activities.length === 0 && page === 1) {
                    $('#activities-empty').show();
                    $('#activities-timeline').hide();
                    $('#load-more-container').hide();
                    return;
                }
                
                // Build activity HTML
                const html = buildActivitiesHtml(activities);
                
                if (append) {
                    $('#activities-timeline').append(html);
                } else {
                    $('#activities-timeline').html(html);
                }
                
                $('#activities-timeline').show();
                
                // Show/hide load more container
                if (activitiesHasMore) {
                    $('#load-more-container').show();
                } else {
                    $('#load-more-container').hide();
                }
            },
            error: function() {
                $('#activities-loader').hide();
                $('#load-more-spinner').hide();
                activitiesLoading = false;
                $('#activities-empty').show();
            }
        });
    }

    /**
     * Build HTML for activity items
     */
    function buildActivitiesHtml(activities) {
        let html = '';
        activities.forEach(function(log) {
            const time = new Date(log.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            const createdBy = log.created_by_name || log.created_by || 'N/A';
            const amount = Math.round(log.amount);
            const patient = log.patient || '';
            const centreName = log.centre ? log.centre.name : (log.location || '');
            const action = log.action;
            const appointmentType = log.appointment_type;
            const planId = log.plan_id;
            
            let content = '';
            if (appointmentType === 'Plan') {
                const actionText = action === 'refunded' ? 'to' : 'from';
                content = `<span style="color: #056FBF;">${createdBy}</span> ${action} <strong>Rs. ${amount}</strong> ${actionText} <span style="color: #056FBF;"> ${patient}</span> for <span style="color: #F5B183;">Plan Id: <a href="/admin/packages/view/${planId}">${planId}</a></span> at ${centreName} Centre.`;
            } else {
                content = `<span style="color: #056FBF;">${createdBy}</span> ${action} <strong>Rs. ${amount}</strong> from <span style="color: #056FBF;"> ${patient}</span> for <span style="color: #F5B183;">${appointmentType}</span> at ${centreName} Centre.`;
            }
            
            html += `
                <div class="timeline-item align-items-start">
                    <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">${time}</div>
                    <div class="timeline-badge"><i class="fa fa-genderless text-danger icon-xl"></i></div>
                    <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">${content}</div>
                </div>
            `;
        });
        return html;
    }

    /**
     * Initialize activities infinite scroll
     */
    function initActivitiesScroll() {
        const container = document.getElementById('activities-container');
        if (!container) return;
        
        let scrollTimeout = null;
        container.addEventListener('scroll', function() {
            if (scrollTimeout) clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                if (activitiesLoading || !activitiesHasMore) return;
                
                const scrollTop = container.scrollTop;
                const scrollHeight = container.scrollHeight;
                const clientHeight = container.clientHeight;
                
                if (scrollTop + clientHeight >= scrollHeight - 100) {
                    loadActivities(activitiesPage + 1, true);
                }
            }, 150);
        });
    }

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
            url: '/api/dashboard/collection-by-service-category',
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
            url: '/api/dashboard/appointment-by-status',
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
            url: '/api/dashboard/appointment-by-status',
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
        
        // Activity is now loaded via API in dashboard.js loadActivities function
        // No need to load here as it's handled by the activities API

        // Initialize role-specific charts
        const centreId = $(".doctorwiseconversion").attr('data-id');
        
        if (window.dashboardConfig.isCSRSupervisor || window.dashboardConfig.isSocialLead) {
            if (typeof initUserWiseArrival === 'function') {
                initUserWiseArrival('today', '', 'firsttime');
            }
            if (!window.dashboardConfig.isCSR && typeof initDoctorWiseConversion === 'function') {
                initDoctorWiseConversion('thismonth', centreId, 'firsttime');
            }
        } else {
            if (typeof initCentreWiseArrival === 'function') {
                initCentreWiseArrival('yesterday', '', 'firsttime');
            }
            if (!window.dashboardConfig.isCSR && typeof initDoctorWiseConversion === 'function') {
                initDoctorWiseConversion('thismonth', centreId, 'firsttime');
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
        loadDashboardStats(); // Load stats via API first
        loadActivities(1); // Load activities via API
        initActivitiesScroll(); // Initialize infinite scroll for activities
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
