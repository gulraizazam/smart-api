<?php

declare(strict_types=1);

namespace App\Services\Town;

use App\Helpers\Filters;
use App\Models\Cities;
use App\Models\Towns;
use App\Support\SafeFilename;
use Carbon\Carbon;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TownService
{
    /**
     * Bulk delete towns from datatable filter, skipping those with child records.
     */
    public function processBulkDelete(array $filters, int $accountId): void
    {
        $ids   = explode(',', $filters['delete']);
        $towns = Towns::getBulkData($ids);

        if ($towns) {
            foreach ($towns as $town) {
                if (! Towns::isChildExists($town->id, $accountId)) {
                    $town->delete();
                }
            }
        }
    }

    /**
     * Total record count for datatable.
     */
    public function getTotalRecords(Request $request, int $accountId, bool $applyFilter): int
    {
        return (int) Towns::getTotalRecords($request, $accountId, $applyFilter);
    }

    /**
     * Paginated rows for datatable.
     */
    public function getDatatableRows(Request $request, int $start, int $length, int $accountId, bool $applyFilter): mixed
    {
        return Towns::getRecords($request, $start, $length, $accountId, $applyFilter);
    }

    /**
     * Active filter snapshot for the current user.
     */
    public function getActiveFilters(): array
    {
        return Filters::all(Auth::id(), 'towns');
    }

    /**
     * Cities dropdown for the datatable filter (slug=custom, active).
     */
    public function getCitiesForFilter(int $accountId): mixed
    {
        $cities = Cities::where([
            ['account_id', '=', $accountId],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
        ])->pluck('name', 'id');

        $cities->prepend('Select a City', '');

        return $cities;
    }

    /**
     * Cities dropdown for the create form (full_name, slug=custom, active).
     */
    public function getCitiesForCreate(int $accountId): mixed
    {
        $cities = Cities::where([
            ['account_id', '=', $accountId],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
        ])->pluck('full_name', 'id');

        $cities->prepend('Select a City', '');

        return $cities;
    }

    /**
     * Cities dropdown for the edit form (full_name, slug=custom, active, is_featured=1).
     */
    public function getCitiesForEdit(int $accountId): mixed
    {
        $cities = Cities::where([
            ['account_id', '=', $accountId],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->pluck('full_name', 'id');

        $cities->prepend('Select a City', '');

        return $cities;
    }

    /**
     * Create a new town record.
     */
    public function create(Request $request, int $accountId): bool
    {
        return (bool) Towns::createRecord($request, $accountId);
    }

    /**
     * Get a single town for editing.
     */
    public function getForEdit(int $id): ?object
    {
        return Towns::getData($id);
    }

    /**
     * Update an existing town record.
     */
    public function update(int $id, Request $request, int $accountId): bool
    {
        return (bool) Towns::updateRecord($id, $request, $accountId);
    }

    /**
     * Delete a town record.
     */
    public function delete(int $id): array
    {
        return Towns::DeleteRecord($id);
    }

    /**
     * Toggle the active status of a town.
     */
    public function toggleStatus(int $id, int $status): bool
    {
        return (bool) Towns::activeRecord($id, $status);
    }

    /**
     * Import towns from an uploaded Excel file.
     *
     * @throws \Exception if file format is invalid or no data found
     */
    public function importFromFile(Request $request, int $accountId): void
    {
        $dir = public_path('/towndata');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 777, true, true);
            File::put($dir . '/index.html', 'Direct access is forbidden');
        }

        $file = $request->file('towns_file');
        // Sanitise the client-supplied name before joining it to a path
        // under public/. The spreadsheet importer only needs a unique
        // disk name; the original name is not surfaced to the UI.
        $safe    = SafeFilename::sanitize(
            $file->getClientOriginalName(),
            ['xls', 'xlsx', 'csv'],
        );
        $newName = pathinfo($safe, PATHINFO_FILENAME)
            . '-' . random_int(11111111, 99999999)
            . '.' . pathinfo($safe, PATHINFO_EXTENSION);

        $file->move($dir, $newName);

        $spreadSheet = IOFactory::load($dir . DIRECTORY_SEPARATOR . $newName);
        $sheetData   = $spreadSheet->getActiveSheet(0)->toArray(null, true, true, true);

        if (empty($sheetData)) {
            throw new \Exception('No input file specified..');
        }

        if (
            ! isset($sheetData[1]) ||
            trim(strtolower($sheetData[1]['A'])) !== 'name'  ||
            trim(strtolower($sheetData[1]['B'])) !== 'city'  ||
            trim(strtolower($sheetData[1]['C'])) !== 'active' ||
            trim(strtolower($sheetData[1]['D'])) !== 'account'
        ) {
            throw new \Exception('Invalid data provided. Pattern should: name, city, active, account');
        }

        $townData = [];
        $count    = 0;

        foreach ($sheetData as $row) {
            if ($count !== 0) {
                $cityInfo   = Cities::where('name', '=', $row['B'])->first();
                $townData[] = [
                    'name'       => $row['A'],
                    'city_id'    => $cityInfo->id,
                    'active'     => $row['C'],
                    'account_id' => $row['D'],
                    'created_at' => Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->format('Y-m-d'),
                    'updated_at' => Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->format('Y-m-d'),
                ];
            }
            $count++;
        }

        Towns::insert($townData);
    }
}
