<div id="cs_field_{{$field_id}}" class="form-group form-group_pdf cf_card cf_field_item update-answer-fields">
    <input id="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}"
           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_NAME}}" type="hidden"
           value="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_TYPE_MULTIPLE}}">
    <h3 class="cf-question-headings" style="padding-bottom:15px;">{{$title}}</h3>
    <div class="clearfix cf_input_option cf_input_option2">
        <?php $value_array = json_decode($value, true); 
            $num = 0;
        ?>
        @foreach($options as $option)
            @if( is_array($value_array) && in_array($option["label"], $value_array))
            <?php
                if($num%2 ==0){
                    echo '<div class="full_width">';
                }
            ?>
                 <div class="md-checkbox cf_input_option_item" style="display: inline-flex;">
                    <input type="checkbox" disabled readonly
                          
                           id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}"
                           value="{{$option["label"]}}"
                           class="md-check" checked="checked">
                    <label class="cf_label" 
                        for="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}">
                        <!-- <span class="check" style="margin-top: 8px;"></span>
                        <span class="box"></span>  -->
                       <span style="padding-left: 20px;padding-bottom:20px;">{{$option["label"]}}</span>
                    </label>
                </div> 
            <?php
                if($num%2 ==0){
                    echo '</div>';
                }
            ?>
            @else
            <?php
                if($num%2 ==0){
                    echo '<div class="full_width">';
                }
            ?>
                <div class="md-checkbox cf_input_option_item"style="display: inline-flex;">
                    <input type="checkbox" disabled readonly
                          
                           id="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"
                           name="{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_FIELD_OPTION_NAME}}"
                           value="{{$option["label"]}}"
                           class="md-check">
                     <label class="cf_label"
                        for="{{\App\Helpers\CustomFormFeedbackHelper::getFieldOptionId($field_id, $option["label"])}}"> 
                        <span></span>
                        <!-- <span class="check" style="margin-top: 8px;"></span> 
                         <span class="box"></span>  -->
                        <span style="padding-left: 20px;padding-bottom:20px;">{{$option["label"]}}</span>
                     </label> 
                </div>
            <?php
                if($num%2 ==0){
                    echo '</div>';
                }
            ?>
            @endif
            <?php $num++;?>
        @endforeach
    </div>
</div>