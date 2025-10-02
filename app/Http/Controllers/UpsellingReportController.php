<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\PackageService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
  use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Font;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
class UpsellingReportController extends Controller
{
    public function index()
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);

        return view('admin.reports.upselling', get_defined_vars());
    }
    public function consultantRevenueReport()
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);

        return view('admin.reports.consultant_revenue', get_defined_vars());
    }
   public function loadUpsellingReport(Request $request)
{
    $request->validate([
        'centre_id' => 'required|integer|exists:locations,id',
    ]);

    $locationId = $request->centre_id;
    $dates = explode(' - ', $request->input('date_range'));
    $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
    $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

    $roleHasUsers = User::whereHas('roles', function($query) {
        $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
    })->pluck('id');

    $fdmUserIds = User::whereHas('roles', function ($q) use ($locationId) {
            $q->where('name', 'FDM');
        })
        ->whereHas('user_has_locations', function ($q) use ($locationId) {
            $q->where('location_id', $locationId);
        })
        ->pluck('id');

    // Get doctors for the location
    $doctorIds = DB::table('doctor_has_locations')
        ->where('location_id', $locationId)
        ->whereIn('user_id', $roleHasUsers)
        ->distinct()
        ->pluck('user_id');

    $allSellerIds = $doctorIds->merge($fdmUserIds)->unique();
    
    if ($allSellerIds->isEmpty()) {
        return response()->json([
            'status' => 200,
            'message' => 'No doctors found for the selected location.',
            'data' => [],
        ]);
    }

    // Get all active users
    $allActiveUsers = User::whereIn('id', $allSellerIds)
        ->where('active', 1)
        ->select('id', 'name')
        ->get()
        ->keyBy('id');

    // Get package services
    $packageServices = PackageService::query()
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->whereIn('package_services.sold_by', $allSellerIds)
        ->whereBetween('package_services.created_at', [$startDate, $endDate])
        ->whereNotNull('sold_by')
        ->where('packages.location_id', $locationId)
        ->select(
            'package_services.id',
            'package_services.package_id',
            'package_services.sold_by',
            'package_services.tax_including_price',
            'package_services.created_at',
            'appointments.appointment_type_id',
            'appointments.doctor_id as appointment_doctor_id'
        )
        ->orderBy('package_services.created_at')
        ->get();

    // Initialize upselling amounts
    $doctorUpsellingAmounts = [];
    foreach ($allSellerIds as $sellerId) {
        $doctorUpsellingAmounts[(int)$sellerId] = 0;
    }

    // Process services by package
    $servicesByPackage = $packageServices->groupBy('package_id');

    foreach ($servicesByPackage as $packageId => $services) {
        $sortedServices = $services->sortBy([
            ['created_at', 'asc'],
            ['id', 'asc']
        ])->values()->all();
        
        $totalServices = count($sortedServices);
        
        for ($i = 0; $i < $totalServices; $i++) {
            $service = $sortedServices[$i];
            
            if (is_null($service->sold_by) || is_null($service->created_at)) {
                continue;
            }
            
            if ($service->appointment_type_id == 1 && $service->appointment_doctor_id == $service->sold_by) {
                continue;
            }
            
            $soldById = (int)$service->sold_by;
            
            if (!isset($doctorUpsellingAmounts[$soldById])) {
                continue;
            }
            
            $serviceAmount = $service->tax_including_price;
            
            if ($serviceAmount <= 0) {
                continue;
            }
            
            $serviceCreatedAt = Carbon::parse($service->created_at);
            
            $nextService = null;
            if ($i < $totalServices - 1) {
                $nextService = $sortedServices[$i + 1];
            }
            
            $paymentsQuery = DB::table('package_advances')
                ->where('package_id', $packageId)
                ->where('cash_flow', 'in')
                ->where('is_refund', 0)
                ->where('is_adjustment', 0)
                ->whereDate('created_at', $serviceCreatedAt->toDateString())
                ->where(function($q) use ($service) {
                    $q->where('created_at', '>', $service->created_at)
                      ->orWhere(function($q2) use ($service) {
                          $q2->where('created_at', '=', $service->created_at)
                             ->where('id', '>', $service->id);
                      });
                });
            
            if ($nextService && !is_null($nextService->created_at)) {
                $nextServiceTime = Carbon::parse($nextService->created_at);
                if ($nextServiceTime->toDateString() === $serviceCreatedAt->toDateString()) {
                    $paymentsQuery->where(function($q) use ($nextService) {
                        $q->where('created_at', '<', $nextService->created_at)
                          ->orWhere(function($q2) use ($nextService) {
                              $q2->where('created_at', '=', $nextService->created_at)
                                 ->where('id', '<', $nextService->id);
                          });
                    });
                }
            }
            
            $paymentsForThisService = $paymentsQuery->sum('cash_amount');
            
            if ($paymentsForThisService > 0) {
                $upsellingAmount = min($paymentsForThisService, $serviceAmount);
                $doctorUpsellingAmounts[$soldById] += $upsellingAmount;
            }
        }
    }

    // Prepare report data
    $reportData = $allActiveUsers->map(function ($user) use ($doctorUpsellingAmounts) {
        return (object)[
            'doctor_id' => $user->id,
            'doctor_name' => $user->name,
            'total_sold_amount' => $doctorUpsellingAmounts[$user->id] ?? 0,
        ];
    })->sortByDesc('total_sold_amount')->values();

    // Store filters in session
    session(['upselling_filters' => [
        'location_id' => $locationId,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'all_seller_ids' => $allSellerIds->toArray()
    ]]);

    return view('admin.reports.upsellingReport', compact('reportData'));
}

