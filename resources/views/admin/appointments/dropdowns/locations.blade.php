@inject('request', 'Illuminate\Http\Request')
@if($request->get('form'))
    @php($form = $request->get('form'))
    @php($select2 = '')
@else
    @php($form = 'FormValidation')
    @php($select2 = 'select2')
@endif
@if($request->get('idPrefix'))
    @php($idPrefix = $request->get('idPrefix'))
@else
    @php($idPrefix = '')
@endif
{!! Form::select('location_id', $locations, null, ['onchange' => $form . '.loadDoctors($(this).val())', 'id' => $idPrefix . 'location_id', 'class' => 'form-control ' . $select2 . ' required']) !!}