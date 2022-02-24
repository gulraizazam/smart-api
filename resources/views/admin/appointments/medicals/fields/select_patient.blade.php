<input type="hidden" value="{{$appointmentinformation->id}}" name="appointment_id" id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_APPOINTMENT}}">

<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Select Patient</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <select class="form-control cf-input-border select2" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_NAME}}" required="required"
                disabled>
            <option value="">Select</option>
            @foreach($users as $user)
                <option @if($user->id == $patient_id) selected="selected"
                        @endif value="{{$user->id}}">{{$user->name}}</option>
            @endforeach
        </select>
    </label>
</div>
<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Date</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <input type="text" name="date" id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_DATE}}" value="{{Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')}}" class="form-control date_to_rota">
    </label>
</div>