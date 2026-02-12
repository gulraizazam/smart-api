<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ACL;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    /**
     * Get locations for the schedule calendar filter
     */
    public function getLocations(): JsonResponse
    {
        $userCentres = ACL::getUserCentres();
        
        $locationsQuery = Locations::where([
            ['account_id', '=', Auth::user()->account_id],
            ['active', '=', '1'],
        ]);

        if ($userCentres && is_array($userCentres) && count($userCentres) > 0) {
            $locationsQuery->whereIn('id', $userCentres);
        }

        $locations = $locationsQuery->orderBy('name', 'asc')->get(['id', 'name']);

        return ApiHelper::apiResponse(200, 'Locations retrieved successfully', true, [
            'locations' => $locations,
        ]);
    }

    /**
     * Get shifts for resources in a given week
     */
    public function getShifts(Request $request): JsonResponse
    {
        $locationId = $request->input('location_id');
        $resourceTypeId = $request->input('resource_type_id', 2); // Default to Doctor (2)
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$locationId || !$startDate || !$endDate) {
            return ApiHelper::apiResponse(400, 'Missing required parameters', false);
        }

        // Get active resources for the location and type
        $resources = $this->getResourcesForLocation($locationId, $resourceTypeId);

        if ($resources->isEmpty()) {
            return ApiHelper::apiResponse(200, 'No resources found', false);
        }

        // Get shifts for these resources in the date range
        $shifts = $this->getShiftsForResources($resources->pluck('id')->toArray(), $locationId, $startDate, $endDate);

        return ApiHelper::apiResponse(200, 'Shifts retrieved successfully', true, [
            'resources' => $resources,
            'shifts' => $shifts,
        ]);
    }

    /**
     * Get resources (doctors or machines) for a location
     */
    private function getResourcesForLocation(int $locationId, int $resourceTypeId)
    {
        $accountId = Auth::user()->account_id;

        // Resource type 2 = Doctor, 1 = Machine
        if ($resourceTypeId == 2) {
            // For doctors: Get from resources table where they have active rotas for this location
            // The resources table stores doctors with external_id pointing to user_id
            return Resources::where('account_id', $accountId)
                ->where('resource_type_id', $resourceTypeId)
                ->where('active', 1)
                ->whereHas('resourceRota', function ($query) use ($locationId) {
                    $query->where('location_id', $locationId)
                        ->where('active', 1);
                })
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'external_id']);
        } else {
            // For machines: Get machines that have active rotas for this location
            return Resources::where('account_id', $accountId)
                ->where('resource_type_id', $resourceTypeId)
                ->where('active', 1)
                ->whereHas('resourceRota', function ($query) use ($locationId) {
                    $query->where('location_id', $locationId)
                        ->where('active', 1);
                })
                ->orderBy('name', 'asc')
                ->get(['id', 'name']);
        }
    }

    /**
     * Get shifts for resources in a date range
     */
    private function getShiftsForResources(array $resourceIds, int $locationId, string $startDate, string $endDate): array
    {
        $shifts = [];

        // Get all active rotas for these resources at this location
        $rotas = ResourceHasRota::whereIn('resource_id', $resourceIds)
            ->where('location_id', $locationId)
            ->where('active', 1)
            ->get();

        if ($rotas->isEmpty()) {
            return $shifts;
        }

        $rotaIds = $rotas->pluck('id')->toArray();

        // Map rota to resource
        $rotaToResource = [];
        foreach ($rotas as $rota) {
            $rotaToResource[$rota->id] = $rota->resource_id;
        }

        // Get rota days for the date range
        // First get ALL rota days for these rotas to debug
        $allRotaDays = ResourceHasRotaDays::whereIn('resource_has_rota_id', $rotaIds)->get();
        
        // Filter by date range
        $rotaDays = $allRotaDays->filter(function ($day) use ($startDate, $endDate) {
            $dayDate = date('Y-m-d', strtotime($day->date));
            return $dayDate >= $startDate && $dayDate <= $endDate;
        });

        // Build shifts array
        foreach ($rotaDays as $rotaDay) {
            $resourceId = $rotaToResource[$rotaDay->resource_has_rota_id] ?? null;
            if ($resourceId) {
                $shifts[] = [
                    'resource_id' => $resourceId,
                    'date' => $rotaDay->date,
                    'start_time' => $rotaDay->start_time,
                    'end_time' => $rotaDay->end_time,
                    'start_off' => $rotaDay->start_off,
                    'end_off' => $rotaDay->end_off,
                ];
            }
        }

        return $shifts;
    }
}
