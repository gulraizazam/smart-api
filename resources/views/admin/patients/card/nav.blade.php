<!--begin::Nav-->
<div class="navi navi-bold navi-hover navi-active navi-link-rounded mt-10">

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
            <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'appointments');" class="change-tab navi-link py-4">
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
        <div class="navi-item mb-2">
            <a href="javascript:void(0)" onclick="changeProfilePage($(this), 'plan-form');" class="change-tab plan-form-tab navi-link py-4">
                <span class="navi-icon mr-2">
                     <i class="la la-paper-plane-o"></i>
                </span>
                <span class="navi-text">Plans</span>
            </a>
        </div>
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
        <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'plan-refunds');" class="change-tab navi-link py-4">
            <span class="navi-icon mr-2">
                 <i class="la la-eject"></i>
            </span>
            <span class="navi-text">Plans Refunds</span>
        </a>
    </div>
    @endcan

    @can("patients_refund_manage")
        <div class="navi-item mb-2">
            <a href="javascript:void(0)"  onclick="changeProfilePage($(this), 'no-plan-refunds');" class="change-tab navi-link py-4">
                <span class="navi-icon mr-2">
                     <i class="la la-eject"></i>
                </span>
                <span class="navi-text">Non Plans Refunds</span>
            </a>
        </div>
    @endcan

</div>
<!--end::Nav-->
