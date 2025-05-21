@extends('admin.layouts.master')
@section('title', 'Feedback Report')
@section('content')

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Feedback Report'])

   <div class="container">
    <h2>Doctor Feedback Report</h2>
    <div class="accordion" id="feedbackAccordion">
        @foreach ($feedbackData as $index => $service)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center" id="heading{{ $index }}">
                    <h5 class="mb-0">
                        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse{{ $index }}" aria-expanded="true" aria-controls="collapse{{ $index }}">
                            {{ $service['name'] }} (Avg: {{ $service['avg_rating'] }})
                        </button>
                    </h5>
                    <span class="badge badge-pill" style="background-color: {{ $service['color'] }};">&nbsp;&nbsp;</span>
                </div>

                <div id="collapse{{ $index }}" class="collapse" aria-labelledby="heading{{ $index }}" data-parent="#feedbackAccordion">
                    <div class="card-body">
                        @if(count($service['treatments']) > 0)
                            <ul class="list-group">
                                @foreach ($service['treatments'] as $treatment)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        {{ $treatment['name'] }} (Avg: {{ $treatment['avg_rating'] }})
                                        <span class="badge badge-pill" style="background-color: {{ $treatment['color'] }};">&nbsp;&nbsp;</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p>No treatments found for this service.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
</div>

<!-- Bootstrap JS for collapse functionality -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

@endsection
