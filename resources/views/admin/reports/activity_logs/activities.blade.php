<div class="col-12 pb-0">
    <div class="timeline custom_timeline timeline-6 mt-3">
        @foreach($data as $activity)

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
        @endforeach
    </div>
</div>