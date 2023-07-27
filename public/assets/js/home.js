var central_wise_arrival_chart;
var doc_wise_conversion_chart;
var CENTRE_ID;
var SELECTED_MONTH;
var DOC_ID;

function initCollectionByCentre(today, yesterday, last7days, week, thismonth, lastmonth) {
    $("#collection_by_centre_menu .active").removeClass('active');
    $("#collection_by_centre_menu").parent().addClass('active');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.collection_by_centre'),
        type: 'GET',
        data: {
            'today': today,
            'yesterday': yesterday,
            'last7days': last7days,
            'week': week,
            'thismonth': thismonth,
            'lastmonth': lastmonth,
        },
        cache: false,
        success: function (response) {
            if (today != '') {
                var pie = response.data.pie.today;
                let total = response.data.total;
                $(".total-pie-chart").text(total);
                $(".collection_by_centre_dropdown").text("Today");
            }
            if (yesterday != '') {
                $(".pie-income-title").text('Yesterday Income');
                var pie = response.data.pie.yesterday;
                let total = response.data.total;
                $(".total-pie-chart").text(total);
                $(".collection_by_centre_dropdown").text("Yesterday");
            }
            if (last7days != '') {
                $(".pie-income-title").text('Weekly Income');
                var pie = response.data.pie.last7days;
                let total = response.data.total;
                $(".total-pie-chart").text(total);
                $(".collection_by_centre_dropdown").text("Last 7 Days");
            }
            if (week != '') {
                $(".pie-income-title").text('Weekly Income');
                var pie = response.data.pie.week;
                let total = response.data.total;
                $(".total-pie-chart").text(total);
                $(".collection_by_centre_dropdown").text("This Week");
            }
            if (thismonth != '') {
                $(".pie-income-title").text('Monthly Income');
                var pie = response.data.pie.thismonth;
                let total = response.data.total;
                $(".total-pie-chart").text(total);
                $(".collection_by_centre_dropdown").text("Tis Month");
            }
            if (lastmonth != '') {
                $(".pie-income-title").text('Last Month Income');
                var pie = response.data.pie.lastmonth;
                let total = response.data.total;
                $(".total-pie-chart").text(total);
                $(".collection_by_centre_dropdown").text("Last Month");
            }
            collectionCentreChart(pie);
        },
    });
}

function collectionCentreChart(pie) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });

    google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(pie);

        var options = {
            title: 'Collections',
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };

        var chart = new google.visualization.PieChart(document.getElementById('collection-by-centre'));
        chart.draw(data, options);
    });

    if (pie.length > 1) {
        $("#collection-by-centre").css("height", "500px");
    }
}

function initRevenueByCentre(period) {
    $("#revenue_by_centre_menu .active").removeClass('active');
    $("#revenue_by_centre_menu").parent().addClass('active');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.revenueByCentre'),
        type: 'GET',
        data: {
            'period': period,
            'performance': '0'
        },
        cache: false,
        success: function (response) {
            if (period == "today") {
                $(".revenue-centre-title").text('Today Income');
                let total = response.data.total;
                $(".total-centre").text(total);
                $(".revenue_by_centre_dropdown").text("Today");
                var pie = response.data.pie;
            }
            if (period == "yesterday") {
                $(".revenue-centre-title").text('Yesterday Income');
                let total = response.data.total;
                $(".total-centre").text(total);
                $(".revenue_by_centre_dropdown").text("Yesterday");
                var pie = response.data.pie;
            }
            if (period == "last7days") {
                let total = response.data.total;
                $(".revenue-centre-title").text('Weekly Income');
                $(".revenue_by_centre_dropdown").text("Last 7 days");
                $(".total-centre").text(total);
                var pie = response.data.pie;
            }
            if (period == "week") {
                let total = response.data.total;
                $(".revenue-centre-title").text('Weekly Income');
                $(".revenue_by_centre_dropdown").text("This Week");
                $(".total-centre").text(total);
                var pie = response.data.pie;
            }
            if (period == "thismonth") {
                $(".revenue-centre-title").text('Monthly Income');
                let total = response.data.total;
                $(".total-centre").text(total);
                $(".revenue_by_centre_dropdown").text("This Month");
                var pie = response.data.pie;
            }
            if (period == "lastmonth") {
                $(".revenue-centre-title").text('Last Month Income');
                let total = response.data.total;
                $(".total-centre").text(total);
                $(".revenue_by_centre_dropdown").text("Last Month");
                var pie = response.data.pie;
            }
            revenueCentreChart(pie);
        },
    });
}

