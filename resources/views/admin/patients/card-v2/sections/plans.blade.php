{{-- Plans Section --}}
<div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Plans</h4>
        <div>
            @can('plans_create')
                <a href="javascript:void(0);" onclick="createPlan('{{ route('admin.packages.create') }}');" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal_add_plan">
                    <i class="la la-plus"></i> Add Procedures
                </a>
                <a href="javascript:void(0);" onclick="createBundle('{{ route('admin.packages.create') }}');" class="btn btn-success btn-sm ml-2" data-toggle="modal" data-target="#modal_add_bundle">
                    <i class="la la-plus"></i> Add Bundle
                </a>
                <a href="javascript:void(0);" onclick="createMembershipForPatient({{ $patient->id }});" class="btn btn-warning btn-sm ml-2" data-toggle="modal" data-target="#modal_add_membership">
                    <i class="la la-plus"></i> Add Membership
                </a>
            @endcan
        </div>
    </div>
    
    {{-- Datatable - uses same ID as main module for shared JS --}}
    <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
</div>

{{-- Include ALL modals from main plans module for true globalization --}}
@include('admin.packages.modals', ['isPatientCard' => true])
