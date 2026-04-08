<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Conversion\WrongConversionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WrongConversionsController extends Controller
{
    public function __construct(
        private readonly WrongConversionService $wrongConversionService,
    ) {}

    public function index(Request $request): \Illuminate\View\View
    {
        $date = $request->get('date', Carbon::yesterday()->format('Y-m-d'));

        $data = $this->wrongConversionService->getIndexData($date);

        return view('admin.wrong_conversions', $data);
    }

    public function reset(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $result = $this->wrongConversionService->resetAppointment($id);

        if (! $result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
    }

    public function resetAll(Request $request): \Illuminate\Http\RedirectResponse
    {
        $ids = $request->get('ids', []);

        $result = $this->wrongConversionService->resetAllAppointments($ids);

        if (! $result['success']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
    }
}
