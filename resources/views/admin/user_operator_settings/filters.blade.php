@php
    $display = 'none;';
    $advance_class = 'fa-caret-right';
if(isset($filters)) {
        if(hasFilter($filters, 'name')
            || hasFilter($filters, 'data')) {
            $display = 'block;';
            $advance_class = 'fa-caret-down';
        }
    }
@endphp

<div class="mt-2 mb-7">

    <div class="row align-items-center">

        <div class="col-lg-12 col-xl-12">
            <div class="row align-items-center">
                <div class="col-md-9">
                    <div class="input-icon">
                        <input type="text" value="{{$filters['operator_name'] ?? ''}}" class="form-control filter-field" placeholder="Operator" id="operator_name" />
                        <span>
                            <i class="flaticon2-search-1 text-muted"></i>
                        </span>
                    </div>
                </div>
                <div class="col-md-3">

                    @include('admin.partials.filter-buttons')
                </div>
            </div>
        </div>

    </div>

</div>
