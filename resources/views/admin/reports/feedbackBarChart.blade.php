@extends('admin.layouts.master')
@section('title', 'Feedback Report')

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    @include('admin.partials.breadcrumb', ['module' => 'Reports', 'title' => 'Feedback Report'])

    <div class="container-fluid mt-4">
        <h2 class="mb-4 font-weight-bold">Doctor Feedback Report</h2>

        <div class="accordion" id="feedbackAccordion">
            @foreach($feedbackData as $index => $service)
            <div class="card border-left" style="border-left: 5px solid {{ $service['color'] }};">
                <div class="card-header p-0" id="heading{{ $index }}">
                    <h2 class="mb-0">
                        <button class="btn btn-link btn-block text-left p-3 d-flex justify-content-between align-items-center toggle-btn" type="button" data-toggle="collapse" data-target="#collapse{{ $index }}" aria-expanded="false" aria-controls="collapse{{ $index }}">
                            <span class="text-dark font-weight-bold">{{ $service['name'] }} (Avg: {{ $service['avg_rating'] }})</span>
                            <span><i class="fas fa-chevron-down rotate-icon transition"></i></span>
                        </button>
                    </h2>
                </div>

                <div id="collapse{{ $index }}" class="collapse toggle-content" aria-labelledby="heading{{ $index }}" data-parent="#feedbackAccordion">
                    <div class="card-body bg-light">
                        @if(count($service['treatments']) > 0)
                        <ul class="pl-3 mb-0">
                            @foreach($service['treatments'] as $treatment)
                            <li class="mb-2">
                                <strong>{{ $treatment['name'] }}</strong>
                                <small class="text-muted">(Avg: {{ $treatment['avg_rating'] }})</small>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <p class="text-muted font-italic">No rated treatments.</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- FontAwesome for arrow icons --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />

{{-- jQuery and Bootstrap JS already included --}}
<script>
    // Optional: Rotate arrow on toggle
    $('.toggle-btn').on('click', function () {
        const icon = $(this).find('.rotate-icon');
        $('.rotate-icon').not(icon).removeClass('rotate-180');
        icon.toggleClass('rotate-180');
    });
</script>

{{-- Optional styling for rotation --}}
<style>
    .rotate-180 {
        transform: rotate(180deg);
        transition: transform 0.3s ease;
    }
</style>
@endsection
