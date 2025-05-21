@extends('admin.layouts.master')
@section('title', 'Feedback Report')
@section('content')

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Feedback Report'])

    <div class="container mt-4">
        <h2 class="mb-4">Feedback by Treatment</h2>

        <div class="accordion" id="feedbackAccordion">
            @foreach($treatments as $index => $treatment)
                <div class="card mb-3">
                    <div class="card-header" id="heading{{ $index }}">
                        <h5 class="mb-0">
                            <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapse{{ $index }}" aria-expanded="false" aria-controls="collapse{{ $index }}">
                                {{ $treatment['treatment_name'] }}
                            </button>
                        </h5>
                    </div>

                    <div id="collapse{{ $index }}" class="collapse" aria-labelledby="heading{{ $index }}" data-parent="#feedbackAccordion">
                        <div class="card-body">
                            <ul class="list-group">
                                @foreach($treatment['services'] as $service)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        {{ $service['name'] }}
                                        <span class="badge badge-primary badge-pill">{{ $service['avg_rating'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
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
