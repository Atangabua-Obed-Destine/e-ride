<?php

namespace Modules\PlaceManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\PlaceManagement\Entities\ListingReservation;
use Modules\PlaceManagement\Entities\ListingVisit;
use Modules\PlaceManagement\Lib\ListingSettings;
use Modules\PlaceManagement\Service\ListingReservationService;
use Modules\PlaceManagement\Transformers\ListingPresenter;
use Throwable;

/**
 * Customer app, Homes & spaces reservation money. Every answer is the visit the
 * reservation belongs to, as the caller sees it (with its "reservation").
 */
class ListingReservationController extends Controller
{
    public function __construct(protected ListingReservationService $reservations)
    {
    }

    /** The seeker starts a reservation after an accepted visit. */
    public function store(Request $request, ListingVisit $visit): JsonResponse
    {
        $days = ListingSettings::reservations()['move_in_days'];
        $data = $request->validate([
            'move_in_date' => 'required|date|after_or_equal:today|before_or_equal:' . now()->addDays($days)->toDateString(),
        ]);
        return $this->run(fn() => $this->reservations->start($visit, $request->user(), $data['move_in_date']), $request);
    }

    public function payWithWallet(Request $request, ListingReservation $reservation): JsonResponse
    {
        return $this->run(fn() => $this->reservations->payWithWallet($reservation, $request->user()), $request);
    }

    /** A link for the app's payment webview, for a gateway (MoMo through Fapshi). */
    public function payUrl(Request $request, ListingReservation $reservation): JsonResponse
    {
        $data = $request->validate(['payment_method' => 'required|string|max:40']);
        if ($reservation->seeker_id !== $request->user()->id) {
            return $this->fail(translate('This reservation is not yours.'));
        }
        if ($reservation->status !== ListingReservation::AWAITING) {
            return $this->fail(translate('This reservation can no longer be paid.'));
        }
        return response()->json(responseFormatter(DEFAULT_200, ['pay_url' => $this->reservations->payUrl($reservation, $data['payment_method'])]));
    }

    /** Opened in the app's webview (signed link, no auth header there). */
    public function pay(Request $request, ListingReservation $reservation): RedirectResponse
    {
        if ($reservation->status !== ListingReservation::AWAITING) {
            return redirect()->route($reservation->isHolding() || $reservation->status === ListingReservation::RELEASED ? 'payment-success' : 'payment-fail');
        }
        try {
            return redirect($this->reservations->paymentLink($reservation, (string)$request->query('payment_method', '')));
        } catch (Throwable) {
            return redirect()->route('payment-fail');
        }
    }

    public function cancel(Request $request, ListingReservation $reservation): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        return $this->run(fn() => $this->reservations->cancel($reservation, $request->user(), $data['note'] ?? null), $request);
    }

    /** The owner: accept (with the move-in date) or decline. */
    public function respond(Request $request, ListingReservation $reservation): JsonResponse
    {
        $days = ListingSettings::reservations()['move_in_days'];
        $data = $request->validate([
            'action' => ['required', Rule::in(['accept', 'decline'])],
            'move_in_date' => 'nullable|date|after_or_equal:today|before_or_equal:' . now()->addDays($days)->toDateString(),
        ]);
        return $this->run(fn() => $data['action'] === 'accept'
            ? $this->reservations->accept($reservation, $request->user(), $data['move_in_date'] ?? null)
            : $this->reservations->decline($reservation, $request->user()), $request);
    }

    public function movedIn(Request $request, ListingReservation $reservation): JsonResponse
    {
        return $this->run(fn() => $this->reservations->movedIn($reservation, $request->user()), $request);
    }

    /** Seeker: "Report a problem"; owner: "Seeker didn't come". */
    public function problem(Request $request, ListingReservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_unique([...ListingReservation::SEEKER_PROBLEMS, ...ListingReservation::OWNER_PROBLEMS]))],
            'note' => 'nullable|string|max:1000',
        ]);
        return $this->run(fn() => $this->reservations->problem($reservation, $request->user(), $data['reason'], $data['note'] ?? null), $request);
    }

    /** A refund in the wallet, sent on mobile money instead. */
    public function refundToMomo(Request $request, ListingReservation $reservation): JsonResponse
    {
        $data = $request->validate(['phone' => 'required|string|min:9|max:20']);
        return $this->run(fn() => $this->reservations->refundToMobileMoney($reservation, $request->user(), $data['phone'])->reservation, $request);
    }

    /** Runs an action and answers with the reservation's visit as the caller sees it. */
    protected function run(callable $action, Request $request): JsonResponse
    {
        try {
            $reservation = $action();
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first());
        }
        $visit = ListingVisit::with(['listing.photos', 'seeker', 'owner'])->find($reservation->visit_id);
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, ListingPresenter::visit($visit, $request->user()->id)));
    }

    protected function fail(string $message): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => 'reservation', 'message' => $message]]), 403);
    }
}
