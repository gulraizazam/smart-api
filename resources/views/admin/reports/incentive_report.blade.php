<div id="incentive_content">
    <table id="incentive_table" class="table table-striped">
        <thead>
            <tr>
                <th>Month</th>
                <th>Total Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthWiseRevenue as $month => $amount)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</td> <!-- Month formatted -->
                    <td>{{ number_format($amount, 2) }}</td> <!-- Total revenue for the month -->
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td><strong>Total Revenue for Selected Range</strong></td>
                <td>{{ number_format($totalRevenue, 2) }}</td> <!-- Total revenue -->
            </tr>
        </tfoot>
    </table>
</div>
