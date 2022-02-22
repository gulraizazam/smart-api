<div class="card-body">
    <!--begin::Search Form-->
@include('admin.patients.card.custom_form_feedbacks.filters')
<!--end::Search Form-->

    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
    <!--end: Datatable-->
</div>

@push('datatable-js')
    <script src="{{asset('assets/js/pages/patients/appointments.js')}}"></script>
@endpush
