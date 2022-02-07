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
        width: 100%;
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
    .page-break {
        page-break-after: always;
    }
    .portlet.light.bordered {
        margin-bottom: 0;
    }
    .order-date {
        font-size: 36px;
    }
    .portlet  .md-checkbox input[type="checkbox"]:disabled:checked ~ label > .check, .portlet  .md-checkbox input[type="checkbox"]:disabled ~ label, .md-checkbox input[type="checkbox"]:disabled ~ label > .box,
    .portlet  .md-checkbox input[type="checkbox"][disabled]:checked ~ label > .check, .portlet  .md-checkbox input[type="checkbox"][disabled] ~ label, .md-checkbox input[type="checkbox"][disabled] ~ label > .box,
    .portlet .md-radio input[type="radio"]:disabled:checked ~ label > .check, .portlet .md-radio input[type="radio"]:disabled ~ label, .portlet .md-radio input[type="radio"]:disabled ~ label > .box,
    .portlet .md-radio input[type="radio"][disabled]:checked ~ label > .check, .portlet .md-radio input[type="radio"][disabled] ~ label, .portlet .md-radio input[type="radio"][disabled] ~ label > .box,
    .portlet .md-checkbox input[type="checkbox"]:disabled:checked ~ label > .check, .portlet .md-checkbox input[type="checkbox"]:disabled ~ label, .portlet .md-checkbox input[type="checkbox"]:disabled ~ label > .box,
    .portlet .md-checkbox input[type="checkbox"][disabled]:checked ~ label > .check, .portlet .md-checkbox input[type="checkbox"][disabled] ~ label, .portlet .md-checkbox input[type="checkbox"][disabled] ~ label > .box{
        opacity: 1;
    }
    .form-data-table .data-split-wrap td {
        width: 50%;
    }

    table{
        /*page-break-inside: avoid;*/
        border-collapse: collapse;
        border-spacing: 0;
    }
    .row-wrap{
        /*display: -webkit-box;*/
        /*display: -ms-flexbox;*/
        /*display: flex;*/
        /*-ms-flex-wrap: wrap;*/
        /*flex-wrap: wrap;*/
        display: table;
        table-layout: fixed;
        width: 100%;
    }

    .row-wrap:nth-child(2n) {
        background: #eee;
    }

    .row-wrap.row-head {
        background-color: #364150;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
    }
    .row-head p{
        margin-bottom: 0;
    }

    .row-wrap .col {
        /*flex-grow: 1;*/
        /*-ms-flex-preferred-size: 0;*/
        /*flex-basis: 0;*/
        /*-webkit-box-flex: 1;*/
        /*-ms-flex-positive: 1;*/
        /*max-width: 100%;*/
        display: table-cell;
        padding: 10px 10px;
    }

    .cf_input_option .md-radio, .cf_input_option .md-checkbox {
        position: relative;
        display: inline-block;
        margin-right: 20px;
        width:30%;
    }



    @media print {

        html, body { height: auto; }
        .row-wrap.row-head {
            background-color: #364150 !important;
            -webkit-print-color-adjust: exact;
        }
        .md-radio label > .check {
            background-color: #35a1d4;
        }
        .md-radio label > .check::before {
            content: '\f111';
            color: #35a1d4 !important;
            font-size: 14px;
            line-height: 9px;
            margin-left: -1px;
            font-family: FontAwesome;
        }
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
                <i class="fa fa-eye font-green-sharp"></i>
                <span class="caption-subject bold uppercase"> @lang('global.app_preview')</span>
            </div>
            <div class="actions">
                <a style="margin: 5px;" href="{{ route('admin.custom_form_feedbacks.index') }}"
                   class="btn dark pull-right">@lang('global.app_back')
                </a>
                @if(Gate::allows('custom_form_feedbacks_manage') && Gate::allows('patients_customform_manage'))
                <a target="_blank" style="margin: 5px;" href="{{ route('admin.patient_custom_form_feedbacks.filled_print',$thisId) }}"
                   class="btn dark pull-right">@lang('global.app_print')
                </a>
                @endif
                @if(Gate::allows('custom_form_feedbacks_manage') && Gate::allows('patients_customform_manage'))
                <a style="margin: 5px;" href="{{ route('admin.patient_custom_form_feedbacks.export_pdf',$thisId) }}"
                   class="btn dark pull-right">@lang('global.app_make_pdf')
                </a>
                @endif
            </div>
        </div>
        <div class="portlet-body rs">
            <!-- custom form -->
            <input id="feedback_id" name="feedback_id" type="hidden" value="{{$custom_form->id}}"/>
            <div id="cf_form">
                <table class="form-data-table">
                    <tbody>
                    <tr class="data-split-wrap">
                        <td>
                            <img src="{{ asset('centre_logo/logo_final.png') }}" height="130">
                        </td>
                        <td>
                            <p class="order-date text-right">#{{ $thisId }} / {{ Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $custom_form->created_at)->format('Y-M-d') }}</p>
                        </td>
                    </tr>
                    <tr class="data-split-wrap">
                        <td>
                            <div class="patient_info">
                                <label><h1 class="sbold margin-top-15">Patient Detail</h1></label>
                                <p><strong>Patient Name:</strong> {{$custom_form->patient?$custom_form->patient->name : "Null"}}</p>
                                <p><strong>Patient ID:</strong> {{$custom_form->patient?' C-'.$custom_form->patient->id : ""}} </p>
                                <p><strong>Email:</strong> {{$custom_form->patient?$custom_form->patient->email : ""}}</p>
                            </div>
                        </td>
                        <td>
                            <div class="caompay-info pull-right">
                                 <label><h1 class="sbold margin-top-15">Company Detail</h1></label>
                                <p><strong>Company Name:</strong> {{ Auth::user()->account->name }}</p>
                                <p><strong>Contact:</strong> {{ Auth::user()->account->contact }} </p>
                                <p><strong>Email:</strong> {{ Auth::user()->account->email }} </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="form-info text-center">
                                <h1 class="sbold margin-top-15">{{$custom_form->form_name}}</h1>
                                <p>{{$custom_form->form_description}}</p>
                            </div>
                        </td>
                    </tr>
                    @foreach($custom_form->form_fields as $field)
                        <?php $content = \App\Helpers\CustomFormHelper::getContentArray($field->content); ?>

                        @if($field->field_type ==1)
                            <tr>
                                <td colspan="2">
                                    @include("admin.custom_form_feedbacks.preview_fields.text_field_preview", ['field_id'=>$field->id, 'title'=>$content["title"],"value" => $field->field_value])
                                </td>
                            </tr>
                        @elseif($field->field_type ==2)
                            <tr>
                                <td colspan="2">
                                    @include("admin.custom_form_feedbacks.preview_fields.text_field_preview", ['field_id'=>$field->id, 'title'=>$content["title"], "value" => $field->field_value])
                                </td>
                            </tr>
                        @elseif($field->field_type ==3)
                            <tr class="data-split-wrap">
                                    <td colspan="2">@include("admin.custom_form_feedbacks.preview_fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])</td>
                            </tr>
                        @elseif($field->field_type ==4 && is_array($content))
                            <tr>
                                <td colspan="2">@include("admin.custom_form_feedbacks.preview_fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])</td>
                            </tr>
                        @elseif($field->field_type ==5 && is_array($content))
                            <tr>
                                <td colspan="2">@include("admin.custom_form_feedbacks.preview_fields.text_field_preview", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])</td>
                            </tr>
                        @elseif($field->field_type ==6 && is_array($content))
                            <tr>
                                <td colspan="2">@include("admin.custom_form_feedbacks.preview_fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"], "value" => $field->field_value])</td>
                            </tr>

                        @elseif($field->field_type ==7 && is_array($content))
                    </tbody>
                </table>
                @include("admin.custom_form_feedbacks.preview_fields.table_input_field_preview", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                <table>
                    <tbody>
                    @endif
                    @endforeach
                    </tbody>
                </table>
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

