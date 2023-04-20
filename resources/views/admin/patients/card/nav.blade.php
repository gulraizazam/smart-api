<!--begin::Nav-->
{{--<div class="navi navi-bold navi-hover navi-active navi-link-rounded mt-10">

    <div class="navi-item mb-2">
        <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'personal_info');" class="change-tab personal-info navi-link py-4 active">
        <span class="navi-icon mr-2">
           <i class="la la-user-alt"></i>
        </span>
            <span class="navi-text font-size-lg">Profile</span>
        </a>
    </div>

    @can('patients_appointment_manage')
        <div class="navi-item mb-2">
            <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'appointment-form');" class="change-tab appointment-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                    <i class="la la-clock"></i>
                </span>
                <span class="navi-text font-size-lg">Appointments</span>
            </a>
        </div>
    @endcan

    @can("patients_customform_manage")
        <div class="navi-item mb-2">
            <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'custom-form');" class="change-tab custom-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                    <i class="la la-file-text-o"></i>
                </span>
                <span class="navi-text font-size-lg">Custom Form Feedbacks</span>
            </a>
        </div>
    @endcan

    @can("appointments_medical_form_manage")
        <div class="navi-item mb-2">
            <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'medical-form');" class="change-tab medical-form-tab navi-link py-4">
            <span class="navi-icon mr-2">
               <i class="la la-medkit"></i>
            </span>
                <span class="navi-text font-size-lg">Medical History Form</span>
            </a>
        </div>
    @endcan

    @can("appointments_measurement_manage")
        <div class="navi-item mb-2">
            <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'measurement-form');" class="change-tab measurement-form-tab navi-link py-4">
            <span class="navi-icon mr-2">
                 <i class="la la-stethoscope"></i>
            </span>
                <span class="navi-text font-size-lg">Measurement History Form</span>
            </a>
        </div>
    @endcan

    @can("patients_document_manage")
        <div class="navi-item mb-2">
            <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'document-form');" class="change-tab document-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                    <i class="la la-file-archive-o"></i>
                </span>
                <span class="navi-text font-size-lg">Documents</span>
            </a>
        </div>
    @endcan

    @can("patients_plan_manage")
        <!--div class="navi-item mb-2">
            <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'plan-form');" class="change-tab plan-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                     <i class="la la-paper-plane-o"></i>
                </span>
                <span class="navi-text">Plans</span>
            </a>
        </div-->
    @endcan

    @can("patients_finance_manage")
        <div class="navi-item mb-2">
            <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'finance-form');" class="change-tab finance-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                     <i class="la la-money"></i>
                </span>
                <span class="navi-text">Finances</span>
            </a>
        </div>
    @endcan

    @can("patients_invoice_manage")
        <div class="navi-item mb-2">
            <a href="javascript:void(0);"  onclick="changeProfilePage($(this), 'invoice-form');" class="change-tab invoice-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                     <i class="la la-file-invoice"></i>
                </span>
                <span class="navi-text">Invoices</span>
            </a>
        </div>
    @endcan

    @can("patients_refund_manage")
    <div class="navi-item mb-2">
        <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'refund-form');" class="change-tab refund-form-tab navi-link py-4">
            <span class="navi-icon mr-2">
                 <i class="la la-eject"></i>
            </span>
            <span class="navi-text">Plans Refunds</span>
        </a>
    </div>
    @endcan

    <!-- @can("patients_refund_manage")
        <div class="navi-item mb-2">
            <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'no-plan-refund-form');" class="change-tab no-plan-refund-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                     <i class="la la-eject"></i>
                </span>
                <span class="navi-text">Non Plans Refunds</span>
            </a>
        </div>
    @endcan

</div>--}}
<!--end::Nav-->

{{--Menu--}}
<div class="card mb-8 menu_section" style="width: 100%">
    <div class="card-body menu-card">
        <ul class="horizontal-nav-bar list-unstyled mb-0">
            <li class="horizontal-nav-bar-li">
                <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'personal_info');" class="change-tab personal-info navi-link py-4 active">
                     <span class="text-muted mb-2 fa_icon">
                    <i class="la la-user-alt"></i>
                    </span>
                    <p class="navi-text ">Profile</p>
                </a>
            </li>
            @can('patients_appointment_manage')
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'appointment-form');" class="change-tab appointment-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-clock"></i>
                        </span>
                        <p class="navi-text">Appointments</p>
                    </a>
                </li>
            @endcan
            @can("patients_customform_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'custom-form');" class="change-tab custom-form-tab navi-link py-4">

                        <span class="text-muted mb-2 fa_icon">
                            <i class="la la-file-text-o"></i>
                        </span>
                        <p class="navi-text font-size-lg">Custom Form Feedbacks</p>

                    </a>
                </li>
            @endcan
            @can("appointments_medical_form_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'medical-form');" class="change-tab medical-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                           <i class="la la-medkit"></i>
                        </span>
                        <p class="navi-text">Medical History Form</p>
                    </a>
                </li>
            @endcan
            @can("appointments_measurement_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'measurement-form');" class="change-tab measurement-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                             <i class="la la-stethoscope"></i>
                        </span>
                        <p class="navi-text">Measurement History Form</p>
                    </a>
                </li>
            @endcan
            @can("patients_document_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'document-form');" class="change-tab document-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                            <i class="la la-file-archive-o"></i>
                        </span>
                        <p class="navi-text">Documents</p>
                    </a>
                </li>
            @endcan
            @can("patients_plan_manage")
                <!--li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'plan-form');" class="change-tab plan-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                             <i class="la la-paper-plane-o"></i>
                        </span>
                        <p class="navi-text">Plans</p>
                    </a>
                </li-->
            @endcan
            @can("patients_finance_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'finance-form');" class="change-tab finance-form-tab navi-link py-4">
                       <span class="text-muted mb-2 fa_icon">
                             <i class="la la-money"></i>
                        </span>
                        <p class="navi-text">Finances</p>
                    </a>
                </li>
            @endcan
            @can("patients_invoice_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0);"  onclick="changeProfilePage($(this), 'invoice-form');" class="change-tab invoice-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-file-invoice"></i>
                        </span>
                        <p class="navi-text">Invoices</p>
                    </a>
                </li>
            @endcan
            @can("patients_refund_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'refund-form');" class="change-tab refund-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                             <i class="la la-eject"></i>
                        </span>
                        <p class="navi-text">Plans Refunds</p>
                    </a>
                </li>
            @endcan
            <!-- @can("patients_refund_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'no-plan-refund-form');" class="change-tab no-plan-refund-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                             <i class="la la-eject"></i>
                        </span>
                        <p class="navi-text">Non Plans Refunds</p>
                    </a>
                </li>
            @endcan -->
        </ul>
    </div>
</div>
{{--End Menu--}}
