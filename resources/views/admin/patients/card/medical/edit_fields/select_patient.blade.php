<div class="form-group form-md-line-input cf_card update_patient_data">
    <h3 class="cf-question-headings">Select Patient</h3>
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <select class="form-control cf-input-border select2"
                name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_NAME}}" required="required" disabled>
            <option value="{{$patient_id}}">{{$patient_name->name}}</option>
        </select>
    </label>
</div>
