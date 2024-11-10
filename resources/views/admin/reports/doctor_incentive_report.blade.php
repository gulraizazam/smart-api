<div class="card mt-4">
            <div class="card-body">
                <h4>Total Cash Amount (Within Date Range and Criteria):</h4>
                <p>${{ number_format($totalCashAmount, 2) }}</p>
                
                <h4>Total Revenue Earned by Doctor (Within Date Range):</h4>
                <p>${{ number_format($totalDoctorRevenue, 2) }}</p>
                
                <h4>Difference:</h4>
                <p>${{ number_format($diff, 2) }}</p>
            </div>
        </div>
