<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Helpers\Filters;
use Illuminate\Support\Facades\Auth;

class ProductsController extends Controller
{
    
    /**
     * Display a listing of products.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $filters = Filters::all(Auth::User()->id, 'products');

        return view('admin.products.index', compact('filters'));
    }
}
