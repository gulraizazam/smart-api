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
        <select class="form-control cf-input-border select2" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_PRIORITY}}" required="required">
            <option value="Low priority" selected>Low priority</option>
            <option value="Medium priority">Medium priority</option>
            <option value="High priority">High priority</option>
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

<div class="form-group form-md-line-input cf_card">
    <h3 class="cf-question-headings">Type</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <select class="form-control cf-input-border select2" name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_TYPE}}" required="required">
            <option value="Before Appointment">Before Appointment</option>
            <option value="After Appointment">After Appointment</option>
        </select>
    </label>
</div>
