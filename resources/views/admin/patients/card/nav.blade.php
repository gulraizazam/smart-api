<ul class="nav">
    <li class="@if(request()->segment(4) == 'preview') {{ 'active' }} @endif">
        <a href="{{ route('admin.patients.preview',[$patient->id]) }}">
            <i class="icon-users"></i> Profile
        </a>
    </li>
    {{--@if(Gate::allows('leads_manage') || Gate::allows('leads_view'))--}}
        {{--<li class="@if(request()->segment(4) == 'leads') {{ 'active' }} @endif">--}}
            {{--<a href="{{ route('admin.patients.leads',[$patient->id]) }}">--}}
                {{--<i class="icon-briefcase"></i> Leads--}}
            {{--</a>--}}
        {{--</li>--}}
    {{--@endif--}}
    @if(Gate::allows('patients_appointment_manage'))
        <li class="@if(request()->segment(4) == 'appointments') {{ 'active' }} @endif">
            <a href="{{ route('admin.patients.appointments',[$patient->id]) }}">
                <i class="icon-clock"></i> Appointments
            </a>
        </li>
    @endif
    @if(Gate::allows('patients_customform_manage'))
        <li class="@if(request()->segment(4) == 'customformpatient' || request()->segment(2) == 'customformfeedbackspatient') {{ 'active' }} @endif">
            <a href="{{ route('admin.customformfeedbackspatient.index',[$patient->id]) }}">
                <i class="fa fa-file-text-o"></i>Custom Form Feedbacks
            </a>
        </li>
    @endif
    @if(Gate::allows('appointments_medical_form_manage'))
        <li class="@if(request()->segment(4) == 'customformpatient' || request()->segment(2) == 'medicalhistoryform') {{ 'active' }} @endif">
            <a href="{{ route('admin.medicalhistoryform.index',[$patient->id]) }}">
                <i class="fa fa-medkit"></i>Medical History Form
            </a>
        </li>
    @endif
    @if(Gate::allows('appointments_measurement_manage'))
        <li class="@if(request()->segment(4) == 'customformpatient' || request()->segment(2) == 'measurementhistoryform') {{ 'active' }} @endif">
            <a href="{{ route('admin.measurementhistoryform.index',[$patient->id]) }}">
                <i class="fa fa-stethoscope"></i>Measurement History Form
            </a>
        </li>
    @endif
    @if(Gate::allows('patients_document_manage') )
        <li class="@if(request()->segment(4) == 'document') {{ 'active' }} @endif">
            <a href="{{ route('admin.patients.document',[$patient->id]) }}">
                <i class="fa fa-file-archive-o"></i>@lang('global.documents.title')
            </a>
        </li>
    @endif
    @if(Gate::allows('patients_plan_manage') )
        <li class="@if(request()->segment(2) == 'plans') {{ 'active' }} @endif">
            <a href="{{ route('admin.plans.index',[$patient->id]) }}">
                <i class="fa fa-paper-plane-o"></i>@lang('global.packages.title')
            </a>
        </li>
    @endif
    @if(Gate::allows('patients_finance_manage') )
        <li class="@if(request()->segment(2) == 'finances') {{ 'active' }} @endif">
            <a href="{{ route('admin.finances.index',[$patient->id]) }}">
                <i class="fa fa-money"></i>@lang('global.packagesadvances.title')
            </a>
        </li>
    @endif
    @if(Gate::allows('patients_invoice_manage') )
        <li class="@if(request()->segment(2) == 'invoicepatient') {{ 'active' }} @endif">
            <a href="{{ route('admin.invoicepatient.index',[$patient->id]) }}">
                <i class="fa fa-rub"></i>@lang('global.invoices.title')
            </a>
        </li>
    @endif
    @if(Gate::allows('patients_refund_manage') )
        <li class="@if(request()->segment(2) == 'refundpatient') {{ 'active' }} @endif">
            <a href="{{ route('admin.refundpatient.index',[$patient->id]) }}">
                <i class="fa fa-eject"></i>@lang('global.refunds.plans_refunds')
            </a>
        </li>
    @endif
    @if(Gate::allows('patients_refund_manage') )
        <li class="@if(request()->segment(2) == 'nonplansrefundspatient') {{ 'active' }} @endif">
            <a href="{{ route('admin.nonplansrefundpatient.index',[$patient->id]) }}">
                <i class="fa fa-eject"></i>@lang('global.refunds.non_plans_refunds')
            </a>
        </li>
    @endif
</ul>