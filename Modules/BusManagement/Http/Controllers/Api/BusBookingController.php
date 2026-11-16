<?php

namespace Modules\BusManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Service\BusPaymentService;
use Modules\BusManagement\Transformers\BusPresenter;

/** Customer app: ask for seats, follow the answer, pay, cancel, see tickets. */
class BusBookingController extends BusController
{
    /** POST departures/{id}/request: seats[], drop_point_id, passengers[] {name, phone, id_number, bags}, phone */
    public function store(Request $request, string $id): JsonResponse
    {
        $departure = BusDeparture::find($id);
        if (!$departure) {
            return $this->fail('departure', translate('This departure does not exist.'), 404);
        }
        $input = $request->only(['seats', 'drop_point_id', 'passengers', 'phone']);
        // Multipart clients send arrays as JSON text.
        foreach (['seats', 'passengers'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = json_decode($input[$key], true);
            }
        }
        try {
            $booking = $this->bookings->request($request->user(), $departure, $input);
        } catch (ValidationException $e) {
            return $this->failWith($e);
        }
        return $this->show($request, $booking->id);
    }

    /** ?when=upcoming|past */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['when' => 'nullable|in:upcoming,past', 'limit' => 'nullable|integer|min:1|max:50', 'offset' => 'nullable|integer|min:1']);
        $limit = (int)$request->input('limit', 20);
        $past = $request->input('when') === 'past';
        $cut = now()->subHours(6);
        $query = BusBooking::with(['agency', 'departure.destination', 'departure.vehicle', 'dropPoint', 'tickets'])
            ->where('customer_id', $request->user()->id)
            ->join('bus_departures as d', 'd.id', '=', 'bus_bookings.departure_id')
            ->select('bus_bookings.*')
            ->when(!$past, fn($q) => $q->whereIn('bus_bookings.status', [...BusBooking::HOLDING, BusBooking::PAID])
                ->whereRaw("TIMESTAMP(d.date, COALESCE(d.delayed_to, d.time)) >= ?", [$cut->format('Y-m-d H:i:s')])
                ->whereNotIn('d.status', [BusDeparture::DEPARTED, BusDeparture::CANCELLED])
                ->orderBy('d.date')->orderBy('d.time'))
            ->when($past, fn($q) => $q->where(fn($w) => $w->whereNotIn('bus_bookings.status', [...BusBooking::HOLDING, BusBooking::PAID])
                ->orWhereRaw("TIMESTAMP(d.date, COALESCE(d.delayed_to, d.time)) < ?", [$cut->format('Y-m-d H:i:s')])
                ->orWhereIn('d.status', [BusDeparture::DEPARTED, BusDeparture::CANCELLED]))
                ->orderByDesc('d.date')->orderByDesc('d.time'));
        $page = $query->paginate($limit, ['*'], 'page', (int)$request->input('offset', 1));
        return $this->ok([
            'bookings' => $page->getCollection()->map(fn(BusBooking $b) => BusPresenter::booking($b))->values()->all(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = $this->mine($request, $id);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }
        return $this->ok(BusPresenter::booking($booking, $this->bookings->cancelQuote($booking)));
    }

    /** A Mobile Money checkout link at the agency's own Fapshi: { link, reference } or { paid: true }. */
    public function pay(Request $request, string $id, BusPaymentService $payments): JsonResponse
    {
        $booking = $this->mine($request, $id);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }
        $result = $payments->start($booking);
        if (isset($result['error'])) {
            return $this->fail('payment', $result['error']);
        }
        return $this->ok($result + ['paid' => false]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $booking = $this->mine($request, $id);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }
        try {
            $this->bookings->cancelByPassenger($request->user(), $booking);
        } catch (ValidationException $e) {
            return $this->failWith($e);
        }
        return $this->show($request, $booking->id);
    }

    private function mine(Request $request, string $id): BusBooking|JsonResponse
    {
        $booking = BusBooking::with(['agency', 'departure.destination', 'departure.vehicle', 'dropPoint', 'tickets'])
            ->where('customer_id', $request->user()->id)->where(fn($q) => $q->where('id', $id)->orWhere('code', $id))->first();
        return $booking ?: $this->fail('booking', translate('This booking does not exist.'), 404);
    }
}
