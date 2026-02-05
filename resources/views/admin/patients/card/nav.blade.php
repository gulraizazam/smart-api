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
            <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'consultation-form');" class="change-tab consultation-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                    <i class="la la-user-md"></i>
                </span>
                <span class="navi-text font-size-lg">Consultations</span>
            </a>
        </div>
    @endcan
    @can('patients_appointment_manage')
        <div class="navi-item mb-2">
            <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'treatment-form');" class="change-tab treatment-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                    <i class="la la-stethoscope"></i>
                </span>
                <span class="navi-text font-size-lg">Treatments</span>
            </a>
        </div>
    @endcan
    @can('patients_appointment_manage')
        <div class="navi-item mb-2">
            <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'vouchers-form');" class="change-tab vouchers-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                    <i class="la la-clock"></i>
                </span>
                <span class="navi-text font-size-lg">Vouchers</span>
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
<style>
    .sticky-nav {
        position: sticky;
        top: 65px;
        z-index: 100;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
</style>
<div class="card mb-8 menu_section sticky-nav" style="width: 100%">
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
            <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'consultation-form');" class="change-tab consultation-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-user-md"></i>
                        </span>
                        <p class="navi-text">Consultations <span id="tab-count-consultations"></span></p>
                    </a>
                </li>
            <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'treatment-form');" class="change-tab treatment-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-stethoscope"></i>
                        </span>
                        <p class="navi-text">Treatments <span id="tab-count-treatments"></span></p>
                    </a>
                </li>
            <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'voucher-form');" class="change-tab voucher-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-gift"></i>
                        </span>
                        <p class="navi-text">Vouchers <span id="tab-count-vouchers"></span></p>
                    </a>
                </li>
            <!-- @can("patients_customform_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'custom-form');" class="change-tab custom-form-tab navi-link py-4">

                        <span class="text-muted mb-2 fa_icon">
                            <i class="la la-file-text-o"></i>
                        </span>
                        <p class="navi-text font-size-lg">Custom Form Feedbacks</p>

                    </a>
                </li>
            @endcan -->
            <!-- @can("appointments_medical_form_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'medical-form');" class="change-tab medical-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                           <i class="la la-medkit"></i>
                        </span>
                        <p class="navi-text">Medical History Form</p>
                    </a>
                </li>
            @endcan -->
            <!-- @can("appointments_measurement_manage")
                <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'measurement-form');" class="change-tab measurement-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                             <i class="la la-stethoscope"></i>
                        </span>
                        <p class="navi-text">Measurement History Form</p>
                    </a>
                </li>
            @endcan -->
            <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0);" onclick="changeProfilePage($(this), 'document-form');" class="change-tab document-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                            <i class="la la-file-archive-o"></i>
                        </span>
                        <p class="navi-text">Documents <span id="tab-count-documents"></span></p>
                    </a>
                </li>
            <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'plan-form');" class="change-tab plan-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                             <i class="la la-paper-plane-o"></i>
                        </span>
                        <p class="navi-text">Plans <span id="tab-count-plans"></span></p>
                    </a>
                </li>
            <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0);"  onclick="changeProfilePage($(this), 'invoice-form');" class="change-tab invoice-form-tab navi-link py-4">
                         <span class="text-muted mb-2 fa_icon">
                             <i class="la la-file-invoice"></i>
                        </span>
                        <p class="navi-text">Invoices <span id="tab-count-invoices"></span></p>
                    </a>
                </li>
            <li class="horizontal-nav-bar-li">
                    <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'refund-form');" class="change-tab refund-form-tab navi-link py-4">
                        <span class="text-muted mb-2 fa_icon">
                             <i class="la la-eject"></i>
                        </span>
                        <p class="navi-text">Refunds <span id="tab-count-refunds"></span></p>
                    </a>
                </li>
            <li class="horizontal-nav-bar-li">
                <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'history-form');" class="change-tab history-form-tab navi-link py-4">
                    <span class="text-muted mb-2 fa_icon">
                         <i class="la la-history"></i>
                    </span>
                    <p class="navi-text">Activity Logs <span id="tab-count-activity"></span></p>
                </a>
            </li>
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
