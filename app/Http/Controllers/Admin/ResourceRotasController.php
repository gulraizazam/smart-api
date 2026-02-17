<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class ResourceRotasController extends Controller
{
    /**
     * Display the schedule calendar view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function scheduleCalendar()
    {
        if (! Gate::allows('resourcerotas_manage')) {
            return abort('401');
        }

        return view('admin.resourcerotas.schedule-calendar');
    }

    /**
     * Display the repeating shifts view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function repeatingShifts()
    {
        if (! Gate::allows('resourcerotas_manage')) {
            return abort('401');
        }

        return view('admin.resourcerotas.repeating-shifts');
    }
}
