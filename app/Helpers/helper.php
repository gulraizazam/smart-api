<?php

    use App\Helpers\Filters;

    function getSortBy($request, $orderBy = 'name', $order = 'asc') {
        if ($request->has('sort')) {
            $orderBy = $request->get('sort')['field'];
            $order = $request->get('sort')['sort'];
        }

        return [$orderBy, $order];
    }

    function getPaginationElement($iTotalRecords) {
        $iDisplayLength = intval($request->pagination['perpage'] ?? 10);
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->pagination['page'] ?? 1);
        $pages = ceil($iTotalRecords / $iDisplayLength);
        return [
            $iDisplayLength,
            $iDisplayStart,
            $pages
        ];
    }

    function getFilters($filters) {
        if (isset($filters['query']) && isset($filters['query']['search'])) {
            return $filters['query']['search'];
        }
        return [];
    }

    function hasFilter($filters, $key) {
        if (count($filters) > 0 && isset($filters[$key]) && $filters[$key] != '') {
            return true;
        }
        return false;
    }

    function checkFilters($filters, $key) {

        $apply_filter = false;
        if(count($filters) > 0 && hasFilter($filters, 'filter')) {
            $action = $filters['filter'];
            if($action == 'filter_cancel') {
                Filters::flush(Auth::User()->id, $key);
            } else if($action == 'filter') {
                $apply_filter = true;
            }
        }

        return $apply_filter;
    }
