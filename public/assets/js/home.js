var central_wise_arrival_chart;
var doc_wise_conversion_chart;
var doc_wise_feedback_chart;
var CENTRE_ID;
var SELECTED_MONTH;
var DOC_ID;

// Helper function to update dropdown period labels
function dropDownList(type, period, centreID) {
    var labels = {
        today: 'Today',
        yesterday: 'Yesterday',
        last7days: 'Last 7 Days',
        week: 'This Week',
        thismonth: 'This Month',
        lastmonth: 'Last Month'
    };
    var label = labels[period] || 'This Month';
    
    if (type === 'centre') {
        $('.centre_wise_arrival_period').html(label + ' <i class="fa fa-angle-down"></i>');
    } else if (type === 'user') {
        $('.user_wise_arrival_period').html(label + ' <i class="fa fa-angle-down"></i>');
    } else if (type === 'doctor') {
        $('.doctor_wise_period').html(label + ' <i class="fa fa-angle-down"></i>');
    }
}

function initCollectionByCentre(type) {
    var $container = $("#collectionbycenter");
    $container.find(".loader-img-attended").show();
    $container.find("#collection-by-centre").hide();

    $.ajax({
        url: '/api/dashboard/collection-by-centre',
        type: 'GET',
        data: { type: type },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#collection-by-centre").show();
            
            var pieData = response.data?.pie?.[type] || response.data?.pie?.today || [];
            $(".total-pie-chart").text(response.data?.total || 0);
            
            var labels = {
                today: 'Today', yesterday: 'Yesterday', last7days: 'Last 7 Days',
                week: 'This Week', thismonth: 'This Month', lastmonth: 'Last Month'
            };
            $(".collection_by_centre_dropdown").text(labels[type] || 'Today');
            
            collectionCentreChart(pieData);
        },
        error: function() {
            $container.find(".loader-img-attended").hide();
        }
    });
}

function collectionCentreChart(pie) {
    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });

    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var chart = new google.visualization.PieChart(document.getElementById('collection-by-centre'));
        chart.draw(data);
    });

    if (pie.length > 1) {
        $("#collection-by-centre").css("height", "500px");
    }
}

function initRevenueByCentre(period) {
    var $container = $("#revenue_by_centre");
    $container.find(".loader-img-attended").show();
    $container.find("#revenue-centre").hide();

    $.ajax({
        url: '/api/dashboard/revenue-by-centre',
        type: 'GET',
        data: { type: period, performance: '0' },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#revenue-centre").show();
            
            var labels = {
                today: { title: 'Today Income', dropdown: 'Today' },
                yesterday: { title: 'Yesterday Income', dropdown: 'Yesterday' },
                last7days: { title: 'Weekly Income', dropdown: 'Last 7 days' },
                week: { title: 'Weekly Income', dropdown: 'This Week' },
                thismonth: { title: 'Monthly Income', dropdown: 'This Month' },
                lastmonth: { title: 'Last Month Income', dropdown: 'Last Month' }
            };
            
            var config = labels[period] || labels.today;
            $(".revenue-centre-title").text(config.title);
            $(".revenue_by_centre_dropdown").text(config.dropdown);
            $(".total-centre").text(response.data?.total || 0);
            
            revenueCentreChart(response.data?.pie || []);
        },
        error: function() {
            $container.find(".loader-img-attended").hide();
        }
    });
}

function revenueCentreChart(pie) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });

    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);

        var chart = new google.visualization.PieChart(document.getElementById('revenue-centre'));
        chart.draw(data);
    });

    if (pie.length > 1) {
        $("#revenue-centre").css("height", "500px");
    }

}

function initRevenueByService(type) {
    var $container = $("#revenue_by_service");
    $container.find(".loader-img-attended").show();
    $container.find("#revenue-service").hide();
    
    $.ajax({
        url: '/api/dashboard/revenue-by-service',
        type: 'GET',
        data: { type: type },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#revenue-service").show();
            
            var labels = {
                today: { title: 'Today Income', dropdown: 'Today' },
                yesterday: { title: 'Yesterday Income', dropdown: 'Yesterday' },
                last7days: { title: 'Weekly Income', dropdown: 'Last 7 Days' },
                week: { title: 'Weekly Income', dropdown: 'Week' },
                thismonth: { title: 'Monthly Income', dropdown: 'This Month' },
                lastmonth: { title: 'Last Month Income', dropdown: 'Last Month' }
            };
            
            var config = labels[type] || labels.today;
            $(".service-title").text(config.title);
            $(".revenue_by_service_dropdown").text(config.dropdown);
            $(".total-service").text(response.data?.total || 0);
            
            var pieData = response.data?.pie?.[type] || response.data?.pie?.today || [];
            var colors = response.data?.colors || [];
            revenueByService(pieData, colors);
        },
        error: function() {
            $container.find(".loader-img-attended").hide();
        }
    });
}

function revenueByService(service, colors) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });

    google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(service);

        var options = {
            title: 'Revenue',
            colors: colors
        };

        var chart = new google.visualization.PieChart(document.getElementById('revenue-service'));
        chart.draw(data, options);
    });

    if (typeof service !== 'undefined' && service.length > 1) {
        $("#revenue-service").css("height", "500px");
    }
}

