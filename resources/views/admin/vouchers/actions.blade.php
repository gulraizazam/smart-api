@if(Gate::allows('vouchers.detail.view'))
    <a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="viewVoucher({{ $voucher['id'] }});" data-toggle="modal" data-target="#modal_view_voucher">View</a>
@endif
@if(Gate::allows('vouchers.edit') && $voucher['can_edit'])
    <a class="btn btn-xs btn-info" href="javascript:void(0);" onclick="editVoucher({{ $voucher['id'] }});" data-toggle="modal" data-target="#modal_edit_voucher">@lang('global.app_edit')</a>
@endif
@if(Gate::allows('vouchers.destroy') && $voucher['can_delete'])
    <a class="btn btn-xs btn-danger" href="javascript:void(0);" onclick="deleteVoucher({{ $voucher['id'] }});">@lang('global.app_delete')</a>
@endif