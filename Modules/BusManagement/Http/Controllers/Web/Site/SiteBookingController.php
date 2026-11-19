<?php

namespace Modules\BusManagement\Http\Controllers\Web\Site;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusPayment;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Lib\BusWebLinks;
use Modules\BusManagement\Service\BookingService;
use Modules\BusManagement\Service\BusPaymentService;
use Modules\BusManagement\Transformers\BusPresenter;
use Modules\UserManagement\Service\IdVerificationService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

/**
 * Booking on the website, signed in: travellers, the request, its page (waiting, pay, ticket), cancelling
 * an unpaid request, printing, and the traveller's trips. Everything goes through BookingService and
 * BusPaymentService, the same as the app, so the seats and money rules are the same.
 */
class SiteBookingController extends SiteBase
{
    public function __construct(protected BookingService $bookings, protected BusPaymentService $payments)
    {
    }

    public function travellers(Request $request, string $id): Response|RedirectResponse
    {
        $t = $this->words($request);
        $me = $this->user();
        $departure = BusDeparture::with(['agency', 'destination.dropPoints', 'vehicle'])->find($id);
        if (!$departure || !$departure->agency) {
            return $this->missing($request, 'departure_not_found');
        }
        $seats = (array)$request->session()->get('bus_pick.' . $departure->id, []);
        if (!$seats) {
            return redirect()->route('bus.web.departure', ['id' => $departure->id])->withErrors(['seats' => $t('seats_gone')]);
        }
        $rules = BusSettings::rules($departure->agency->rules);
        $hasId = (bool)app(IdVerificationService::class)->usable($me);
        // The agency trusts only IDs sent to E-Ride: that happens in the app (camera and selfie).
        if (!$hasId && $rules['id_check'] !== 'number') {
            return $this->page($request, 'id', [
                't' => $t, 'departure' => $departure,
                'appLink' => BusWebLinks::openInApp('bv'),
                'appPage' => route('bus.web.app', ['to' => 'bv', 'back' => $request->fullUrl()]),
            ]);
        }
        $info = BusPresenter::departure($departure, BookingService::takenSeats($departure), $this->bookings->onlineBlock($departure));
        return $this->page($request, 'travellers', [
            't' => $t,
            'departure' => $departure,
            'info' => $info,
            'seats' => $seats,
            'rules' => $rules,
            'askIdNumber' => !$hasId,
            'settings' => BusSettings::all(),
            'channels' => \Modules\BusManagement\Lib\BusMessenger::channels(),
        ]);
    }