function initAppointmentsByStatus(period) {
    $.ajax({
        url: '/api/dashboard/appointment-by-status',
        type: 'GET',
        data: { period: period },
        success: function(response) {
            var colors = response.data?.colors || [];
            var pieData = response.data?.pie?.[period] || response.data?.pie?.today || [];
            
            var labels = { today: 'Today', yesterday: 'Yesterday', last7days: 'Last 7 Days', thismonth: 'This Month', lastmonth: 'Last Month' };
            $(".revenue_by_service_dropdown").text(labels[period] || 'Today');
            
            AppointmentByStatus(pieData, colors);
        }
    });
}

function AppointmentByStatus(pie, colors) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });

    google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(pie);

        var options = {
            colors: colors
        };

        var chart = new google.visualization.PieChart(document.getElementById('appointment_status_today'));
        chart.draw(data, options);
    });
    if (typeof pie !== 'undefined' && pie.length > 1) {
        $("#appointment_status_today").css("height", "500px");
    }
}

function initAppointmentsByType(period) {
    $.ajax({
        url: '/api/dashboard/appointment-by-type',
        type: 'GET',
        data: { period: period },
        success: function(response) {
            var colors = response.data?.colors || [];
            var pieData = response.data?.pie?.[period] || response.data?.pie?.today || [];
            AppointmentByType(pieData, colors);
        }
    });
}

function AppointmentByType(pie, colors) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });

    google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(pie);

        var options = {
            colors: colors
        };

        var chart = new google.visualization.PieChart(document.getElementById('appointment_type_today'));
        chart.draw(data, options);
    });
    if (typeof pie !== 'undefined' && pie.length > 1) {
        $("#appointment_type_today").css("height", "500px");
    }
}

function initConsultancyByStatus(period, type) {
    var $container = $("#consultancy_status1");
    $container.find(".loader-img-attended").show();
    $container.find("#consultancy_by_status").hide();

    $.ajax({
        url: '/api/dashboard/appointment-by-status',
        type: 'GET',
        data: { period: period, type: type },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#consultancy_by_status").show();
            
            var colors = response.data?.colors || [];
            var pieData = response.data?.pie?.[period] || response.data?.pie?.today || [];
            
            var labels = { today: 'Today', yesterday: 'Yesterday', last7days: 'Last 7 Days', week: 'This Week', thismonth: 'This Month', lastmonth: 'Last Month' };
            $(".appointment_by_status_dropdown").text(labels[period] || 'Today');
            
            setTimeout(function() { ConsultancyByStatus(pieData, colors); }, 500);
        },
        error: function() { $container.find(".loader-img-attended").hide(); }
    });
}

function initTreatmentByStatus(period, type) {
    var $container = $("#treatment_status1");
    $container.find(".loader-img-attended").show();
    $container.find("#treatment_by_status").hide();

    $.ajax({
        url: '/api/dashboard/appointment-by-status',
        type: 'GET',
        data: { period: period, type: type },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#treatment_by_status").show();
            
            var colors = response.data?.colors || [];
            var pieData = response.data?.pie?.[period] || response.data?.pie?.today || [];
            
            var labels = { today: 'Today', yesterday: 'Yesterday', last7days: 'Last 7 Days', week: 'This Week', thismonth: 'This Month', lastmonth: 'Last Month' };
            $(".appointment_by_type_dropdown").text(labels[period] || 'Today');
            
            setTimeout(function() { TreatmentByStatus(pieData, colors); }, 500);
        },
        error: function() { $container.find(".loader-img-attended").hide(); }
    });
}

function TreatmentByStatus(pie, colors) {
    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var options = {
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('treatment_by_status'));
        chart.draw(data, options);
    });
    if (pie.length > 1) {
        $("#treatment_by_status").css("height", "500px");
    }
}

function ConsultancyByStatus(pie, colors) {
    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var options = {
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('consultancy_by_status'));
        chart.draw(data, options);
    });
    if (pie.length > 1) {
        $("#consultancy_by_status").css("height", "500px");
    }
}

function InitRevenueByServiceCategory(type) {
    var $container = $("#revenue_by_service_category");
    $container.find(".loader-img-attended").show();
    $container.find("#revenue-service-category").hide();
    
    $.ajax({
        url: '/api/dashboard/revenue-by-service-category',
        type: 'GET',
        data: { type: type },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#revenue-service-category").show();
            
            var pieData = response.data?.pie?.[type] || response.data?.pie?.today || [];
            var labels = { today: 'Today', yesterday: 'Yesterday', last7days: 'Last 7 Days', week: 'This Week', thismonth: 'This Month' };
            $(".revenue_by_service_category_dropdown").text(labels[type] || 'Today');
            
            RevenueByServiceCategory(pieData);
        },
        error: function() { $container.find(".loader-img-attended").hide(); }
    });
}

function RevenueByServiceCategory(service, colors) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });

    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(service);
        var options = {
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('revenue-service-category'));
        chart.draw(data, options);
    });

    if (typeof service !== 'undefined' && service.length > 1) {
        $("#revenue-service-category").css("height", "500px");
    }
}

