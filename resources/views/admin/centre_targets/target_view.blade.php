<style>
    .has-y-scroll {
        min-height: inherit;
        max-height: 500px;
        overflow-y: auto;
    }
</style>
<div class="modal-body">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
        <h4 class="modal-title">@lang('global.app_detail')</h4>
    </div>
    <table class="table table-striped">
        <tbody>
        <tr>
            <th width="20%">Month</th>
            <td>{{ $centertarget->month }}</td>
            <th width="20%">Year</th>
            <td>{{ $centertarget->year }}</td>
        </tr>
        </tbody>
    </table>
    <div class="has-y-scroll">
        <table class="table table-striped table-bordered table-advance table-hover ">
            <thead>
            <tr>
                <th>Sr#</th>
                <th>Location</th>
                <th>Target Amount</th>
            </tr>
            </thead>
            <tbody>
            @if($centertarget->center_target_meta)
                @php($count = 0)
                @foreach($centertarget->center_target_meta as $center_target_meta)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td>{{ $center_target_meta->location->name }}</td>
                        <td align="right">{{ number_format($center_target_meta->target_amount) }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="3">
                        No record found.
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
</div>