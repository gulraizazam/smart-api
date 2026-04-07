<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleReview\GoogleReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GoogleReviewsController extends Controller
{
    public function __construct(
        private readonly GoogleReviewService $googleReviewService,
    ) {
        $this->middleware('can:google_reviews_manage');
    }

    /**
     * Display Google Reviews management page.
     */
    public function index(): mixed
    {
        if (!Gate::allows('google_reviews_manage')) {
            return abort(401);
        }

        return view('admin.google_reviews.index');
    }

    /**
     * Get reviews grid data for a given month/year.
     */
    public function getData(Request $request): mixed
    {
        try {
            $accountId = Auth::user()->account_id;
            $month = (int) $request->get('month', now()->month);
            $year = (int) $request->get('year', now()->year);

            $data = $this->googleReviewService->getGridData($accountId, $month, $year);

            return $this->successResponse('Reviews data loaded', $data, 200);
        } catch (\Exception $e) {
            \Log::error('Google Reviews getData Error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Save a single doctor's review count (immediate save).
     */
    public function save(Request $request): mixed
    {
        try {
            $request->validate([
                'doctor_id' => 'required|integer|exists:users,id',
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020',
                'review_count' => 'required|integer|min:0',
            ]);

            $accountId = Auth::user()->account_id;

            $this->googleReviewService->saveReview(
                $accountId,
                $request->doctor_id,
                $request->month,
                $request->year,
                $request->review_count,
                Auth::id(),
            );

            return $this->successResponse('Review count saved', null, 200);
        } catch (\Exception $e) {
            \Log::error('Google Reviews save Error: ' . $e->getMessage());
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
