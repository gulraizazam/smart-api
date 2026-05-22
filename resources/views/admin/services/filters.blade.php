<div class="d-flex align-items-center flex-wrap" style="gap: 5px;">
    <input type="text" class="form-control form-control-sm" placeholder="Search by name..." id="search_name" style="max-width: 200px; flex: 1 1 140px;">
    @if(\Illuminate\Support\Facades\Gate::allows("services.list.view_inactive"))
        <select class="form-control form-control-sm" id="search_status" style="max-width: 120px; flex: 0 1 120px;">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    @endif
    <button class="btn btn-sm btn-primary py-1 px-3" id="apply-filters"><i class="la la-search p-0"></i></button>
    <button class="btn btn-sm btn-light py-1 px-3" id="reset-filters" type="button" title="Reset"><i class="la la-undo p-0"></i></button>
</div>
