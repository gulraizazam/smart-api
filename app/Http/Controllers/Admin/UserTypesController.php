<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     * All other operations are handled by the API controller.
     */
    public function index(): View
    {
        if (!Gate::allows('user_types_manage')) {
            abort(401);
        }

        return view('admin.user_types.index');
    }
}
