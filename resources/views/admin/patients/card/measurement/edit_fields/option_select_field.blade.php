<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_OPTION}}">
    <div class="form-group form-md-line-input cf_input_option"/>
    <label>
        <select class="form-control cf-input-border"
                name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}">
            <option value="">Select</option>
            @foreach($options as $option)
                @if($value == $option["label"])
                    <option value="{{$option["label"]}}" selected="selected">{{$option["label"]}}</option>
                @else
                    <option value="{{$option["label"]}}">{{$option["label"]}}</option>
                @endif
            @endforeach
        </select>
    </label>
</div>
</div>