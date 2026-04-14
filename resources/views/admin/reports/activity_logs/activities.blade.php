@forelse($data as $activity)
    <div class="timeline-item align-items-start">
        <div class="timeline-label font-weight-bolder text-dark-75 font-size-sm">
            {{ $activity['time'] }}
        </div>
        <div class="timeline-badge">
            <i class="fa fa-genderless icon-xl {{ $activity['colorClass'] }}"></i>
        </div>
        <div class="timeline-content font-size-lg text-dark-75 pl-3">
            {!! $activity['message'] !!}
        </div>
    </div>
@empty
    @if($isFirstPage ?? true)
        <div class="no_data text-center pb-3 text-danger font-weight-bold">No Activity Logs</div>
    @endif
@endforelse
