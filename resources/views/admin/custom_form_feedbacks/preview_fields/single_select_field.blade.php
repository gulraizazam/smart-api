<div id="cs_field_{{$field_id}}" class="form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 style="margin:20px 0px 12px;" class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_SINGLE}}"/>
    <ul class="cf_input_option" style="padding-left: 0; margin:0px;">
        @foreach($options as $option)
            @if($value == $option["label"])
                <li class="md-radio cf_input_option_item list-unstyled">
              
                <label class="radio" style="height: 30px; display:inline-flex;">
				    <input type="radio" 
                           id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                           name="{{str_replace(' ', '_', $title)}}"
                           value="{{$option["label"]}}"
                           class="md-radiobtn" checked="checked" >
					
                    
                        <span class="box " ></span>
                         <p class="single-title" style="margin-top:10px;font-size: 16px; padding-left: 20px;">{{$option["label"]}}</p>
                </label>

                
                </li>
            @else
                <li class="md-radio cf_input_option_item list-unstyled">

                <label class="radio" style="height: 30px; display:inline-flex;">
				    <input type="radio" 
                           id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                           name="{{str_replace(' ', '_', $title)}}"
                           value="{{$option["label"]}}">
					
                    
                        <span class="box" ></span> 
                        <p style="margin-top:10px;font-size: 16px; padding-left: 20px;">{{$option["label"]}}</p>
                </label>

                </li>
            @endif
        @endforeach
    </ul>
</div>