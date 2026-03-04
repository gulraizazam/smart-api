@extends('admin.layouts.master')
@section('title', 'Patient')

@push('css')
<style>
    /* Hide tabs until JS is ready */
    .change-tab { cursor: pointer; }
</style>
@endpush

@section('content')
    {{-- Load patient-card-v2.js early so changeProfilePage is available --}}
    <script src="{{asset('assets/js/pages/patients/patient-card-v2.js')}}"></script>
    <script>
        // Patient Card V2 - Initialize context immediately
        var patientCardID = "{{request('id')}}";
        var patientCardPermissions = {
            edit: {{ Gate::allows('appointments_edit') ? 'true' : 'false' }},
            delete: {{ Gate::allows('appointments_delete') ? 'true' : 'false' }},
            status: {{ Gate::allows('appointments_status') ? 'true' : 'false' }},
            consultancy: {{ Gate::allows('consultancy_manage') ? 'true' : 'false' }},
            treatment: {{ Gate::allows('treatments_manage') ? 'true' : 'false' }},
            invoice: {{ Gate::allows('consultancy_invoice') ? 'true' : 'false' }},
            invoice_display: {{ Gate::allows('consultancy_invoice_display') ? 'true' : 'false' }},
            log: {{ Gate::allows('appointments_log') ? 'true' : 'false' }},
            image_manage: {{ Gate::allows('appointments_image_manage') ? 'true' : 'false' }},
            measurement_manage: {{ Gate::allows('appointments_measurement_manage') ? 'true' : 'false' }},
            medical_form_manage: {{ Gate::allows('appointments_medical_form_manage') ? 'true' : 'false' }},
            plans_create: {{ Gate::allows('plans_create') ? 'true' : 'false' }},
            patient_card: {{ Gate::allows('patient_card') ? 'true' : 'false' }},
            contact: {{ Gate::allows('contact') ? 'true' : 'false' }},
        };
        initPatientCardContext(patientCardID, patientCardPermissions);
    </script>
    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
            <!--begin::Subheader-->
        @include('admin.partials.breadcrumb', ['module' => 'Patients', 'title' => 'Patients'])
    <!--end::Subheader-->
        <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">
                @include('admin.patients.card.nav')
                
                {{-- Edit Patient Button - Positioned at top right, aligned with nav --}}
               
                
                <!--begin::Profile Change Password-->
                <div class="d-flex flex-row">
                    <!--begin::Aside-->
                    <div class="flex-row-auto offcanvas-mobile w-250px w-xxl-350px" id="kt_profile_aside">
                        <!--begin::Profile Card-->
                        <div class="card card-custom" style="height: fit-content;">
                            <!--begin::Body-->
                            <div class="card-body pt-4">
                                <!--begin::User-->
                                <div class="text-center mb-10">
                                    <div class="symbol symbol-60 symbol-circle symbol-xl-90">
                                        <div class="symbol-label" id="profile_patient_avatar" style="background-image:url('{{asset('assets/media/logos/avatar.jpg')}}')"></div>
                                        <i class="symbol-badge symbol-badge-bottom bg-success statuses" id="active-icon"></i>
                                        <i class="symbol-badge symbol-badge-bottom bg-danger d-none statuses" id="inactive-icon"></i>
                                    </div>
                                    <h4 class="font-weight-bold my-2" id="profile_patient_name"></h4>
                                    <div class="text-muted mb-2" id="profile_patient_id"></div>
                                    <span class="label label-light-success label-inline font-weight-bold label-lg d-none statuses" id="profile-active">Active</span>
                                    <span class="label label-light-danger label-inline font-weight-bold label-lg d-none statuses" id="profile-inactive">In-Active</span>
                                    <div class="mt-3" id="profile_membership_container" style="display: none;">
                                        <span class="label label-inline font-weight-bold label-lg" id="profile_membership"></span>
                                    </div>
                                </div>
                                <!--end::User-->
                                <!--begin::Nav bar-->
{{--                                    @include('admin.patients.card.nav')--}}
                                <!--end::Nav bar-->
                            </div>
                            <!--end::Body-->
                        </div>
                        <!--end::Profile Card-->
                    </div>
                    <!--end::Aside-->
                    <!--begin::Content-->
                    <div class="flex-row-fluid ml-lg-8 main-patient-section">
                        <!--begin::Card-->
                        <div class="card card-custom">
                            <!--begin::Header-->
                            <div class="card-header py-3">
                                <div class="card-title align-items-start flex-column">
                                    <h3 class="card-label font-weight-bolder text-dark" id="page_name">Profile</h3>
                                </div>
                                <div class="card-toolbar profile-buttons">
                                    <button type="button" class="btn btn-sm btn-default mr-2 change-tab persnl_info active" onclick="changeProfilePage($(this), 'personal_info');">Personal Info</button>
                                    <button type="button" class="btn btn-sm btn-default change-tab change_profile_pic" onclick="changeProfilePage($(this), 'change_profile_picture');">Change Profile Picture</button>
                                </div>
                                <div class="card-toolbar submit-btn toolbar-custom-form d-none">
                                    <button type="button" class="btn btn-sm btn-primary mr-2 change-tab" onclick="addCustomForm('{{ route('admin.customformfeedbackspatient.addnew', request('id')) }}');" data-toggle="modal" data-target="#modal_add_custom_form">
                                        Add New
                                    </button>
                                </div>
                                <div class="card-toolbar submit-btn toolbar-document-form d-none">
                                    <button type="button" class="btn btn-sm btn-primary mr-2 change-tab" onclick="addDocumentForm('{{ request('id') }}');" data-toggle="modal" data-target="#modal_add_document_form">
                                        Add New
                                    </button>
                                </div>
                                <div class="card-toolbar submit-btn toolbar-plan-form d-none">
                                    @if(Gate::allows('plans_create'))
                                        <button type="button" class="btn btn-sm btn-primary mr-2 change-tab" onclick="createPlan('{{ route('admin.packages.create') }}');" data-toggle="modal" data-target="#modal_add_plan">
                                            <i class="la la-plus"></i> Add Procedures
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary mr-2 change-tab" onclick="createBundle('{{ route('admin.packages.create') }}');" data-toggle="modal" data-target="#modal_add_bundle">
                                            <i class="la la-plus"></i> Add Bundle
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary mr-2 change-tab" onclick="createMembershipForPatient({{ request('id') }});" data-toggle="modal" data-target="#modal_add_membership">
                                            <i class="la la-plus"></i> Add Membership
                                        </button>
                                    @endif
                                </div>
                                <div class="card-toolbar submit-btn toolbar-finance-form d-none">
                                    <button type="button" class="btn btn-sm btn-primary mr-2 change-tab" onclick="createFinance('{{request('id')}}');" data-toggle="modal" data-target="#modal_add_finance_form">
                                        Add New
                                    </button>
                                </div>
                                <div class="card-toolbar submit-btn toolbar-voucher-form d-none">
                                    @if(Gate::allows('vouchers_create'))
                                        <button type="button" class="btn btn-sm btn-primary mr-2 change-tab" onclick="assignVoucherToPatient();" data-toggle="modal" data-target="#modal_assign_voucher_patient">
                                            <i class="la la-plus"></i> Add Voucher
                                        </button>
                                    @endif
                                </div>
                                <div class="card-toolbar submit-btn toolbar-consultation-form d-none">
                                    <button type="button" class="btn btn-sm btn-primary mr-2" id="create-consultation-btn">
                                        <i class="la la-plus"></i> Create Consultation
                                    </button>
                                </div>
                                <div class="card-toolbar submit-btn toolbar-treatment-form d-none">
                                    <button type="button" class="btn btn-sm btn-success mr-2" id="create-treatment-btn">
                                        <i class="la la-plus"></i> Create Treatment
                                    </button>
                                </div>
                            </div>
                            <!--end::Header-->
                            <!--begin::Form-->
                            <div class="card-body">
                                <!--begin::Alert-->
                                <div class="alert alert-custom alert-light-danger fade show mb-10 profile-message d-none" role="alert">
                                    <div class="alert-icon">
                                            <span class="svg-icon svg-icon-3x svg-icon-danger">
                                                <!--begin::Svg Icon | path:assets/media/svg/icons/Code/Info-circle.svg-->
                                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                        <rect x="0" y="0" width="24" height="24" />
                                                        <circle fill="#000000" opacity="0.3" cx="12" cy="12" r="10" />
                                                        <rect fill="#000000" x="11" y="10" width="2" height="7" rx="1" />
                                                        <rect fill="#000000" x="11" y="7" width="2" height="2" rx="1" />
                                                    </g>
                                                </svg>
                                                <!--end::Svg Icon-->
                                            </span>
                                    </div>
                                    <div class="alert-text font-weight-bold message-body"></div>
                                    <div class="alert-close">
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                <span aria-hidden="true">
                                                    <i class="ki ki-close"></i>
                                                </span>
                                        </button>
                                    </div>
                                </div>
                                <!--end::Alert-->


                                <div id="personal_info" class="content-section">
                                    @include('admin.patients.card.profile.personal-info')
                                    @include('admin.patients.card.profile.notes')
                                </div>

                                <div id="change_profile_picture" class="content-section d-none">
                                    @include('admin.patients.card.profile.profile-picture', ['customId' => 'profile-picture-search'])
                                </div>

                                <div id="consultation-form" class="content-section d-none">
                                    @include('admin.patients.card.consultations.index', ['customId' => 'consultation-form-search'])
                                </div>
                                <div id="treatment-form" class="content-section d-none">
                                    @include('admin.patients.card.treatments.index', ['customId' => 'treatment-form-search'])
                                </div>
                                <div id="voucher-form" class="content-section d-none">
                                    @include('admin.patients.card.vouchers.index', ['customId' => 'vouchers-form-search'])
                                </div>
                                <div id="custom-form" class="content-section d-none">
                                    @include('admin.patients.card.custom_form_feedbacks.index', ['customId' => 'custom-form-search'])
                                </div>

                                <div id="medical-form" class="content-section d-none">
                                    @include('admin.patients.card.medical.index', ['customId' => 'medical-search'])
                                </div>

                                <div id="measurement-form" class="content-section d-none">
                                    @include('admin.patients.card.measurement.index', ['customId' => 'measurement-search'])
                                </div>

                                <div id="document-form" class="content-section d-none">
                                    @include('admin.patients.card.documents.index', ['customId' => 'document-search'])
                                </div>

                                <div id="plan-form" class="content-section d-none">
                                    @include('admin.patients.card.plans.index', ['customId' => 'plan-search'])
                                </div>

                                <div id="invoice-form" class="content-section d-none">
                                    @include('admin.patients.card.invoices.index', ['customId' => 'invoice-search'])
                                </div>

                                <div id="refund-form" class="content-section d-none">
                                    @include('admin.patients.card.refunds.index', ['customId' => 'refund-search'])
                                </div>

                                <div id="history-form" class="content-section d-none">
                                    @include('admin.patients.card.history.index')
                                </div>


                            </div>

                            <!--end::Form-->
                        </div>
                    </div>
                    <!--end::Content-->
                </div>
                <!--end::Profile Change Password-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->
    @push('datatable-js')
        {{-- Shared scripts for consultations/treatments --}}
        <script src="{{asset('assets/js/pages/appointment/consultation-common.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/invoice.js')}}"></script>
        <script src="{{asset('assets/js/pages/appointment/common.js')}}"></script>
        {{-- Legacy scripts --}}
        <script src="{{asset('assets/js/pages/patients/history-form.js')}}"></script>
        <script src="{{asset('assets/js/profile.js')}}"></script>
        <script src="{{asset('assets/js/pages/crud/forms/validation/patients/patient-card-edit.js')}}"></script>
    @endpush

    {{-- Edit Patient Modal --}}
    <div class="modal fade" id="modal_edit_patients" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_patients">
            @include('admin.patients.edit')
        </div>
    </div>

    {{-- Assign Voucher Modal --}}
    <div class="modal fade" id="modal_assign_voucher_patient" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered form-popup" id="assign_voucher_patient">
            @include('admin.patients.assignVoucher')
        </div>
    </div>

    {{-- Include all appointment modals from main module (for consultations and treatments tabs) --}}
    @include('admin.appointments.appointment-forms.modals')

@endsection
