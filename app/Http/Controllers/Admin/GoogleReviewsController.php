<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\DoctorGoogleReview;
use App\Services\DoctorDashboard\DoctorIdentifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GoogleReviewsController extends Controller
{
    public $success;
    public $error;

    private DoctorIdentifier $doctorIdentifier;

    public function __construct(DoctorIdentifier $doctorIdentifier)
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->doctorIdentifier = $doctorIdentifier;
    }

    /**
     * Display Google Reviews management page.
     */
    public function index()
    {
        if (!Gate::allows('centre_targets_manage')) {
            return abort(401);
        }

        return view('admin.google_reviews.index');
    }

    /**
     * Get reviews grid data for a given month/year.
     */
    public function getData(Request $request)
    {
        try {
            $accountId = Auth::user()->account_id;
            $month = (int) $request->get('month', now()->month);
            $year = (int) $request->get('year', now()->year);

            // Get all active doctors
            $doctorIds = $this->doctorIdentifier->getAllActiveDoctorIds($accountId);
            $doctors = [];

            foreach ($doctorIds as $docId) {
                $info = $this->doctorIdentifier->getDoctorInfo($docId);
                if (!empty($info)) {
                    $doctors[] = $info;
                }
            }

            // Get existing reviews for this month
            $reviews = DoctorGoogleReview::getForMonth($month, $year, $accountId);

            // Build grid data
            $grid = [];
            foreach ($doctors as $doctor) {
                $review = $reviews->get($doctor['id']);
                $grid[] = [
                    'doctor_id' => $doctor['id'],
                    'doctor_name' => $doctor['name'],
                    'locations' => collect($doctor['locations'])->pluck('name')->implode(', '),
                    'review_count' => $review ? (int) $review->review_count : 0,
                ];
            }

            return ApiHelper::apiResponse($this->success, 'Reviews data loaded', true, [
                'grid' => $grid,
                'month' => $month,
                'year' => $year,
            ]);
        } catch (\Exception $e) {
            \Log::error('Google Reviews getData Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    /**
     * Save a single doctor's review count (immediate save).
     */
    public function save(Request $request)
    {
        try {
            $request->validate([
                'doctor_id' => 'required|integer|exists:users,id',
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020',
                'review_count' => 'required|integer|min:0',
            ]);

            $accountId = Auth::user()->account_id;

            DoctorGoogleReview::updateOrCreate(
                [
                    'account_id' => $accountId,
                    'doctor_id' => $request->doctor_id,
                    'month' => $request->month,
                    'year' => $request->year,
                ],
                [
                    'review_count' => $request->review_count,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]
            );

            return ApiHelper::apiResponse($this->success, 'Review count saved', true);
        } catch (\Exception $e) {
            \Log::error('Google Reviews save Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }
}
