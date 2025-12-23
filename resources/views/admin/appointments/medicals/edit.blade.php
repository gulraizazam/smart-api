@extends('admin.layouts.master')


@section('content')
    <style>
        .md-checkbox label>.box {
            border: none !important;
        }
    </style>
    @push('css')
        <link href="{{asset('assets/css/components.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('assets/css/layout.min.css')}}" rel="stylesheet" type="text/css" />
    @endpush
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Custom Form Feedbacks', 'title' => 'Custom Form Feedbacks'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">Edit</h3>
                        </div>
                        <div class="col-md-10">
                            <a href="{{route('admin.appointmentsmedical.medicals', $medicalinformation->appointment_id)}}" class="btn btn-sm btn-dark mt-3" style="float: right;"><i class="fa fa-arrow-left"></i>Back</a>
                        </div>

                    </div>

                    <div class="card-body">

                        <h1 style="color: red;">Form is auto saved. Whenever you change</h1>

                        <h3 class="card-label mt-2">Medical History Form</h3>

                        <h4 class="card-label">Client Medical History Form</h4>

                        <div class="form-group">

                            <div class="row mt-15">

                                <div class="col-md-12">
                                    @include('admin.appointments.medicals.edit_fields.select_patient')
                                </div>

                            </div>

                            <div class="form-group form-md-line-input cf_main_title mt-20">
                                <div class="form-group form-md-line-input cf_input_question">
                                    <h1 class="rs-head">{{$custom_form->form_name}}</h1>
                                </div>
                            </div>
                            <input id="feedback_id" name="feedback_id" type="hidden" value="{{$custom_form->id}}"/>

                            <div class="form-group form-md-line-input cf_main_title mt-10">
                                <div class="form-group form-md-line-input cf_input_question">
                                    <p>{{$custom_form->form_description}}</p>
                                </div>
                            </div>


                            <form id="cf_form">

                                <div class="row mt-15">

                                    @if ($custom_form)
                                        @foreach($custom_form->form_fields as $field)
                                            <?php $content = \App\Helpers\CustomFormHelper::getContentArray($field->content); ?>

                                            @if($field->field_type ==1)
                                                <div class="col-md-12">
                                                    @include("admin.custom_form_feedbacks.edit_fields.text_field", ['field_id'=>$field->id, 'title'=>$content["title"],"value" => $field->field_value])
                                                </div>
                                            @elseif($field->field_type ==2)
                                                <div class="col-md-12">
                                                    @include("admin.custom_form_feedbacks.edit_fields.paragraph_field", ['field_id'=>$field->id, 'title'=>$content["title"], "value" => $field->field_value])
                                                </div>
                                            @elseif($field->field_type ==3)
                                                <div class="col-md-12">
                                                    @include("admin.custom_form_feedbacks.edit_fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                                                </div>
                                            @elseif($field->field_type ==4 && is_array($content))
                                                <div class="col-md-12">
                                                    @include("admin.custom_form_feedbacks.edit_fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                                                </div>
                                            @elseif($field->field_type ==7 && is_array($content))
                                                <div class="col-md-12">
                                                    @include("admin.custom_form_feedbacks.edit_fields.table_input_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                                                </div>
                                            @elseif($field->field_type ==5 && is_array($content))
                                                <div class="col-md-12">
                                                    @include("admin.custom_form_feedbacks.edit_fields.option_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])
                                                </div>
                                            @elseif($field->field_type ==6 && is_array($content))
                                                <div class="col-md-12">
                                                    @include("admin.custom_form_feedbacks.edit_fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"], "value" => $field->field_value])
                                                </div>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>

                            </form>

                        </div>


                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

@stop

@push('js')
    <script src="{{asset('assets/js/jquery.validate.min.js')}}"></script>

    <script>

        function updatePatientMeasurement() {
            $(".update_measurement_data").bind("change", function () {
                patient_id = $("select[name=reference_id]").val();
                if (parseInt(patient_id) > 0) {
                    update_feedback((res) => {
                    }, (xhr, ajaxOptions, thrownError) => {
                    });
                }
            });
        }

        function fieldChangeUpdateBinding() {

            $(".update-answer-fields").bind("change", function () {

                field_id = this.id.split("cs_field_")[1];

                if (field_id != "") {
            
                    field_type = $(this).find("input#field_type[type=hidden]").val();


                    data = {};
                    if (field_type == 1) {
                        text_answer = this.querySelector("input[name=answer]").value;
                        data["field_value"] = text_answer;
                    } else if (field_type == 2) {
                        text_answer = this.querySelector("textarea[name=answer]").value;
                        data["field_value"] = text_answer;
                    } else if (field_type == 3) {
                        radio_answer = this.querySelector("input[name=field_option]:checked");
                        if (radio_answer) {
                            radio_answer = radio_answer.value;
                        } else {
                            radio_answer = "null";
                        }
                        data["field_value"] = radio_answer;
                    }
                    else if (field_type == 4) {
                        checkbox = this.querySelectorAll(".field_option:checked")
                        if (checkbox.length) {
                            checkbox_answer = [];
                            for (let i = 0; i < checkbox.length; i++) {
                                checkbox_answer[i] = checkbox[i].value;
                            }
                            data["field_value"] = JSON.stringify(checkbox_answer);
                        } else {
                            data["field_value"] = "null";
                        }

                    }
                    else if (field_type == 7) {

                        // options = fields[i].querySelectorAll("table thead th")
                        rows = this.querySelectorAll("table tbody tr");
                        row_data = [];
                        for(let i=0; i< rows.length; i++){
                            let row = {};
                            row.order = i;
                            row.cols = [];
                            let cols = rows[i].querySelectorAll("input")
                            for(let j =0; j < cols.length; j++){
                                let cell = {};
                                cell.row = cols[j].getAttribute("row");
                                cell.col = cols[j].getAttribute("col");
                                cell.question = cols[j].getAttribute("question");
                                cell.order = j;
                                cell.answer = cols[j].value;
                                row.cols.push(cell);
                            }
                            row_data.push(row);
                        }

                        if (row_data.length > 0) {
                            data["field_value"] = JSON.stringify(row_data);
                        } else {
                            data["field_value"] = "";
                        }

                    }
                    else if (field_type == 5) {
                        selected_value = this.querySelector("select[name=field_option]").value;
                        data["field_value"] = selected_value;
                    } else {

                        data["field_value"] = "title";
                    }

                    update_form_field(field_id, data, (response) => {

                    }, (xhr, ajaxOptions, thrownError) => {

                    });
                }
            });

        }

        function update_form_field(field_id, data, success_callback, error_callback) {

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.custom_form_feedbacks.update_field', {
                    'feedback_id': $("#feedback_id").val(),
                    'feedback_field_id': field_id
                }),
                type: 'POST',
                data: data,
                cache: false,
                success: success_callback,
                error: error_callback
            });
        }

        function update_feedback(success_callback, error_callback) {
            priority = $("[name=priority]").val();
            date = $("[name=date]").val();
            type = $("[name=type]").val();
            medical_id = $("[name=medical_id]").val();

            const data = {
                priority: $("[name=priority]").val(),
                date: $("[name=date]").val(),
                type: $("[name=type]").val(),
                medical_id: $("[name=medical_id]").val(),
            };
     
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: '{{route('admin.appointmentsmedical.update', $custom_form->id)}}',
                type: 'POST',
                data: data,
                cache: false,
                success: success_callback,
                error: error_callback
            });
        }

        $(document).ready(function () {
            fieldChangeUpdateBinding();
            updatePatientMeasurement();
        });

    </script>


@endpush

