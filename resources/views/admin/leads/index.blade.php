@extends('admin.layouts.master')
@section('title', 'Leads')
@section('content')

    <!--begin::Content-->
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">

    @include('admin.partials.breadcrumb', ['module' => 'Leads List', 'title' => 'Leads'])

    <!--begin::Entry-->
        <div class="d-flex flex-column-fluid">
            <!--begin::Container-->
            <div class="container">

                <!--begin::Card-->
                <div class="card card-custom">
                    <div class="card-header py-3">
                        <div class="card-title">
                            <span class="card-icon">
                                <span class="svg-icon svg-icon-md svg-icon-primary">
                                    <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Chart-bar1.svg-->
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                            <rect x="0" y="0" width="24" height="24" />
                                            <rect fill="#000000" opacity="0.3" x="12" y="4" width="3" height="13" rx="1.5" />
                                            <rect fill="#000000" opacity="0.3" x="7" y="9" width="3" height="8" rx="1.5" />
                                            <path d="M5,19 L20,19 C20.5522847,19 21,19.4477153 21,20 C21,20.5522847 20.5522847,21 20,21 L4,21 C3.44771525,21 3,20.5522847 3,20 L3,4 C3,3.44771525 3.44771525,3 4,3 C4.55228475,3 5,3.44771525 5,4 L5,19 Z" fill="#000000" fill-rule="nonzero" />
                                            <rect fill="#000000" opacity="0.3" x="17" y="11" width="3" height="6" rx="1.5" />
                                        </g>
                                    </svg>
                                    <!--end::Svg Icon-->
                                </span>
                            </span>
                            <h3 class="card-label">{{\Illuminate\Support\Str::title(request('type'))}} Leads</h3>

                        </div>

                        <div class="card-toolbar">
                            <!--begin::Dropdown-->
                            @if(Gate::allows('leads_destroy'))
                                <div class="delete-records d-none">
                                    <span>Selected Rows: <span class="checkbox-count"></span></span>
                                    <a id="delete-table-rows" href="javascript:void(0);" class="btn btn-danger font-weight-bolder">
                                        <i class="fa fa-trash-alt"></i>Delete
                                    </a>
                                </div>&nbsp;&nbsp;&nbsp;
                            @endif

                            @if(request('type') != 'junk')
                                @if(Gate::allows('leads_import'))
                                    <a href="javascript:void(0);" data-toggle="modal" data-target="#modal_import_leads" class="btn btn-primary pull-right margin-r-5">
                                        <i class="fa fa-upload"></i>
                                        <span class="hidden-xs"> Import </span>
                                    </a>
                                @endif
                                &nbsp;&nbsp;
                                @if(Gate::allows('leads_export'))
                                    <a href="#" id="export-leads" data-href="{{route('admin.leads.export.excel')}}" class="btn btn-primary">
                                        <i class="fa fa-download"></i>
                                        <span class="hidden-xs"> Export </span>
                                    </a>
                                @endif
                                &nbsp;&nbsp;
                                @if(Gate::allows('leads_create'))
                                    <a href="javascript:void(0);" id="create_lead" onclick="createLead('{{ route('admin.leads.create') }}');" class="btn btn-primary" data-toggle="modal" data-target="#modal_add_leads">
                                        <i class="la la-plus"></i>
                                        Add New
                                    </a>
                                @endif
                            @endif

                        <!--end::Button-->
                        </div>

                    </div>

                    <div class="card-body">
                        <!--begin::Search Form-->
                        @include('admin.leads.filters')
                        <!--end::Search Form-->

                        <!--begin: Datatable-->
                        <div class="datatable datatable-bordered datatable-head-custom" id="kt_datatable"></div>
                        <!--end: Datatable-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Container-->
        </div>
        <!--end::Entry-->
    </div>
    <!--end::Content-->

    <div class="modal fade" id="modal_change_status" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="leads_change_status">

            @include('admin.leads.change-status')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_view_lead" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mediam-modal" id="leads_view_lead">

            @include('admin.leads.view')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_convert_lead" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered mediam-modal" id="convert_lead">

            @include('admin.leads.convert')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_add_leads" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="leads_add">

            @include('admin.leads.create')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_edit_leads" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="edit_leads">

            @include('admin.leads.edit')

        </div>
        <!--end::Modal dialog-->
    </div>

    <div class="modal fade" id="modal_import_leads" tabindex="-1" aria-hidden="true">
        <!--begin::Modal dialog-->
        <div class="modal-dialog modal-dialog-centered form-popup" id="import_leads">

            @include('admin.leads.import')

        </div>
        <!--end::Modal dialog-->
    </div>

    @push('js')
        <script src="{{asset('assets/js/jquery.inputmask.bundle.min.js')}}"></script>
        <script src="{{asset('assets/js/jquery.copy-to-clipboard.js')}}"></script>

        <script src="{{asset('assets/js/pages/crud/forms/validation/leads/leads.js')}}"></script>
        <script src="{{asset('assets/js/search-phone.js')}}"></script>
    @endpush

    @push('datatable-js')
        <script>

            let lead_type = '{{request('type')}}';
            let junk = '{{config('constants.lead_status_junk')}}';

            var limit = '{{config('constants.export-lead-excel-limit')}}';
            var offset = 0;

            var pdf_limit = '{{config('constants.export-lead-pdf-limit')}}';
            var pdf_offset = 0;

            $(document).ready(function () {
                //$("#export-leads").attr('href', route('admin.leads.export.excel', [limit, offset]));

                //$("#export-pdf-leads").attr('href', route('admin.leads.export.pdf', [pdf_limit, pdf_offset]));
            });

            function setExportLimit($this) {

                let previousLimit = limit;
                let next = {{config('constants.export-lead-excel-limit')}};
                limit = parseInt(limit) + parseInt(next);
                offset = parseInt(offset) + parseInt(next);

                setTimeout( function () {
                    $this.attr('href', route('admin.leads.export.excel', [limit, offset]));

                    $(".export-excel-limit").text("("+previousLimit+" to "+limit+")")
                },1000);
            }

            function setPdfLimit($this) {

                let pdf_previousLimit = pdf_limit;
                let next = {{config('constants.export-lead-pdf-limit')}};

                pdf_limit = parseInt(pdf_limit) + parseInt(next);
                pdf_offset = parseInt(pdf_offset) + parseInt(next);

                setTimeout( function () {
                    //$this.attr('href', route('admin.leads.export.pdf', [pdf_limit, pdf_offset]));

                    $(".export-pdf-limit").text("("+pdf_previousLimit+" to "+pdf_limit+")")
                },1000);
            }
        </script>
        <script src="{{asset('assets/js/pages/leads/leads.js')}}"></script>

        <script>
            jQuery(document).ready( function () {
                @if(request('create') != '' && request('create') !== null)
                    $("#create_lead").click()
                @endif
                @if(request('from') != '' && request('to') != '')
                    setTimeout( function () {
                        $("#date_range").val("{{request('from')}}");
                        //$("#search_created_from").val("{{request('from')}}");
                        //$("#search_created_to").val("{{request('to')}}");
                        $("#apply-filters").click();

                    }, 800);
                @endif
            });
            function getUserCity() {
                <?php if(auth()->id() != 1): ?>
                $.ajax({
                    url: '<?php echo e(route('admin.users.get_cities')); ?>',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status) {
                            $("#search_city_id").val(response.data.city).change();
                            $("#add_city_id").val(response.data.city).change();
                        }
                    },
                    error: function () {

                    }
                });

                <?php endif; ?>

            }
            function loadLocation() {
              var cityId = $('#add_city_id').val();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.appointments.load_locations'),
                    type: 'POST',
                    data: {
                        city_id: cityId
                    },
                    cache: false,
                    success: function(response) {
                        if(response.status) {
                            let dropdowns =  response.data.dropdown;
                            let dropdown_options =  '<option selected="selected" disabled value="">Select a Location</option>';
                            Object.entries(dropdowns).forEach(function (dropdown) {
                                dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                            });
                            $('#add_location_id').html(dropdown_options);
                        } else {
                            resetDropdowns();
                        }
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        resetDropdowns();
                    }
                });
            }
            function loadEditLocation() {
              var cityId = $('#edit_city_id').val();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.appointments.load_locations'),
                    type: 'POST',
                    data: {
                        city_id: cityId
                    },
                    cache: false,
                    success: function(response) {
                        if(response.status) {
                            let dropdowns =  response.data.dropdown;
                            let dropdown_options =  '<option selected="selected" disabled value="">Select a Location</option>';
                            Object.entries(dropdowns).forEach(function (dropdown) {
                                dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                            });
                            $('#edit_location_id').html(dropdown_options);
                        } else {
                            resetDropdowns();
                        }
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        resetDropdowns();
                    }
                });
            }
            function loadChildServices(){
                var serviceId = $('#add_service_id').val();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.leads.load_child_services'),
                    type: 'POST',
                    data: {
                        serviceId: serviceId
                    },
                    cache: false,
                    success: function(response) {
                        if(response.status) {
                            let dropdowns =  response.data.dropdown;
                            let dropdown_options =  '<option selected="selected" disabled value="">Select a Service</option>';
                            Object.entries(dropdowns).forEach(function (dropdown) {
                                dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';
                            });
                            $('#add_child_service_id').html(dropdown_options);
                        } else {
                            resetDropdowns();
                        }
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        resetDropdowns();
                    }
                });
            }
            function loadEditChildServices(){
                var serviceId = $('#edit_service_id').val();
                var leadId = $('#edit_lead_id').val();
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: route('admin.leads.load_child_services'),
                    type: 'POST',
                    data: {
                        serviceId: serviceId,
                        leadId: leadId,
                    },
                    cache: false,
                    success: function(response) {
                        if(response.status) {
                            let dropdowns =  response.data.dropdown;
                            let old_child_service = response.data.lead_child_service;
             
                            let dropdown_options = '<option value="">Select Service</option>';
                            Object.entries(dropdowns).forEach(function (dropdown) {
                                dropdown_options += '<option value="'+dropdown[0]+'">'+dropdown[1]+'</option>';

                            });

                            $('#edit_child_service_id').html(dropdown_options);
                        } else {
                            resetDropdowns();
                        }
                    },
                    error: function (xhr, ajaxOptions, thrownError) {
                        resetDropdowns();
                    }
                });
            }

            // Lead Search for Filter
            let leadSearchDebounceTimer;
            $('.lead_search_filter').on('keyup', function() {
                let searchValue = $(this).val();
                
                // Clear previous timer and hide suggestions
                clearTimeout(leadSearchDebounceTimer);
                $('.suggesstion-box-leads').hide();
                
                if (searchValue.length < 1) {
                    return false;
                }
                
                // Show searching indicator
                setTimeout(function() {
                    if ($('.lead_search_filter').val() === searchValue) {
                        $('.suggestion-list-leads').html('<li style="padding: 10px;">Searching...</li>');
                        $('.suggesstion-box-leads').show();
                    }
                }, 200);
                
                leadSearchDebounceTimer = setTimeout(function() {
                    if ($('.lead_search_filter').val() === searchValue) {
                        $.ajax({
                            type: 'GET',
                            url: route('admin.leads.getlead.id'),
                            dataType: 'json',
                            data: { search: searchValue },
                            success: function(response) {
                                if ($('.lead_search_filter').val() !== searchValue) {
                                    return;
                                }
                                
                                let html = '';
                                let leads = response.data.leads;
                                
                                if (leads.length) {
                                    leads.forEach(function(lead) {
                                        html += '<li onclick="selectLeadFilter(\'' + lead.id + '\', \'' + lead.name + '\', \'' + lead.phone + '\');" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #eee;">' + lead.name + ' - ' + lead.phone + '</li>';
                                    });
                                    $('.suggestion-list-leads').html(html);
                                    $('.suggesstion-box-leads').show();
                                } else {
                                    $('.suggesstion-box-leads').hide();
                                }
                            }
                        });
                    }
                }, 400);
            });

            // Select lead from suggestions
            function selectLeadFilter(id, name, phone) {
                $('#search_id').val(id);
                $('#search_full_name').val(name);
                $('#search_phone').val(phone);
                $('.lead_search_filter').val(name + ' - ' + phone);
                $('.suggesstion-box-leads').hide();
            }

            // Clear search on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.lead_search_filter, .suggesstion-box-leads').length) {
                    $('.suggesstion-box-leads').hide();
                }
            });
        </script>
    @endpush

@endsection
