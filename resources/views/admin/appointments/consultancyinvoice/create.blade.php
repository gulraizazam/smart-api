<!--begin::Modal content-->
<div class="modal-content">
    <!--begin::Modal header-->
    <div class="modal-header" id="kt_modal_password_header">
        <!--begin::Modal title-->
        <h2 class="fw-bolder">{{ isset($patient) ? ucfirst($patient->name) : 'Generate Invoice' }}@if(isset($doctor) && $doctor) - Consultation with <span style="color: #3699FF;">{{ $doctor->name }}</span>@endif</h2>
        <!--end::Modal title-->
        <!--begin::Close-->
        <div class="btn btn-icon btn-sm btn-active-icon-primary popup-close" data-kt-users-modal-action="close">
            <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
            <span class="svg-icon svg-icon-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                    <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                </svg>
            </span>
            <!--end::Svg Icon-->
        </div>
        <!--end::Close-->
    </div>
    <!--end::Modal header-->
    <!--begin::Modal body-->
    <div class="modal-body scroll-y mx-5 mx-xl-15">
        
        @if($invoice_status != true)
        <table style="margin-top: 20px;">
            <tr>
                <td>
                    <img style="width: 235px; margin-bottom: 10px;" class="img-responsive logo" src="{{asset('assets/media/new_logo.png')}}" alt=""/>
                    <p class="logo_caption">{{$location_info->address ?? ''}}.</p>
                    <p class="logo_caption logo_caption2">Phone. {{$location_info->fdo_phone ?? ''}}  &nbsp; |  &nbsp; Email. {{$account->email ?? ''}}  &nbsp; | &nbsp;  www.alluraesthetics.pk  &nbsp; | &nbsp; NTN. {{$location_info->ntn ?? ''}} &nbsp; | &nbsp; STN. {{$location_info->stn ?? ''}}</p>
                </td>
                <td style="padding:0px !important; float:right; width:120px; text-align:right;">
                    <div class="invoice_btn" style="width:120px; float:right; text-align:right;">
                        <span>INVOICE</span>
                    </div>
                </td>
            </tr>
        </table>
        <table style="margin:19px 0px 30px;">
            <tr>
                <td class="main_heading">{{\Carbon\Carbon::now()->format('F j, Y')}}, {{\Carbon\Carbon::now()->format('h:i a')}}</td>
            </tr>
            <tr>
                <td class="main_heading">{{isset($patient) ? ucfirst($patient->name) : ''}}, <strong>C-{{$patient->id ?? ''}}</strong></td>
            </tr>
        </table>

        <!--begin::Form-->
        <div class="d-flex flex-column scroll-y me-n7 pe-7" id="kt_modal_appointment_type_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_add_user_header" data-kt-scroll-wrappers="#kt_modal_add_user_scroll" data-kt-scroll-offset="300px">

            <div class="form-group">
                @include('admin.appointments.consultancyinvoice.fields')
            </div>

        </div>
        <!--end::Scroll-->
        @else
            <h2>Invoice Already Paid</h2>
        @endif
        <!--end::Form-->
    </div>
    <!--end::Modal body-->
</div>
<!--end::Modal content-->




