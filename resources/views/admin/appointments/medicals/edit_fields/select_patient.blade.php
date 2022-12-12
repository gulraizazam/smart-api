<input type="hidden" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_MEDICAL}}" value="{{$medicalinformation->id}}">
<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Select Patient</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label class="w-100">
        <select class="form-control cf-input-border select2"
                name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_NAME}}" required="required" disabled>
            <option value="">Select</option>
            @foreach($users as $user)
                @if($custom_form->reference_id == $user["id"])
                    <option value="{{$user["id"]}}" selected>{{$user["name"]}}</option>
                @else
                    <option value="{{$user["id"]}}">{{$user["name"]}}</option>
                @endif
            @endforeach
        </select>
    </label>
</div>
<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Date</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label class="w-100 custom-datepicker">
        <input type="text" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_DATE}}" id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_DATE}}" value="@if($medicalinformation->date == null){{Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')}}@else{{$medicalinformation->date}}@endif" class="form-control date_to_rota_edit update_measurement_data">
    </label>
</div>
