<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingDayException extends Model
{
    protected $table = 'working_day_exceptions';

    protected $fillable = [
        'account_id',
        'exception_date',
        'is_working',
        'title',
        'created_by',
    ];

    protected $casts = [
        'exception_date' => 'date',
        'is_working' => 'boolean',
    ];

    /**
     * Check if a specific date has an exception
     */
    public static function getExceptionForDate($accountId, $date)
    {
        return self::where('account_id', $accountId)
            ->whereDate('exception_date', $date)
            ->first();
    }

    /**
     * Check if a date is a working day considering exceptions
     */
    public static function isWorkingDay($accountId, $date, $defaultWorkingDays)
    {
        $dateCarbon = \Carbon\Carbon::parse($date);
        $dayOfWeek = $dateCarbon->dayOfWeek;
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $dayName = $dayNames[$dayOfWeek];

        // Check for exception first
        $exception = self::getExceptionForDate($accountId, $date);
        if ($exception) {
            return $exception->is_working;
        }

        // Fall back to default working days
        return isset($defaultWorkingDays[$dayName]) && $defaultWorkingDays[$dayName];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
