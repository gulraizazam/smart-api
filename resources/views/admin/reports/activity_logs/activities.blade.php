
  <div class="col-12 mb-3">
    <p style="margin-top: -5px;">Total: <strong>{{$data ? count($data) : 0}} Records</strong></p>
  </div>
   
<div class="col-12 pb-0">
    <div class="timeline custom_timeline timeline-6 mt-3">
        @forelse($data as $activity)

        <div class="timeline-item align-items-start">
            <div class="timeline-label font-weight-bolder text-dark-75 font-size-sm">
                {{$activity['time']}}
            </div>
            <div class="timeline-badge">
                <i class="fa fa-genderless icon-xl {{$activity['colorClass']}}"></i>
            </div>
            <div class="timeline-content font-size-lg text-dark-75 pl-3">
            {!!$activity['message']!!}
            </div>
        </div>
        @empty
        <div class="no_data text-center pb-3 text-danger font-weight-bold">No Activity Logs</div>
        @endforelse
    </div>
</div>