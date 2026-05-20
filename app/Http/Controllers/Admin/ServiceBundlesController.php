<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class ServiceBundlesController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('bundles.list.view')) {
            abort(401);
        }

        return view('admin.service-bundles.index');
    }

    public function sort(): \Illuminate\View\View
    {
        if (! Gate::allows('bundles.sort')) {
            abort(401);
        }

        return view('admin.service-bundles.sort');
    }
}
