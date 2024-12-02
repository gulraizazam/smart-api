<div id="revenue_report">
    <h4>Total Revenue for Selected Range: {{ number_format($totalRevenue, 2) }}</h4>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Month</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthWiseRevenue as $month => $revenue)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</td>
                    <td>{{ number_format($revenue, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
