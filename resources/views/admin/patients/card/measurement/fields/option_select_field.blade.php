<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_OPTION}}">
    <div class="form-group form-md-line-input cf_input_option"/>
        <label>
            <select class="form-control cf-input-border {{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}"
                    name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}_{{$index}}" required>
                <option value="">Select</option>
                @foreach($options as $option)
                    <option value="{{$option["label"]}}">{{$option["label"]}}</option>
                @endforeach
            </select>
        </label>
    </div>
</div>