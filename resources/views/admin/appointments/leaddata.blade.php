{!! Form::hidden('patient_id', '', ['id' => 'patient_id']) !!}
{!! Form::email('email', null, ['id' => 'email', 'placeholder' => 'Patient Email']) !!}
{!! Form::select('lead_source_id',$lead_sources, null, ['class' => 'form-control required', ]) !!}