function revenueCentreChart(pie) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });

    google.setOnLoadCallback(function () {

        var data = google.visualization.arrayToDataTable(pie);

        var options = {
            title: 'Revenue',
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };

        var chart = new google.visualization.PieChart(document.getElementById('revenue-centre'));
        chart.draw(data, options);
    });

    if (pie.length > 1) {
        $("#revenue-centre").css("height", "500px");
    }

}

function initRevenueByService(today, yesterday, last7days, week, thismonth, lastmonth) {
    $("#revenue_by_service_menu .active").removeClass('active');
    $("#revenue_by_service_menu").parent().addClass('active');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.revenueByService'),
        type: 'GET',
        cache: false,
        data: {
            'today': today,
            'yesterday': yesterday,
            'last7days': last7days,
            'week': week,
            'thismonth': thismonth,
            'lastmonth': lastmonth,
        },
        success: function (response) {
            let colors = response.data.colors;
            if (today != '') {
                $(".service-title").text('Today Income');
                let total = response.data.total;
                $(".total-service").text(total);
                $(".revenue_by_service_dropdown").text("Today");
                var pie = response.data.pie.today;
            }
            if (yesterday != '') {
                $(".service-title").text('Yesterday Income');
                let total = response.data.total;
                $(".total-service").text(total);
                $(".revenue_by_service_dropdown").text("Yesterday");
                var pie = response.data.pie.yesterday;
            }
            if (last7days != '') {
                $(".service-title").text('Weekly Income');
                let total = response.data.total;
                $(".total-service").text(total);
                $(".revenue_by_service_dropdown").text("Last 7 Days");
                var pie = response.data.pie.last7days;
            }
            if (week != '') {
                $(".service-title").text('Weekly Income');
                let total = response.data.total;
                $(".total-service").text(total);
                $(".revenue_by_service_dropdown").text("Week");
                var pie = response.data.pie.week;
            }
            if (thismonth != '') {
                $(".service-title").text('Monthly Income');
                let total = response.data.total;
                $(".total-service").text(total);
                $(".revenue_by_service_dropdown").text("This Month");
                var pie = response.data.pie.month;
            }
            if (lastmonth != '') {
                $(".service-title").text('Last Month Income');
                let total = response.data.total;
                $(".total-service").text(total);
                $(".revenue_by_service_dropdown").text("Last Month");
                var pie = response.data.pie.lastmonth;
            }
            revenueByService(pie, colors);
        },

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
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_status'),
        type: 'GET',
        data: { 'period': period },
        cache: false,
        success: function (response) {
            let colors = response.data.colors;
            if (period == "today") {
                var pie = response.data.pie.today;
                $(".revenue_by_service_dropdown").text("Today");
            }
            if (period == "yesterday") {
                var pie = response.data.pie.yesterday;
                $(".revenue_by_service_dropdown").text("Yesterday");
            }
            if (period == "last7days") {
                var pie = response.data.pie.last7days;
                $(".revenue_by_service_dropdown").text("Last 7 Days");
            }
            if (period == "thismonth") {
                var pie = response.data.pie.thismonth;
                $(".revenue_by_service_dropdown").text("This Month");
            }
            if (period == "lastmonth") {
                var pie = response.data.pie.lastmonth;
                $(".revenue_by_service_dropdown").text("Last Month");
            }
            AppointmentByStatus(pie, colors);

        },

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
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_type'),
        type: 'GET',
        data: { 'period': period },
        cache: false,
        success: function (response) {
            let colors = response.data.colors;
            if (period == "today") {
                var pie = response.data.pie.today;
            }
            if (period == "yesterday") {
                var pie = response.data.pie.yesterday;
            }
            if (period == "last7days") {
                var pie = response.data.pie.last7days;
            }
            if (period == "thismonth") {
                var pie = response.data.pie.thismonth;
            }
            if (period == "lastmonth") {
                var pie = response.data.pie.lastmonth;
            }
            AppointmentByType(pie, colors);

        },

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
    $("#appointment_by_status_menu .active").removeClass('active');
    $("#appointment_by_status_menu").parent().addClass('active');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_status'),
        type: 'GET',
        data: { 'period': period, 'type': type },
        cache: false,
        success: function (response) {

            let colors = response.data.colors;
            if (period == "today") {
                var pie = response.data.pie.today;
                $(".appointment_by_status_dropdown").text("Today");
            }
            if (period == "yesterday") {
                var pie = response.data.pie.yesterday;
                $(".appointment_by_status_dropdown").text("Yesterday");
            }
            if (period == "last7days") {
                var pie = response.data.pie.last7days;
                $(".appointment_by_status_dropdown").text("Last 7 Days");
            }
            if (period == "week") {
                var pie = response.data.pie.week;
                $(".appointment_by_status_dropdown").text("This Week");
            }
            if (period == "thismonth") {
                var pie = response.data.pie.thismonth;
                $(".appointment_by_status_dropdown").text("This Month");
            }
            if (period == "lastmonth") {
                var pie = response.data.pie.lastmonth;
                $(".appointment_by_status_dropdown").text("Last Month");
            }
            ConsultancyByStatus(pie, colors);

        },

    });
}

