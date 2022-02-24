<input type="hidden" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_MEASUREMENT}}" value="{{$measurementinformation->id}}">
<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Select Patient</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
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
    <h3 class="cf-question-headings">Select Service</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <select class="form-control select2" id="service_id" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_SERVICENAME}}" disabled>
            <option value="">Select Service</option>
            @foreach($Services as $id => $Service)
                @if ($id == 0) @continue; @endif
                @if($id < 0)
                    @php($tmp_id = ($id * -1))
                @else
                    @php($tmp_id = ($id * 1))
                @endif
                <option @if($tmp_id==$leadServices) selected="selected"
                        @endif value="@if($id < 0){{ ($id * -1) }}@else{{ $id }}@endif">@if($id < 0)
                        <b>{!! $Service['name'] !!}</b>@else{!! $Service['name'] !!}@endif</option>
            @endforeach
        </select>
    </label>
</div>

<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Priority(default:Low)</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <select class="form-control cf-input-border select2 update_measurement_data" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_PRIORITY}}" required="required">
            <option @if($measurementinformation->priority == 'Low priority') selected="selected" @else value="Low priority" @endif>Low priority</option>
            <option @if($measurementinformation->priority == 'Medium priority') selected="selected" @else value="Medium priority" @endif>Medium priority</option>
            <option @if($measurementinformation->priority == 'High priority') selected="selected" @else value="High priority" @endif>High priority</option>
        </select>
    </label>
</div>

<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Date</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <input type="text" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_DATE}}" id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_DATE}}" value="@if($measurementinformation->date == null){{Carbon\Carbon::parse(\Carbon\Carbon::now())->format('Y-m-d')}}@else{{$measurementinformation->date}}@endif" class="form-control date_to_rota_edit update_measurement_data">
    </label>
</div>

<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Type</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <select class="form-control cf-input-border select2 update_measurement_data" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_TYPE}}" required="required">
            <option @if($measurementinformation->type == 'Before Appointment')  selected="selected" @else value="Before Appointment" @endif>Before Appointment</option>
            <option @if($measurementinformation->type == 'After Appointment')  selected="selected" @else value="After Appointment" @endif>After Appointment</option>
        </select>
    </label>
</div>
