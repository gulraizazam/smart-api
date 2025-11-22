@extends('admin.layouts.master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Upselling Details for {{ $doctorName }}</h3>
                        <a href="{{ url()->previous() }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Report
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Summary Info -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-user-md"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Doctor/Seller</span>
                                    <span class="info-box-number">{{ $doctorName }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-money-bill-wave"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Amount</span>
                                    <span class="info-box-number">{{ number_format($totalAmount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Unique Packages</span>
                                    <span class="info-box-number">{{ $uniqueUpsellings }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Services Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Package ID</th>
                                    <th>Patient Name</th>
                                    <th>Service Name</th>
                                    <th>Price (PKR)</th>
                                   
                                    <th>Sold Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($packageServices as $index => $service)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <span class="badge badge-primary">{{ $service->package_id }}</span>
                                        </td>
                                        <td>
                                            <i class="fas fa-user text-info"></i>
                                            <strong>{{ $service->patient_name }}</strong>
                                        </td>
                                        <td>{{ $service->service_name }}</td>
                                        <td>
                                            <strong class="text-success">{{ number_format($service->tax_including_price, 2) }}</strong>
                                        </td>
                                        
                                        <td>
                                            <i class="fas fa-clock"></i>
                                            {{ date('M d, Y H:i', strtotime($service->created_at)) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            <i class="fas fa-inbox"></i> No services found for this doctor.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($packageServices->count() > 0)
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="4">TOTAL</th>
                                        <th>
                                            <strong class="text-success">{{ number_format($totalAmount, 2) }}</strong>
                                        </th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection