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
function initCentreWiseArrival(period){
    var dataID ;
   if(jQuery('.btn.arrivalbtn').attr('data-id') != ''){
    dataID = jQuery('.btn.arrivalbtn').attr('data-id');
   }else{
    dataID = 'All';
   }
   if(dataID == 30){
    dataID = 'All';
   }
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.location_wise_arrival'),
        type: 'GET',
        cache: false,
        data: {
            'period': period,
            'centre_id':dataID
        },
        success: function (response) {
            jQuery('#centre_wise_arrival_02').html('');
            ConsultanciesByStatus(response);
            setTimeout(function() {
                 jQuery('#centre_wise_arrival_02').html('');
                var TABLE_HTML = "";
                var barLenght = response.data.bar;
                for(var i = 0; i < barLenght.length; i++){
                    var walkin = "";
                    if(response.data.walkin[i] === undefined) {
                        walkin = "0";
                    } else {
                        walkin = response.data.walkin[i];
                    }
                    TABLE_HTML += "<div class='col-12'><h6 class='centre-name'>"+barLenght[i]+"</h6><div class='table-responsive'><table class='table'><thead><tr><th class='table-cols'>Total</th><th class='table-cols'>Arrived</th><th class=table-cols'>Walk in</th><th class=table-cols'>Percentage</th></tr></thead><tbody><tr><td>"+response.data.total[i]+"</td><td>"+response.data.arrived[i]+"</td><td>"+walkin+"</td><td>"+((response.data.arrived[i] / response.data.total[i]) * 100).toFixed(2)+"%</td></tr></tbody></table></div></div>";
                }
                jQuery('#centre_wise_arrival_02').append(TABLE_HTML);
            }, 2000); 
        },
    });
} 
function initUserWiseArrival(period){
    var dataID ;
   if(jQuery('.btn.arrivalbtn').attr('data-id') != ''){
    dataID = jQuery('.btn.arrivalbtn').attr('data-id');
   }else{
    dataID = 'All';
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
            'user_id':dataID
        },
        success: function (response) {
            ConsultanciesByStatus(response);
        },
    });
} 
function LoadBarChart(centreID,period){
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.centre_wise_arrival'),
        type: 'GET',
        cache: false,
        data: {
            'period': period,
            'centre_id':centreID
        },
        success: function (response) {
           
            ConsultanciesByStatus(response);
            jQuery('#centre_wise_arrival_02').html('');
            var TABLE_HTML = "";
            var barLenght = response.data.bar;
            for(var i = 0; i < barLenght.length; i++){
                var walkin = "";
                if(response.data.walkin[i] === undefined) {
                    walkin = "0";
                } else {
                    walkin = response.data.walkin[i];
                }
                TABLE_HTML += "<div class='col-12'><h6 class='centre-name'>"+barLenght[i]+"</h6><div class='table-responsive'><table class='table'><thead><tr><th class='table-cols'>Total</th><th class='table-cols'>Arrived</th><th class=table-cols'>Walk in</th><th class=table-cols'>Percentage</th></tr></thead><tbody><tr><td>"+response.data.total[i]+"</td><td>"+response.data.arrived[i]+"</td><td>"+walkin+"</td><td>"+((response.data.arrived[i] / response.data.total[i]) * 100).toFixed(2)+"%</td></tr></tbody></table></div></div>";
                 }
            jQuery('#centre_wise_arrival_02').append(TABLE_HTML);
        },
    });
}
function LoadBarChartUserWise(UserID,period){
    $.ajax({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        url: route('admin.dashboard.user_wise_arrival'),
        type: 'GET',
        cache: false,
        data: {
            'period': period,
            'user_id':UserID
        },
        success: function (response) {
            BarChartUserWise(response);
            
        },
    });
}
function BarChartUserWise(bar)
{
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    var options = {
        series: [{
            name: 'Total Appointments',
            data: bar.data.total
        }, {
            name: 'Arrived',
            data: bar.data.arrived
        },],
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
    $("#centre_wise_arrival").html('');
    var chart = new ApexCharts(document.querySelector("#centre_wise_arrival"), options);
    chart.render();
}
function ConsultanciesByStatus(bar)
{
    const primary = '#6993FF';
    const success = '#1BC5BD';
    const info = '#8950FC';
    const warning = '#FFA800';
    const danger = '#F64E60';
    let locations = bar.data.bar;
    let modifiedLocations;
    if(locations.length > 0){
        if (locations.some(str => str.includes('CUTERA'))) {
            modifiedLocations = locations.map(location => location.replace('CUTERA ', ''));
        }else{
            modifiedLocations = locations;
        }
        
    }else{
        modifiedLocations =['Bahadurabad Karachi','Gulshan Johar','DHA Karachi','Johar Town Lahore','Gulberg Lahore','DHA Lahore'];
    }
    
    var options = {
        series: [{
            name: 'Total Appointments',
            data: bar.data.total
        }, {
            name: 'Arrived',
            data: bar.data.arrived
        }, {
            name: 'Walk-in',
            data: bar.data.walkin
        }],
        chart: {
            type: 'bar',
            height: 350,
            
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '45%',
                endingShape: 'rounded'
            },
        },
        stroke: {
            show: true,
            width: 1,
            colors: ['transparent']
        },
        xaxis: {
            categories: modifiedLocations,
        },
        colors: [primary, success, warning]
    };
    $("#centre_wise_arrival").html('');
    var chart = new ApexCharts(document.querySelector("#centre_wise_arrival"), options);
    chart.render();
} 


    