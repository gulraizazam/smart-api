<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Filters;
use App\Models\AppointmentStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AppointmentStatusService
{
    /**
     * Process bulk delete and return base records array.
     */
    public function processBulkDelete(array $filters, int $accountId): void
    {
        $ids = explode(',', $filters['delete']);
        $statuses = AppointmentStatuses::getBulkData($ids);
        if ($statuses) {
            foreach ($statuses as $status) {
                if (! AppointmentStatuses::isChildExists($status->id, $accountId)) {
                    $status->delete();
                }
            }
        }
    }

    /**
     * Get total record count for datatable.
     */
    public function getTotalRecords(Request $request, int $accountId, bool $applyFilter): int
    {
        return (int) AppointmentStatuses::getTotalRecords($request, $accountId, $applyFilter);
    }

    /**
     * Get paginated rows for datatable, with display transformations applied.
     */
    public function getDatatableRows(Request $request, int $start, int $length, int $accountId, bool $applyFilter): mixed
    {
        $allStatuses = AppointmentStatuses::getAllRecordsDictionary($accountId);

        $rows = AppointmentStatuses::getRecords($request, $start, $length, $accountId, $applyFilter);

        if ($rows) {
            foreach ($rows as $row) {
                // Overwrite parent_id with the parent name (or '-' for top-level records).
                $row->parent_id = ($row->parent_id && array_key_exists($row->parent_id, $allStatuses))
                    ? $allStatuses[$row->parent_id]->name
                    : '-';
                $row->is_comment     = $row->is_comment ? 'Yes' : 'No';
                // After reassignment parent_id is a string; preserve original loose != 0 comparison.
                $row->allow_message  = ($row->parent_id != 0) ? ($row->allow_message ? 'Yes' : 'No') : '-';
                $row->is_default     = ($row->parent_id != 0) ? ($row->is_default ? 'Yes' : 'No') : '-';
                $row->is_arrived     = $row->is_arrived ? 'Yes' : 'No';
                $row->is_cancelled   = $row->is_cancelled ? 'Yes' : 'No';
                $row->is_unscheduled = $row->is_unscheduled ? 'Yes' : 'No';
            }
        }

        return $rows;
    }

    /**
     * Get filter values for the datatable dropdown.
     */
    public function getFilterValues(int $accountId): array
    {
        return [
            'parents' => AppointmentStatuses::getParentRecords(false, $accountId, [], true),
            'status'  => config('constants.status'),
        ];
    }

    /**
     * Get the active filters for the current user.
     */
    public function getActiveFilters(): array
    {
        return Filters::all(Auth::id(), 'appointment_statuses');
    }

    /**
     * Create a new appointment status.
     */
    public function create(Request $request, int $accountId): bool
    {
        return (bool) AppointmentStatuses::createRecord($request, $accountId);
    }

    /**
     * Get a single record for editing.
     */
    public function getForEdit(int $id, int $accountId): ?object
    {
        $status = AppointmentStatuses::getData($id);
        if (! $status) {
            return null;
        }

        $status->parent_options = AppointmentStatuses::getParentRecords(false, $accountId, $status->id, true);

        return $status;
    }

    /**
     * Update an existing appointment status.
     */
    public function update(int $id, Request $request, int $accountId): bool
    {
        return (bool) AppointmentStatuses::updateRecord($id, $request, $accountId);
    }

    /**
     * Delete an appointment status record.
     */
    public function delete(int $id): Collection
    {
        return AppointmentStatuses::deleteRecord($id);
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(int $id, int $status): Collection
    {
        return $status === 0
            ? AppointmentStatuses::inactiveRecord($id)
            : AppointmentStatuses::activeRecord($id);
    }

    /**
     * Get parent records for the create form dropdown.
     */
    public function getParentRecords(int $accountId): mixed
    {
        return AppointmentStatuses::getParentRecords('Parent Group', $accountId, false, true);
    }
}
