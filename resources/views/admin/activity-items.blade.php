@foreach ($finance_log as $log)
    @if ($log['appointment_type'] == 'Plan')
        <div class="timeline-item align-items-start">
            <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">
                {{ \Illuminate\Support\Carbon::parse($log['created_at'])->format('h:i') }}
            </div>
            <div class="timeline-badge">
                <i class="fa fa-genderless text-danger icon-xl"></i>
            </div>
            <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                <span style="color: #056FBF;">{{ $log->created_by_name ?? $log['created_by'] ?? 'N/A' }}</span>
                {{ $log['action'] }}
                @if ($log['action'] == 'refunded')
                    <strong>Rs. {{ round($log['amount']) }}</strong> to
                @else
                    <strong>Rs. {{ round($log['amount']) }}</strong> from
                @endif
                <span style="color: #056FBF;"> {{ $log['patient'] }}</span> for
                <span style="color: #F5B183;">Plan Id: <a href="{{ route('admin.packages.view.package', $log['planId']) }}">{{ $log['planId'] }}</a></span>
                at {{ $log->centre->name ?? $log['location'] }} Centre.
            </div>
        </div>
    @elseif($log['appointment_type'] == 'Consultancy')
        <div class="timeline-item align-items-start">
            <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">
                {{ \Illuminate\Support\Carbon::parse($log['created_at'])->format('h:i') }}
            </div>
            <div class="timeline-badge">
                <i class="fa fa-genderless text-danger icon-xl"></i>
            </div>
            <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                <span style="color: #056FBF;">{{ $log->created_by_name ?? $log['created_by'] ?? 'N/A' }}</span>
                {{ $log['action'] }}
                <strong>Rs. {{ round($log['amount']) }}</strong> from
                <span style="color: #056FBF;"> {{ $log['patient'] }}</span> for
                <span style="color: #F5B183;">{{ $log['appointment_type'] }}</span>
                at {{ $log->centre->name ?? $log['location'] }} Centre.
            </div>
        </div>
    @else
        <div class="timeline-item align-items-start">
            <div class="timeline-label font-weight-bolder text-dark-75 font-size-lg">
                {{ \Illuminate\Support\Carbon::parse($log['created_at'])->format('h:i') }}
            </div>
            <div class="timeline-badge">
                <i class="fa fa-genderless text-danger icon-xl"></i>
            </div>
            <div class="timeline-content font-weight-bolder font-size-lg text-dark-75 pl-3">
                <span style="color: #056FBF;">{{ $log->created_by_name ?? $log['created_by'] ?? 'N/A' }}</span>
                {{ $log['action'] }}
                <strong>Rs. {{ round($log['amount']) }}</strong> from
                <span style="color: #056FBF;"> {{ $log['patient'] }}</span> for
                <span style="color: #F5B183;">{{ $log['appointment_type'] }}</span>
                at {{ $log->centre->name ?? $log['location'] }} Centre.
            </div>
        </div>
    @endif
@endforeach