function InitCollectionByServiceCategory(type) {
    $.ajax({
        url: '/api/dashboard/collection-by-service-category',
        type: 'GET',
        data: { type: type || 'today' },
        success: function(response) {
            var pieData = response.data?.pie?.[type] || response.data?.pie?.today || [];
            var colors = response.data?.colors || [];
            CollectionByServiceCategory(pieData, colors);
        }
    });
}

function CollectionByServiceCategory(service, colors) {
    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(service);
        var chart = new google.visualization.PieChart(document.getElementById('revenue-service-collection'));
        chart.draw(data);
    });
    if (typeof service !== 'undefined' && service.length > 1) {
        $("#revenue-service-collection").css("height", "500px");
    }
}

function initCentreWiseArrival(period, centreID, time = '') {
    var $container = $("#staff_wise_arrival");
    $container.find(".loader-img-attended").show();
    $container.find("#centre_wise_arrival, #centre_wise_arrival_02").hide();
    
    if (time != 'firsttime' && central_wise_arrival_chart) {
        central_wise_arrival_chart.destroy();
    }
    if (centreID == 'centre') {
        centreID = $('#centervise_center option:selected').val();
    }
    if (centreID == '' || centreID == 30 || centreID == 'All') {
        centreID = 'All';
    }
    
    $.ajax({
        url: '/api/dashboard/centre-wise-arrival',
        type: 'GET',
        data: { period: period, centre_id: centreID },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#centre_wise_arrival, #centre_wise_arrival_02").show();

            $('#table-body').html("");
            dropDownList('centre', period, centreID = '');
            var TABLE_HTML = "";
            var walkin_t = 0, arrived_t = 0, total_t = 0;
            var barLenght = response.data?.bar || [];
            
            for (var i = 0; i < barLenght.length; i++) {
                var walkin = response.data?.walkin?.[i] ?? 0;
                var arrived = (response.data?.arrived?.[i] || 0) - walkin;
                var total = (response.data?.total?.[i] || 0) - walkin;
                walkin_t += walkin;
                arrived_t += arrived;
                total_t += total;
                var centre_name = barLenght[i].replace(/\bALLURA \b/gi, '');
                if (total != 0 && !isNaN(total)) {
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + centre_name + "</td><td>" + arrived + "/" + total + "</td><td>" + walkin + "</td><td>" + ((arrived / total) * 100).toFixed(2) + "%</td></tr>";
                }
            }
            var percentage = ((arrived_t / total_t) * 100).toFixed(2);
            TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>Total</td><td>" + (isNaN(arrived_t) ? 0 : arrived_t) + "/" + (isNaN(total_t) ? 0 : total_t) + "</td><td>" + (isNaN(walkin_t) ? 0 : walkin_t) + "</td><td>" + (isNaN(percentage) ? 0 : percentage) + "%</td></tr>";
            jQuery('#table-body').append(TABLE_HTML);
            ConsultanciesByStatus(response);
        },
        error: function(xhr) { 
            $container.find(".loader-img-attended").hide();
            if (typeof errorMessage === 'function') errorMessage(xhr);
        }
    });
}

function initUserWiseArrival(period, userID, time = '') {
    var $container = $("#staff_wise_arrival");
    $container.find(".loader-img-attended").show();
    $container.find("#centre_wise_arrival, #centre_wise_arrival_02").hide();

    if (time != 'firsttime' && central_wise_arrival_chart) {
        central_wise_arrival_chart.destroy();
    }
    if (userID == 'user') {
        userID = $('#userwise_arrival option:selected').val();
    }
    if (userID == '' || userID == 'All') {
        userID = 'All';
    }

    $.ajax({
        url: '/api/dashboard/csr-wise-arrival',
        type: 'GET',
        data: { period: period, user_id: userID },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#centre_wise_arrival, #centre_wise_arrival_02").show();

            jQuery('#table-body').html("");
            dropDownList('user', period);
            var TABLE_HTML = "";
            var total = 0, arrived = 0;
            var barLenght = response.data?.bar || [];
            var csr_name = $('.arrivalbtn').text();

            for (var i = 0; i < barLenght.length; i++) {
                arrived += response.data?.arrived?.[i] || 0;
                total += response.data?.total?.[i] || 0;
                if (userID == 'All') {
                    var arrVal = response.data?.arrived?.[i] || 0;
                    var totVal = response.data?.total?.[i] || 0;
                    var pct = totVal > 0 ? ((arrVal / totVal) * 100).toFixed(2) : 0;
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + barLenght[i] + "</td><td>" + arrVal + "/" + totVal + "</td><td>" + pct + "%</td></tr>";
                }
            }
            if (total != 0) {
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + csr_name + "</td><td>" + arrived + "/" + total + "</td><td>" + ((arrived / total) * 100).toFixed(2) + "%</td></tr>";
            }
            jQuery('#table-body').append(TABLE_HTML);
            ConsultanciesByStatus(response);
        },
        error: function(xhr) { 
            $container.find(".loader-img-attended").hide();
            if (typeof errorMessage === 'function') errorMessage(xhr);
        }
    });
}

