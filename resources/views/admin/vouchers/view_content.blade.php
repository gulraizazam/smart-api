<!-- Voucher & Patient Information -->
<div class="row mb-5">
    <div class="col-md-12">
        <div class="card card-custom bg-light">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Patient Name:</strong><br>
                        {{ $user->name }}
                    </div>
                    <div class="col-md-3">
                        <strong>Voucher Type:</strong><br>
                        {{ $voucher->name }}
                    </div>
                    <div class="col-md-3">
                        <strong>Total Amount:</strong><br>
                        {{ number_format($userVoucher->total_amount, 2) }}
                    </div>
                    <div class="col-md-3">
                        <strong>Remaining Amount:</strong><br>
                        {{ number_format($userVoucher->amount, 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<h5 class="mb-4">Services & Packages Where This Voucher Was Used</h5>

@if(count($voucherUsageData) > 0)
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Package ID</th>
                    <th>Service Name</th>
                    <th>Voucher Amount Used</th>
                </tr>
            </thead>
            <tbody>
                @foreach($voucherUsageData as $index => $usage)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $usage['package_id'] ?? 'N/A' }}</td>
                    <td>{{ $usage['service_name'] }}</td>
                    <td>
                        @if($usage['discount_type'] == 'Percentage')
                            {{ number_format($usage['discount_price'], 2) }}%
                        @else
                            {{ number_format($usage['discount_price'], 2) }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <div class="alert alert-info">
            <strong>Total Records:</strong> {{ count($voucherUsageData) }} service(s) using this voucher
        </div>
    </div>
@else
    <div class="alert alert-warning">
        <i class="la la-info-circle"></i>
        This voucher has not been used in any packages or services yet.
    </div>
@endif
