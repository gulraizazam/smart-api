<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityLogService
{
    /**
     * Get activity logs with optional filters
     * 
     * @param array $filters - Optional filters: patient_id, start_date, end_date, service_id, location_id, activity_type, user_id
     * @return array
     */
    public static function getActivityLogs(array $filters = []): array
    {
        $query = Activity::with(['user', 'serviceR', 'patientR', 'centre'])
            ->where('account_id', Auth::user()->account_id);

        // Filter by patient_id if provided
        if (!empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        // Filter by date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $startDate = $filters['start_date'] . ' 00:00:00';
            $endDate = $filters['end_date'] . ' 23:59:59';
            
            $query->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->orWhereBetween('updated_at', [$startDate, $endDate]);
            });
        }

        // Filter by service_id
        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        // Filter by location_id (centre_id)
        if (!empty($filters['location_id'])) {
            $query->where('centre_id', $filters['location_id']);
        }

        // Filter by activity_type
        if (!empty($filters['activity_type']) && $filters['activity_type'] !== 'all') {
            $query->where('activity_type', $filters['activity_type']);
        }

        // Filter by user_id
        if (!empty($filters['user_id'])) {
            $query->where('created_by', $filters['user_id']);
        }

        $activities = $query->orderBy('created_at', 'desc')->get();

        return self::formatActivities($activities);
    }

    /**
     * Format activities for display
     */
    private static function formatActivities($activities): array
    {
        $data = [];
        
        foreach ($activities as $activity) {
            $timestamp = $activity->updated_at ?? $activity->created_at;
            
            $data[] = [
                'type' => $activity->activity_type ?? $activity->action ?? 'unknown',
                'description' => $activity->description ?? self::buildActivityDescription($activity),
                'created_at' => $timestamp,
                'time_formatted' => date('M j, Y g:i A', strtotime($timestamp)),
                'time_short' => date('m-d-Y H:i', strtotime($timestamp)),
            ];
        }

        return $data;
    }

    /**
     * Build activity description from activity record if description is not set
     */
    private static function buildActivityDescription($activity): string
    {
        $action = $activity->action ?? 'Activity';
        $patient = $activity->patientR->name ?? $activity->patient ?? '';
        $service = $activity->serviceR->name ?? $activity->service ?? '';
        $location = $activity->centre->name ?? $activity->location ?? '';
        $amount = $activity->amount ?? '';
        $planId = $activity->planId ?? $activity->plan_id ?? '';
        $appointmentType = $activity->appointment_type ?? '';
        $scheduleDate = $activity->schedule_date ?? '';
        $createdBy = $activity->created_by ?? '';
        
        // Get creator name
        $creatorName = self::getCreatorName($activity);
        
        // Format date
        $dateStr = '';
        if ($scheduleDate) {
            $dateStr = date('M j, Y', strtotime($scheduleDate));
        } elseif ($activity->created_at) {
            $dateStr = date('M j, Y', strtotime($activity->created_at));
        }
        
        $type = $activity->activity_type ?? '';
        
        switch ($type) {
            case 'lead_created':
                return '<span class="highlight">' . $creatorName . '</span> created a lead for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'lead_booked':
                return '<span class="highlight">' . $creatorName . '</span> booked a <span class="highlight-orange">' . ($service ?: 'Service') . '</span> Consultation for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'lead_arrived':
                return '<span class="highlight">' . $creatorName . '</span> marked <span class="highlight-orange">' . $patient . '</span> as arrived' . ($service ? ' for <span class="highlight-orange">' . $service . '</span>' : '') . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'consultation_booked':
            case 'Consultancy':
                return '<span class="highlight">' . $creatorName . '</span> booked <span class="highlight-orange">' . ($service ?: 'Service') . '</span> Consultation for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'treatment_booked':
                return '<span class="highlight">' . $creatorName . '</span> booked <span class="highlight-orange">' . ($service ?: 'Service') . '</span> Treatment for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'package_created':
                return '<span class="highlight">' . $creatorName . '</span> created Package <span class="highlight-purple">Plan Id: ' . $planId . '</span>' . ($amount ? ' for Rs. ' . number_format($amount) : '') . ' for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'payment_received':
                return '<span class="highlight">' . $creatorName . '</span> received payment <span class="highlight-green">Rs. ' . number_format($amount) . '</span> from <span class="highlight-orange">' . $patient . '</span>' . ($planId ? ' for <span class="highlight-purple">Plan Id: ' . $planId . '</span>' : '') . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'refund_made':
                return '<span class="highlight">' . $creatorName . '</span> made refund <span class="highlight-green">Rs. ' . number_format($amount) . '</span> to <span class="highlight-orange">' . $patient . '</span>' . ($planId ? ' for <span class="highlight-purple">Plan Id: ' . $planId . '</span>' : '') . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'invoice_created':
                return '<span class="highlight">' . $creatorName . '</span> created invoice <span class="highlight-green">Rs. ' . number_format($amount) . '</span> for <span class="highlight-orange">' . ($appointmentType ?: $service ?: 'Consultation') . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'appointment_updated':
                return '<span class="highlight">' . $creatorName . '</span> updated <span class="highlight-orange">' . ($service ?: 'Service') . '</span> appointment for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'membership_assigned':
                return '<span class="highlight">' . $creatorName . '</span> assigned membership to <span class="highlight-orange">' . $patient . '</span>' . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'membership_cancelled':
                return '<span class="highlight">' . $creatorName . '</span> cancelled membership for <span class="highlight-orange">' . $patient . '</span>' . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'appointment_converted':
                return '<span class="highlight-orange">' . $patient . '</span>\'s <span class="highlight-orange">' . ($service ?: 'Service') . '</span> Consultation status changed to <span class="highlight-green">Converted</span>' . ($amount ? ' with payment of <span class="highlight-green">Rs. ' . number_format($amount) . '</span>' : '') . ($planId ? ' in <span class="highlight-purple">Plan #' . sprintf('%05d', $planId) . '</span>' : '');
            
            case 'lead_converted':
                return '<span class="highlight-orange">' . ($service ?: 'Service') . '</span> lead status changed to <span class="highlight-green">Converted</span> against <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($amount ? ' with payment of <span class="highlight-green">Rs. ' . number_format($amount) . '</span>' : '');
            
            case 'consultation_deleted':
            case 'treatment_deleted':
                $apptType = $type == 'consultation_deleted' ? 'Consultation' : 'Treatment';
                return '<span class="highlight">' . $creatorName . '</span> deleted <span class="highlight-orange">' . $patient . '</span>\'s <span class="highlight-orange">' . ($service ?: 'Service') . '</span> ' . $apptType . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' scheduled on ' . $dateStr : '');
            
            case 'patient_updated':
                return '<span class="highlight">' . $creatorName . '</span> updated <span class="highlight-orange">' . $patient . '</span>\'s profile' . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'invoice_cancelled':
                return '<span class="highlight">' . $creatorName . '</span> cancelled invoice <span class="highlight-green">Rs. ' . number_format($amount) . '</span> for <span class="highlight-orange">' . $patient . '</span>\'s <span class="highlight-orange">' . ($service ?: $appointmentType ?: 'Consultation') . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'feedback_added':
                return '<span class="highlight">' . $creatorName . '</span> added feedback for <span class="highlight-orange">' . ($service ?: 'Service') . '</span> against <span class="highlight-orange">' . $patient . '</span>' . ($dateStr ? ' scheduled on <span class="highlight-purple">' . $dateStr . '</span>' : '');
            
            case 'voucher_assigned':
                return '<span class="highlight">' . $creatorName . '</span> assigned voucher of <span class="highlight-green">Rs. ' . number_format($amount) . '</span> to <span class="highlight-orange">' . $patient . '</span>';
            
            case 'voucher_updated':
                return '<span class="highlight">' . $creatorName . '</span> updated voucher amount to <span class="highlight-green">Rs. ' . number_format($amount) . '</span> for <span class="highlight-orange">' . $patient . '</span>';
            
            case 'voucher_consumed':
                return '<span class="highlight-green">Rs. ' . number_format($amount) . '</span> consumed from voucher against <span class="highlight-orange">' . $patient . '</span>';
            
            case 'voucher_refunded':
                return '<span class="highlight-green">Rs. ' . number_format($amount) . '</span> refunded to voucher for <span class="highlight-orange">' . $patient . '</span>';
            
            case 'note_added':
                return '<span class="highlight">' . $creatorName . '</span> added a note for <span class="highlight-orange">' . $patient . '</span>' . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'note_updated':
                return '<span class="highlight">' . $creatorName . '</span> updated a note for <span class="highlight-orange">' . $patient . '</span>' . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'note_deleted':
                return '<span class="highlight">' . $creatorName . '</span> deleted a note for <span class="highlight-orange">' . $patient . '</span>' . ($dateStr ? ' on ' . $dateStr : '');
            
            default:
                // Fallback for existing records - check action field
                return self::buildFallbackDescription($activity, $creatorName, $patient, $service, $location, $amount, $planId, $appointmentType, $dateStr);
        }
    }

    /**
     * Build fallback description for old records based on action field
     */
    private static function buildFallbackDescription($activity, $creatorName, $patient, $service, $location, $amount, $planId, $appointmentType, $dateStr): string
    {
        $action = $activity->action ?? '';
        
        switch ($action) {
            case 'booked':
            case 'treatment_booked':
            case 'Treatment Booked':
                $activityType = $activity->activity_type == 'Consultancy' ? 'Consultation' : ($activity->activity_type ?? 'Consultation');
                return '<span class="highlight">' . $creatorName . '</span> booked <span class="highlight-orange">' . ($service ?: 'Service') . '</span> ' . $activityType . ' for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'received':
                if ($appointmentType == "Plan" || $planId) {
                    return '<span class="highlight">' . $creatorName . '</span> received payment <span class="highlight-green">Rs. ' . number_format($amount) . '</span> from <span class="highlight-orange">' . $patient . '</span> for <span class="highlight-purple">Plan Id: ' . $planId . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
                }
                return '<span class="highlight">' . $creatorName . '</span> received payment' . ($amount ? ' <span class="highlight-green">Rs. ' . number_format($amount) . '</span>' : '') . ' from <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'consumed':
                $activityType = $activity->activity_type == 'Consultancy' ? 'Consultation' : ($activity->activity_type ?? '');
                return '<span class="highlight">' . $creatorName . '</span> consumed <span class="highlight-green">Rs. ' . number_format($amount) . '</span> from <span class="highlight-orange">' . $patient . '</span> for <span class="highlight-orange">' . ($service ?: $appointmentType ?: 'Service') . '</span> ' . $activityType . ($location ? ' at <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'rescheduled':
            case 'Appointment Rescheduled':
            case 'Appointment Updated':
                $activityType = $activity->activity_type == 'Consultancy' ? 'Consultation' : ($activity->activity_type ?? '');
                return '<span class="highlight">' . $creatorName . '</span> ' . strtolower($action) . ' <span class="highlight-orange">' . ($service ?: 'Service') . '</span> ' . $activityType . ' for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'deleted':
                $activityType = $activity->activity_type == 'Consultancy' ? 'Consultation' : ($activity->activity_type ?? '');
                return '<span class="highlight">' . $creatorName . '</span> deleted <span class="highlight-orange">' . ($service ?: 'Service') . '</span> ' . $activityType . ' for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' scheduled on ' . $dateStr : '');
            
            default:
                if ($patient) {
                    return '<span class="highlight">' . $creatorName . '</span> ' . strtolower($action ?: 'activity') . ' for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
                }
                return '<span class="highlight">' . $creatorName . '</span> performed ' . ($action ?: 'activity');
        }
    }

    /**
     * Get creator name from activity
     */
    private static function getCreatorName($activity): string
    {
        // If user relationship exists and has name, use it
        if ($activity->user && $activity->user->name) {
            return $activity->user->name;
        }
        
        // If created_by is not numeric (old records stored name directly), return it
        if (!is_numeric($activity->created_by) && $activity->created_by) {
            return $activity->created_by;
        }
        
        // If created_by is numeric, try to find the user
        if (is_numeric($activity->created_by) && $activity->created_by > 0) {
            $user = DB::table('users')->where('id', $activity->created_by)->first();
            if ($user) {
                return $user->name;
            }
        }
        
        return 'System';
    }
}
