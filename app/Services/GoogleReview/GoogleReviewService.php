<?php

declare(strict_types=1);

namespace App\Services\GoogleReview;

use App\Models\DoctorGoogleReview;
use App\Services\DoctorDashboard\DoctorIdentifier;
use Illuminate\Support\Facades\Auth;

class GoogleReviewService
{
    private DoctorIdentifier $doctorIdentifier;

    public function __construct(DoctorIdentifier $doctorIdentifier)
    {
        $this->doctorIdentifier = $doctorIdentifier;
    }

    public function getGridData(int $accountId, int $month, int $year): array
    {
        $doctorIds = $this->doctorIdentifier->getAllActiveDoctorIds($accountId);
        $doctors = [];

        foreach ($doctorIds as $docId) {
            $info = $this->doctorIdentifier->getDoctorInfo($docId);
            if (!empty($info)) {
                $doctors[] = $info;
            }
        }

        $reviews = DoctorGoogleReview::getForMonth($month, $year, $accountId);

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

        return [
            'grid' => $grid,
            'month' => $month,
            'year' => $year,
        ];
    }

    public function saveReview(int $accountId, int $doctorId, int $month, int $year, int $reviewCount, int $authUserId): bool
    {
        $review = DoctorGoogleReview::firstOrNew([
            'account_id' => $accountId,
            'doctor_id' => $doctorId,
            'month' => $month,
            'year' => $year,
        ]);

        if (!$review->exists) {
            $review->created_by = $authUserId;
        }

        $review->review_count = $reviewCount;
        $review->updated_by = $authUserId;
        $review->save();

        return true;
    }
}
