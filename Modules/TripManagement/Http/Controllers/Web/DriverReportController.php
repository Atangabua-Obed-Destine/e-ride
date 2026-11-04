<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\TripManagement\Entities\TripDeposit;
use Modules\TripManagement\Entities\TripReport;

/** Trip management > Driver reports: customers' reports, for follow-up. */
class DriverReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('trip_view');
        $status = in_array($request->query('status'), [TripReport::OPEN, TripReport::REVIEWED, TripReport::RESOLVED])
            ? $request->query('status') : null;
        $reports = TripReport::with([
            'trip:id,ref_id,type,current_status,trip_cancellation_reason,created_at',
            'customer:id,first_name,last_name,phone',
            'driver:id,first_name,last_name,phone',
            'handler:id,first_name,last_name',
        ])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'open', 'reviewed', 'resolved')")
            ->latest()
            ->paginate(paginationLimit())
            ->withQueryString();

        // How often each reported driver is reported, and cancels after a paid deposit.
        $driverIds = $reports->getCollection()->pluck('driver_id')->unique()->values();
        $reportCounts = TripReport::whereIn('driver_id', $driverIds)->selectRaw('driver_id, count(*) as total')->groupBy('driver_id')->pluck('total', 'driver_id');
        $cancelCounts = TripDeposit::whereIn('driver_id', $driverIds)->where('status', TripDeposit::REFUNDED)
            ->where('note', 'Driver cancelled after the deposit was paid')
            ->selectRaw('driver_id, count(*) as total')->groupBy('driver_id')->pluck('total', 'driver_id');
        $openCount = TripReport::where('status', TripReport::OPEN)->count();

        return view('tripmanagement::admin.driver-report.index', compact('reports', 'status', 'reportCounts', 'cancelCounts', 'openCount'));
    }

    public function update(Request $request, TripReport $report): RedirectResponse
    {
        $this->authorize('trip_edit');
        $data = $request->validate([
            'status' => ['required', Rule::in([TripReport::OPEN, TripReport::REVIEWED, TripReport::RESOLVED])],
            'admin_note' => 'nullable|string|max:1000',
        ]);
        $report->update([
            'status' => $data['status'],
            'admin_note' => trim((string)($data['admin_note'] ?? '')) ?: null,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);
        Toastr::success(translate('Report updated'));
        return back();
    }
}
