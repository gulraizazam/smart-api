<table class="table table-striped table-bordered table-advance table-hover">
    <thead>
    <tr>
        <th>Location Name</th>
        <th>Target Amount</th>
    </tr>
    </thead>
    <tbody>
    @foreach($targetlocation as $locationdata)
        <tr>
            <td>{{ $locationdata['location_name'] }}</td>
            <td>
                {!! Form::number('target_amount[' . $locationdata['location_id'] . ']', $locationdata['target_amount'], ['id' => 'target_amount' . $locationdata['location_id'], 'min' => '0', 'class' => 'form-control target_amount']) !!}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
