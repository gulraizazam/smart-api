<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;

class ViewTitleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        view()->composer('*', function ($view) {
            $title = '';
            // Set title based on URL path
            switch (Request::segment(2)) {
                case 'home':
                    $title = 'Dashboard';
                    break;
                case 'permissions':
                    $title = 'Permissions';
                    break;
                case 'roles':
                    $title = 'Roles';
                    break;
                case 'users':
                    $title = 'Users';
                    break;
                case 'user_types':
                    $title = 'User Types';
                    break;
                case 'patients':
                    $title = 'Patients';
                    break;
                case 'leads':
                    $title = 'Leads';
                    break;
                case 'consultancy':
                    $title = 'Consultaions';
                    break;
                case 'treatment':
                    $title = 'Treatments';
                    break;
                case 'packages':
                    $title = 'Plans';
                    break;
                case 'services':
                    $title = 'Services';
                    break;
                case 'bundles':
                    $title = 'Packages';
                    break;
                case 'discounts':
                    $title = 'Discounts';
                    break;
                case 'resourcerotas':
                    $title = 'Rota Management';
                    break;
                case 'settings':
                    $title = 'Global Settings';
                    break;
                case 'user_operator_settings':
                    $title = 'Operator Settings';
                    break;
                case 'payment_modes':
                    $title = 'Payment Modes';
                    break;
                case 'regions':
                    $title = 'Regions';
                    break;
                case 'cities':
                    $title = 'Cities';
                    break;
                case 'towns':
                    $title = 'Towns';
                    break;
                case 'lead_sources':
                    $title = 'Lead Sources';
                    break;
                case 'lead_statuses':
                    $title = 'Lead Statuses';
                    break;
                case 'locations':
                    $title = 'Centres';
                    break;
                case 'appointment_statuses':
                    $title = 'Appointment Statuses';
                    break;
                case 'machine_types':
                    $title = 'Machine Types';
                    break;
                case 'resources':
                    $title = 'Resources';
                    break;
                case 'logs':
                    $title = 'Logs';
                    break;
                case 'sms_templates':
                    $title = 'Sms Templates';
                    break;
                case 'centre_targets':
                    $title = 'Centre Targets';
                    break;
                case 'doctors':
                    $title = 'Doctors';
                    break;
                case 'packagesadvances':
                    $title = 'Finance';
                    break;
                case 'invoices':
                    $title = 'Invoices';
                    break;
                case 'custom_form_feedbacks':
                    $title = 'Custom Form Feedbacks';
                    break;
                case 'custom_forms':
                    $title = 'Custom Forms';
                    break;
                case 'refunds':
                    $title = 'Refunds';
                    break;
                case 'nonplansrefunds':
                    $title = 'Non Plans Refunds';
                    break;
                case 'brands':
                    $title = 'Brands';
                    break;
                case 'products':
                    $title = 'Products';
                    break;
                case 'orders':
                    $title = 'Orders';
                    break;
                case 'reports':
                    $title = 'General Revenue Reports';
                    break;
                case 'operation_reports':
                    $title = 'Operation Reports';
                    break;
                case 'follow-up-report':
                    $title = 'Patients Follow Up Report';
                    break;
                case '':
                    $title = '';
                    break;
                case '':
                    $title = '';
                    break;
                case '':
                    $title = '';
                    break;
                case '':
                    $title = '';
                    break;
                case '':
                    $title = '';
                    break;
                case '':
                    $title = '';
                    break;
                default:
                    $title = null;
                    break;
            }

            $view->with('title', $title);
        });
    }
}
