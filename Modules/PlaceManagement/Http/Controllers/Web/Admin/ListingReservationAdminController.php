<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\PlaceManagement\Entities\ListingPayout;
use Modules\PlaceManagement\Entities\ListingReservation;
use Modules\PlaceManagement\Service\ListingReservationService;

/** Admin, Homes & spaces: reservation money, problems to decide, and payouts to send. */
class ListingReservationAdminController extends Controller
{
    public const TABS = ['problems', 'waiting', 'reserved', 'closed', 'all'];

    public function __construct(protected ListingReservationService $reservations)
    {
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'problems';
        $search = trim((string)$request->query('search', ''));
        $reservations = ListingReservation::with(['listing', 'seeker', 'owner'])
            ->when($tab === 'problems', fn($q) => $q->where('status', ListingReservation::PROBLEM))
            ->when($tab === 'waiting', fn($q) => $q->whereIn('status', [ListingReservation::AWAITING, ListingReservation::HELD]))
            ->when($tab === 'reserved', fn($q) => $q->where('status', ListingReservation::RESERVED))
            ->when($tab === 'closed', fn($q) => $q->whereIn('status', [ListingReservation::RELEASED, ListingReservation::REFUNDED, ListingReservation::LAPSED]))
            ->when($search !== '', fn($q) => $q->where(fn($w) => $w
                ->whereHas('listing', fn($l) => $l->withTrashed()->where('title', 'like', "%{$search}%"))
                ->orWhereHas('seeker', fn($u) => $u->where('phone', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"))
                ->orWhereHas('owner', fn($u) => $u->where('phone', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"))))
            ->latest()->paginate(20)->withQueryString();
        $monthStart = now()->startOfMonth();
        $totals = [
            'held' => (float)ListingReservation::whereIn('status', ListingReservation::HOLDING)->sum('amount'),
            'released' => (float)ListingReservation::where('status', ListingReservation::RELEASED)->where('settled_at', '>=', $monthStart)->sum('owner_amount'),
            'fees' => (float)ListingReservation::where('settled_at', '>=', $monthStart)->sum('fee'),
            'to_pay' => (float)ListingPayout::where('status', ListingPayout::PENDING)->sum('amount'),
        ];
        return view('placemanagement::admin.listings.reservations', compact('reservations', 'tab', 'search', 'totals'));
    }

    public function show(ListingReservation $reservation): View
    {
        $reservation->load(['listing.photos', 'seeker', 'owner', 'visit', 'payouts.paidBy']);
        return view('placemanagement::admin.listings.reservation', compact('reservation'));
    }

    public function decide(Request $request, ListingReservation $reservation): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['refund', 'release', 'split'])],
            'note' => 'required|string|max:500',
            'seeker_amount' => 'required_if:decision,split|nullable|numeric|min:1',
            'strike' => 'nullable|boolean',
        ]);
        try {
            $this->reservations->decide($reservation, auth()->user(), $data['decision'], $data['note'],
                isset($data['seeker_amount']) ? (float)$data['seeker_amount'] : null, $request->boolean('strike'));
        } catch (ValidationException $e) {
            Toastr::error(collect($e->errors())->flatten()->first());
            return back()->withInput();
        }
        Toastr::success(translate('Decision saved. Both sides were told.'));
        return back();
    }

    public function payouts(Request $request): View
    {
        $status = $request->query('status') === 'paid' ? ListingPayout::PAID : ListingPayout::PENDING;
        $payouts = ListingPayout::with(['user', 'reservation.listing', 'paidBy'])->where('status', $status)
            ->when($status === ListingPayout::PENDING, fn($q) => $q->oldest(), fn($q) => $q->latest('paid_at'))
            ->paginate(30)->withQueryString();
        return view('placemanagement::admin.listings.payouts', compact('payouts', 'status'));
    }

    public function markPaid(Request $request, ListingPayout $payout): RedirectResponse
    {
        $data = $request->validate(['reference' => 'required|string|max:60']);
        $this->reservations->markPayoutPaid($payout, auth()->user(), trim($data['reference']));
        Toastr::success(translate('Marked paid. The customer was told, with the reference.'));
        return back();
    }
}
