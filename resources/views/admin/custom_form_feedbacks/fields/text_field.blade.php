<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item">
    <h3 class="cf-question-headings">{{$title}}</h3>

    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_TEXT}}"/>
    <div class="form-group cf_input_option">
        <label>
            <input type="text" class="form-control cf-input-border {{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_TEXT_FIELD_NAME}}"
                   name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_TEXT_FIELD_NAME}}_{{$index}}"
                   placeholder="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_TEXT_FIELD_PLACEHOLDER}}" required>
        </label>
    </div>
</div>