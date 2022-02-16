@extends('layouts.app')

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.leads.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('stylesheets')
    <!-- BEGIN PAGE LEVEL PLUGINS -->
    <link href="{{ url('metronic/assets/global/css/components.min.css') }}" rel="stylesheet" id="style_components" type="text/css" />
    <!-- END PAGE LEVEL PLUGINS -->
@stop

@section('content')
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-eye font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.app_detail')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.leads.index') }}" class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body">
            <div class="box-body no-padding">
                <table class="table table-striped">
                    <tbody>
                    <tr>
                        <th>Full Name</th>
                        <td>{{ $lead->patient->full_name }}</td>
                        <th>Email</th>
                        <td>@if($lead->patient->email){{ $lead->patient->email }}@else{{'N/A'}}@endif</td>
                        <th>Phone</th>
                        <td>@if($lead->patient->phone){{ \App\Helpers\GeneralFunctions::prepareNumber4Call($lead->patient->phone) }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>DOB</th>
                        <td>{{ $lead->patient->dob }}</td>
                        <th>Address</th>
                        <td colspan="3">@if($lead->patient->address){{ $lead->patient->address }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>Gender</th>
                        <td>@if($lead->patient->gender){{ Config::get('constants.gender_array')[$lead->patient->gender] }}@else{{'N/A'}}@endif</td>
                        <th>SMS Status</th>
                        <td>@if($lead->msg_count){{ 'Delivered' }}@else{{'Not Delivered'}}@endif</td>
                        <th>City</th>
                        <td>@if($lead->city_id){{ $lead->city->name }}@else{{'N/A'}}@endif</td>
                    </tr>
                    <tr>
                        <th>Lead Source</th>
                        <td>@if($lead->lead_source_id){{ $lead->lead_source->name }}@else{{'N/A'}}@endif</td>
                        <th>Lead Status</th>
                        <td>@if($lead->lead_status_id){{ $lead->lead_status->name }}@else{{'N/A'}}@endif</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-user font-green-sharp"></i>
                <span class="caption-subject bold uppercase">Comments</span>
            </div>
        </div>
        <div class="portlet-body">
            <div class="mt-comments">
                @if(count($lead->lead_comments))
                    @foreach($lead->lead_comments as $comment)
                        <div class="mt-comment">
                            <div class="mt-comment-img">
                                <img src="{{ url('/img/avatar6.png') }}" width="45">
                            </div>
                            <div class="mt-comment-body">
                                <div class="mt-comment-info">
                                    <span class="mt-comment-author">@if($comment->created_by){{ $comment->user->name }}@else{{'N/A'}}@endif</span>
                                    <span class="mt-comment-date">{{ \Carbon\Carbon::parse($comment->created_at)->format('D M, j Y h:i A') }}</span>
                                </div>
                                <div class="mt-comment-text">@if($comment->comment){{ $comment->comment }}@else{{'N/A'}}@endif</div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <!-- /.chat -->
            <div class="box-footer">
                {!! Form::open(['method' => 'POST', 'route' => ['admin.leads.comment_store']]) !!}
                <div class="input-group">
                    {!! Form::text('comment', old('comment'), ['class' => 'form-control', 'placeholder' => 'Type comment...', 'required' => '']) !!}
                    {!! Form::hidden('lead_id', $lead->id) !!}
                    <div class="input-group-btn">
                        {!! Form::submit('Send', ['class' => 'btn btn-success']) !!}
                    </div>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@stop