// New function for doctor detail view
public function doctorUpsellingDetail($doctorId)
{
    $filters = session('upselling_filters');

    if (!$filters) {
        return redirect()->back()->with('error', 'Session expired. Please reload the report.');
    }

    // Get doctor name
    $doctorName = User::find($doctorId)->name ?? 'Unknown Doctor';

    // Get all package services for this doctor in the period
    $packageServices = PackageService::query()
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->join('services', 'package_services.service_id', '=', 'services.id')
        ->where('package_services.sold_by', $doctorId)
        ->where('packages.location_id', $filters['location_id'])
        ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
        ->whereNotNull('sold_by')
        ->select(
            'package_services.id',
            'package_services.package_id',
            'package_services.sold_by',
            'package_services.service_id',
            'package_services.tax_including_price',
            'package_services.created_at',
            'services.name as service_name',
            'appointments.appointment_type_id',
            'appointments.doctor_id as appointment_doctor_id',
            'appointments.patient_id',
            'appointments.name as patient_name',
            'appointments.scheduled_date'
        )
        ->orderBy('package_services.created_at')
        ->get();

    // Process each package's services to calculate upselling
    $detailData = collect();
    $servicesByPackage = $packageServices->groupBy('package_id');
    $totalAmount = 0;
    $uniquePackages = collect();

    foreach ($servicesByPackage as $packageId => $services) {
        $sortedServices = $services->sortBy([
            ['created_at', 'asc'],
            ['id', 'asc']
        ])->values()->all();
        
        $totalServicesInPackage = count($sortedServices);
        
        for ($i = 0; $i < $totalServicesInPackage; $i++) {
            $service = $sortedServices[$i];
            
            // Skip self-consultation sales
            $isSelfConsultation = ($service->appointment_type_id == 1 && 
                                   $service->appointment_doctor_id == $service->sold_by);
            
            if ($isSelfConsultation) {
                continue;
            }
            
            $serviceAmount = $service->tax_including_price;
            
            if ($serviceAmount <= 0) {
                continue;
            }
            
            $serviceCreatedAt = Carbon::parse($service->created_at);
            
            // Find next service in this package
            $nextService = null;
            if ($i < $totalServicesInPackage - 1) {
                $nextService = $sortedServices[$i + 1];
            }
            
            // Calculate payments for this service
            $paymentsQuery = DB::table('package_advances')
                ->where('package_id', $packageId)
                ->where('cash_flow', 'in')
                ->where('is_refund', 0)
                ->where('is_adjustment', 0)
                ->whereDate('created_at', $serviceCreatedAt->toDateString())
                ->where(function($q) use ($service) {
                    $q->where('created_at', '>', $service->created_at)
                      ->orWhere(function($q2) use ($service) {
                          $q2->where('created_at', '=', $service->created_at)
                             ->where('id', '>', $service->id);
                      });
                });
            
            // Limit to before next service if on same day
            if ($nextService && !is_null($nextService->created_at)) {
                $nextServiceTime = Carbon::parse($nextService->created_at);
                if ($nextServiceTime->toDateString() === $serviceCreatedAt->toDateString()) {
                    $paymentsQuery->where(function($q) use ($nextService) {
                        $q->where('created_at', '<', $nextService->created_at)
                          ->orWhere(function($q2) use ($nextService) {
                              $q2->where('created_at', '=', $nextService->created_at)
                                 ->where('id', '<', $nextService->id);
                          });
                    });
                }
            }
            
            $paymentsReceived = $paymentsQuery->sum('cash_amount');
            $upsellingAmount = 0;
            
            if ($paymentsReceived > 0) {
                $upsellingAmount = min($paymentsReceived, $serviceAmount);
                $uniquePackages->push($packageId);
            }
            
            // Add to detail - show ALL services with their upselling status
            $detailData->push((object)[
                'doctor_name' => $doctorName,
                'package_id' => $packageId,
                'service_name' => $service->service_name,
                'tax_including_price' => $serviceAmount,
                'created_at' => $service->created_at,
                'patient_id' => $service->patient_id,
                'patient_name' => $service->patient_name,
                'scheduled_date' => $service->scheduled_date,
                'actual_amount' => $upsellingAmount, // Upselling credited
                'payment_received' => $paymentsReceived, // Total payment
                'has_upselling' => $upsellingAmount > 0
            ]);
            
            $totalAmount += $upsellingAmount;
        }
    }

    // Sort by created_at descending
    $detailData = $detailData->sortByDesc('created_at')->values();
    
    // Count unique packages with upselling
    $uniqueUpsellings = $uniquePackages->unique()->count();

    return view('admin.reports.doctorUpsellingDetail', compact(
        'detailData', 
        'doctorName', 
        'totalAmount', 
        'uniqueUpsellings'
    ));
}
public function loadConsultantRevenueReport(Request $request)
{
    $request->validate([
        'centre_id' => 'required|integer|exists:locations,id',
    ]);

    $locationId = $request->centre_id;
    $dates = explode(' - ', $request->input('date_range'));
    $startDate = date('Y-m-d 00:00:00', strtotime($dates[0]));
    $endDate = date('Y-m-d 23:59:59', strtotime($dates[1]));

    // Step 1: Get only Consultant and Lifestyle Consultant users who are active
    $consultantUserIds = User::whereHas('roles', function($query) {
        $query->where('name', 'Consultant')->orWhere('name', 'Lifestyle Consultant');
    })->where('active', 1)->pluck('id');

    // Step 2: Get consultants assigned to the specific location
    $consultantIds = DB::table('doctor_has_locations')
        ->where('location_id', $locationId)
        ->whereIn('user_id', $consultantUserIds)
        ->distinct()
        ->pluck('user_id');

    if ($consultantIds->isEmpty()) {
        return response()->json([
            'status' => 200,
            'message' => 'No consultants found for the selected location.',
            'data' => [],
        ]);
    }

    // Get all potential sellers (doctors + FDMs)
    $roleHasUsers = User::whereHas('roles', function($query) {
        $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
    })->pluck('id');

    $fdmUserIds = User::whereHas('roles', function ($q) {
            $q->where('name', 'FDM');
        })
        ->whereHas('user_has_locations', function ($q) use ($locationId) {
            $q->where('location_id', $locationId);
        })
        ->pluck('id');

    $allSellerIds = $roleHasUsers->merge($fdmUserIds)->unique();

    $reportQuery = PackageService::query()
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->join('users as appointment_doctors', 'appointments.doctor_id', '=', 'appointment_doctors.id')
        ->whereIn('package_services.sold_by', $allSellerIds)
        ->whereIn('appointments.doctor_id', $consultantIds)
        ->where('packages.location_id', $locationId);

    // Apply date range filter on created_at
    if ($startDate && $endDate) {
        $reportQuery->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by');
    }

    // Fetch consultant revenue data - upselling attributed to consultants
    $reportData = $reportQuery
        ->select(
            'appointment_doctors.name as consultant_name',
            'appointments.doctor_id as consultant_id',
            DB::raw("
                SUM(
                    CASE
                        WHEN NOT (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                        THEN package_services.tax_including_price
                        ELSE 0
                    END
                ) as total_consultation_revenue
            "),
            DB::raw("
                SUM(
                    CASE
                        WHEN NOT (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                        AND package_services.is_consumed = 1
                        AND package_services.consumed_at BETWEEN '{$startDate}' AND '{$endDate}'
                        THEN package_services.tax_including_price
                        ELSE 0
                    END
                ) as total_consumed_amount
            ")
        )
        ->groupBy('appointments.doctor_id', 'appointment_doctors.name')
        ->get();

    // Get all eligible consultants and add those with 0 amounts
    $reportedConsultantIds = $reportData->pluck('consultant_id')->toArray();
    $missingConsultantIds = $consultantIds->diff($reportedConsultantIds);
    
    if ($missingConsultantIds->isNotEmpty()) {
        $missingConsultants = User::whereIn('id', $missingConsultantIds)
            ->select('id as consultant_id', 'name as consultant_name')
            ->get()
            ->map(function ($user) {
                return (object) [
                    'consultant_name' => $user->consultant_name,
                    'consultant_id' => $user->consultant_id,
                    'total_consultation_revenue' => 0,
                    'total_consumed_amount' => 0
                ];
            });
        
        $reportData = $reportData->concat($missingConsultants);
    }
    
    // Re-sort the final collection
    $reportData = $reportData->sortByDesc('total_consultation_revenue')->values();

    // Store filters in session for detail view
    session(['consultant_revenue_filters' => [
        'location_id' => $locationId,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'consultant_ids' => $consultantIds->toArray(),
        'all_seller_ids' => $allSellerIds->toArray()
    ]]);

    return view('admin.reports.consultantRevenueReport', compact('reportData'));
}
public function consultantRevenueDetail($consultantId)
{
    $filters = session('consultant_revenue_filters');

    if (!$filters) {
        return redirect()->back()->with('error', 'Session expired. Please reload the report.');
    }

    $reportQuery = PackageService::query()
        ->join('users', 'package_services.sold_by', '=', 'users.id')
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->join('services', 'package_services.service_id', '=', 'services.id')
        ->where('package_services.sold_by', $consultantId)
        ->whereIn('package_services.sold_by', $filters['consultant_ids'])
        ->where('packages.location_id', $filters['location_id'])
        ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
        ->whereNotNull('sold_by');

    $detailData = $reportQuery
        ->select(
            'users.name as consultant_name',
            'package_services.package_id',
            'services.name as service_name',
            'package_services.tax_including_price',
            'package_services.created_at',
            'appointments.patient_id',
            'appointments.name as patient_name',
            'appointments.scheduled_date',
            DB::raw("
                CASE
                    WHEN (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                    THEN package_services.tax_including_price
                    ELSE 0
                END as actual_amount
            ")
        )
        ->where(DB::raw("
            CASE
                WHEN (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                THEN package_services.tax_including_price
                ELSE 0
            END
        "), '>', 0)
        ->orderBy('package_services.created_at', 'desc')
        ->get();

    $consultantName = $detailData->first()->consultant_name ?? 'Unknown Consultant';
    $totalAmount = $detailData->sum('actual_amount');
    
    // Count unique consultations (appointments) instead of service records
    $uniqueConsultations = $detailData->unique('package_id')->count();
    
    // Alternative: Count unique appointments if you want true appointment count
    // $uniqueAppointments = $detailData->unique(function($item) {
    //     return $item->patient_id . '-' . $item->scheduled_date;
    // })->count();

    return view('admin.reports.consultantRevenueDetail', compact('detailData', 'consultantName', 'totalAmount', 'uniqueConsultations'));
}
public function getDoctorUpsellingData(Request $request)
{
    try {
        $centreId = $request->centre_id;
        $period = $request->period ?: 'thismonth';
        
        // Define date ranges
        $periods = [
            'yesterday' => [
                'start_date' => Carbon::now()->subDay(1)->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'last7days' => [
                'start_date' => Carbon::now()->subDay(6)->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'week' => [
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'thismonth' => [
                'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'lastmonth' => [
                'start_date' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d 23:59:59'),
            ],
        ];

        $startDate = $periods[$period]['start_date'];
        $endDate = $periods[$period]['end_date'];

        // Get users with specific roles
        $roleHasUsers = User::whereHas('roles', function($query) {
            $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
        })->pluck('id');

        $fdmUserIds = User::whereHas('roles', function ($q) {
                $q->where('name', 'FDM');
            })
            ->whereHas('user_has_locations', function ($q) use ($centreId) {
                if ($centreId !== 'all') {
                    $q->where('location_id', $centreId);
                }
            })
            ->pluck('id');

        // Get doctors for the location(s)
        if ($centreId === 'all') {
            // Get all locations user has access to
            $userLocations = \App\Helpers\ACL::getUserCentres();
            $doctorIds = DB::table('doctor_has_locations')
                ->whereIn('location_id', $userLocations)
                ->whereIn('user_id', $roleHasUsers)
                ->distinct()
                ->pluck('user_id');
        } else {
            $doctorIds = DB::table('doctor_has_locations')
                ->where('location_id', $centreId)
                ->whereIn('user_id', $roleHasUsers)
                ->distinct()
                ->pluck('user_id');
        }

        $allSellerIds = $doctorIds->merge($fdmUserIds)->unique();

        if ($allSellerIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No doctors found for the selected location.',
                'data' => [],
            ]);
        }

        // Get all active users (doctors, consultants, FDMs) for the location
        $allActiveUsers = User::whereIn('id', $allSellerIds)
            ->where('active', 1)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        // Get package services created in the date range
        $packageServicesQuery = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->whereIn('package_services.sold_by', $allSellerIds)
            ->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by');

        // Apply location filter
        if ($centreId !== 'all') {
            $packageServicesQuery->where('packages.location_id', $centreId);
        } else {
            $userLocations = \App\Helpers\ACL::getUserCentres();
            $packageServicesQuery->whereIn('packages.location_id', $userLocations);
        }

        $packageServices = $packageServicesQuery
            ->select(
                'package_services.id',
                'package_services.package_id',
                'package_services.sold_by',
                'package_services.tax_including_price',
                'package_services.created_at',
                'appointments.appointment_type_id',
                'appointments.doctor_id as appointment_doctor_id'
            )
            ->orderBy('package_services.created_at')
            ->get();

        // Initialize upselling amounts for each doctor
        $doctorUpsellingAmounts = [];
        foreach ($allSellerIds as $sellerId) {
            $doctorUpsellingAmounts[(int)$sellerId] = 0;
        }

        // Group services by package_id for processing
        $servicesByPackage = $packageServices->groupBy('package_id');

        foreach ($servicesByPackage as $packageId => $services) {
            // Sort all services by created_at, then by id
            $sortedServices = $services->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc']
            ])->values()->all();
            
            $totalServices = count($sortedServices);
            
            for ($i = 0; $i < $totalServices; $i++) {
                $service = $sortedServices[$i];
                
                // Skip if sold_by or created_at is null
                if (is_null($service->sold_by) || is_null($service->created_at)) {
                    continue;
                }
                
                // Skip self-consultation sales
                if ($service->appointment_type_id == 1 && $service->appointment_doctor_id == $service->sold_by) {
                    continue;
                }
                
                $soldById = (int)$service->sold_by;
                
                // Skip if sold_by not in our initialized array
                if (!isset($doctorUpsellingAmounts[$soldById])) {
                    continue;
                }
                
                $serviceAmount = $service->tax_including_price;
                
                // Skip zero or negative amounts
                if ($serviceAmount <= 0) {
                    continue;
                }
                
                $serviceCreatedAt = Carbon::parse($service->created_at);
                
                // Find the next service
                $nextService = null;
                if ($i < $totalServices - 1) {
                    $nextService = $sortedServices[$i + 1];
                }
                
                // Build payment query - SAME DAY ONLY
                $paymentsQuery = DB::table('package_advances')
                    ->where('package_id', $packageId)
                    ->where('cash_flow', 'in')
                    ->where('is_refund', 0)
                    ->where('is_adjustment', 0)
                    ->whereDate('created_at', $serviceCreatedAt->toDateString())
                    ->where(function($q) use ($service) {
                        $q->where('created_at', '>', $service->created_at)
                          ->orWhere(function($q2) use ($service) {
                              $q2->where('created_at', '=', $service->created_at)
                                 ->where('id', '>', $service->id);
                          });
                    });
                
                // If there's a next service on same day, limit payments to before that service
                if ($nextService && !is_null($nextService->created_at)) {
                    $nextServiceTime = Carbon::parse($nextService->created_at);
                    if ($nextServiceTime->toDateString() === $serviceCreatedAt->toDateString()) {
                        $paymentsQuery->where(function($q) use ($nextService) {
                            $q->where('created_at', '<', $nextService->created_at)
                              ->orWhere(function($q2) use ($nextService) {
                                  $q2->where('created_at', '=', $nextService->created_at)
                                     ->where('id', '<', $nextService->id);
                              });
                        });
                    }
                }
                
                $paymentsForThisService = $paymentsQuery->sum('cash_amount');
                
                // Calculate upselling
                if ($paymentsForThisService > 0) {
                    $upsellingAmount = min($paymentsForThisService, $serviceAmount);
                    $doctorUpsellingAmounts[$soldById] += $upsellingAmount;
                }
            }
        }

        // Combine all users with their upselling data
        $reportData = $allActiveUsers->map(function ($user) use ($doctorUpsellingAmounts) {
            return (object)[
                'doctor_id' => $user->id,
                'doctor_name' => $user->name,
                'total_upselling_amount' => $doctorUpsellingAmounts[$user->id] ?? 0,
            ];
        })->sortByDesc('total_upselling_amount')->values();

        return response()->json([
            'success' => true,
            'message' => 'Doctor upselling data retrieved successfully.',
            'data' => $reportData,
        ]);

    } catch (\Exception $e) {
        \Log::error('Doctor Upselling Data Error: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving doctor upselling data.',
            'data' => [],
        ], 500);
    }
}
public function doctorConsultantBreakdown($sellerId)
{
    $filters = session('upselling_filters');

    if (!$filters) {
        return redirect()->back()->with('error', 'Session expired. Please reload the report.');
    }

    // Get seller information
    $seller = User::find($sellerId);
    if (!$seller) {
        return redirect()->back()->with('error', 'Seller not found.');
    }

    // Get all package services for this seller
    $packageServices = PackageService::query()
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->where('package_services.sold_by', $sellerId)
        ->where('packages.location_id', $filters['location_id'])
        ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
        ->whereNotNull('sold_by')
        ->select(
            'package_services.id',
            'package_services.package_id',
            'package_services.sold_by',
            'package_services.tax_including_price',
            'package_services.created_at',
            'appointments.appointment_type_id',
            'appointments.doctor_id as appointment_doctor_id'
        )
        ->orderBy('package_services.created_at')
        ->get();

    // Initialize consultant tracking
    $consultantData = [];
    
    // Process services by package
    $servicesByPackage = $packageServices->groupBy('package_id');

    foreach ($servicesByPackage as $packageId => $services) {
        $sortedServices = $services->sortBy([
            ['created_at', 'asc'],
            ['id', 'asc']
        ])->values()->all();
        
        $totalServices = count($sortedServices);
        
        for ($i = 0; $i < $totalServices; $i++) {
            $service = $sortedServices[$i];
            
            // Skip self-consultation sales
            if ($service->appointment_type_id == 1 && $service->appointment_doctor_id == $service->sold_by) {
                continue;
            }
            
            $serviceAmount = $service->tax_including_price;
            
            if ($serviceAmount <= 0) {
                continue;
            }
            
            $serviceCreatedAt = Carbon::parse($service->created_at);
            $consultantId = $service->appointment_doctor_id;
            
            // Find next service
            $nextService = null;
            if ($i < $totalServices - 1) {
                $nextService = $sortedServices[$i + 1];
            }
            
            // Get payments for this service
            $paymentsQuery = DB::table('package_advances')
                ->where('package_id', $packageId)
                ->where('cash_flow', 'in')
                ->where('is_refund', 0)
                ->where('is_adjustment', 0)
                ->whereDate('created_at', $serviceCreatedAt->toDateString())
                ->where(function($q) use ($service) {
                    $q->where('created_at', '>', $service->created_at)
                      ->orWhere(function($q2) use ($service) {
                          $q2->where('created_at', '=', $service->created_at)
                             ->where('id', '>', $service->id);
                      });
                });
            
            if ($nextService && !is_null($nextService->created_at)) {
                $nextServiceTime = Carbon::parse($nextService->created_at);
                if ($nextServiceTime->toDateString() === $serviceCreatedAt->toDateString()) {
                    $paymentsQuery->where(function($q) use ($nextService) {
                        $q->where('created_at', '<', $nextService->created_at)
                          ->orWhere(function($q2) use ($nextService) {
                              $q2->where('created_at', '=', $nextService->created_at)
                                 ->where('id', '<', $nextService->id);
                          });
                    });
                }
            }
            
            $paymentsReceived = $paymentsQuery->sum('cash_amount');
            
            if ($paymentsReceived > 0) {
                $upsellingAmount = min($paymentsReceived, $serviceAmount);
                
                // Initialize consultant data if not exists
                if (!isset($consultantData[$consultantId])) {
                    $consultantData[$consultantId] = [
                        'consultant_id' => $consultantId,
                        'consultant_name' => null, // Will be populated later
                        'total_amount' => 0,
                        'packages' => [],
                    ];
                }
                
                // Add to consultant's total
                $consultantData[$consultantId]['total_amount'] += $upsellingAmount;
                $consultantData[$consultantId]['packages'][$packageId] = true;
            }
        }
    }

    // Get consultant names
    $consultantIds = array_keys($consultantData);
    if (!empty($consultantIds)) {
        $consultants = User::whereIn('id', $consultantIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');
        
        foreach ($consultantData as $consultantId => $data) {
            if (isset($consultants[$consultantId])) {
                $consultantData[$consultantId]['consultant_name'] = $consultants[$consultantId]->name;
            } else {
                $consultantData[$consultantId]['consultant_name'] = 'Unknown Consultant';
            }
            
            // Count unique packages
            $consultantData[$consultantId]['total_packages'] = count($consultantData[$consultantId]['packages']);
            unset($consultantData[$consultantId]['packages']); // Remove packages array, only keep count
        }
    }

    // Convert to collection and sort by total amount
    $consultantBreakdown = collect($consultantData)
        ->map(function($data) {
            return (object)$data;
        })
        ->sortByDesc('total_amount')
        ->values();

    // Calculate totals
    $totalSoldAmount = $consultantBreakdown->sum('total_amount');
    $totalPackages = $consultantBreakdown->sum('total_packages');
    $totalConsultants = $consultantBreakdown->count();

    $sellerName = $seller->name;

    return view('admin.reports.doctorConsultantBreakdown', compact(
        'consultantBreakdown', 
        'sellerName', 
        'sellerId',
        'totalSoldAmount',
        'totalPackages',
        'totalConsultants'
    ));
}
// You'll also need to add this route in your web.php:
// Route::get('/admin/dashboard/doctor/upselling/data', [YourController::class, 'getDoctorUpsellingData'])->name('admin.dashboard.doctor.upselling.data');
public function consultantSellerDetail($consultantId, $sellerId)
{
    $filters = session('upselling_filters');

    if (!$filters) {
        return redirect()->back()->with('error', 'Session expired. Please reload the report.');
    }

    // Get consultant and seller information
    $consultant = User::find($consultantId);
    $seller = User::find($sellerId);
    
    if (!$consultant || !$seller) {
        return redirect()->back()->with('error', 'Consultant or seller not found.');
    }

    // First, let's check what column exists in appointments table
    // Try different possible column names for the doctor/consultant
    $appointmentColumns = \Schema::getColumnListing('appointments');
    
    $doctorColumn = null;
    if (in_array('doctor_id', $appointmentColumns)) {
        $doctorColumn = 'doctor_id';
    } elseif (in_array('user_id', $appointmentColumns)) {
        $doctorColumn = 'user_id';
    } elseif (in_array('consultant_id', $appointmentColumns)) {
        $doctorColumn = 'consultant_id';
    } elseif (in_array('assigned_doctor_id', $appointmentColumns)) {
        $doctorColumn = 'assigned_doctor_id';
    }
    
    if (!$doctorColumn) {
        return redirect()->back()->with('error', 'Unable to identify doctor column in appointments table.');
    }

    $reportQuery = PackageService::query()
        ->join('packages', 'package_services.package_id', '=', 'packages.id')
        ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
        ->join('users as appointment_doctors', "appointments.{$doctorColumn}", '=', 'appointment_doctors.id')
        ->join('users as sellers', 'package_services.sold_by', '=', 'sellers.id')
        ->join('services', 'package_services.service_id', '=', 'services.id')
        ->where('package_services.sold_by', $sellerId)
        ->where("appointments.{$doctorColumn}", $consultantId)
        ->whereIn('package_services.sold_by', $filters['all_seller_ids'])
        ->where('packages.location_id', $filters['location_id'])
        ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
        ->whereNotNull('sold_by');

    $detailData = $reportQuery
        ->select(
            'appointment_doctors.name as consultant_name',
            'sellers.name as seller_name',
            "appointments.{$doctorColumn} as consultant_id",
            'package_services.sold_by as seller_id',
            'package_services.package_id',
            'services.name as service_name',
            'package_services.tax_including_price',
            'package_services.created_at',
            'appointments.patient_id',
            'appointments.name as patient_name',
            'appointments.scheduled_date',
            'package_services.is_consumed',
            'package_services.consumed_at',
            DB::raw("
                CASE
                    WHEN NOT (appointments.appointment_type_id = 1 AND appointments.{$doctorColumn} = package_services.sold_by)
                    THEN package_services.tax_including_price
                    ELSE 0
                END as actual_amount
            "),
            DB::raw("
                CASE
                    WHEN NOT (appointments.appointment_type_id = 1 AND appointments.{$doctorColumn} = package_services.sold_by)
                    AND package_services.is_consumed = 1
                    AND package_services.consumed_at BETWEEN '{$filters['start_date']}' AND '{$filters['end_date']}'
                    THEN package_services.tax_including_price
                    ELSE 0
                END as consumed_amount
            ")
        )
        ->where(DB::raw("
            CASE
                WHEN NOT (appointments.appointment_type_id = 1 AND appointments.{$doctorColumn} = package_services.sold_by)
                THEN package_services.tax_including_price
                ELSE 0
            END
        "), '>', 0)
        ->orderBy('package_services.created_at', 'desc')
        ->get();

    $consultantName = $consultant->name;
    $sellerName = $seller->name;
    $totalAmount = $detailData->sum('actual_amount');
    $totalConsumedAmount = $detailData->sum('consumed_amount');
    
    // Count unique packages
    $uniquePackages = $detailData->unique('package_id')->count();
    $uniquePatients = $detailData->unique('patient_id')->count();

    return view('admin.reports.consultantSellerDetail', compact(
        'detailData', 
        'consultantName', 
        'sellerName', 
        'totalAmount', 
        'totalConsumedAmount', 
        'uniquePackages',
        'uniquePatients',
        'consultantId',
        'sellerId'
    ));
}
public function downloadDoctorUpsellingExcel(Request $request)
    {
        try {
            $period = $request->period ?: 'lastmonth';
            
            // Hardcoded centre IDs
            $centreIds = [
                2 => 'CUTERA DHA Karachi',
                3 => 'CUTERA Bahadurabad Karachi',
               
                46 => 'CUTERA Johar Town',
                47 => 'CUTERA Johar Karachi',
                48 => 'CUTERA DHA Lahore',
                49 => 'CUTERA Gulberg Lahore',
                50 => 'CUTERA Faisalabad',
                51 => 'CUTERA F-7 Islamabad',
               
                53 => 'CUTERA Saddar Rawalpindi',
                54 => 'CUTERA I-8 Islamabad',
                55 => 'CUTERA Hyderabad',
                56 => 'CUTERA Sialkot'
            ];
            
            // Define date ranges
            $periods = [
                'yesterday' => [
                    'start_date' => Carbon::now()->subDay(1)->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                    'label' => 'Yesterday'
                ],
                'last7days' => [
                    'start_date' => Carbon::now()->subDay(6)->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                    'label' => 'Last 7 Days'
                ],
                'week' => [
                    'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                    'label' => 'This Week'
                ],
                'thismonth' => [
                    'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                    'label' => 'This Month'
                ],
                'lastmonth' => [
                    'start_date' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d 23:59:59'),
                    'label' => 'Last Month'
                ],
            ];

            $currentPeriod = $periods[$period];
            $allCentreData = [];

            // Get data for each centre
            foreach ($centreIds as $centreId => $centreName) {
                $centreData = $this->getDoctorUpsellingDataForCentre($centreId, $currentPeriod['start_date'], $currentPeriod['end_date']);
                
                // Include centre even if no data (will show empty sheet)
                $allCentreData[] = [
                    'centre_name' => $centreName,
                    'centre_id' => $centreId,
                    'data' => $centreData
                ];
            }

            // Create Excel file
            $fileName = 'Doctor_Upselling_Report_' . $currentPeriod['label'] . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
            
            return $this->generateExcelFile($allCentreData, $fileName, $currentPeriod);

        } catch (\Exception $e) {
            \Log::error('Doctor Upselling Excel Download Error: ' . $e->getMessage());  
            return redirect()->back()->with('error', 'Error generating Excel file.');
        }
    }

private function getDoctorUpsellingDataForCentre($centreId, $startDate, $endDate)
{
    try {
        // Get users with specific roles
        $roleHasUsers = User::whereHas('roles', function($query) {
            $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
        })->pluck('id');

        $fdmUserIds = User::whereHas('roles', function ($q) use ($centreId) {
                $q->where('name', 'FDM');
            })
            ->whereHas('user_has_locations', function ($q) use ($centreId) {
                $q->where('location_id', $centreId);
            })
            ->pluck('id');

        // Get doctors for the specific location
        $doctorIds = DB::table('doctor_has_locations')
            ->where('location_id', $centreId)
            ->whereIn('user_id', $roleHasUsers)
            ->distinct()
            ->pluck('user_id');

        $allSellerIds = $doctorIds->merge($fdmUserIds)->unique();

        if ($allSellerIds->isEmpty()) {
            return [];
        }

        // Get all active users (doctors, consultants, FDMs) for the location
        $allActiveUsers = User::whereIn('id', $allSellerIds)
            ->where('active', 1)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        // Get package services created in the date range
        $packageServicesQuery = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->whereIn('package_services.sold_by', $allSellerIds)
            ->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by')
            ->where('packages.location_id', $centreId);

        $packageServices = $packageServicesQuery
            ->select(
                'package_services.id',
                'package_services.package_id',
                'package_services.sold_by',
                'package_services.tax_including_price',
                'package_services.created_at',
                'appointments.appointment_type_id',
                'appointments.doctor_id as appointment_doctor_id'
            )
            ->orderBy('package_services.created_at')
            ->get();

        // Initialize upselling amounts for each doctor
        $doctorUpsellingAmounts = [];
        foreach ($allSellerIds as $sellerId) {
            $doctorUpsellingAmounts[(int)$sellerId] = 0;
        }

        // Group services by package_id for processing
        $servicesByPackage = $packageServices->groupBy('package_id');

        foreach ($servicesByPackage as $packageId => $services) {
            // Sort all services by created_at, then by id
            $sortedServices = $services->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc']
            ])->values()->all();
            
            $totalServices = count($sortedServices);
            
            for ($i = 0; $i < $totalServices; $i++) {
                $service = $sortedServices[$i];
                
                // Skip if sold_by or created_at is null
                if (is_null($service->sold_by) || is_null($service->created_at)) {
                    continue;
                }
                
                // Skip self-consultation sales
                if ($service->appointment_type_id == 1 && $service->appointment_doctor_id == $service->sold_by) {
                    continue;
                }
                
                $soldById = (int)$service->sold_by;
                
                // Skip if sold_by not in our initialized array
                if (!isset($doctorUpsellingAmounts[$soldById])) {
                    continue;
                }
                
                $serviceAmount = $service->tax_including_price;
                
                // Skip zero or negative amounts
                if ($serviceAmount <= 0) {
                    continue;
                }
                
                $serviceCreatedAt = Carbon::parse($service->created_at);
                
                // SIMPLIFIED LOGIC: Get all payments on the same day
                $paymentsForThisService = DB::table('package_advances')
                    ->where('package_id', $packageId)
                    ->where('cash_flow', 'in')
                    ->where('is_refund', 0)
                    ->where('is_adjustment', 0)
                    ->whereDate('created_at', $serviceCreatedAt->toDateString())
                    ->sum('cash_amount');
                
                /* COMMENTED OUT: Complex payment calculation logic
                // Find the next service
                $nextService = null;
                if ($i < $totalServices - 1) {
                    $nextService = $sortedServices[$i + 1];
                }
                
                // Build payment query - SAME DAY ONLY
                $paymentsQuery = DB::table('package_advances')
                    ->where('package_id', $packageId)
                    ->where('cash_flow', 'in')
                    ->where('is_refund', 0)
                    ->where('is_adjustment', 0)
                    ->whereDate('created_at', $serviceCreatedAt->toDateString()) // SAME DAY
                    ->where(function($q) use ($service) {
                        // Payment after service OR same timestamp but higher ID
                        $q->where('created_at', '>', $service->created_at)
                          ->orWhere(function($q2) use ($service) {
                              $q2->where('created_at', '=', $service->created_at)
                                 ->where('id', '>', $service->id);
                          });
                    });
                
                // If there's a next service on same day, limit payments to before that service
                if ($nextService && !is_null($nextService->created_at)) {
                    $nextServiceTime = Carbon::parse($nextService->created_at);
                    if ($nextServiceTime->toDateString() === $serviceCreatedAt->toDateString()) {
                        $paymentsQuery->where(function($q) use ($nextService) {
                            $q->where('created_at', '<', $nextService->created_at)
                              ->orWhere(function($q2) use ($nextService) {
                                  $q2->where('created_at', '=', $nextService->created_at)
                                     ->where('id', '<', $nextService->id);
                              });
                        });
                    }
                }
                
                $paymentsForThisService = $paymentsQuery->sum('cash_amount');
                */
                
                // Calculate upselling
                if ($paymentsForThisService > 0) {
                    $upsellingAmount = min($paymentsForThisService, $serviceAmount);
                    $doctorUpsellingAmounts[$soldById] += $upsellingAmount;
                }
            }
        }

        // Prepare the report data
        $reportData = $allActiveUsers->map(function ($user) use ($doctorUpsellingAmounts) {
            return [
                'doctor_id' => $user->id,
                'doctor_name' => $user->name,
                'total_upselling_amount' => $doctorUpsellingAmounts[$user->id] ?? 0,
            ];
        })->sortByDesc('total_upselling_amount')->values()->toArray();

        return $reportData;

    } catch (\Exception $e) {
        \Log::error('Get Centre Data Error: ' . $e->getMessage());
        return [];
    }
}
    // Your existing API method
    public function getDoctorPaymentBasedUpsellingData(Request $request)
    {
        try {
            $centreId = $request->centre_id;
            $period = $request->period ?: 'thismonth';
            
            // Define date ranges
            $periods = [
                'yesterday' => [
                    'start_date' => Carbon::now()->subDay(1)->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                ],
                'last7days' => [
                    'start_date' => Carbon::now()->subDay(6)->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                ],
                'week' => [
                    'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                ],
                'thismonth' => [
                    'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                ],
                'lastmonth' => [
                    'start_date' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d 00:00:00'),
                    'end_date' => Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d 23:59:59'),
                ],
            ];

            $startDate = $periods[$period]['start_date'];
            $endDate = $periods[$period]['end_date'];

            // Get users with specific roles
            $roleHasUsers = User::whereHas('roles', function($query) {
                $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
            })->pluck('id');

            $fdmUserIds = User::whereHas('roles', function ($q) {
                    $q->where('name', 'FDM');
                })
                ->whereHas('user_has_locations', function ($q) use ($centreId) {
                    if ($centreId !== 'all') {
                        $q->where('location_id', $centreId);
                    }
                })
                ->pluck('id');

            // Get doctors for the location(s)
            if ($centreId === 'all') {
                // Get all locations user has access to
                $userLocations = \App\Helpers\ACL::getUserCentres();
                $doctorIds = DB::table('doctor_has_locations')
                    ->whereIn('location_id', $userLocations)
                    ->whereIn('user_id', $roleHasUsers)
                    ->distinct()
                    ->pluck('user_id');
            } else {
                $doctorIds = DB::table('doctor_has_locations')
                    ->where('location_id', $centreId)
                    ->whereIn('user_id', $roleHasUsers)
                    ->distinct()
                    ->pluck('user_id');
            }

            $allSellerIds = $doctorIds->merge($fdmUserIds)->unique();

            if ($allSellerIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No doctors found for the selected location.',
                    'data' => [],
                ]);
            }

            // Get all active users (doctors, consultants, FDMs) for the location
            $allActiveUsers = User::whereIn('id', $allSellerIds)
                ->where('active', 1)
                ->select('id', 'name')
                ->get()
                ->keyBy('id');

            // Get package services created in the date range
            $packageServicesQuery = PackageService::query()
                ->join('packages', 'package_services.package_id', '=', 'packages.id')
                ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
                ->whereIn('package_services.sold_by', $allSellerIds)
                ->whereBetween('package_services.created_at', [$startDate, $endDate])
                ->whereNotNull('sold_by');

            // Apply location filter
            if ($centreId !== 'all') {
                $packageServicesQuery->where('packages.location_id', $centreId);
            } else {
                $userLocations = \App\Helpers\ACL::getUserCentres();
                $packageServicesQuery->whereIn('packages.location_id', $userLocations);
            }

            $packageServices = $packageServicesQuery
                ->select(
                    'package_services.id',
                    'package_services.package_id',
                    'package_services.sold_by',
                    'package_services.tax_including_price',
                    'package_services.created_at',
                    'appointments.appointment_type_id',
                    'appointments.doctor_id as appointment_doctor_id'
                )
                ->orderBy('package_services.created_at')
                ->get();

            // Initialize upselling amounts for each doctor
            $doctorUpsellingAmounts = [];
            foreach ($allSellerIds as $sellerId) {
                $doctorUpsellingAmounts[$sellerId] = 0;
            }

            // Group services by package_id for processing
            $servicesByPackage = $packageServices->groupBy('package_id');

foreach ($servicesByPackage as $packageId => $services) {
    // Filter out any services with null created_at BEFORE grouping
    $servicesWithTimestamps = $services->filter(function($service) {
        return !is_null($service->created_at) && !is_null($service->sold_by);
    });
    
    if ($servicesWithTimestamps->isEmpty()) {
        continue;
    }
    
    // Sort all services by created_at, then by id to maintain consistent order
    $sortedServices = $servicesWithTimestamps->sortBy([
        ['created_at', 'asc'],
        ['id', 'asc']
    ])->values();
    
    foreach ($sortedServices as $index => $service) {
        // Skip self-consultation sales
        if ($service->appointment_type_id == 1 && $service->appointment_doctor_id == $service->sold_by) {
            continue;
        }
        
        $soldById = (int)$service->sold_by;
        
        if (!isset($doctorUpsellingAmounts[$soldById])) {
            continue;
        }
        
        $serviceCreatedAt = Carbon::parse($service->created_at);
        $serviceAmount = $service->tax_including_price;
        
        // Find the next service (regardless of same timestamp or not)
        $nextService = null;
        if ($index < count($sortedServices) - 1) {
            $nextService = $sortedServices[$index + 1];
        }
        
        // Build payment query - get payments after this service on same day
        $paymentsQuery = DB::table('package_advances')
            ->where('package_id', $packageId)
            ->where('cash_flow', 'in')
            ->where('is_refund', 0)
            ->where('is_adjustment', 0)
            ->whereDate('created_at', $serviceCreatedAt->toDateString())
            ->where('created_at', '>', $service->created_at)
            ->whereBetween('created_at', [$startDate, $endDate]);
        
        // If there's a next service on the SAME DAY, limit payments to before that service
        if ($nextService) {
            $nextServiceTime = Carbon::parse($nextService->created_at);
            if ($nextServiceTime->toDateString() === $serviceCreatedAt->toDateString()) {
                $paymentsQuery->where('created_at', '<=', $nextService->created_at);
            }
        }
        
        $paymentsForThisService = $paymentsQuery->sum('cash_amount');
        
        // Calculate upselling amount
        if ($paymentsForThisService > 0) {
            if ($paymentsForThisService >= $serviceAmount) {
                // Payment covers full service amount
                $upsellingAmount = $serviceAmount;
            } else {
                // Payment is less than service amount
                $upsellingAmount = $paymentsForThisService;
            }
            
            $doctorUpsellingAmounts[$soldById] += $upsellingAmount;
        }
    }
}

            // Prepare the final report data
            $reportData = $allActiveUsers->map(function ($user) use ($doctorUpsellingAmounts) {
                return (object)[
                    'doctor_id' => $user->id,
                    'doctor_name' => $user->name,
                    'total_upselling_amount' => $doctorUpsellingAmounts[$user->id] ?? 0,
                ];
            })->sortByDesc('total_upselling_amount')->values();

            return response()->json([
                'success' => true,
                'message' => 'Doctor payment-based upselling data retrieved successfully.',
                'data' => $reportData,
            ]);

        } catch (\Exception $e) {
            \Log::error('Doctor Payment-Based Upselling Data Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving doctor payment-based upselling data.',
                'data' => [],
            ], 500);
        }
    }

    // Function to generate Excel file with separate sheets
    private function generateExcelFile($allCentreData, $fileName, $periodInfo)
    {
        try {
            // Check if PhpSpreadsheet is available
            if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                throw new \Exception('PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet');
            }

            $spreadsheet = new Spreadsheet();
            
            // Remove default worksheet
            $spreadsheet->removeSheetByIndex(0);
            
            $sheetIndex = 0;
            foreach ($allCentreData as $centreInfo) {
                // Create worksheet for each centre
                $worksheet = $spreadsheet->createSheet($sheetIndex);
                
                // Clean sheet name (Excel has restrictions)
                $sheetName = substr(str_replace(['/', '*', '?', ':', '[', ']'], '', $centreInfo['centre_name']), 0, 31);
                $worksheet->setTitle($sheetName);
                
                // Set headers
                $worksheet->setCellValue('A1', 'Doctor Upselling Report - ' . $centreInfo['centre_name']);
                $worksheet->setCellValue('A2', 'Period: ' . $periodInfo['label']);
                $worksheet->setCellValue('A3', 'Date Range: ' . $periodInfo['start_date'] . ' to ' . $periodInfo['end_date']);
                
                // Style the header
                $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $worksheet->getStyle('A2:A3')->getFont()->setBold(true);
                
                // Table headers
                $worksheet->setCellValue('A5', 'Doctor ID');
                $worksheet->setCellValue('B5', 'Doctor Name');
                $worksheet->setCellValue('C5', 'Total Upselling Amount');
                
                // Style table headers
                $worksheet->getStyle('A5:C5')->getFont()->setBold(true);
                $worksheet->getStyle('A5:C5')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E2EFDA');
                
                // Add data
                $row = 6;
                $totalAmount = 0;
                
                if (!empty($centreInfo['data'])) {
                    foreach ($centreInfo['data'] as $doctorData) {
                        $worksheet->setCellValue('A' . $row, $doctorData['doctor_id']);
                        $worksheet->setCellValue('B' . $row, $doctorData['doctor_name']);
                        $worksheet->setCellValue('C' . $row, $doctorData['total_upselling_amount']);
                        
                        // Format currency
                        $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                        
                        $totalAmount += $doctorData['total_upselling_amount'];
                        $row++;
                    }
                } else {
                    // Show "No data found" if centre has no data
                    $worksheet->setCellValue('A6', 'No data found for this centre');
                    $worksheet->mergeCells('A6:C6');
                    $worksheet->getStyle('A6')->getFont()->setItalic(true);
                    $row = 7;
                }
                
                // Add total row
                $worksheet->setCellValue('A' . $row, '');
                $worksheet->setCellValue('B' . $row, 'TOTAL');
                $worksheet->setCellValue('C' . $row, $totalAmount);
                $worksheet->getStyle('B' . $row . ':C' . $row)->getFont()->setBold(true);
                $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
                
                // Auto-size columns
                $worksheet->getColumnDimension('A')->setAutoSize(true);
                $worksheet->getColumnDimension('B')->setAutoSize(true);
                $worksheet->getColumnDimension('C')->setAutoSize(true);
                
                // Add borders to data table
                $tableRange = 'A5:C' . $row;
                $worksheet->getStyle($tableRange)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                
                $sheetIndex++;
            }
            
            // Set first sheet as active
            if (count($allCentreData) > 0) {
                $spreadsheet->setActiveSheetIndex(0);
            }
            
            // Generate and download file
            $writer = new Xlsx($spreadsheet);
            
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'doctor_upselling_');
            $writer->save($tempFile);
            
            // Return file download response
            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            \Log::error('Excel Generation Error: ' . $e->getMessage());
            throw new \Exception('Excel generation failed: ' . $e->getMessage());
        }
    }
}
