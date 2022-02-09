<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_PARAGRAPH}}"/>
    <div class="form-group cf_input_option">
        <label>
            <textarea class="form-control cf-input-border {{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_PARAGRAPH_FIELD_NAME}}"
                      id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, 0)}}"
                      name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_PARAGRAPH_FIELD_NAME}}_{{$index}}"
                      placeholder="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_PARAGRAPH_FIELD_PLACEHOLDER}}" required></textarea>
        </label>
    </div>
</div>