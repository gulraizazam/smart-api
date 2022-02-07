<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_MULTIPLE}}">
    <ul class="cf_input_option">
        <?php $value_array = json_decode($value, true); ?>
        @foreach($options as $option)
            @if( is_array($value_array) && in_array($option["label"], $value_array))
                <li class="md-checkbox cf_input_option_item">
                    <input type="checkbox" disabled readonly
                           id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}"
                           value="{{$option["label"]}}"
                           class="md-check" checked="checked">
                    <label class="cf_label"
                           for="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}">
                        <span></span>
                        <span class="check" style="margin-top: 8px;"></span>
                        <span class="box"></span> <h4>{{$option["label"]}}</h4>
                    </label>
                </li>
            @else
                <li class="md-checkbox cf_input_option_item">
                    <input type="checkbox" disabled readonly
                           id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}"
                           value="{{$option["label"]}}"
                           class="md-check">
                    <label class="cf_label"
                           for="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}">
                        <span></span>
                        <span class="check" style="margin-top: 8px;"></span>
                        <span class="box"></span> <h4>{{$option["label"]}}</h4>
                    </label>
                </li>
            @endif
        @endforeach
    </ul>
</div>