function initCollectionByCentre(today, yesterday, last7days, thismonth,lastmonth) {
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
            'thismonth': thismonth,
            'lastmonth': lastmonth,
        },
        cache: false,
        success: function (response) {
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
            collectionCentreChart(pie);
        },
        
    });
}
function initMyCollectionByCentre(today, yesterday, last7days, thismonth,lastmonth) {
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
            'thismonth': thismonth,
            'lastmonth': lastmonth,
            'performance': '1'
        },
        cache: false,
        success: function (response) {
           
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
            myCollectionCentreChart(pie);
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
function myCollectionCentreChart(pie) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
    var data = google.visualization.arrayToDataTable(pie);
    var options = {
        title: 'My Collections',
        colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
    };
    var chart = new google.visualization.PieChart(document.getElementById('my-collection-by-centre'));
        chart.draw(data, options);
    });
    if (pie.length > 1) {
        $("#my-collection-by-centre").css("height", "500px");
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
                var pie = response.data.pie;
            }
            if(period=="yesterday"){
                var pie = response.data.pie;
            }
            if(period=="last7days"){
                var pie = response.data.pie;
            }
            if(period=="thismonth"){
                var pie = response.data.pie;
            }
            if(period=="lastmonth"){
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
var initMyRevenueByCentre = function ( period ) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.myRevenueByCentre'),
        type: 'GET',
        data: {
            'period': period,
            performance: '1'
        },
        cache: false,
        success: function (response) {
            if(period=="today"){
                var pie = response.data.pie;
            }
            if(period=="yesterday"){
                var pie = response.data.pie;
            }
            if(period=="last7days"){
                var pie = response.data.pie;
            }
            if(period=="thismonth"){
                var pie = response.data.pie;
            }
            if(period=="lastmonth"){
                var pie = response.data.pie;
            }
            myRevenueCentreChart(pie);
        },
    });
}
function myRevenueCentreChart(centerRevenue) {

    google.load('visualization', '1', {
    packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
    var data = google.visualization.arrayToDataTable(centerRevenue);
    var options = {
        title: 'My Revenue',
        colors: ['#f6aa33', '#6e4ff5', '#2abe81', '#c7d2e7', '#593ae1', '#fe3995']
    };
    var chart = new google.visualization.PieChart(document.getElementById('my-revenue-centre'));
        chart.draw(data, options);
    });
    if (centerRevenue.length > 1) {
        $("#my-revenue-centre").css("height", "500px");
    }

}
function initRevenueByService(today, yesterday, last7days, thismonth,lastmonth) {
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
function initMyRevenueByService(period) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.myRevenueByService'),
        type: 'GET',
        data: {
            'period': period,
            performance: '1'
        },
        cache: false,
        success: function (response) {
            let colors = response.data.colors;
            if (period == 'today') {
                var pie = response.data.pie.today;
            }
            if (period == 'yesterday') {
                var pie = response.data.pie.yesterday;    
            }
            if (period == 'last7days') {
                var pie = response.data.pie.week;  
            }
            if (period == 'thismonth') {
                var pie = response.data.pie.month;  
            }
            if (period == 'lastmonth') {
                var pie = response.data.pie.lastmonth;  
            }
            myrevenueByService(pie, colors);
        },
    });
}
function myrevenueByService(pie, colors) {
    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
    var data = google.visualization.arrayToDataTable(pie);
    var options = {
        title: 'My Revenue',
        colors: colors
    };
    var chart = new google.visualization.PieChart(document.getElementById('my-revenue-service'));
        chart.draw(data, options);
    });
    if (typeof pie !== 'undefined' && pie.length > 1) {
        $("#my-revenue-service").css("height", "500px");
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
function initMyAppointmentsByStatus( period ) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_status'),
        type: 'GET',
        data:{ 'period':period ,performance:'1'},
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
            MyAppointmentByStatus(pie, colors);
        },
    });
}
function MyAppointmentByStatus(pie, colors) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var options = { 
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('my_appointment_status_today'));
            chart.draw(data, options);
    });
    if (typeof pie !== 'undefined' && pie.length > 1) {
        $("#my_appointment_status_today").css("height", "500px");
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
function initMyAppointmentsByType( period ) {
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.appointment_by_type'),
        type: 'GET',
        data:{ 'period':period,performance:"1"},
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
            MyAppointmentByType(pie, colors);
        },
    });
}
function MyAppointmentByType(pie, colors) {

    google.load('visualization', '1', {
        packages: ['corechart', 'bar', 'line']
    });
    google.setOnLoadCallback(function () {
        var data = google.visualization.arrayToDataTable(pie);
        var options = {
            colors: colors
        };
        var chart = new google.visualization.PieChart(document.getElementById('my_appointment_type_today'));
            chart.draw(data, options);
    });
    if (typeof pie !== 'undefined' && pie.length > 1) {
        $("#my_appointment_type_today").css("height", "500px");
    }
}
    


    