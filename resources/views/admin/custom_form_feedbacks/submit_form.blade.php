@extends('layouts.app-rs')

@section('title')
    <!-- BEGIN PAGE TITLE-->
    <h1 class="page-title">@lang('global.custom_form_feedbacks.title')</h1>
    <!-- END PAGE TITLE-->
@endsection

@section('content')
    <div class="portlet light bordered">
        <div class="portlet-title">
            <div class="caption font-green-sharp">
                <i class="icon-pencil font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.app_edit')</span>
            </div>
            <div class="actions">
                <a href="{{ route('admin.custom_form_feedbacks.index') }}"
                   class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body rs">
            <!-- custom form -->
            <div id="cf_form">

                <!-- Form Name -->
                <div class="form-group form-md-line-input">
                    <input type="text" class="form-control rs-head custom_form_feedback_update_single_value" name="name"
                           placeholder=" Form name" value="{{$custom_form_feedback->name}}">
                </div>

                <input id="form_id" name="form_id" type="hidden" value="{{$custom_form_feedback->id}}">
                <!-- Form Description -->

                <div class="form-group form-md-line-input">
                    <textarea class="form-control custom_form_feedback_update_single_value" name="description"
                              placeholder="Form Description">{{$custom_form_feedback->description}}</textarea>
                </div>

                <!-- field list -->

                <div id="cf_field_list">

                    @foreach($custom_form_feedback->form_fields as $field)
                        <?php $content = \App\Helpers\CustomFormFeedbackHelper::getContentArray($field->content); ?>

                        @if($field->field_type ==1)
                            @include("admin.custom_form_feedbacks.fields.text_field", ['field_id'=>$field->id, 'title'=>$content["title"]])
                        @elseif($field->field_type ==2)
                            @include("admin.custom_form_feedbacks.fields.paragraph_field", ['field_id'=>$field->id, 'title'=>$content["title"]])
                        @elseif($field->field_type ==3)
                            @include("admin.custom_form_feedbacks.fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"]])
                        @elseif($field->field_type ==4 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"]])
                        @elseif($field->field_type ==5 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.option_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"]])
                        @elseif($field->field_type ==6 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"]])
                        @endif

                    @endforeach

                </div>
            </div>
            <!-- end of custom form -->

            <!-- start of custom form navigation -->
            <div class="cf_nav">
                @include("admin.custom_form_feedbacks.partials.nav")
            </div>
            <!-- end of custom form navigation -->

        </div>

    </div>
@stop

@section('javascript')
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
            type="text/javascript"></script>
    <script src="{{ url('js/admin/custom_form_feedbacks/fields.js') }}" type="text/javascript"></script>

    <script type="application/javascript">
        /**
         * crate form field ajax request
         **/
        function create_field_ajax(data, success_callback, error_callback) {
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{route('admin.custom_form_feedback_feedbacks.create_field', ['id'=>$custom_form_feedback_feedback->id])}}',
                type: 'POST',
                data: data,
                cache: false,
                success: success_callback,
                error: error_callback
            });
        }


        $(document).ready(function () {


        });

    </script>
@endsection