function initTreatmentByStatus(period, type) {
    $("#appointment_by_type_menu .active").removeClass('active');
    $("#appointment_by_type_menu").parent().addClass('active');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_status'),
        type: 'GET',
        data: { 'period': period, 'type': type },
        cache: false,
        success: function (response) {
            let colors = response.data.colors;
            if (period == "today") {
                var pie = response.data.pie.today;
                $(".appointment_by_type_dropdown").text("Today");
            }
            if (period == "yesterday") {
                var pie = response.data.pie.yesterday;
                $(".appointment_by_type_dropdown").text("Yesterday");
            }
            if (period == "last7days") {
                var pie = response.data.pie.last7days;
                $(".appointment_by_type_dropdown").text("Last 7 Days");
            }
            if (period == "week") {
                var pie = response.data.pie.week;
                $(".appointment_by_type_dropdown").text("This Week");
            }
            if (period == "thismonth") {
                var pie = response.data.pie.thismonth;
                $(".appointment_by_type_dropdown").text("This Month");
            }
            if (period == "lastmonth") {
                var pie = response.data.pie.lastmonth;
                $(".appointment_by_type_dropdown").text("Last Month");
            }
            TreatmentByStatus(pie, colors);

        },

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

function InitRevenueByServiceCategory(today, yesterday, last7days, thismonth, lastmonth) {
    $("#revenue_by_service_category_menu .active").removeClass('active');
    $("#revenue_by_service_category_menu").parent().addClass('active');

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.revenueByServiceCategory'),
        type: 'GET',
        cache: false,
        data: {
            'today': today,
            'yesterday': yesterday,
            'last7days': last7days,
            'thismonth': thismonth,
            'lastmonth': lastmonth,
        },
        success: function (response) {
            let colors = response.data.colors;
            if (today != '') {
                var pie = response.data.pie.today;
                $(".revenue_by_service_category_dropdown").text("Today");
            }
            if (yesterday != '') {
                var pie = response.data.pie.yesterday;
                $(".revenue_by_service_category_dropdown").text("Yesterday");
            }
            if (last7days != '') {
                var pie = response.data.pie.week;
                $(".revenue_by_service_category_dropdown").text("Last 7 Days");
            }
            if (thismonth != '') {
                var pie = response.data.pie.month;
                $(".revenue_by_service_category_dropdown").text("This Month");
            }
            if (lastmonth != '') {
                var pie = response.data.pie.lastmonth;
                $(".revenue_by_service_category_dropdown").text("Last Month");
            }
            RevenueByServiceCategory(pie, colors);
        },
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

function InitCollectionByServiceCategory(today, yesterday, last7days, thismonth, lastmonth) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.CollectionByServiceCategory'),
        type: 'GET',
        cache: false,
        data: {
            'today': today,
            'yesterday': yesterday,
            'last7days': last7days,
            'thismonth': thismonth,
            'lastmonth': lastmonth,
        },
        success: function (response) {
            let colors = response.data.colors;
            if (today != '') {
                var pie = response.data.pie.today;
            }
            if (yesterday != '') {
                var pie = response.data.pie.yesterday;
            }
            if (last7days != '') {
                var pie = response.data.pie.last7days;
            }
            if (thismonth != '') {
                var pie = response.data.pie.thismonth;
            }
            if (lastmonth != '') {
                var pie = response.data.pie.lastmonth;
            }
            CollectionByServiceCategory(pie, colors);
        },
    });
}

