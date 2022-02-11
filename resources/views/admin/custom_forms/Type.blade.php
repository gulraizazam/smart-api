@if($custom_form->custom_form_type == '0')
    <label>General Form</label>
@elseif($custom_form->custom_form_type == '1')
    <label>Measurement Form</label>
@else
    <label>Medical Form</label>
@endif