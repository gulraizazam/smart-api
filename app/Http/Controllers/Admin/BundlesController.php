<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Admin Bundles Controller
 * 
 * This controller now only handles view rendering.
 * All API operations have been moved to App\Http\Controllers\Api\BundlesController
 * 
 * @see \App\Http\Controllers\Api\BundlesController for API operations
 * @see \App\Services\Bundle\BundleService for business logic
 */
class BundlesController extends Controller
{
    /**
     * Display a listing of Packages.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|never
     */
    public function index()
    {
        if (!Gate::allows('packages_manage')) {
            return abort(401);
        }

        return view('admin.bundles.index');
    }
}
