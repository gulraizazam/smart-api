<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class BusinessClosureController extends Controller
{
    /**
     * Display the business closures listing page.
     *
     * @return \Illuminate\View\View
     */
    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('business_closures.list.view')) {
            abort(401);
        }

        return view('admin.business-closures.index');
    }
}
