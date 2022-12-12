@extends('admin.layouts.master')

@section('content')

    @push('css')
        <style>
            @media print {
                input[type=checkbox], input[type=radio] {
                    z-index: 99 !important;
                    opacity: 1 !important;
                }


                #kt_header,
                #kt_header_mobile_topbar_toggle,
                #kt_subheader,
                .card-header,
                .footer,
                .topbar {
                    display: none !important;
                }
            }
        </style>
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

                            <a href="{{route('admin.patients.preview', $patientId)}}" class="btn btn-sm btn-dark ">
                                <i class="las la-arrow-left"></i>
                                Back
                            </a>
                            &nbsp;&nbsp;&nbsp;

                            @if(Gate::allows('custom_form_feedbacks_manage') && Gate::allows('patients_customform_manage'))
                                <a href="{{ route('admin.patient_custom_form_feedbacks.export_pdf',$thisId) }}" class="btn btn-sm btn-primary">
                                    <i class="las la-file-pdf"></i>
                                    PDF
                                </a>
                            @endif
                            &nbsp;&nbsp;&nbsp;
                            @if(Gate::allows('custom_form_feedbacks_manage') && Gate::allows('patients_customform_manage'))
                                <a href="javascript:print();" class="btn btn-sm btn-primary">
                                    <i class="las la-print"></i>
                                    Print
                                </a>
                        @endif


                        <!--end::Button-->
                        </div>
                    </div>

                    <div class="card-body">

                        <div class="form-group">

                            <div class="row">

                                <div class="col-md-6">
                                    <img class="w-50" src="{{asset('assets/media/logos/logo_final.png')}}">
                                    <div class="mt-15">
                                        <h1>Patient Detail</h1>
                                        <p> <strong>Patient Name: </strong> {{$custom_form->patient?$custom_form->patient->name : "Null"}}</p>
                                        <p> <strong>Patient ID: </strong> {{$custom_form->patient?' C-'.$custom_form->patient->id : ""}}</p>
                                        <p>  <strong>Email: </strong> {{$custom_form->patient?$custom_form->patient->email : ""}}</p>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h2 id="custom_form_id">#{{ $thisId }} / {{ Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $custom_form->created_at)->format('Y-M-d') }}</h2>
                                    <h1 class="mt-30">Company Detail</h1>
                                    <p> <strong>Company Name: </strong> {{ Auth::user()->account->name }}</p>
                                    <p> <strong>Contact: </strong> {{ Auth::user()->account->contact }}</p>
                                    <p>  <strong>Email: </strong> {{ Auth::user()->account->email }}</p>
                                </div>


                                <div class="col-md-8 text-center mt-10">
                                    <h1 >{{$custom_form->form_name ?? ''}}</h1>
                                    <p>{{$custom_form->form_description ?? ''}}</p>
                                </div>

                            </div>


                            <div class="row mt-15">
                                @foreach($custom_form->form_fields as $field)

                                    <?php $content = \App\Helpers\CustomFormHelper::getContentArray($field->content); ?>


                                    @if($field->field_type ==1)
                                        <div class="col-md-6">
                                            @include("admin.custom_form_feedbacks.preview_fields.text_field_preview", ['field_id'=>$field->id, 'title'=>$content["title"],"value" => $field->field_value])
                                        </div>
                                    @elseif($field->field_type ==2)
                                        <div class="col-md-6">
                                            @include("admin.custom_form_feedbacks.preview_fields.text_field_preview", ['field_id'=>$field->id, 'title'=>$content["title"], "value" => $field->field_value])
                                        </div>
                                    @elseif($field->field_type ==3)
                                        <div class="col-md-6">
                                            @include("admin.custom_form_feedbacks.preview_fields.single_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])</td>
                                        </div>
                                    @elseif($field->field_type ==4 && is_array($content))
                                        <div class="col-md-6">
                                            @include("admin.custom_form_feedbacks.preview_fields.multi_select_field", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])</td>
                                        </div>
                                    @elseif($field->field_type ==5 && is_array($content))
                                        <div class="col-md-6">
                                            @include("admin.custom_form_feedbacks.preview_fields.text_field_preview", ["field_id"=>$field->id, 'title'=>$content["title"],"options"=>$content["options"], "value" => $field->field_value])</td>
                                        </div>
                                    @elseif($field->field_type ==6 && is_array($content))
                                        <div class="col-md-6">
                                            @include("admin.custom_form_feedbacks.preview_fields.title_description_field", ["field_id"=>$field->id, 'title'=>$content["title"], "value" => $field->field_value])</td>
                                        </div>

                                    @elseif($field->field_type ==7 && is_array($content))
                                    @endif
                                @endforeach

                            </div>


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


@endsection
