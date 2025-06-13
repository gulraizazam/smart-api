<?php

use App\Helpers\Filters;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\Auth;

function getSortBy($request, $orderBy = 'name', $order = 'asc', $prefix = null)
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

function getPaginationElement($request, $iTotalRecords, $defaultPerPage = 30)
{

    $iDisplayLength = intval($request->pagination['perpage'] ?? $defaultPerPage);

    $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
    $iDisplayStart = intval(isset($request->pagination['page']) ? (($request->pagination['page'] - 1) * $iDisplayLength) : 0);
    $page = intval($request->pagination['page'] ?? 1);
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

function getFilters($filters)
{
    if (isset($filters['query']) && isset($filters['query']['search'])) {
        return $filters['query']['search'];
    }

    return [];
}

function hasFilter($filters, $key): bool
{
    if (isset($filters) && count($filters) > 0 && isset($filters[$key]) && $filters[$key] != '' && $filters[$key] != null) {
        return true;
    }

    return false;
}

function checkFilters($filters, $key): bool
{
    $apply_filter = false;
    if (count($filters) > 0 && hasFilter($filters, 'filter')) {
        $action = $filters['filter'];
        if ($action == 'filter_cancel') {
            Filters::flush(Auth::User()->id, $key);
        } elseif ($action == 'filter') {
            $apply_filter = true;
        }
    }

    return $apply_filter;
}

function openMenu($routes, $class = 'menu-item-open')
{
    if (in_array(request()->route()->getName(), $routes)) {
        return $class;
    }

    return '';
}

function activeMenu($route, $class = 'menu-item-active', $queryString = null)
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

function isActive($url, $query = 'junk')
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

function getPatientName($id)
{
    return \App\Models\Patients::find($id)?->name ?? '';
}

function getPatientInfo()
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


