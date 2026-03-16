<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorGoogleReview extends Model
{
    use HasFactory;

    protected $table = 'doctor_google_reviews';

    protected $fillable = [
        'account_id',
        'doctor_id',
        'month',
        'year',
        'review_count',
        'created_by',
        'updated_by',
    ];

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get review count for a specific doctor, month, and year.
     */
    public static function getForDoctorMonth(int $doctorId, int $month, int $year, int $accountId): ?self
    {
        return self::where([
            'doctor_id' => $doctorId,
            'month' => $month,
            'year' => $year,
            'account_id' => $accountId,
        ])->first();
    }

    /**
     * Get all reviews for a given month/year (all doctors).
     */
    public static function getForMonth(int $month, int $year, int $accountId)
    {
        return self::where([
            'month' => $month,
            'year' => $year,
            'account_id' => $accountId,
        ])->get()->keyBy('doctor_id');
    }
}
