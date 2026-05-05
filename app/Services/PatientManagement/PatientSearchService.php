<?php

declare(strict_types=1);

namespace App\Services\PatientManagement;

use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Patients;
use Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PatientSearchService
{
    public static function contactStatus(string|null $contact): string
    {
        if (!Gate::allows('contact')) {
            return '***********';
        }

        return $contact ?? '';
    }

    public static function patientSearch(string|int $id): string|int
    {
        if (is_numeric($id)) {
            return $id;
        }

        if (str_starts_with($id, 'C-')) {
            $id = str_replace('C-', '', $id);
            if (str_starts_with($id, 'c-')) {
                return str_replace('c-', '', $id);
            }

            return $id;
        }

        return $id;
    }

    public static function patientSearchStringAdd(string|int $id): string
    {
        if (is_numeric($id)) {
            return 'C-' . $id;
        }

        return (string) $id;
    }

    public static function patientNameUpdate(string $phone, string $name): void
    {
        $accountId = Auth::user()->account_id;
        $patient_phone = GeneralFunctions::cleanNumber($phone);
        Leads::where(['phone' => $patient_phone])->update([
            'name' => $name,
        ]);

        Patients::where([
            'phone' => $patient_phone,
            'user_type_id' => Config::get('constants.patient_id'),
            'account_id' => $accountId,
        ])->update(['name' => $name]);

        Appointments::whereIn('patient_id', function ($query) use ($patient_phone, $accountId) {
            $query->select('id')
                ->from('users')
                ->where([
                    'phone' => $patient_phone,
                    'user_type_id' => Config::get('constants.patient_id'),
                    'account_id' => $accountId,
                ]);
        })->update(['name' => $name]);
    }
}
