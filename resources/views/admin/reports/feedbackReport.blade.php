<div id="revenue_report">
    <h4>Doctor Ratings Report</h4>

    <table id="feedback_table" class="display">
        <thead>
            <tr>
                @if(isset($result[0]->doctor))
                    <th>Doctor</th>
                @endif

                @if(isset($result[0]->service))
                    <th>Service</th>
                @endif

                <th>Average Rating (out of 10)</th>
                <th>Total Feedbacks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($result as $row)
                <tr>
                    @if(isset($row->doctor))
                        <td><a href="{{ route('admin.feedback.view', $row->doctor->id) }}" style="color: black";> {{$row->doctor->name ?? 'N/A' }}</a></td>
                    @endif

                    @if(isset($row->service))
                        <td>{{ $row->service->name ?? 'N/A' }}</td>
                    @endif

                    <td>{{$row->avg_rating ? number_format($row->avg_rating, 2) : 0   }}</td>
                    <td>{{ $row->total_feedbacks ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