function ConsultanciesByStatus(bar) {
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    let Data = bar.data.bar;
    let modifiedData;
    if (Data.length > 0) {
        if (Data.some(str => str.includes('ALLURA'))) {
            modifiedData = Data.map(location => location.replace('ALLURA ', ''));
        } else {
            modifiedData = Data;
        }
    } else {
        modifiedData = ['BHD KHI', 'Gulshan Johar', 'DHA KHI', 'JT LHR', 'Gulberg LHR', 'DHA LHR', 'Faisalabad'];
    }
    if (bar.data?.walkin != undefined) {
        for (var i = 0; i < bar.data.walkin.length; i++) {
            bar.data.total[i] -= bar.data.walkin[i];
            bar.data.arrived[i] -= bar.data.walkin[i];
        }
    }
    var options = {
        series: [{
            name: 'Total Appointments',
            data: bar.data.total ?? []
        }, {
            name: 'Arrived',
            data: bar.data.arrived ?? []
        }, {
            name: 'Walk-in',
            data: bar.data.walkin ?? []
        }],
        chart: {
            type: 'bar',
            height: 400,
            toolbar: {
                show: true
            }
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '70%',
                endingShape: 'rounded',
                dataLabels: {
                    position: 'center',
                    orientation: 'vertical'
                }
            },
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: modifiedData,
            labels: {
                rotate: -45,
                rotateAlways: true,
                style: {
                    fontSize: '10px'
                }
            }
        },
        colors: [primary, success, warning],
        dataLabels: {
            enabled: true,
            offsetY: 0,
            textAnchor: 'middle',
            style: {
                fontSize: '11px',
                fontWeight: 600,
                colors: ['#fff']
            },
            formatter: function (val) {
                return val > 0 ? val : '';
            }
        },
        legend: {
            show: true,
            position: 'top'
        },
        tooltip: {
            enabled: true,
            shared: true,
            intersect: false
        }
    };
    central_wise_arrival_chart = new ApexCharts(document.querySelector("#centre_wise_arrival"), options);
    central_wise_arrival_chart.render();
}

function changeCenterDoct(period, center_id) {
    initDoctorWiseConversion(period, center_id, '', true);
}
function changeCenterFeedback(period, center_id) {
    initDoctorWiseFeedback(period, center_id, '', true);
}
function initDoctorWiseConversion(period, centre_id, time = '', nochangeDr = true) {
    var $container = $("#doctor_wise_conversion_section");
    $container.find(".loader-img-attended").show();
    $container.find("#doc_wise_conversion, #centre_wise_arrival_02").hide();
    dropDownList('doctor', period);
    
    if (time != 'firsttime' && doc_wise_conversion_chart) {
        doc_wise_conversion_chart.destroy();
    }

    $('.loader-imgs').show();
    SELECTED_MONTH = period;
    centre_id = $('.selectcenter option:selected').val();
    CENTRE_ID = centre_id;
    var doc_id = $("#doc_nav option:selected").val();
    DOC_ID = doc_id;

    $("#categories-table-body").html("");
    
    if (nochangeDr) {
        $.ajax({
            url: route('admin.getdoctors'),
            type: "GET",
            data: { centre_id: centre_id },
            success: function(response) {
                var html = "<option value='all-docs'>All Doctors</option>";
                jQuery.each(response.doctors, function(index, doctor) {
                    html += "<option value=" + doctor.id + ">" + doctor.name + "</option>";
                });
                jQuery('#doc_nav').html(html);
            }
        });
    }

    var selectedPeriod = $('#dr_wise_con option:selected').val();
    var check_doc_id = doc_id == 'all-docs' ? '' : doc_id;

    $.ajax({
        url: '/api/dashboard/doctor-wise-conversion',
        type: 'GET',
        data: {
            period: selectedPeriod == 'month' ? 'thismonth' : selectedPeriod,
            centre_id: centre_id,
            doc_id: check_doc_id
        },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#doc_wise_conversion, #centre_wise_arrival_02").show();
            $('.loader-imgs').hide();

            var categories = response.data?.categories || [];
            var converted = 0, arrived = 0;
            var TABLE_HTML = "";
            
            jQuery.each(categories, function(index, category) {
                arrived += category.total_arrival || 0;
                converted += category.total_conversion || 0;
                var pct = category.total_arrival > 0 ? ((category.total_conversion / category.total_arrival) * 100).toFixed(2) : 0;
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + category.service + "</td><td>" + category.total_conversion + "/" + category.total_arrival + "</td><td>" + pct + "%</td><td>" + (category.avg || 0).toFixed(2) + "</td></tr>";
            });
            
            var avg = arrived > 0 ? ((converted / arrived) * 100).toFixed(2) : 0;
            var avgValue = converted > 0 ? ((response.data?.sum_val || 0) / converted).toFixed(2) : 0;
            TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>Total</td><td>" + converted + "/" + arrived + "</td><td>" + avg + "%</td><td>" + avgValue + "</td></tr>";

            jQuery('#categories-table-body').html(TABLE_HTML);
            DoctorWiseConversion(response);
        },
        error: function(xhr) {
            $container.find(".loader-img-attended").hide();
            $('.loader-imgs').hide();
            if (typeof errorMessage === 'function') errorMessage(xhr);
        }
    });
}
function initDoctorWiseFeedback(period, centre_id, time = '', nochangeDr = true) {
    var $container = $("#doctor_wise_feedback_section");
    $container.find(".loader-img-attended").show();
    $container.find("#doc_wise_feedback_data").hide();

    dropDownList('doctor', period);
    if (time != 'firsttime' && doc_wise_feedback_chart) {
        doc_wise_feedback_chart.destroy();
    }

    $('.loader-imgs').show();
    SELECTED_MONTH = period;
    centre_id = $('.selectcenterfeedback option:selected').val();
    CENTRE_ID = centre_id;

    var selectedPeriod = $('#dr_wise_fed option:selected').val() || 'thismonth';

    $.ajax({
        url: '/api/dashboard/doctor-wise-feedback',
        type: 'GET',
        data: { period: selectedPeriod, centre_id: centre_id },
        success: function(response) {
            $container.find(".loader-img-attended").hide();
            $container.find("#doc_wise_feedback_data").show();
            $('.loader-imgs').hide();
            
            DoctorWiseFeedback(response);
        },
        error: function(xhr) {
            $container.find(".loader-img-attended").hide();
            $('.loader-imgs').hide();
            if (typeof errorMessage === 'function') errorMessage(xhr);
        }
    });
}

