<?php

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
    public function index()
    {
        if (!Gate::allows('business_closures_manage')) {
            return abort('401');
        }

        return view('admin.business-closures.index');
    }
}
