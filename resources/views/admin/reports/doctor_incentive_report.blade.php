
        <div id="revenue_report">
    <h4>Total Revenue for Selected Range: {{ number_format($totalRevenue, 2) }}</h4>

    <table class="table table-striped">
        <thead>
            <tr>
                
                <th>Total Amount In Given Range</th>
                <th>Total Conversion Amount</th>
                <th>Difference</th>
            </tr>
        </thead>
        <tbody>
            
                <tr>
                    <td>{{ number_format($totalCashAmount, 2) }}</td>
                    <td>{{ number_format($totalDoctorRevenue, 2) }}</td>
                    <td>{{ number_format($diff, 2) }}</td>
                </tr>
            
        </tbody>
    </table>
</div>
