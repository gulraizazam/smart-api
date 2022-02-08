<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_MULTIPLE}}">
    <ul class="cf_input_option" style="padding-left: 0;">
        <?php $value_array = json_decode($value, true); ?>
        @foreach($options as $option)
            @if( is_array($value_array) && in_array($option["label"], $value_array))
            <li class="md-checkbox cf_input_option_item list-unstyled" >
                <label  style="height: 30px;" class="custom_checkbox checkbox-all" for="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}">
                        <input disabled readonly
                        class="select-all-checkboxes" type="checkbox"
                        name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}"
                        id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                        value="{{$option["label"]}}"
                       checked >
                        <strong></strong>
                        <span class="check" ></span>
                            <span class="box"></span> <p style="margin-top:10px;font-size: 16px;margin-left:10px;">{{$option["label"]}}</p>
                </label>
            </li>
            @else
                <li class="md-checkbox cf_input_option_item list-unstyled" >
                    <label  style="height: 30px;" class="custom_checkbox checkbox-all" for="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}">
                        <input disabled readonly
                        class="select-all-checkboxes" type="checkbox"
                        name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}"
                        id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                        value="{{$option["label"]}}"
                        >
                        <strong></strong>
                        <span class="check"></span>
                            <span class="box"></span> <p style="margin-top:10px;font-size: 16px;margin-left:10px;">{{$option["label"]}}</p>
                    </label>
                    
               
                </li>
            @endif
        @endforeach
    </ul>
</div>