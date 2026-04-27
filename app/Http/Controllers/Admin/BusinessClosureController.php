<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class BusinessClosureController extends Controller
{
    /**
     * Display the business closures listing page.
     *
     * @return \Illuminate\View\View
     */
    public function index(): \Illuminate\View\View
    {
        return view('admin.business-closures.index');
    }
}