function GetDoctors(centre_id, time = '') {
    if (time != 'firsttime') {
        doc_wise_conversion_chart.destroy();
    }
    dropDownList('doctor', 'thismonth');
    $("#categories-table-body").html('');
    let converted = 0;
    let arrived = 0;
    let avg_sum = 0;
    var period = centre_id == 'all' ? 'lastmonth' : 'thismonth';
    $.ajax({
        url: '/api/dashboard/doctor-wise-conversion',
        type: 'GET',
        cache: false,
        data: {
            'period': period,
            'centre_id': centre_id
        },
        success: function (response) {
            var categories = response.data?.categories || [];
            jQuery('#categories-table-body').html("");
            var TABLE_HTML = "";
            jQuery.each(categories, function (index, category) {
                arrived += category.total_arrival || 0;
                converted += category.total_conversion || 0;
                avg_sum += category.avg || 0;
                var pct = category.total_arrival > 0 ? ((category.total_conversion / category.total_arrival) * 100).toFixed(2) : 0;
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + category.service + "</td><td>" + category.total_conversion + "/" + category.total_arrival + "</td><td>" + pct + "%</td><td>" + (category.avg || 0).toFixed(2) + "</td></tr>";
            });
            var avg = arrived > 0 ? ((converted / arrived) * 100).toFixed(2) : 0;
            var avgValue = converted > 0 ? ((response.data?.sum_val || 0) / converted).toFixed(2) : 0;
            TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>Total</td><td>" + converted + "/" + arrived + "</td><td>" + (avg == "NaN" ? 0 : avg) + "%</td><td>" + (avgValue == "NaN" ? 0 : avgValue) + "</td></tr>";
            jQuery('#categories-table-body').append(TABLE_HTML);
            if (centre_id == 'all') {
                AllDoctorWiseConversion(response);
            } else {
                DoctorWiseConversion(response);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (typeof errorMessage === 'function') errorMessage(xhr);
        }
    });
    var TABLE_HTML = " <option  value='all-docs'>All Doctors</option>";
    $.ajax({
        url: route('admin.getdoctors'),
        type: "GET",
        data: { 'centre_id': centre_id },
        cache: false,
        success: function (response) {
            jQuery('#doc_nav').html("");
            jQuery.each(response.doctors, function (index, doctor) {
                TABLE_HTML += " <option  value=" + doctor.id + ">" + doctor.name + "</option>";
            });
            jQuery('#doc_nav').append(TABLE_HTML);
        },
    });
}

function LoadDocWiseConversion(doc_id, time = '') {
    $("#doctor_wise_conversion_section .loader-img-attended").css('display', '');
    $("#doctor_wise_conversion_section #doc_wise_conversion").css('display', 'none');
    $("#doctor_wise_conversion_section #centre_wise_arrival_02").css('display', 'none');

    if (time != 'firsttime') {
        doc_wise_conversion_chart.destroy();
    }
    dropDownList('doctor', 'thismonth');
    var centre_id = $(".selectcenter option:selected").val();
    DOC_ID = doc_id;
    let converted = 0;
    let arrived = 0;
    let avg_sum = 0;
    $.ajax({
        url: '/api/dashboard/doctor-wise-conversion',
        type: 'GET',
        cache: false,
        data: {
            'period': $('#dr_wise_con option:selected').val() == 'month' ? 'thismonth' : $('#dr_wise_con option:selected').val(),
            'doc_id': DOC_ID,
            'centre_id': centre_id
        },
        success: function (response) {
            $("#doctor_wise_conversion_section .loader-img-attended").css('display', 'none');
            $("#doctor_wise_conversion_section #doc_wise_conversion").css('display', '');
            $("#doctor_wise_conversion_section #centre_wise_arrival_02").css('display', '');

            $("#doc_wise_conversion").html("");
            jQuery('#categories-table-body').html("");
            var TABLE_HTML = "";
            var categories = response.data?.categories || [];

            jQuery.each(categories, function (index, category) {
                arrived += category.total_arrival || 0;
                converted += category.total_conversion || 0;
                avg_sum += category.avg || 0;
                var pct = category.total_arrival > 0 ? ((category.total_conversion / category.total_arrival) * 100).toFixed(2) : 0;
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + category.service + "</td><td>" + category.total_conversion + "/" + category.total_arrival + "</td><td>" + pct + "%</td><td>" + (category.avg || 0).toFixed(2) + "</td></tr>";
            });
            var avg = arrived > 0 ? ((converted / arrived) * 100).toFixed(2) : 0;
            var avgValue = converted > 0 ? ((response.data?.sum_val || 0) / converted).toFixed(2) : 0;
            TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>Total</td><td>" + converted + "/" + arrived + "</td><td>" + (avg == "NaN" ? 0 : avg) + "%</td><td>" + (avgValue == "NaN" ? 0 : avgValue) + "</td></tr>";

            jQuery('#categories-table-body').append(TABLE_HTML);
            DoctorWiseConversion(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            if (typeof errorMessage === 'function') errorMessage(xhr);
        }
    });
}

function DoctorWiseConversion(bar) {
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    let labels = bar.data.labels;
    
    // Calculate dynamic width based on number of doctors (min 800px, 60px per doctor)
    let dynamicWidth = Math.max(800, labels.length * 60);
    
    // Calculate bar width and dynamic font size based on number of doctors
    let barWidthPx = (dynamicWidth * 0.55) / (labels.length * 2); // 55% columnWidth divided by number of bar groups (2 series)
    let dynamicFontSize = Math.min(14, Math.max(9, Math.floor(barWidthPx * 0.4))); // Scale font: min 9px, max 14px

    var options = {
        series: [{
            name: 'Total Appointments ' + `(${bar.data.total_appointments.reduce((a, b) => a + b, 0)})`,
            data: bar.data.total_appointments
        }, {
            name: 'Converted ' + `(${bar.data.converted_appointments.reduce((a, b) => a + b, 0)})`,
            data: bar.data.converted_appointments
        }],
        chart: {
            type: 'bar',
            height: 400,
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
                columnWidth: '70%',
                endingShape: 'rounded',
                dataLabels: {
                    position: 'center',
                    orientation: 'vertical'
                }
            },
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return val > 0 ? val : '';
            },
            style: {
                fontSize: dynamicFontSize + 'px',
                colors: ['#fff'],
                fontWeight: 600
            },
            offsetY: 0
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: labels,
            labels: {
                rotate: -45,
                rotateAlways: true,
                style: {
                    fontSize: '10px'
                },
                trim: true,
                maxHeight: 100
            }
        },
        legend: {
            show: true,
            position: 'top'
        },
        tooltip: {
            shared: true,
            intersect: false
        },
        colors: [primary, success]
    };
    $("#doc_wise_conversion").html("");
    doc_wise_conversion_chart = new ApexCharts(document.querySelector("#doc_wise_conversion"), options);
    doc_wise_conversion_chart.render();
}
function DoctorWiseFeedback(bar) {
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    // Handle both response structures (direct or wrapped in data)
    let responseData = bar.data || bar;
    let labels = responseData.labels || [];
    let totals = responseData.total || [];
    let ratings = responseData.rating || [];

    // Calculate dynamic width based on number of doctors (min 100%, 60px per doctor)
    let dynamicWidth = Math.max(800, labels.length * 60);
    
    // Calculate dynamic column width - narrower bars when fewer doctors
    let columnWidthPercent = labels.length <= 5 ? '35%' : labels.length <= 10 ? '50%' : '60%';
    
    // Dynamic font size - larger for fewer doctors, smaller for many
    let dynamicFontSize = labels.length <= 5 ? 14 : labels.length <= 10 ? 12 : 10;

    var options = {
        series: [{
            name: 'Rating ' + `(${ratings.reduce((a, b) => a + b, 0).toFixed(1)})`,
            data: ratings
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
                    position: 'center',
                    orientation: 'vertical'
                }
            },
        },
        dataLabels: {
            enabled: true,
            formatter: function (val, opts) {
                return val.toFixed(1) + ' (' + totals[opts.dataPointIndex] + ')';
            },
            style: {
                fontSize: dynamicFontSize + 'px',
                colors: ['#fff'],
                fontWeight: 600
            },
            offsetY: 0
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: labels,
            labels: {
                rotate: -45,
                rotateAlways: true,
                style: {
                    fontSize: '11px'
                },
                trim: true,
                maxHeight: 100
            }
        },
        yaxis: {
            min: 0,
            max: 10,
            tickAmount: 5,
            labels: {
                formatter: function (val) {
                    return parseInt(val);
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (val, opts) {
                    return 'Rating: ' + val.toFixed(2) + ' (' + totals[opts.dataPointIndex] + ' reviews)';
                }
            }
        },
        colors: [primary]
    };

    $("#doc_wise_feedback_data").html("");
    
    // Remove any previous stats info first
    $('.feedback-stats-info').remove();
    
    doc_wise_feedback_chart = new ApexCharts(document.querySelector("#doc_wise_feedback_data"), options);
    doc_wise_feedback_chart.render();
    
    // Display feedback statistics under the chart
    var feedbackStats = responseData.feedback_stats || bar.feedback_stats;
    if (feedbackStats) {
        var stats = feedbackStats;
        var badgeClass = stats.percentage >= 50 ? 'success' : (stats.percentage >= 25 ? 'warning' : 'danger');
        var statsHtml = '<div class="feedback-stats-info text-center mt-3 p-2 col-12" style="border-radius: 5px;">' +
            '<span class="font-weight-bold">Feedback Response Rate: </span>' +
            '<span class="text-primary">' + stats.total_feedbacks + '/' + stats.total_treatments + '</span>' +
            ' <span class="badge badge-' + badgeClass + '">' + stats.percentage + '%</span>' +
            '</div>';
        $("#doc_wise_feedback_data").closest('.row').append(statsHtml);
    }
}
function AllDoctorWiseConversion(bar) {
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    let labels = bar.data.labels;
    let modifiedData = labels;
    
    if (labels.some(str => str.includes('All Centres'))) {
        modifiedData = labels.map(location => location.replace('All Centres ', ''));
    }
    if (labels.some(str => str.includes('ALLURA'))) {
        modifiedData = labels.map(location => location.replace('ALLURA ', ''));
    }

    // Calculate dynamic width based on number of items (min 800px, 60px per item)
    let dynamicWidth = Math.max(800, modifiedData.length * 60);
    
    // Calculate bar width and dynamic font size
    let barWidthPx = (dynamicWidth * 0.55) / (modifiedData.length * 2);
    let dynamicFontSize = Math.min(14, Math.max(9, Math.floor(barWidthPx * 0.4)));

    var options = {
        series: [{
            name: 'Total Appointments ' + `(${bar.data.total_appointments.reduce((a, b) => a + b, 0)})`,
            data: bar.data.total_appointments
        }, {
            name: 'Converted ' + `(${bar.data.converted_appointments.reduce((a, b) => a + b, 0)})`,
            data: bar.data.converted_appointments
        }],
        chart: {
            type: 'bar',
            height: 400,
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
                columnWidth: '70%',
                endingShape: 'rounded',
                dataLabels: {
                    position: 'center',
                    orientation: 'vertical'
                }
            },
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                return val > 0 ? val : '';
            },
            style: {
                fontSize: dynamicFontSize + 'px',
                colors: ['#fff'],
                fontWeight: 600
            },
            offsetY: 0
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: modifiedData,
            labels: {
                rotate: -45,
                rotateAlways: true,
                style: {
                    fontSize: '10px'
                },
                trim: true,
                maxHeight: 100
            }
        },
        legend: {
            show: true,
            position: 'top'
        },
        tooltip: {
            shared: true,
            intersect: false
        },
        colors: [primary, success]
    };
    $("#doc_wise_conversion").html("");
    doc_wise_conversion_chart = new ApexCharts(document.querySelector("#doc_wise_conversion"), options);
    doc_wise_conversion_chart.render();
}

