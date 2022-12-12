<div class="card-body page-custom-form">
    <!--begin::Search Form-->
@include('admin.patients.card.custom_form_feedbacks.filters')
<!--end::Search Form-->

    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom custom-form"></div>
    <!--end: Datatable-->
</div>

<div class="modal fade" id="modal_add_custom_form" tabindex="-1" aria-hidden="true">
    <!--begin::Modal dialog-->
    <div class="modal-dialog modal-dialog-centered form-popup" id="custom_form">

        @include('admin.patients.card.custom_form_feedbacks.AddNewForms')

    </div>
    <!--end::Modal dialog-->
</div>


@push('js')
    <script src="{{asset('assets/js/pages/patients/create-custom-form.js')}}"></script>
@endpush
