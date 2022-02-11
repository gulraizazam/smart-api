{!! Form::hidden('id', old('id'), ['id' => 'lead']) !!}

<div class="form-group">
    {!! Form::select('lead_status_parent_id',$lead_statuses_Pdata, $lead_status_parent->id, ['id' => 'lead_status_parent_id', 'class' => 'form-control lead_status_parent_id', 'placeholder' => '', 'required' => '']) !!}
</div>
@if($lead_status_chalid!='null')
    <div class="form-group">
        <select name="lead_status_chalid_id" id="lead_status_chalid_id" class="form-control">
            <option value="">Select a Lead Status</option>
            @foreach($lead_statuses_Cdata as $lead_statuses_Cdata)
                <option @if($lead_status_chalid->id == $lead_statuses_Cdata->id) selected="selected"
                        @endif value="{{$lead_statuses_Cdata->id}}">{{$lead_statuses_Cdata->name}}</option>
            @endforeach
        </select>
    </div>
@endif
@if($lead_status_chalid=='null' && $lead_statuses_Cdata!='nothing')
    <div class="form-group">
        <select name="lead_status_chalid_id" id="lead_status_chalid_id" class="form-control">
            <option value="">Select a Lead Status</option>
            @foreach($lead_statuses_Cdata as $lead_statuses_Cdata)
                <option value="{{$lead_statuses_Cdata->id}}">{{$lead_statuses_Cdata->name}}</option>
            @endforeach
        </select>
    </div>
@endif
<div class="form-group">
    <select name="lead_status_chalid_id" id="lead_status_chalid_id" class="form-control" style="display:none">
    </select>
</div>
@if($lead_status_parent->is_comment=='1')
    <div class="form-group" id="lead_status_comment_id">
        {!! Form::textarea('comment1','', ['rows' => 3, 'class' => 'form-control', 'placeholder' => 'Type your comment..']) !!}
    </div>
@endif
<div class="form-group" id="lead_status_comment_id" style="display: none">
    {!! Form::textarea('comment2','', ['rows' => 3, 'class' => 'form-control', 'placeholder' => 'Type your comment..']) !!}
</div>
