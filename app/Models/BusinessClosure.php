<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class BusinessClosure extends Model
{
    use SoftDeletes;

    protected $table = 'business_closures';

    protected $fillable = [
        'account_id',
        'title',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $dates = [
        'start_date',
        'end_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the locations for this business closure
     */
    public function locations()
    {
        return $this->belongsToMany(Locations::class, 'business_closure_locations', 'business_closure_id', 'location_id');
    }

    /**
     * Get the user who created this closure
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the account
     */
    public function account()
    {
        return $this->belongsTo(Accounts::class, 'account_id');
    }

    /**
     * Scope to filter by account
     */
    public function scopeByAccount($query, $accountId = null)
    {
        $accountId = $accountId ?? Auth::user()->account_id;
        return $query->where('account_id', $accountId);
    }

    /**
     * Check if closure applies to a specific location
     */
    public function appliesToLocation($locationId)
    {
        if ($this->locations->isEmpty()) {
            return true;
        }
        return $this->locations->contains('id', $locationId);
    }

    /**
     * Check if a date falls within this closure period
     */
    public function coversDate($date)
    {
        $checkDate = \Carbon\Carbon::parse($date)->startOfDay();
        $startDate = \Carbon\Carbon::parse($this->start_date)->startOfDay();
        $endDate = \Carbon\Carbon::parse($this->end_date)->startOfDay();

        return $checkDate->between($startDate, $endDate);
    }

    /**
     * Get closures that affect a specific date and location
     */
    public static function getClosuresForDateAndLocation($date, $locationId, $accountId = null)
    {
        $accountId = $accountId ?? Auth::user()->account_id;

        return self::where('account_id', $accountId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationId) {
                $query->whereHas('locations', function ($q) use ($locationId) {
                    $q->where('location_id', $locationId);
                })->orWhereDoesntHave('locations');
            })
            ->get();
    }

    /**
     * Check if business is closed on a specific date for a location
     */
    public static function isClosedOnDate($date, $locationId, $accountId = null)
    {
        return self::getClosuresForDateAndLocation($date, $locationId, $accountId)->isNotEmpty();
    }
}
