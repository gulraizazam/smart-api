@if(Gate::allows('patients_document_manage') )
    <a class="btn btn-xs btn-warning" href="{{asset('patient_document/')}}/{{$document->url}}" target="_blank">@lang('global.app_view')</a>
@endif
@if(Gate::allows('patients_document_edit') )
    <a class="btn btn-xs btn-info" href="{{ route('admin.patients.documentedit',[$document->id]) }}" data-target="#ajax_patient_documents" data-toggle="modal">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('patients_document_destroy') )
    {!! Form::open(array(
        'style' => 'display: inline-block;',
        'method' => 'POST',
        'onsubmit' => "return confirm('".trans("global.app_are_you_sure")."');",
        'route' => ['admin.patients.documentsdestroy', $document->id])) !!}
    {!! Form::submit(trans('global.app_delete'), array('class' => 'btn btn-xs btn-danger')) !!}
    {!! Form::close() !!}
@endif