function CollectionByServiceCategory(service, colors) {
    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(service);
        var options = {
            colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
        };
        var chart = new google.visualization.PieChart(document.getElementById('revenue-service-collection'));

        chart.draw(data, options);
    });
    if (typeof service !== 'undefined' && service.length > 1) {
        $("#revenue-service-collection").css("height", "500px");
    }
}

function initCentreWiseArrival(period, centreID, time = '') {
    if (time != 'firsttime') {
        central_wise_arrival_chart.destroy();
    }
    if (centreID == 'centre') {
        centreID = $('.btn.arrivalbtn').attr('data-id');
    }
    if (centreID == '' || centreID == 30 || centreID == 'All') {
        centreID = 'All';
    }
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.centre_wise_arrival'),
        type: 'GET',
        cache: false,
        data: {
            'period': period,
            'centre_id': centreID
        },
        success: function (response) {
            $('#table-body').html("");
            dropDownList('centre', period, centreID = '');
            var TABLE_HTML = "";
            let walkin_t = 0;
            let arrived_t = 0;
            let total_t = 0;

            var barLenght = response.data.bar;
            for (var i = 0; i < barLenght.length; i++) {
                let walkin = response.data.walkin[i] ?? 0;
                let arrived = response.data.arrived[i] - walkin;
                let total = response.data.total[i] - walkin;
                walkin_t += walkin;
                arrived_t += response.data.arrived[i];
                total_t += response.data.total[i];

                let str = barLenght[i];
                let wordToRemove = "CUTERA ";
                let centre_name = str.replace(new RegExp('\\b' + wordToRemove + '\\b', 'gi'), '');
                if (total != 0) {
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + centre_name + "</td><td>" + arrived + "/" + total + "</td><td>" + walkin + "</td><td>" + ((arrived / total) * 100).toFixed(2) + "%</td></tr>";
                }
            }
            arrived_t -= walkin_t;
            total_t -= walkin_t;

            if ((centreID == "All" || centreID == "") && total_t != 0) {
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'></td><td>" + arrived_t + "/" + total_t + "</td><td>" + walkin_t + "</td><td>" + ((arrived_t / total_t) * 100).toFixed(2) + "%</td></tr>";

            }
            jQuery('#table-body').append(TABLE_HTML);
            ConsultanciesByStatus(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function initUserWiseArrival(period, userID, time = '') {
    if (time != 'firsttime') {
        central_wise_arrival_chart.destroy();
    }
    if (userID == 'user') {
        userID = $('.btn.arrivalbtn').attr('data-id');
    }
    if (userID == '' || userID == 'All') {
        userID = 'All';
    }

    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.csr_wise_arrival'),
        type: 'GET',
        cache: false,
        data: {
            'period': period,
            'user_id': userID
        },
        success: function (response) {
            jQuery('#table-body').html("");
            dropDownList('user', period);
            var TABLE_HTML = "";
            let total = 0;
            let arrived = 0;
            var barLenght = response.data.bar;
            var csr_name = $('.arrivalbtn').text();

            for (var i = 0; i < barLenght.length; i++) {
                arrived += response.data.arrived[i];
                total += response.data.total[i];
                if (userID == 'All') {
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + barLenght[i] + "</td><td>" + response.data.arrived[i] + "/" + response.data.total[i] + "</td><td>" + ((response.data.arrived[i] / response.data.total[i]) * 100).toFixed(2) + "%</td></tr>";
                }
            }
            if (total != 0) {
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + csr_name + "</td><td>" + arrived + "/" + total + "</td><td>" + ((arrived / total) * 100).toFixed(2) + "%</td></tr>";
            }

            jQuery('#table-body').append(TABLE_HTML);
            ConsultanciesByStatus(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
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
        if (Data.some(str => str.includes('CUTERA'))) {
            modifiedData = Data.map(location => location.replace('CUTERA ', ''));
        } else {
            modifiedData = Data;
        }
    } else {
        modifiedData = ['Bahadurabad Karachi', 'Gulshan Johar', 'DHA Karachi', 'Johar Town Lahore', 'Gulberg Lahore', 'DHA Lahore'];
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
        },],
        noData: {
            text: 'No Data',
            align: 'center',
            verticalAlign: 'top',
            style: {
                color: 'red',
                fontSize: '14px',
                fontFamily: undefined
            }
        },
        chart: {
            type: 'bar',
            height: 350,

        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: modifiedData,
        },
        colors: [primary, success, warning]
    };
    central_wise_arrival_chart = new ApexCharts(document.querySelector("#centre_wise_arrival"), options);
    central_wise_arrival_chart.render();
}

function initDoctorWiseConversion(period, time = '') {
    dropDownList('doctor', period);
    if (time != 'firsttime') {
        doc_wise_conversion_chart.destroy();
    }
    $('.loader-imgs').css('display', "block");
    SELECTED_MONTH = period;
    var centre_id = $(".doctorwiseconversion").attr('data-id');
    CENTRE_ID = centre_id;console.log(centre_id)
    var doc_id = $(".doctorname").attr('data-id');
    DOC_ID = doc_id;
    let converted = 0;
    let arrived = 0;
    let avg_sum = 0;
    $('.arrivalbtn').text();
    $("#categories-table-body").html("");
    if (centre_id == 'all' && doc_id == 'all-docs') {
        $.ajax({
            url: route('admin.dashboard.all_doctor_wise_conversion'),
            type: 'GET',
            cache: false,
            data: {
                'period': period,
                'centre_id': centre_id
            },
            success: function (response) {
                $('.loader-imgs').css('display', "none");
                var categories = response.data.categories
                jQuery('#categories-table-body').html("");
                var TABLE_HTML = "";
                jQuery.each(categories, function (index, category) {
                    arrived += category.total_arrival;
                    converted += category.total_conversion;
                    avg_sum += category.avg;
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + category.service + "</td><td>" + category.total_conversion + "/" + category.total_arrival + "</td><td>" + ((category.total_conversion / category.total_arrival) * 100).toFixed(2) + "%</td><td>" + (category.avg).toFixed(2) + "</td></tr>";

                });
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + "</td><td>" + converted + "/" + arrived + "</td><td>" + ((converted / arrived) * 100).toFixed(2) + "%</td><td>" + ((avg_sum / categories.length)).toFixed(2) + "</td></tr>";

                jQuery('#categories-table-body').append(TABLE_HTML);
                AllDoctorWiseConversion(response);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });
    } else {
        $.ajax({
            url: route('admin.dashboard.doctor_wise_conversion'),
            type: 'GET',
            cache: false,
            data: {
                'period': SELECTED_MONTH,
                'centre_id': CENTRE_ID,
                'doc_id': DOC_ID
            },
            success: function (response) {
                $('.loader-imgs').css('display', "none");
                var categories = response.data.categories;
                jQuery('#categories-table-body').html("");
                var TABLE_HTML = "";
                jQuery.each(categories, function (index, category) {
                    arrived += category.total_arrival;
                    converted += category.total_conversion;
                    avg_sum += category.avg;
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + category.service + "</td><td>" + category.total_conversion + "/" + category.total_arrival + "</td><td>" + ((category.total_conversion / category.total_arrival) * 100).toFixed(2) + "%</td><td>" + (category.avg).toFixed(2) + "</td></tr>";

                });
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + "</td><td>" + converted + "/" + arrived + "</td><td>" + ((converted / arrived) * 100).toFixed(2) + "%</td><td>" + ((avg_sum / categories.length)).toFixed(2) + "</td></tr>";

                jQuery('#categories-table-body').append(TABLE_HTML);
                DoctorWiseConversion(response);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });
    }

}

function GetDoctors(centre_id, time = '') {
    if (time != 'firsttime') {
        doc_wise_conversion_chart.destroy();
    }
    dropDownList('doctor', 'thismonth');
    $('#doc_nav').empty();
    $(".doctorname").attr('data-id', '');
    $(".doctorname").html('Select doctor <i class="fa fa-angle-down"></i>');
    $("#categories-table-body").html('');
    let converted = 0;
    let arrived = 0;
    let avg_sum = 0;
    if (centre_id == 'all') {
        $.ajax({
            url: route('admin.dashboard.all_doctor_wise_conversion'),
            type: 'GET',
            cache: false,
            data: {
                'period': 'lastmonth',
                'centre_id': centre_id
            },
            success: function (response) {
                var categories = response.data.categories
                jQuery('#categories-table-body').html("");
                var TABLE_HTML = "";
                jQuery.each(categories, function (index, category) {
                    arrived += category.total_arrival;
                    converted += category.total_conversion;
                    avg_sum += category.avg;
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + category.service + "</td><td>" + category.total_conversion + "/" + category.total_arrival + "</td><td>" + ((category.total_conversion / category.total_arrival) * 100).toFixed(2) + "%</td><td>" + (category.avg).toFixed(2) + "</td></tr>";

                });
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + "</td><td>" + converted + "/" + arrived + "</td><td>" + ((converted / arrived) * 100).toFixed(2) + "%</td><td>" + ((avg_sum / categories.length)).toFixed(2) + "</td></tr>";

                jQuery('#categories-table-body').append(TABLE_HTML);
                AllDoctorWiseConversion(response);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });
    } else {
        $.ajax({
            url: route('admin.dashboard.doctor_wise_conversion'),
            type: 'GET',
            cache: false,
            data: {
                'period': 'thismonth',
                'centre_id': centre_id
            },
            success: function (response) {
                var categories = response.data.categories
                jQuery('#categories-table-body').html("");
                var TABLE_HTML = "";
                jQuery.each(categories, function (index, category) {
                    arrived += category.total_arrival;
                    converted += category.total_conversion;
                    avg_sum += category.avg;
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + category.service + "</td><td>" + category.total_conversion + "/" + category.total_arrival + "</td><td>" + ((category.total_conversion / category.total_arrival) * 100).toFixed(2) + "%</td><td>" + (category.avg).toFixed(2) + "</td></tr>";

                });
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + "</td><td>" + converted + "/" + arrived + "</td><td>" + ((converted / arrived) * 100).toFixed(2) + "%</td><td>" + ((avg_sum / categories.length)).toFixed(2) + "</td></tr>";

                jQuery('#categories-table-body').append(TABLE_HTML);
                DoctorWiseConversion(response);
            },
            error: function (xhr, ajaxOptions, thrownError) {
                errorMessage(xhr);
            }
        });
    }
    var all = "all";
    var TABLE_HTML = "";
    $.ajax({
        url: route('admin.getdoctors'),
        type: "GET",
        data: { 'centre_id': centre_id },
        cache: false,
        success: function (response) {
            jQuery('#doc_nav').html("");
            jQuery.each(response.doctors, function (index, doctor) {

                TABLE_HTML += " <li><a class='dropdown-item centre-item'  data-id=" + doctor.id + " onclick='LoadDocWiseConversion(" + doctor.id + ")'>" + doctor.name + "</a></li>";
            });
            jQuery('#doc_nav').append(TABLE_HTML);
        },
    });
}
function LoadDocWiseConversion(doc_id) {
    doc_wise_conversion_chart.destroy();
    dropDownList('doctor', 'thismonth');
    var DrName = $('#doc_nav').find('li').find('a[data-id=' + doc_id + ']').text();
    jQuery('.btn.doctorname').html(DrName + '<i class="fa fa-angle-down"></i>')
    jQuery('.btn.doctorname').attr('data-id', doc_id);
    var centre_id = $(".doctorwiseconversion").attr('data-id');
    DOC_ID = doc_id;
    let converted = 0;
    let arrived = 0;
    $.ajax({
        url: route('admin.dashboard.doctor_wise_conversion'),
        type: 'GET',
        cache: false,
        data: {
            'period': 'thismonth',
            'doc_id': DOC_ID,
            'centre_id': centre_id
        },
        success: function (response) {
            $("#doc_wise_conversion").html("");
            jQuery('#categories-table-body').html("");
            var TABLE_HTML = "";

            jQuery.each(response.data.categories, function (index, category) {
                arrived += category.total_arrival;
                converted += category.total_conversion;
                TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + category.service + "</td><td>" + category.total_conversion + "/" + category.total_arrival + "</td><td>" + ((category.total_conversion / category.total_arrival) * 100).toFixed(2) + "%</td><td>" + (category.avg).toFixed(2) + "</td></tr>";

            });

            TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + "</td><td>" + converted + "/" + arrived + "</td><td>" + ((converted / arrived) * 100).toFixed(2) + "%</td></tr>";

            jQuery('#categories-table-body').append(TABLE_HTML);
            DoctorWiseConversion(response);
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}
function DoctorWiseConversion(bar) {
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    let lables = bar.data.labels;
    var options = {
        series: [{
            name: 'Total Appointments',
            data: bar.data.total_appointments
        }, {
            name: 'Converted',
            data: bar.data.converted_appointments
        }],
        noData: {
            text: 'No Data',
            align: 'center',
            verticalAlign: 'top',
            style: {
                color: 'red',
                fontSize: '14px',
                fontFamily: undefined
            }
        },
        chart: {
            type: 'bar',
            height: 350,

        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: lables,
        },
        colors: [primary, success, warning]
    };
    $("#doc_wise_conversion").html("");
    doc_wise_conversion_chart = new ApexCharts(document.querySelector("#doc_wise_conversion"), options);
    doc_wise_conversion_chart.render();
}
function AllDoctorWiseConversion(bar) {
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    let lables = bar.data.labels;
    if (lables.some(str => str.includes('All Centres'))) {
        modifiedData = lables.map(location => location.replace('All Centres ', ''));
    } else {
        modifiedData = lables;
    }
    var options = {
        series: [{
            name: 'Total Appointments',
            data: bar.data.total_appointments
        }, {
            name: 'Converted',
            data: bar.data.converted_appointments
        }],
        noData: {
            text: 'No Data',
            align: 'center',
            verticalAlign: 'top',
            style: {
                color: 'red',
                fontSize: '14px',
                fontFamily: undefined
            }
        },
        chart: {
            type: 'bar',
            height: 350,

        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: modifiedData,
        },
        colors: [primary, success, warning]
    };
    $("#doc_wise_conversion").html("");
    doc_wise_conversion_chart = new ApexCharts(document.querySelector("#doc_wise_conversion"), options);
    doc_wise_conversion_chart.render();
}
function initPatientFollowUp(period, centre_id, arrived = null) {
    if (centre_id == 'centre') {
        centre_id = $('.btn.arrivalbtn').attr('data-id');
    }
    if (centre_id == '' || centre_id == 30) {
        centre_id = 'All';
    }
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.patient_follow_up'),
        type: 'GET',
        cache: false,
        data: {
            'period': period,
            'centre_id': centre_id,
            'arrived': arrived
        },
        success: function (response) {
            $('#patient-follow-up').html("");
            var TABLE_HTML = "";
            var balance = 0;
            let patientData = response.data.patient_data;
            if (patientData.length > 0) {
                for (let i = 0; i < patientData.length; i++) {
                    console.log('patientData[i]' , patientData[i]);
                    let patient = patientData[i];
                    balance = patient.cash_receive - patient.settle_amount_with_tax;
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + patient.patient_id + "</td><td>" + patient.name + "</td><td>" + ((patient.is_treatment == 0) ? 'Not Booked' : 'No Show') + "</td><td>PKR: "+balance+"</td><td>" + patient.created_at + "</td></tr>";
                }
            } else {
                TABLE_HTML = "<tr><td colspan='5' style='color: #2b7bc1;font-weight: bold;text-align:center;'>No Data</td></tr>";
            }

            $('#patient-follow-up').append(TABLE_HTML);
            $('#followbtn').css('display', 'inline-block');
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function initPatientFollowUpOneMonth() {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.patient_follow_up_one_month'),
        type: 'GET',
        cache: false,
        data: {},
        success: function (response) {
            console.log('response' , response);
            $('#patient-follow-up-one-month').html("");
            var TABLE_HTML = "";
            var balance = 0;
            let patientData = response.data.patient_data;
            if (patientData.length > 0) {
                for (let i = 0; i < patientData.length; i++) {
                    let patient = patientData[i];
                    balance = patient.cash_receive - patient.settle_amount_with_tax;
                    TABLE_HTML += "<tr><td style='color: #2b7bc1;font-weight: bold;'>" + patient.patient_id + "</td><td>" + patient.name + "</td><td>PKR: "+balance+"</td><td>" + patient.scheduled_date + "</td></tr>";
                }
            } else {
                TABLE_HTML = "<tr><td colspan='5' style='color: #2b7bc1;font-weight: bold;text-align:center;'>No Data</td></tr>";
            }

            $('#patient-follow-up-one-month').append(TABLE_HTML);
            $('#mfollowbtn').css('display', 'inline-block');
        },
        error: function (xhr, ajaxOptions, thrownError) {
            errorMessage(xhr);
        }
    });
}

function dropDownList(report, period) {
    $("#" + report + "_wise_list .active").removeClass('active');
    $("#" + report + "_wise_list li." + period + " a").addClass('active');console.log($("." + report + "_period"));
    if (period == "today") {
        $("." + report + "_period").html('Today <i class="fa fa-angle-down"></i>');
    }
    if (period == "yesterday") {
        $("." + report + "_period").html('Yesterday <i class="fa fa-angle-down"></i>');
    }
    if (period == "last7days") {
        $("." + report + "_period").html('Last 7 Days <i class="fa fa-angle-down"></i>');
    }
    if (period == "week") {
        $("." + report + "_period").html('This Week <i class="fa fa-angle-down"></i>');
    }
    if (period == "thismonth") {
        $("." + report + "_period").html('This Month <i class="fa fa-angle-down"></i>');
    }
    if (period == "lastmonth") {
        $("." + report + "_period").html('Last Month <i class="fa fa-angle-down"></i>');
    }
}


