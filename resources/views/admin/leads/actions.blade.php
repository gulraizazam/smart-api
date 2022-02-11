<a class="btn btn-xs btn-warning" href="{{ route('admin.leads.detail',[$lead->lead_id]) }}" data-target="#ajax_leads_detail" data-toggle="modal"><i class="fa fa-eye"></i></a>

@if(Gate::allows('leads_edit'))
    <a class="btn btn-xs btn-info" href="{{ route('admin.leads.edit',[$lead->lead_id]) }}" data-target="#ajax_leads" data-toggle="modal"><i class="fa fa-edit"></i></a>
@endif

@if(Gate::allows('appointments_manage') && Gate::allows('leads_convert') && ($default_converted_lead_status_id != $lead->lead_status_id))
    <a href="{{ route('admin.leads.convert',[$lead->lead_id]) }}" class="btn btn-xs btn-success" data-target="#ajax_leads" data-toggle="modal" title="Convert"><i class="fa fa-recycle"></i></a>
@endif
@if(Gate::allows('leads_destroy'))
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'DELETE',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.leads.destroy', $lead->lead_id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif