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

/**
 * Build a safe (column, direction) tuple for an Eloquent orderBy() from a
 * datatables-style request payload.
 *
 * Security: the field and direction are pulled from `request('sort')`, which
 * is user-controlled. Without sanitisation Laravel passes the column name
 * straight into a raw SQL `ORDER BY <col>` clause — confirmed exploitable
 * via UNION-style injection. We restrict the field to `[A-Za-z0-9_]` plus a
 * single optional `prefix.` segment, and the direction to `asc`/`desc`. If
 * the input fails validation we silently fall back to the caller-provided
 * defaults rather than throwing, since the helper is invoked from 70+ list
 * controllers that have no error path.
 */
function getSortBy(\Illuminate\Http\Request $request, string $orderBy = 'name', string $order = 'asc', ?string $prefix = null): array
{
    if ($request->has('sort')) {
        $candidateField = $request->get('sort')['field'] ?? null;
        $candidateOrder = $request->get('sort')['sort'] ?? null;

        // Allow `column` or `prefix.column`. Anything else (spaces, parens,
        // commas, semicolons, hyphens, etc.) is rejected — those are the
        // characters an attacker would need for SQL injection.
        if (is_string($candidateField)
            && $candidateField !== ''
            && preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $candidateField) === 1
        ) {
            $orderBy = $candidateField;
        }

        if (is_string($candidateOrder)) {
            $candidateOrder = strtolower($candidateOrder);
            if ($candidateOrder === 'asc' || $candidateOrder === 'desc') {
                $order = $candidateOrder;
            }
        }
    }

    if ($prefix && $orderBy === 'created_at') { /*to append prefix */
        $orderBy = $prefix.'.'.$orderBy;
    }

    return [$orderBy, $order];
}

/**
 * Defang a single cell value before it lands in a CSV / XLSX export.
 *
 * Round 4 Inj-H1..4 — Excel and LibreOffice both interpret a cell whose
 * first character is `=`, `+`, `-`, `@`, tab, or carriage return as a
 * formula expression. An attacker who controls any user-editable field
 * (vendor name, expense description, patient name…) can store a payload
 * like `=cmd|'/c calc'!A0` and then trigger an export. When a different
 * user opens the resulting file, the formula executes in their Excel
 * context (DDE / WEBSERVICE / hyperlink-based exfil are all possible).
 *
 * The fix is to prepend a single quote to any string starting with one
 * of those trigger characters. Excel treats `'=foo` as the literal text
 * `=foo` (the quote is not displayed). Numbers and other strings pass
 * through unchanged so legitimate exports look identical.
 *
 * Apply at the cell-value boundary, not the row level — for `fputcsv()`
 * pair this with `fputcsv_safe()` below; for PhpSpreadsheet's
 * `setCellValue()` wrap each user-controlled value individually.
 */
function csv_safe(mixed $value): mixed
{
    if (! is_string($value) || $value === '') {
        return $value;
    }

    $first = $value[0];
    if ($first === '=' || $first === '+' || $first === '-' || $first === '@'
        || $first === "\t" || $first === "\r"
    ) {
        return "'" . $value;
    }

    return $value;
}

/**
 * Drop-in replacement for `fputcsv()` that runs every cell through
 * `csv_safe()` first. Use this anywhere user data is written to CSV.
 */
function fputcsv_safe($handle, array $fields, string $separator = ',', string $enclosure = '"', string $escape = '\\'): int|false
{
    return fputcsv($handle, array_map('csv_safe', $fields), $separator, $enclosure, $escape);
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


