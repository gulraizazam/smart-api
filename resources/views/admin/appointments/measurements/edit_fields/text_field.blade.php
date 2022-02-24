<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 class="cf-question-headings">{{$title}}</h3>

    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_TEXT}}"/>
    <div class="form-group cf_input_option">
        <label>
            <input type="text" class="form-control cf-input-border"
                   name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_TEXT_FIELD_NAME}}"
                   placeholder="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_TEXT_FIELD_PLACEHOLDER}}"
                   value="{{$value}}">
        </label>
    </div>
</div>