@extends('admin.layouts.master')
@section('title', 'Doctor Ratings Report')

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Doctor Ratings Report'])

    <div class="container-fluid mt-4">
        <h2 class="mb-4 font-weight-bold">Doctor Ratings Report</h2>

        <div class="accordion" id="feedbackAccordion">
            @foreach($feedbackData as $index => $service)
            <div class="card shadow-sm mb-3 border-left" style="border-left: 5px solid {{ $service['color'] }} !important;">
                <div class="card-header bg-white" id="heading{{ $index }}">
                    <h5 class="mb-0 d-flex justify-content-between align-items-center">
                        <button class="btn btn-link text-dark text-decoration-none w-100 d-flex justify-content-between" type="button" data-toggle="collapse" data-target="#collapse{{ $index }}" aria-expanded="false" aria-controls="collapse{{ $index }}">
                            <span><i class="fas fa-stethoscope mr-2 text-muted"></i> {{ $service['name'] }}</span>
                            <span class="badge badge-primary badge-pill">Avg: {{ $service['avg_rating'] }}</span>
                        </button>
                    </h5>
                </div>

                <div id="collapse{{ $index }}" class="collapse toggle-content" aria-labelledby="heading{{ $index }}" data-parent="#feedbackAccordion">
                    <div class="card-body bg-light">
                        @if(count($service['treatments']) > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($service['treatments'] as $treatment)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-angle-right mr-2 text-secondary"></i> {{ $treatment['name'] }}</span>
                                <span class="badge badge-secondary">Avg: {{ $treatment['avg_rating'] }}</span>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <p class="text-muted font-italic mb-0">No rated treatments.</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- FontAwesome --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />

{{-- Optional styling enhancements --}}
<style>
    .card-header:hover {
        background-color: #f7f7f7;
        cursor: pointer;
    }

    .btn-link:hover {
        text-decoration: none;
    }

    .card {
        transition: box-shadow 0.2s ease;
    }

    .card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }
</style>
@endsection
