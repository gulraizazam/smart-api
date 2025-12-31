@extends('admin.layouts.master')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Wrong Conversions Report</h3>
                </div>
                <div class="card-body">
                    <!-- Date Filter -->
                    <form method="GET" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <input type="date" name="date" class="form-control" value="{{ $date }}">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </div>
                    </form>

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @if(isset($error))
                        <div class="alert alert-danger">{{ $error }}</div>
                    @else
                        <!-- Summary -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h4>{{ $totalConverted ?? 0 }}</h4>
                                        <p class="mb-0">Total Converted</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h4>{{ $validCount ?? 0 }}</h4>
                                        <p class="mb-0">Valid Conversions</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h4>{{ $invalidCount ?? 0 }}</h4>
                                        <p class="mb-0">Wrong Conversions</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($appointments->count() > 0)
                            <!-- Reset All Form -->
                            <form method="POST" action="{{ route('admin.wrong-conversions.reset-all') }}" class="mb-3">
                                @csrf
                                @foreach($appointments as $apt)
                                    <input type="hidden" name="ids[]" value="{{ $apt->id }}">
                                @endforeach
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset all {{ $appointments->count() }} appointments?')">
                                    Reset All {{ $appointments->count() }} Wrong Conversions
                                </button>
                            </form>

                            <!-- Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Patient</th>
                                            <th>Phone</th>
                                            <th>Service</th>
                                            <th>Location</th>
                                            <th>Doctor</th>
                                            <th>Scheduled Date</th>
                                            <th>Converted At</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($appointments as $appointment)
                                            <tr>
                                                <td>{{ $appointment->id }}</td>
                                                <td>{{ $appointment->patient->name ?? 'N/A' }}</td>
                                                <td>{{ $appointment->patient->phone ?? 'N/A' }}</td>
                                                <td>{{ $appointment->service->name ?? 'N/A' }}</td>
                                                <td>{{ $appointment->location->name ?? 'N/A' }}</td>
                                                <td>{{ $appointment->doctor->name ?? 'N/A' }}</td>
                                                <td>{{ \Carbon\Carbon::parse($appointment->scheduled_date)->format('M d, Y') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($appointment->converted_at)->format('M d, Y H:i') }}</td>
                                                <td>
                                                    <form method="POST" action="{{ route('admin.wrong-conversions.reset', $appointment->id) }}" style="display:inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Reset this appointment to arrived?')">
                                                            Reset
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-success">
                                No wrong conversions found for {{ $date }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
