<?php

declare(strict_types=1);
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
    public function scheduleCalendar(): \Illuminate\View\View
    {
        if (! Gate::allows('scheduling_shifts.list.view')) {
            return abort(401);
        }

        return view('admin.resourcerotas.schedule-calendar');
    }

    /**
     * Display the repeating shifts view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function repeatingShifts(): \Illuminate\View\View
    {
        if (! Gate::allows('scheduling_shifts.list.view')) {
            return abort(401);
        }

        return view('admin.resourcerotas.repeating-shifts');
    }
}
