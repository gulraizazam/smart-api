<?php

declare(strict_types=1);
use App\Helpers\Filters;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\Auth;

/**
 * Safely parse a monetary string to float, preserving decimal precision.
 * Strips commas and non-numeric characters except digits, dots, and minus.
 * Replaces FILTER_SANITIZE_NUMBER_INT which destroys decimal points.
 */
function sanitize_money(mixed $value): float
{
    if (is_numeric($value)) {
        return (float) $value;
    }

    $cleaned = preg_replace('/[^\d.\-]/', '', (string) $value);

    return (float) $cleaned;
}

function getSortBy(\Illuminate\Http\Request $request, string $orderBy = 'name', string $order = 'asc', ?string $prefix = null): array
{

    if ($request->has('sort')) {
        $orderBy = $request->get('sort')['field'];
        $order = $request->get('sort')['sort'];
    }

    if ($prefix && $orderBy === 'created_at') { /*to append prefix */
        $orderBy = $prefix.'.'.$orderBy;
    }

    return [$orderBy, $order];
}

function getPaginationElement(\Illuminate\Http\Request $request, int $iTotalRecords, int $defaultPerPage = 30): array
{

    $iDisplayLength = (int) ($request->pagination['perpage'] ?? $defaultPerPage);

    $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
    $iDisplayStart = (int) (isset($request->pagination['page']) ? (($request->pagination['page'] - 1) * $iDisplayLength) : 0);
    $page = (int) ($request->pagination['page'] ?? 1);
    $pages = 7;

    if ($iDisplayLength >= $iTotalRecords) {
        $iDisplayStart = 0;
    }

    return [
        $iDisplayLength,
        $iDisplayStart,
        $pages,
        $page,
    ];
}

function getFilters(array $filters): array|string
{
    if (isset($filters['query']) && isset($filters['query']['search'])) {
        return $filters['query']['search'];
    }

    return [];
}

function hasFilter(array $filters, string $key): bool
{
    if (isset($filters) && !empty($filters) && isset($filters[$key]) && $filters[$key] != '' && $filters[$key] != null) {
        return true;
    }

    return false;
}

function checkFilters(array $filters, string $key): bool
{
    $apply_filter = false;
    if (!empty($filters) && hasFilter($filters, 'filter')) {
        $action = $filters['filter'];
        if ($action == 'filter_cancel') {
            Filters::flush(Auth::user()->id, $key);
        } elseif ($action == 'filter') {
            $apply_filter = true;
        }
    }

    return $apply_filter;
}

function openMenu(array $routes, string $class = 'menu-item-open'): string
{
    if (in_array(request()->route()->getName(), $routes, true)) {
        return $class;
    }

    return '';
}

function activeMenu(string $route, string $class = 'menu-item-active', ?string $queryString = null): string
{

    if ($queryString && request('tab') != null && request('tab') != '') {

        if (request()->route()->getName() == $route && request('tab') == $queryString) {

            return $class;
        }
    } elseif (request()->route()->getName() == $route) {
        return $class;
    }

    return '';
}

function isActive(string $url, string $query = 'junk'): string
{

    if ($query == 'junk' && request()->fullUrl() == $url) {
        return 'menu-item-active';
    } elseif ($query == 'create' && request()->fullUrl() == $url) {
        return 'menu-item-active';
    } elseif ($query == 'other' && request()->fullUrl() == $url) {
        return 'menu-item-active';
    }

    return '';
}

function getPatientName(int|string $id): string
{
    return \App\Models\Patients::find($id)?->name ?? '';
}

function getPatientInfo(): array
{
    $total_cash_in = PackageAdvances::where('cash_flow', '=', 'in')
        ->where('patient_id', request('id'))
        ->sum('cash_amount');
    $total_cash_out = PackageAdvances::where('cash_flow', '=', 'out')
        ->where('patient_id', request('id'))
        ->sum('cash_amount');

    $balance = $total_cash_in - $total_cash_out;

    return [
        $total_cash_in,
        $total_cash_out,
        $balance,
    ];
}


