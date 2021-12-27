<?php

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