    public function request(Request $request, string $id): RedirectResponse
    {
        $t = $this->words($request);
        $me = $this->user();
        $departure = BusDeparture::with(['agency', 'destination'])->find($id);
        if (!$departure) {
            return redirect()->route('bus.web.home');
        }
        if (!BusSettings::web()['booking']) {
            return redirect()->route('bus.web.departure', ['id' => $id])->withErrors(['seats' => $t('not_bookable')]);
        }
        $seats = array_values((array)$request->session()->get('bus_pick.' . $departure->id, []));
        if (!$seats) {
            return redirect()->route('bus.web.departure', ['id' => $id])->withErrors(['seats' => $t('seats_gone')]);
        }
        $data = $request->validate([
            'names' => 'required|array',
            'names.*' => 'nullable|string|max:120',
            'drop_point_id' => 'required|string|max:64',
            'notify' => 'nullable|in:whatsapp,sms',
            'booker_id_number' => 'nullable|string|max:40',
        ]);
        $passengers = array_map(fn($seat) => ['name' => trim((string)($data['names'][$seat] ?? ''))], $seats);
        try {
            $booking = $this->bookings->request($me, $departure, [
                'seats' => $seats,
                'drop_point_id' => $data['drop_point_id'],
                'passengers' => $passengers,
                'booker_id_number' => $data['booker_id_number'] ?? null,
            ], ['via' => BusBooking::WEB, 'notify_channel' => $data['notify'] ?? (\Modules\BusManagement\Lib\BusMessenger::channels()[0] ?? 'whatsapp')]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            // A seat taken meanwhile: back to the seat map to choose again.
            if (isset($errors['seats']) || isset($errors['departure'])) {
                $request->session()->forget('bus_pick.' . $departure->id);
                return redirect()->route('bus.web.departure', ['id' => $id])->withErrors(['seats' => collect($errors)->flatten()->first()]);
            }
            return back()->withInput()->withErrors($errors);
        }
        $request->session()->forget('bus_pick.' . $departure->id);
        return redirect()->route('bus.web.booking', ['code' => $booking->code]);
    }

    public function show(Request $request, string $code): Response
    {
        $t = $this->words($request);
        $booking = $this->mine($code);
        if (!$booking) {
            return $this->missing($request, 'booking_not_found');
        }
        // Back from Mobile Money: settle now rather than wait for the minute tick.
        if ($request->query('pay') === 'done' && $booking->status === BusBooking::ACCEPTED) {
            $this->syncPayments($booking, 0);
            $booking = $this->mine($code);
        }
        $data = BusPresenter::booking($booking, $this->bookings->cancelQuote($booking));
        $collect = BusSettings::rules($booking->agency?->rules)['tickets'] === 'collect';
        $qr = fn(string $text) => (string)QrCode::format('svg')->size(168)->margin(0)->errorCorrection('M')->generate($text);
        return $this->page($request, 'booking', [
            't' => $t,
            'booking' => $booking,
            'b' => $data,
            'collect' => $collect,
            'qr' => $qr,
            'payState' => $request->query('pay'),
            'paying' => $booking->status === BusBooking::ACCEPTED && BusPayment::where('booking_id', $booking->id)->whereIn('status', BusPayment::OPEN)->whereNotNull('payment_link')->exists(),
            'appLink' => BusWebLinks::openInApp('bt:' . $booking->code),
            'appPage' => route('bus.web.app', ['to' => 'bt:' . $booking->code, 'back' => route('bus.web.booking', ['code' => $booking->code])]),
            'settings' => BusSettings::all(),
            'channels' => \Modules\BusManagement\Lib\BusMessenger::channels(),
            'ticketOnWhatsApp' => \Modules\BusManagement\Entities\BusMessage::where('booking_id', $booking->id)->where('event', 'paid')
                ->where('channel', 'whatsapp')->whereIn('status', ['sent', 'delivered', 'read'])->exists(),
        ]);
    }

    /** Polled by the booking page: its state, so the page reloads when the agency answers or the money arrives. */
    public function status(string $code): JsonResponse
    {
        $booking = $this->mine($code);
        if (!$booking) {
            return response()->json(['state' => null], 404);
        }
        if ($booking->status === BusBooking::ACCEPTED) {
            $this->syncPayments($booking, 10);
            $booking->refresh();
        }
        return response()->json(['state' => $booking->status . ':' . ($booking->departure?->status) . ':' . (int)$booking->tickets()->whereNotNull('collected_at')->count()]);
    }

    public function pay(Request $request, string $code): RedirectResponse
    {
        $t = $this->words($request);
        $booking = $this->mine($code);
        if (!$booking) {
            return redirect()->route('bus.web.trips');
        }
        $result = $this->payments->start($booking);
        if (!empty($result['link'])) {
            return redirect()->away($result['link']);
        }
        if (!empty($result['paid'])) {
            return redirect()->route('bus.web.booking', ['code' => $code]);
        }
        return redirect()->route('bus.web.booking', ['code' => $code])->withErrors(['pay' => $result['error'] ?? $t('pay_failed')]);
    }

    /** Only a request not paid yet; a paid ticket is cancelled in the app (refund rules). */
    public function cancel(Request $request, string $code): RedirectResponse
    {
        $t = $this->words($request);
        $booking = $this->mine($code);
        if (!$booking) {
            return redirect()->route('bus.web.trips');
        }
        $page = redirect()->route('bus.web.booking', ['code' => $code]);
        if (!in_array($booking->status, BusBooking::HOLDING, true)) {
            return $page->withErrors(['booking' => $t('cancel_in_app')]);
        }
        try {
            $this->bookings->cancelByPassenger($this->user(), $booking);
        } catch (ValidationException $e) {
            return $page->withErrors(['booking' => collect($e->errors())->flatten()->first()]);
        }
        return redirect()->route('bus.web.booking', ['code' => $code]);
    }

    public function print(Request $request, string $code): Response|RedirectResponse
    {
        $t = $this->words($request);
        $booking = $this->mine($code);
        if (!$booking || $booking->status !== BusBooking::PAID) {
            return redirect()->route('bus.web.booking', ['code' => $code]);
        }
        $collect = BusSettings::rules($booking->agency?->rules)['tickets'] === 'collect';
        $qr = fn(string $text) => (string)QrCode::format('svg')->size(150)->margin(0)->errorCorrection('M')->generate($text);
        return $this->page($request, 'print', ['t' => $t, 'booking' => $booking, 'b' => BusPresenter::booking($booking), 'collect' => $collect, 'qr' => $qr]);
    }

    public function trips(Request $request): Response
    {
        $t = $this->words($request);
        $me = $this->user();
        $cut = now()->subHours(6);
        $all = BusBooking::with(['agency', 'departure.destination', 'tickets'])->where('customer_id', $me->id)
            ->latest()->limit(60)->get();
        $upcoming = $all->filter(fn(BusBooking $b) => in_array($b->status, [...BusBooking::HOLDING, BusBooking::PAID], true)
            && $b->departure && $b->departure->leavesAt()->gte($cut)
            && !in_array($b->departure->status, [BusDeparture::DEPARTED, BusDeparture::CANCELLED], true))
            ->sortBy(fn($b) => $b->departure->leavesAt()->timestamp)->values();
        $past = $all->diff($upcoming)->sortByDesc(fn($b) => $b->departure?->leavesAt()->timestamp)->take(20)->values();
        return $this->page($request, 'trips', ['t' => $t, 'upcoming' => $upcoming, 'past' => $past]);
    }

    protected function mine(string $code): ?BusBooking
    {
        $me = $this->user();
        if (!$me || !preg_match('/^[A-Z0-9]{6,12}$/', $code)) {
            return null;
        }
        return BusBooking::with(['agency', 'departure.destination', 'departure.vehicle', 'dropPoint', 'tickets'])
            ->where('customer_id', $me->id)->where('code', $code)->first();
    }

    /** Asks the agency's Fapshi about payments in progress (at most every $seconds). */
    protected function syncPayments(BusBooking $booking, int $seconds): void
    {
        BusPayment::where('booking_id', $booking->id)->whereIn('status', BusPayment::OPEN)->whereNotNull('trans_id')->get()
            ->each(function (BusPayment $p) use ($seconds) {
                try {
                    $this->payments->sync($p, $seconds);
                } catch (Throwable) {
                }
            });
    }
}
