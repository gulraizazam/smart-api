<div class="card-body page-finance-form">

    <div class="card-body">
        <table class="table table-bordered ">
            <thead>
            <tr role="row" class="heading">
                <th width="20%">Total Cash In</th>
                <th width="20%">Total Cash Out</th>
                <th width="20%">Balance</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                @php
                   list($total_cash_in, $total_cash_out, $balance) = getPatientInfo();
                @endphp
                <td><?php echo number_format($total_cash_in ?? 0); ?></td>
                <td><?php echo number_format($total_cash_out ?? 0); ?></td>
                <td><?php echo number_format($balance ?? 0); ?></td>
            </tr>

            </tbody>
        </table>
    </div>

    <div class="clearfix"></div>

    <div class="card-body">
        <!--begin::Search Form-->
        @include('admin.patients.card.packagesadvances.filters')
        <!--end::Search Form-->

        <!--begin: Datatable-->
        <div class="datatable datatable-bordered datatable-head-custom finance-form"></div>
        <!--end: Datatable-->
    </div>

    <div class="modal fade" id="modal_add_finance_form" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered big-modal" id="add_finance_form">

            @include('admin.patients.card.packagesadvances.create')

        </div>
        <!--end::Modal dialog-->
    </div>

</div>