// Unattended Payments lazy loading state
var unattendedPaymentsState = {
    page: 1,
    loading: false,
    hasMore: true
};

function initPatientFollowUp(period, centre_id, arrived, reset = true) {
    if (reset) {
        unattendedPaymentsState.page = 1;
        unattendedPaymentsState.hasMore = true;
        $('#patient-follow-up').html("");
    }
    
    if (unattendedPaymentsState.loading || !unattendedPaymentsState.hasMore) return;
    
    unattendedPaymentsState.loading = true;
    
    if (unattendedPaymentsState.page === 1) {
        $('.loader-img-unattended').show();
    } else {
        $('#unattended-loader').show();
    }
    
    $.ajax({
        url: '/api/dashboard/unattended-payments',
        type: 'GET',
        data: {
            page: unattendedPaymentsState.page,
            per_page: 10
        },
        success: function (response) {
            $('.loader-img-unattended').hide();
            $('#unattended-loader').hide();
            unattendedPaymentsState.loading = false;
            
            var patientData = response.data?.patient_data || [];
            unattendedPaymentsState.hasMore = response.data?.has_more || false;
            
            if (patientData.length > 0) {
                var TABLE_HTML = "";
                for (let i = 0; i < patientData.length; i++) {
                    let patient = patientData[i];
                    let routeValue = route('admin.reports.follow_up', { patient_id: patient.patient_id, report_type: 'weekly' });
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'><a href='" + routeValue + "'>" + patient.patient_id + "</a></td><td>" + patient.name + "</td><td>" + ((patient.is_treatment == 0) ? 'Not Booked' : 'No Show') + "</td><td>PKR: " + (patient.balance || 0).toFixed(2) + "</td><td>" + formatDate(patient.created_at, 'MMM, DD yyyy ') + "</td></tr>";
                }
                $('#patient-follow-up').append(TABLE_HTML);
                unattendedPaymentsState.page++;
            } else if (unattendedPaymentsState.page === 1) {
                $('#patient-follow-up').html("<tr><td colspan='5' style='color: #000; text-align:center;font-size: 12px;padding: 90px 0px 0px;font-family: Arial;'>No Data</td></tr>");
            }
            
            $('#followbtn').css('display', 'inline-block');
        },
        error: function (xhr) {
            $('.loader-img-unattended').hide();
            $('#unattended-loader').hide();
            unattendedPaymentsState.loading = false;
            if (typeof errorMessage === 'function') errorMessage(xhr);
        }
    });
}

