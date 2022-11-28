function initCollectionByCentre(today, yesterday, last7days, thismonth) {
  
        
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
            },
            
            cache: false,
            success: function (response) {
                
                if (today != '') {
                    
                    $(".pie-income-title").text('Today Income');
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
                if (thismonth != '') {
                    $(".pie-income-title").text('Monthly Income');
                    var pie = response.data.pie.thismonth;
                    let total = response.data.total;
                    $(".total-pie-chart").text(total);
                    
                }
                collectionCentreChart(pie);
            },
            
        });
    }
    function initMyCollectionByCentre(today, yesterday, last7days, thismonth) {
        
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
                'performance': '1'
            },
            cache: false,
            success: function (response) {
                
                if (today != '') {
                    $(".my-collection-title").text('Today Income');
                    let total = response.data.total;
                    $(".my-total-collection-center").text(total);
                    var pie = response.data.pie.today;
                }
                if (yesterday != '') {
                    $(".my-collection-title").text('Yesterday Income');
                    let total = response.data.total;
                    $(".my-total-collection-center").text(total);
                    var pie = response.data.pie.yesterday;
                }

                if (last7days != '') {
                    $(".my-collection-title").text('Weekly Income');
                    let total = response.data.total;
                    $(".my-total-collection-center").text(total);
                    var pie = response.data.pie.last7days;
                }
                if (thismonth != '') {
                    $(".my-collection-title").text('Monthly Income');
                    let total = response.data.total;
                    $(".my-total-collection-center").text(total);
                    var pie = response.data.pie.thismonth;
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
                if(period=="thismonth"){
                    $(".revenue-centre-title").text('Monthly Income');
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
                    $(".my-revenue-centre-title").text('Today Income');
                    let total = response.data.total;
                    $(".total-my-revenue-centre").text(total);
                    var pie = response.data.pie;
                }
                if(period=="yesterday"){
                    $(".my-revenue-centre-title").text('Yesterday Income');
                    let total = response.data.total;
                    $(".total-my-revenue-centre").text(total);
                    var pie = response.data.pie;
                }
                if(period=="last7days"){
                    let total = response.data.total;
                    $(".total-my-revenue-centre").text(total);
                    var pie = response.data.pie;
                }
                if(period=="thismonth"){
                    $(".my-revenue-centre-title").text('Monthly Income');
                    let total = response.data.total;
                    $(".total-my-revenue-centre").text(total);
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
    function initRevenueByService(today, yesterday, last7days, thismonth) {
      
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
                    var pie = response.data.pie.week;
                }
                if (thismonth != '') {
                    $(".service-title").text('Monthly Income');
                    let total = response.data.total;
                    $(".total-service").text(total);
                    var pie = response.data.pie.month;
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
                let total = response.data.total;
                $(".total-my-service").text(total)
                if (today != '') {
                    $(".my-service-title").text('Today Income')
                    var pie = response.data.pie.today;
                    
                }
                if (yesterday != '') {
                    $(".my-service-title").text('Yesterday Income')
                    var pie = response.data.pie.yesterday;
                    
                }

                if (last7days != '') {
                    $(".my-service-title").text('Weekly Income')
                    var pie = response.data.pie.last7days;
                    
                }
                if (thismonth != '') {
                    $(".my-service-title").text('Monthly Income')
                    var pie = response.data.pie.thismonth;
                    
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
    


    