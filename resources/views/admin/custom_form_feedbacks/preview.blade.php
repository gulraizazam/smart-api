@extends('layouts.app-rs')
<style type="text/css">
    .cf_label {
        width: 100%;
    }

    .cf_input_question {
        font-size: 18px !important;
        color: #000 !important;
    }

    .cf_input_option {
        padding-top: 10px !important;
    }

    .cf_card {
        padding-bottom: 60px;
    }

    .cf_input_option .md-checkbox label > .box {
        width: 15px;
        height: 15px;
    }

    .rs .md-checkbox label > .box {
        top: 10px;
    }

    .rs .form-group.form-md-line-input .form-control {
        border-bottom: 0;
        font-size: 16px;
        display: inline-block;
    }

    .cf_input_option label {
        width: 90%;
    }

    .cf_main_title {
        padding-bottom: 30px;
    }

    .cf_form_internal_title {
        background: purple;
        font-weight: bold;
        font-size: 18px;
        color: #fff;
    }

    .cf-question-headings {
        color: #000;
        font-weight: 500;
    }

    .cf_form_internal_title {
        background: #35a1d4;
        font-weight: bold;
        font-size: 18px;
        color: #fff;
        display: inline-block;
        padding: 10px;
        position: relative;
    }

    h3.cf_form_internal_title:after {
        content: "";
        width: 0;
        height: 0;
        border-left: 3px solid transparent;
        border-right: 20px solid transparent;
        border-top: 39px solid #35a1d4;
        position: absolute;
        right: -20px;
        top: 0px;
    }

    .cf-input-border {
        border-bottom: 1px solid #ddd !important;
    }
</style>
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
                <a href="{{ route('admin.custom_forms.index') }}"
                   class="btn dark pull-right">@lang('global.app_back')</a>
            </div>
        </div>
        <div class="portlet-body rs">
            <!-- custom form -->
            <div id="cf_form">

                <div class="form-group form-md-line-input cf_main_title">
                    <div class="form-group form-md-line-input cf_input_question">
                        <h1 class="rs-head">{{$custom_form->name}}</h1>
                    </div>
                </div>
                <input id="form_id" name="form_id" type="hidden" value="{{$custom_form->id}}"/>
                <div class="form-group form-md-line-input cf_main_title">
                    <div class="form-group form-md-line-input cf_input_question">
                        <p>{{$custom_form->description}}</p>
                    </div>
                </div>


                <div id="cf_field_list">


                    @foreach($custom_form->form_fields as $field)
                        <?php $content = \App\Helpers\CustomFormHelper::getContentArray($field->content); ?>

                        @if($field->field_type ==1)
                            @include("admin.custom_form_feedbacks.fields.text_field", ['field_id'=>$field->id, 'title'=>$content["title"],"index"=>$loop->index])
                        @elseif($field->field_type ==2)
                            @include("admin.custom_form_feedbacks.fields.paragraph_field", ['field_id'=>$field->id, 'title'=>$content["title"],"index"=>$loop->index])
                        @elseif($field->field_type ==3)
                            @include("admin.custom_form_feedbacks.fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                        @elseif($field->field_type ==4 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                        @elseif($field->field_type ==5 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.option_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                        @elseif($field->field_type ==6 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"],"index"=>$loop->index])
                        @elseif($field->field_type ==7 && is_array($content))
                            @include("admin.custom_form_feedbacks.fields.table_input_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "rows"=> $content["rows"],"index"=>$loop->index])
                            @endif
                    @endforeach
                </div>
                <div class="margin-top-10">
                    <button class="btn green" disabled>Save Changes</button>
                    <a href="{{route("admin.custom_forms.index")}}" class="btn default">Cancel </a>
                </div>
            </div>

        </div>
        @stop

        @section('javascript')
            <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/jquery.validate.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('metronic/assets/global/plugins/jquery-validation/js/additional-methods.min.js') }}"
                    type="text/javascript"></script>
            <script src="{{ url('js/admin/custom_form_feedbacks/fields.js') }}" type="text/javascript"></script>

@endsection

