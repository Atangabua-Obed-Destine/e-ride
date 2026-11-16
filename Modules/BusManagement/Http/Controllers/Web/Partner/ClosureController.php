<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusClosure;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Service\ClosureService;
use Modules\ZoneManagement\Entities\SafetyArea;

/** Lockdowns and ghost towns: declare one (with a preview), decide one at risk, lift one, see who is affected. */
class ClosureController extends Controller
{
    public function __construct(protected ClosureService $closures)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $list = BusClosure::where('agency_id', $agency->id)->orderByRaw("status = 'active' desc")->orderByDesc('created_at')->limit(30)->get();
        $destinations = BusDestination::where('agency_id', $agency->id)->where('active', true)->orderBy('city')->get();
        $suggestions = $this->closures->suggestions($agency);
        $area = $request->filled('area') ? $suggestions->firstWhere('id', $request->query('area')) : null;
        $counts = BusDeparture::whereIn('closure_id', $list->pluck('id'))->selectRaw('closure_id, count(*) as n')->groupBy('closure_id')->pluck('n', 'closure_id');
        return view('busmanagement::partner.closures', compact('list', 'destinations', 'suggestions', 'area', 'counts'));
    }

    public function preview(Request $request): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + $this->closures->preview($request->attributes->get('busAgency'), $this->input($request)));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $closure = $this->closures->declare($request->attributes->get('busAgency'), $this->input($request), auth()->id());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        return redirect()->route('bus-partner.closures.show', $closure->id)->with('toast', $closure->outcome === BusClosure::AT_RISK
            ? translate('Declared. Sales stopped and passengers warned. Decide before the time you set.')
            : translate('Declared. Passengers were moved or refunded and told.'));
    }

    public function show(Request $request, string $closure): View
    {
        $c = $this->find($request, $closure);
        $departures = BusDeparture::with('destination')->where('closure_id', $c->id)->orderBy('date')->orderBy('time')->get();
        $bookings = BusBooking::with(['tickets', 'departure.destination', 'customer'])
            ->where(fn($q) => $q->where('closure_id', $c->id)->orWhere(fn($w) => $w->whereIn('departure_id', $departures->pluck('id'))->where('status', BusBooking::PAID)))
            ->orderBy('source')->get();
        return view('busmanagement::partner.closure', ['closure' => $c, 'departures' => $departures, 'bookings' => $bookings]);
    }

    public function confirm(Request $request, string $closure): RedirectResponse
    {
        $c = $this->find($request, $closure);
        try {
            $this->closures->confirm($c, (string)$request->input('outcome'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back()->with('toast', translate('Done. Passengers were moved or refunded and told.'));
    }

    public function lift(Request $request, string $closure): RedirectResponse
    {
        $c = $this->find($request, $closure);
        try {
            $this->closures->lift($c);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back()->with('toast', translate('Lifted. Departures that have not left are on sale again.'));
    }

    protected function input(Request $request): array
    {
        $in = $request->only(['kind', 'note', 'repeat', 'days', 'from_date', 'until_date', 'day_from', 'day_to', 'destination_ids', 'outcome', 'safety_area_id']);
        // Date and time come from separate fields.
        foreach (['starts_at' => ['start_date', 'start_time'], 'ends_at' => ['end_date', 'end_time'], 'decide_by' => ['decide_date', 'decide_time']] as $key => [$d, $t]) {
            if ($request->filled($d)) {
                $in[$key] = $request->input($d) . ' ' . ($request->input($t) ?: ($key === 'ends_at' ? '23:59' : '00:00'));
            }
        }
        $in['repeat'] = $request->boolean('repeat');
        if ($request->input('scope') !== 'some') {
            $in['destination_ids'] = null;
        }
        return $in;
    }

    protected function find(Request $request, string $id): BusClosure
    {
        return BusClosure::where('agency_id', $request->attributes->get('busAgency')->id)->findOrFail($id);
    }
}
