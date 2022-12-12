@extends('admin.layouts.master')


@section('content')

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
                            <h3 class="card-label">Custom Form Feedbacks</h3>
                        </div>
                        <div class="card-toolbar">
                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            <a href="{{ route('admin.custom_forms.index') }}" class="btn btn-sm btn-dark" >
                                <i class="la la-arrow-left"></i>
                                Back
                            </a>

                            <!--end::Button-->
                        </div>
                        <!--end::Button-->
                    </div>
                    </div>

                    <div class="card-body">
                        <form id="cf_form">
                            <div class="form-group">

                                <div class="row mt-15">

                                    <div class="col-md-12">
                                        @include('admin.custom_form_feedbacks.edit_fields.select_patient')
                                    </div>

                                </div>

                                <div class="form-group form-md-line-input cf_main_title">
                                <div class="form-group form-md-line-input cf_input_question">
                                    <h2 class="rs-head">{{$custom_form->name}}</h2>
                                </div>
                            </div>
                            <input id="form_id" name="form_id" type="hidden" value="{{$custom_form->id}}"/>


                            <div class="form-group form-md-line-input cf_main_title mt-10">
                                <div class="form-group form-md-line-input cf_input_question">
                                    <p>{{$custom_form->description}}</p>
                                </div>
                            </div>



                            <div class="row mt-15">

                        @if ($custom_form)
                            @foreach($custom_form->form_fields as $field)
                                <?php $content = \App\Helpers\CustomFormHelper::getContentArray($field->content); ?>

                                @if($field->field_type ==1)
                                <div class="col-md-12">
                                    @include("admin.custom_form_feedbacks.fields.text_field", ['field_id'=>$field->id, 'title'=>$content["title"],"index"=>$loop->index])
                                </div>
                                @elseif($field->field_type ==2)
                                <div class="col-md-12">
                                    @include("admin.custom_form_feedbacks.fields.paragraph_field", ['field_id'=>$field->id, 'title'=>$content["title"],"index"=>$loop->index])
                                </div>
                                @elseif($field->field_type ==3)
                                <div class="col-md-12">
                                    @include("admin.custom_form_feedbacks.fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                                </div>
                                @elseif($field->field_type ==4 && is_array($content))
                                <div class="col-md-12">
                                    @include("admin.custom_form_feedbacks.fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                                </div>
                                @elseif($field->field_type ==5 && is_array($content))
                                <div class="col-md-12">
                                    @include("admin.custom_form_feedbacks.fields.option_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"index"=>$loop->index])
                                </div>
                                @elseif($field->field_type ==6 && is_array($content))
                                <div class="col-md-12">
                                    @include("admin.custom_form_feedbacks.fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"]])
                                </div>
                                @elseif($field->field_type ==7 && is_array($content))
                                <div class="col-md-12">
                                    @include("admin.custom_form_feedbacks.fields.table_input_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"],"rows"=>$content["rows"],"index"=>$loop->index])
                                </div>
                                @endif
                            @endforeach
                        @endif
                    </div>

                            <div class="margin-top-10">
                                <button class="btn btn-primary">Save Changes</button>
                                <a href="{{route("admin.custom_forms.index")}}" class="btn btn-danger">Cancel </a>
                            </div>
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


    <script type="text/javascript">

        $(function () {


             $("#cf_form").validate();

            $("#cf_form").submit(function(e){

                 if($(".patient_id").val() == null || $(".patient_id").val() == '') {


                    $(".patient_id").parent('.form-group').append('<label id="reference_id-error" class="error" for="reference_id">This field is required.</label>');
                    $(".patient_id").focus();
                    return false;
                }

                if($("#cf_form").valid()) {
                    e.preventDefault();

                    data = {};
                    fields = document.querySelectorAll("#cf_field_list> .cf_field_item");
                    data['reference_id'] = document.querySelector("select[name='{{\App\Helpers\CustomFormFeedbackHelper::DEFAULT_SELECT_PATIENT_NAME}}']").value;
                    for (let i = 0; i < fields.length; i++) {
                        field_type = fields[i].querySelector("input#field_type").value;
                        field_id = fields[i].id.split("cs_field_")[1];

                        if (field_type == "{{config("constants.custom_form.field_types.text")}}") {
                            text_answer = fields[i].querySelector("input.answer").value;
                            data[field_id] = text_answer;
                        } else if (field_type == "{{config("constants.custom_form.field_types.paragraph")}}") {
                            text_answer = fields[i].querySelector("textarea.answer").value;
                            data[field_id] = text_answer;
                        } else if (field_type == "{{config("constants.custom_form.field_types.single")}}")
                        {
                            radio_answer = fields[i].querySelector("input.field_option:checked");
                            if (radio_answer) {
                                radio_answer = radio_answer.value;
                            } else {
                                radio_answer = "";
                            }
                            data[field_id] = radio_answer;
                        }
                        else if (field_type == "{{config("constants.custom_form.field_types.multiple")}}")
                        {
                            checkbox = fields[i].querySelectorAll("input.field_option:checked")

                            if (checkbox.length) {
                                checkbox_answer = [];
                                for (let i = 0; i < checkbox.length; i++) {
                                    checkbox_answer[i] = checkbox[i].value;
                                }
                                data[field_id] = JSON.stringify(checkbox_answer);
                            } else {
                                data[field_id] = "";
                            }

                        }
                        else if (field_type == "{{config("constants.custom_form.field_types.table_input")}}")
                        {
                            // options = fields[i].querySelectorAll("table thead th")
                            rows = fields[i].querySelectorAll("table tbody tr");
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
                                data[field_id] = JSON.stringify(row_data);
                            } else {
                                data[field_id] = "";
                            }

                        }
                        else if (field_type == "{{config("constants.custom_form.field_types.option")}}")
                        {
                            selected_value = fields[i].querySelector("select.field_option").value;
                            data[field_id] = selected_value;
                        }

                    }

                    fill_form(data, (response) => {
                        toastr.success("Form Submitted Successfully");
                        window.location.href = '{{route('admin.custom_forms.index')}}';

                    });
                } else {
                    toastr.error("fill form properly");
                }
            });


        });


        /**
         * saved filled form on server
         * @param data
         * @param success_callback
         * @param error_callback
         */

        function fill_form(data, success_callback, error_callback) {

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                url: route('admin.custom_form_feedbacks.submit_form', {
                    'form_id': $("input[type=hidden]#form_id").val(),
                }),
                type: 'POST',
                data: data,
                cache: false,
                success: success_callback,
                error: error_callback
            });
        }


    </script>


@endpush

