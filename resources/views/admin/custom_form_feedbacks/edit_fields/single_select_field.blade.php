<div id="cs_field_{{$field_id}}" class="form-group form-md-line-input cf_card cf_field_item update-answer-fields">
    <h3 class="cf-question-headings">{{$title}}</h3>
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_SINGLE}}"/>
    <div class="cf_input_option">
        @foreach($options as $option)
            @if($value == $option["label"])
                <div class="md-radio cf_input_option_item">
                   

                <label class="radio" style="height: 30px;">
				    <input type="radio" 
                           id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                           name="{{str_replace(' ', '_', $title)}}"
                           value="{{$option["label"]}}"
                           class="md-radiobtn field_option" checked="checked">
					
                    
                        <span class="box" ></span> 
                        <p class="single-title" style="margin-top:10px;font-size: 16px; margin-left:10px;">
                            {{$option["label"]}}
                        </p>
                </label>


               

                </div>
            @else
                <div class="md-radio cf_input_option_item">
                  

                <label class="radio" style="height: 30px;">
				    <input type="radio" 
                           id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                           name="{{str_replace(' ', '_', $title)}}"
                           value="{{$option["label"]}}"
                           class="md-radiobtn field_option"
                           >
					
                    
                        <span class="box" ></span>
                         <p style="margin-top:10px;font-size: 16px; margin-left:10px;">
                            {{$option["label"]}}
                        </p>
                </label>



                </div>
            @endif
        @endforeach
    </div>
</div>