// Overdue Treatments lazy loading state
var overdueTreatmentsState = {
    page: 1,
    loading: false,
    hasMore: true
};

function initPatientFollowUpOneMonth(reset = true) {
    if (reset) {
        overdueTreatmentsState.page = 1;
        overdueTreatmentsState.hasMore = true;
        $('#patient-follow-up-one-month').html("");
    }
    
    if (overdueTreatmentsState.loading || !overdueTreatmentsState.hasMore) return;
    
    overdueTreatmentsState.loading = true;
    
    if (overdueTreatmentsState.page === 1) {
        $('.loader-img-overdue').show();
    } else {
        $('#overdue-loader').show();
    }
    
    $.ajax({
        url: '/api/dashboard/overdue-treatments',
        type: 'GET',
        data: {
            page: overdueTreatmentsState.page,
            per_page: 10
        },
        success: function (response) {
            $('.loader-img-overdue').hide();
            $('#overdue-loader').hide();
            overdueTreatmentsState.loading = false;
            
            var patientData = response.data?.patient_data || [];
            overdueTreatmentsState.hasMore = response.data?.has_more || false;
            
            if (patientData.length > 0) {
                var TABLE_HTML = "";
                for (let i = 0; i < patientData.length; i++) {
                    let patient = patientData[i];
                    let routeValue = route('admin.reports.follow_up', { patient_id: patient.patient_id, report_type: 'monthly' });
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'><a href='" + routeValue + "'>" + patient.patient_id + "</a></td><td>" + patient.name + "</td><td>PKR: " + (patient.balance || 0).toFixed(2) + "</td><td>" + patient.scheduled_date + "</td></tr>";
                }
                $('#patient-follow-up-one-month').append(TABLE_HTML);
                overdueTreatmentsState.page++;
            } else if (overdueTreatmentsState.page === 1) {
                $('#patient-follow-up-one-month').html("<tr><td colspan='5' style='color: #000; text-align:center;font-size: 12px;padding: 90px 0px 0px;font-family: Arial;'>No Data</td></tr>");
            }
            
            $('#mfollowbtn').css('display', 'inline-block');
        },
        error: function (xhr) {
            $('.loader-img-overdue').hide();
            $('#overdue-loader').hide();
            overdueTreatmentsState.loading = false;
            if (typeof errorMessage === 'function') errorMessage(xhr);
        }
    });
}

