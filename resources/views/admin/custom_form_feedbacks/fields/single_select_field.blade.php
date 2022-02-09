<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_SINGLE}}"/>
    <div class="cf_input_option">
        @foreach($options as $option)
        <div class="md-radio cf_input_option_item">
            <input type="radio"
                   id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                   name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}_{{$index}}"
                   value="{{$option["label"]}}"
                   class="md-radiobtn {{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}" required>
            <label class="cf_label"
                   for="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}">
                <span></span>
                <span class="check" style="margin-top: 8px;"></span>
                <span class="box"></span> <h4>{{$option["label"]}}</h4></label>
        </div>
        @endforeach
    </div>
</div>