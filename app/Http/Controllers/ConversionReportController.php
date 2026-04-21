<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Reports\Finanaces;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConversionReportController extends Controller
{
    use ParsesDateRange;

    /**
     * Strip the "all" sentinel from a centre filter submission and fall back to
     * the user's permitted centres when the result is empty. Accepts either a
     * scalar or an array of ids (strings/ints), returns an int[].
     *
     * @return int[]
     */
    private function normaliseCentreFilter(mixed $raw): array
    {
        $ids = empty($raw)
            ? []
            : array_values(array_filter(
                array_map('intval', (array) $raw),
                fn (int $id): bool => $id > 0,
            ));

        if (empty($ids)) {
            $ids = array_map('intval', ACL::getUserCentres());
        }

        return $ids;
    }

    public function index(): View
    {
        $services = Services::where(['parent_id' => 0])->where('slug', '!=', 'all')->pluck('id', 'name');
        $employees = User::getAllActiveEmployeeRecords(Auth::user()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $operators = User::getAllActivePractionersRecords(Auth::user()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $select_All = ['' => 'All'];
        $users = ($select_All + $employees->toArray() + $operators->toArray());
        $operators->prepend('All', '');
        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        if (Auth::user()->hasRole('FDM')) {
        } else {
            $locations->prepend('All', '');
        }
        $locations_com = Locations::getActiveSorted(ACL::getUserCentres());

        return view('admin.reports.conversion', compact('services', 'users', 'operators', 'locations', 'locations_com'));
    }

    public function LoadConversionReport(Request $request): View
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        [$start_date, $end_date] = $this->parseDateRange($request->date_range);

        $payload = $request->all();
        $payload['location_id'] = $this->normaliseCentreFilter($payload['location_id'] ?? null);
        $payload['location_id_com'] = $this->normaliseCentreFilter($payload['location_id_com'] ?? null);

        [$report_data, $locationData, $maxConversion, $minConversion, $CategoryConversionData, $arrival_to_conversion_ratio, $average_client_coversion, $conversionsByPatient, $total_conversion, $total_arrival, $avg_cxlient_valu] = Finanaces::LoadConversionReport($payload, Auth::user()->account_id);

        return view('admin.reports.conversion_report', compact(
            'report_data', 'locationData', 'maxConversion', 'minConversion',
            'CategoryConversionData', 'arrival_to_conversion_ratio', 'average_client_coversion',
            'conversionsByPatient', 'total_conversion', 'total_arrival', 'avg_cxlient_valu',
            'start_date', 'end_date'
        ));
    }
}
