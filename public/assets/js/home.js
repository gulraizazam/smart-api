function initCollectionByCentre(today, yesterday, last7days,week, thismonth,lastmonth) {
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
                
            }
            if (yesterday != '') {
                $(".pie-income-title").text('Yesterday Income');
                var pie = response.data.pie.yesterday;
                let total = response.data.total;
                $(".total-pie-chart").text(total)
                collectionCentreChart(pie);
            }
            if (last7days != '') {
                $(".pie-income-title").text('Weekly Income');
                var pie = response.data.pie.last7days;
                let total = response.data.total;
                $(".total-pie-chart").text(total);  
            }
            if (week != '') {
                $(".pie-income-title").text('Weekly Income');
                var pie = response.data.pie.week;
                let total = response.data.total;
                $(".total-pie-chart").text(total);  
            }
            if (thismonth != '') {
                $(".pie-income-title").text('Monthly Income');
                var pie = response.data.pie.thismonth;
                let total = response.data.total;
                $(".total-pie-chart").text(total); 
            }
            if (lastmonth != '') {
                $(".pie-income-title").text('Last Month Income');
                var pie = response.data.pie.lastmonth;
                let total = response.data.total;
                $(".total-pie-chart").text(total); 
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
            if(period=="today"){
                $(".revenue-centre-title").text('Today Income');
                let total = response.data.total;
                $(".total-centre").text(total);
                var pie = response.data.pie;
            }
            if(period=="yesterday"){
                $(".revenue-centre-title").text('Yesterday Income');
                let total = response.data.total;
                $(".total-centre").text(total);
                var pie = response.data.pie;
            }
            if(period=="last7days"){
                let total = response.data.total;
                $(".revenue-centre-title").text('Weekly Income');
                $(".total-centre").text(total);
                var pie = response.data.pie;
            }
            if(period=="week"){
                let total = response.data.total;
                $(".revenue-centre-title").text('Weekly Income');
                $(".total-centre").text(total);
                var pie = response.data.pie;
            }
            if(period=="thismonth"){
                $(".revenue-centre-title").text('Monthly Income');
                let total = response.data.total;
                $(".total-centre").text(total);
                var pie = response.data.pie;
            }
            if(period=="lastmonth"){
                $(".revenue-centre-title").text('Last Month Income');
                let total = response.data.total;
                $(".total-centre").text(total);
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
function initRevenueByService(today, yesterday, last7days,week, thismonth,lastmonth) {
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
            console.log(response);
            let colors = response.data.colors;
            if (today != '') {
                $(".service-title").text('Today Income');
                let total = response.data.total;
                $(".total-service").text(total);
                var pie = response.data.pie.today;
            }
            if (yesterday != '') {
                $(".service-title").text('Yesterday Income');
                let total = response.data.total;
                $(".total-service").text(total);
                var pie = response.data.pie.yesterday;
            }
            if (last7days != '') {
                $(".service-title").text('Weekly Income');
                let total = response.data.total;
                $(".total-service").text(total);
                var pie = response.data.pie.last7days;
            }
            if (week != '') {
                $(".service-title").text('Weekly Income');
                let total = response.data.total;
                $(".total-service").text(total);
                var pie = response.data.pie.week;
            }
            if (thismonth != '') {
                $(".service-title").text('Monthly Income');
                let total = response.data.total;
                $(".total-service").text(total);
                var pie = response.data.pie.month;
            }
            if (lastmonth != '') {
                $(".service-title").text('Last Month Income');
                let total = response.data.total;
                $(".total-service").text(total);
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
function initAppointmentsByStatus( period ) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_status'),
        type: 'GET',
        data:{ 'period':period },
        cache: false,
        success: function (response) {
            let colors = response.data.colors;
        if(period=="today"){
            var pie = response.data.pie.today;
        }
        if(period=="yesterday"){
            var pie = response.data.pie.yesterday;
        }   
        if(period=="last7days"){
            var pie = response.data.pie.last7days;
        }
        if(period=="thismonth"){
            var pie = response.data.pie.thismonth;
        }
        if(period=="lastmonth"){
            var pie = response.data.pie.lastmonth;
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
function initAppointmentsByType( period ) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_type'),
        type: 'GET',
        data:{ 'period':period},
        cache: false,
        success: function (response) {
            let colors = response.data.colors;
            if(period=="today"){
                var pie = response.data.pie.today;
            }
            if(period=="yesterday"){
                var pie = response.data.pie.yesterday;
            }   
            if(period=="last7days"){
                var pie = response.data.pie.last7days;
            }
            if(period=="thismonth"){
                var pie = response.data.pie.thismonth;
            }
            if(period=="lastmonth"){
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
function initConsultancyByStatus( period,type) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_status'),
        type: 'GET',
        data:{ 'period':period,'type':type },
        cache: false,
        success: function (response) {
            
            let colors = response.data.colors;
        if(period=="today"){
            var pie = response.data.pie.today;
        }
        if(period=="yesterday"){
            var pie = response.data.pie.yesterday;
        }   
        if(period=="last7days"){
            var pie = response.data.pie.last7days;
        }
        if(period=="week"){
            var pie = response.data.pie.week;
        }
        if(period=="thismonth"){
            var pie = response.data.pie.thismonth;
        }
        if(period=="lastmonth"){
            var pie = response.data.pie.lastmonth;
        }
        ConsultancyByStatus(pie, colors);

        },
        
    });
}
function initTreatmentByStatus( period,type) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_status'),
        type: 'GET',
        data:{ 'period':period,'type':type },
        cache: false,
        success: function (response) {
            let colors = response.data.colors;
        if(period=="today"){
            var pie = response.data.pie.today;
        }
        if(period=="yesterday"){
            var pie = response.data.pie.yesterday;
        }   
        if(period=="last7days"){
            var pie = response.data.pie.last7days;
        }
        if(period=="week"){
            var pie = response.data.pie.week;
        }
        if(period=="thismonth"){
            var pie = response.data.pie.thismonth;
        }
        if(period=="lastmonth"){
            var pie = response.data.pie.lastmonth;
        }
        TreatmentByStatus(pie, colors);

        },
        
    });
}
function TreatmentByStatus(pie,colors) {
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
function ConsultancyByStatus(pie,colors) {
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
function InitRevenueByServiceCategory(today, yesterday, last7days, thismonth,lastmonth){
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
            }
            if (yesterday != '') {
                var pie = response.data.pie.yesterday;
            }
            if (last7days != '') {
                var pie = response.data.pie.week;
            }
            if (thismonth != '') {
                var pie = response.data.pie.month;
            }
            if (lastmonth != '') {
                var pie = response.data.pie.lastmonth;
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
function InitCollectionByServiceCategory(today, yesterday, last7days, thismonth,lastmonth){
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
function initConsultanciesByStatus(today, yesterday, last7days, week,thismonth,lastmonth){
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.consultancies-by-status'),
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
            ConsultanciesByStatus(response);
        },
    });
} 
function ConsultanciesByStatus(bar)
{
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    var options = {
        series: [{
            name: 'Scheduled',
            data: bar.data.total
        }, {
            name: 'Arrived',
            data: bar.data.arrived
        }],
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
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: bar.data.bar,
        },
        colors: [primary, success, warning]
    };
    $("#chart_status").html('');
    var chart = new ApexCharts(document.querySelector("#chart_status"), options);
    chart.render();
}
function initConvertedConsultancies(today, yesterday, last7days, week,thismonth,lastmonth){
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.converted-consultancies'),
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
            ConvertedConsultanciesChart(response);
        },
    });
} 
function ConvertedConsultanciesChart(bar)
{
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    var options = {
        series: [{
            name: 'Arrived',
            data: bar.data.arrived 
        }, {
            name: 'Converted',
            data: bar.data.total
        }],
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
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: bar.data.bar,
        },
        colors: [primary, success, warning]
    };
    $("#chart_converted").html('');
    var chart = new ApexCharts(document.querySelector("#chart_converted"), options);
    chart.render();
}

    