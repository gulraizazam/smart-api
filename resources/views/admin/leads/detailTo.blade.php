<div class="modal-body">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
        <h4 class="modal-title">@lang('global.app_detail')</h4>
    </div>
    <table class="table table-striped">
        <tbody>
        <tr>
            <th>Full Name</th>
            <td>{{ $lead->patient->name }}</td>
            <th>Email</th>
            <td>@if($lead->patient->email){{ $lead->patient->email }}@else{{'N/A'}}@endif</td>
            <th>Phone</th>
            <td>@if($lead->patient->phone){{ \App\Helpers\GeneralFunctions::prepareNumber4Call($lead->patient->phone) }}@else{{'N/A'}}@endif</td>
        </tr>
        <tr>
            <th>DOB</th>
            <td>{{ $lead->patient->dob }}</td>
            <th>Gender</th>
            <td>@if($lead->patient->gender){{ Config::get('constants.gender_array')[$lead->patient->gender] }}@else{{'N/A'}}@endif</td>
            <th>SMS Status</th>
            <td>@if($lead->msg_count){{ 'Delivered' }}@else{{'Not Delivered'}}@endif</td>
        </tr>
        <tr>
            <th>Address</th>
            <td colspan="3">@if($lead->patient->address){{ $lead->patient->address }}@else{{'N/A'}}@endif</td>
            <th>City</th>
            <td>@if($lead->city_id){{ $lead->city->full_name }}@else{{'N/A'}}@endif</td>
        </tr>
        <tr>
            <th>Town</th>
            <td>@if($lead->town_id){{ $lead->towns->name }}@else{{'N/A'}}@endif</td>
            <th>Lead Source</th>
            <td>@if($lead->lead_source_id){{ $lead->lead_source->name }}@else{{'N/A'}}@endif</td>
            <th>Lead Status</th>
            <td>@if($lead->lead_status_id){{ $lead->lead_status->name }}@else{{'N/A'}}@endif</td>
        </tr>
        <tr>
            <th>Treatment</th>
            <td>@if($lead->service_id){{ $lead->service->name }}@else{{'N/A'}}@endif</td>
        </tr>
        </tbody>
    </table>
</div>
<div class="col-md-11">
    <div class="col-md-12">
        <div class="box-header ui-sortable-handle" style="cursor: move;">
            <h3 class="box-title">Comments</h3>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-11">
        <div class="col-md-12">
            <div class="portlet-body" id="commentsection">
                @if(count($lead->lead_comments))
                    @foreach($lead->lead_comments as $comment)
                        <div class="tab-content" id="itemComment">
                            <div class="tab-pane active" id="portlet_comments_1">
                                <!-- BEGIN: Comments -->
                                <div class="mt-comments">
                                    <div class="mt-comment">
                                        <div class="mt-comment-img" id="imgContainer">
                                            <img src="{{ url('img/avatar.jpg') }}" alt="Avatar">
                                        </div>
                                        <div class="mt-comment-body">
                                            <div class="mt-comment-info">
                                                <span class="mt-comment-author" id="creat_by">@if($comment->created_by){{ $comment->user->name }}@else{{'N/A'}}@endif</span>
                                                <span class="mt-comment-date" id="datetime">{{ \Carbon\Carbon::parse($comment->created_at)->format('D M, j Y h:i A') }}</span>
                                            </div>
                                            <div class="mt-comment-text" id="message">@if($comment->comment){{ $comment->comment }}@else{{'N/A'}}@endif</div>
                                            <div class="mt-comment-details">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@if(Gate::allows('leads_manage'))
    <div class="container" style="width:100%;padding-bottom:5%; ">
        <div class="box-footer">
            <form id="cment">
                <div class="col-md-12">
                    <label>Comment</label>
                    <input type="text" name="comment" class="form-control" required/>
                    <br/>
                </div>
                <input type="hidden" name="lead_id" id="lead_id" class="form-control" value="{{$lead->id}}" /><br/>
                <div class="col-md-12">
                    <button type="button" name="Add_comment" id="Add_comment" class="btn btn-success">Comment</button>
                </div>
            </form>
        </div>
    </div>
@endif



<script>
    $("#Add_comment").click(function(){
        $.ajax({
            type: 'get',
            url:route('admin.leads.storecomment'),
            data: {
                '_token': $('input[name=_token]').val(),
                'comment': $('input[name=comment]').val(),
                'lead_id': $('input[name=lead_id]').val(),
            },
            success: function(myarray) {
                console.log(myarray);
                $('#commentsection').prepend('<div class="tab-content" id="itemComment"><div class="tab-pane active" id="portlet_comments_1"><div class="mt-comments"><div class="mt-comment"><div class="mt-comment-img"><img src="{{ url('img/avatar.jpg') }}" alt="Avatar"></div><div class="mt-comment-body"><div class="mt-comment-info"><span class="mt-comment-author">'+myarray.username+'</span><span class="mt-comment-date">'+myarray.leadCommentDate+'</span></div><div class="mt-comment-text">'+myarray.lead.comment+'</div></div></div></div></div></div>')
            },

        });
        $('#cment')[0].reset();
    });
</script>