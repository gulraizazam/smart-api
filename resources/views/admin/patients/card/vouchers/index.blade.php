<div class="card-body page-vouchers-form">
    <!--begin: Datatable-->
    <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
    <!--end: Datatable-->

</div>

{{-- Include all modals from main vouchers module --}}
<div class="modal fade" id="modal_view_voucher" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" id="voucher_view">
        @include('admin.vouchers.view_modal')
    </div>
</div>

<div class="modal fade" id="modal_edit_voucher" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup medium_modal" id="voucher_edit">
        @include('admin.vouchers.edit')
    </div>
</div>

<div class="modal fade" id="modal_assign_voucher" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered form-popup" id="voucher_assign">
        @include('admin.vouchers.assign')
    </div>
</div>
