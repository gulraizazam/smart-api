<div id="incentive_content">
    <table id="incentive_table" class="table table-striped">
        <thead>
            <tr>
                <th>Month</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monthWiseRevenue as $month => $revenue)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</td> <!-- Month -->
                    <td>{{ number_format($revenue, 2) }}</td> <!-- Monthly Revenue -->
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td><strong>Total Amount for Selected Range</strong></td>
                <td>{{ number_format($currentRangeTotal, 2) }}</td> <!-- Total cash amount for selected range -->
            </tr>
        </tfoot>
    </table>
</div>
