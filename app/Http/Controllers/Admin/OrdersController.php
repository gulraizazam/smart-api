<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Helpers\Filters;
use Illuminate\Support\Facades\Auth;

class OrdersController extends Controller
{
    /**
     * Display a listing of orders.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $filters = Filters::all(Auth::User()->id, 'orders');

        return view('admin.orders.index', compact('filters'));
    }

    /**
     * Display a listing of orders.
     *
     * @return \Illuminate\Http\Response
     */
    public function refund()
    {
        $filters = Filters::all(Auth::User()->id, 'orders');

        return view('admin.orders.index', compact('filters'));
    }
}