// Initialize infinite scroll for Unattended Payments
$(document).ready(function() {
    $('#unattended-payments-scroll').on('scroll', function() {
        var $this = $(this);
        if ($this.scrollTop() + $this.innerHeight() >= $this[0].scrollHeight - 50) {
            initPatientFollowUp('', '', '', false);
        }
    });
    
    // Initialize infinite scroll for Overdue Treatments
    $('#overdue-treatments-scroll').on('scroll', function() {
        var $this = $(this);
        if ($this.scrollTop() + $this.innerHeight() >= $this[0].scrollHeight - 50) {
            initPatientFollowUpOneMonth(false);
        }
    });
});

function changePeriodDropdown(period, report) {
    var labels = {
        today: 'Today',
        yesterday: 'Yesterday',
        last7days: 'Last 7 Days',
        week: 'This Week',
        thismonth: 'This Month',
        lastmonth: 'Last Month'
    };
    $("." + report + "_period").html((labels[period] || 'Today') + ' <i class="fa fa-angle-down"></i>');
}

$(document).ready(function () {
    $('#centervise_center').select2();
    $('#centervise_center').on('change', function () {
        var selectedValue = $(this).val();
        var period = 'thismonth';
        initCentreWiseArrival($('#initCentreWiseArrival option:selected').val(), selectedValue, '')
    });

    $('#userwise_arrival').select2();
    $('#userwise_arrival').on('change', function () {
        var selectedValue = $(this).val();
        var period = $('#center_wise_arrival').val();
        initUserWiseArrival(period, selectedValue, '')
    });

    $('#doc_nav').select2();
    $('#doc_nav').on('change', function () {
        var selectedValue = $(this).val();
        var period = 'thismonth';

        LoadDocWiseConversion(selectedValue, '', '', true)
    });


    $('.selectcenter').select2();
    $('.selectcenter').on('change', function () {
        var selectedValue = $(this).val();
        var period = 'thismonth';
        changeCenterDoct(period, selectedValue)
    });
    
    $('.selectcenterfeedback').select2();
    $('.selectcenterfeedback').on('change', function () {
        var selectedValue = $(this).val();
        var period = 'thismonth';
        changeCenterFeedback(period, selectedValue)
    });
    
    $('.selectcenterupselling').select2();
});

