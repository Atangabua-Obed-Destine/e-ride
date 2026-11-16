<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelMessage;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\HotelManagement\Service\InquiryService;
use Modules\HotelManagement\Service\StayService;
use Modules\HotelManagement\Transformers\HotelPresenter;
use Modules\UserManagement\Entities\IdVerification;
use Modules\UserManagement\Entities\User;

/** Guests' questions: the list, one chat, and the reception's answers (text, price, keep a room, close). */
class InboxController extends Controller
{
    public function __construct(protected InquiryService $inquiries, protected StayService $stays)
    {
    }

    public function index(Request $request): View
    {
        $hotel = $request->attributes->get('hotel');
        $list = HotelInquiry::with(['customer', 'roomType'])->where('hotel_id', $hotel->id)
            ->where(fn($q) => $q->whereIn('status', HotelInquiry::ACTIVE)->orWhere('updated_at', '>=', now()->subDays(7)))
            ->orderByRaw('unread_hotel > 0 desc')->orderByRaw("status = 'closed' asc")->orderByDesc('last_message_at')->limit(80)->get();
        $current = null;
        $chat = null;
        if ($request->filled('q') && ($current = $list->firstWhere('id', $request->query('q')) ?? HotelInquiry::where('hotel_id', $hotel->id)->find($request->query('q')))) {
            $this->inquiries->markReadByHotel($current);
            $chat = $this->chat($current);
        }
        $types = $hotel->roomTypes()->where('active', true)->get();
        return view('hotelmanagement::partner.inbox', compact('list', 'current', 'chat', 'types'));
    }

    /** New messages after a given id (the open chat polls this). */
    public function messages(Request $request, string $inquiry): JsonResponse
    {
        $q = $this->find($request, $inquiry);
        $this->inquiries->markReadByHotel($q);
        $after = (int)$request->query('after', 0);
        return response()->json(['ok' => true] + $this->chat($q, $after));
    }

    public function send(Request $request, string $inquiry): JsonResponse
    {
        $q = $this->find($request, $inquiry);
        return $this->act(fn() => $this->inquiries->hotelWrite($q, auth()->id(), (string)$request->input('body')));
    }

    public function price(Request $request, string $inquiry): JsonResponse
    {
        $q = $this->find($request, $inquiry);
        return $this->act(fn() => $this->inquiries->sendPrice($q, auth()->id(), (int)preg_replace('/\D/', '', (string)$request->input('amount'))));
    }

    public function keep(Request $request, string $inquiry): JsonResponse
    {
        $q = $this->find($request, $inquiry);
        $until = trim($request->input('date') . ' ' . $request->input('time'));
        return $this->act(fn() => $this->inquiries->keep($q, auth()->id(), [
            'room_type_id' => $request->input('room_type_id'), 'room_id' => $request->input('room_id') ?: null, 'until' => $until,
        ]));
    }

    public function close(Request $request, string $inquiry): JsonResponse
    {
        $q = $this->find($request, $inquiry);
        return $this->act(fn() => $this->inquiries->close($q, auth()->id(), (string)$request->input('reason')));
    }

    protected function chat(HotelInquiry $q, int $after = 0): array
    {
        $messages = $q->messages()->where('id', '>', $after)->limit(300)->get();
        $names = User::whereIn('id', $messages->pluck('user_id')->filter()->unique())->pluck('first_name', 'id')->mapWithKeys(fn($n, $id) => [(string)$id => $n])->all();
        $customer = $q->customer;
        $kept = $q->stays()->with('room')->whereIn('status', HotelStay::HOLDING)->get();
        $free = $q->isActive() ? $this->stays->freeRooms($q->hotel, null, $q->check_in->copy(), $q->check_out->copy()) : collect();
        return [
            'inquiry' => [
                'id' => $q->id, 'status' => $q->status, 'close_reason' => $q->close_reason,
                'guest' => trim(($customer?->first_name ?? '') . ' ' . mb_substr((string)$customer?->last_name, 0, 1)) . ($customer?->last_name ? '.' : ''),
                // The reception can call back while the question is going, like a driver can call a rider.
                'phone' => $q->isActive() ? $customer?->phone : null,
                'verified' => $customer ? IdVerification::where('user_id', $customer->id)->where('status', 'verified')->exists() : false,
                'rides' => $customer ? \Modules\TripManagement\Entities\TripRequest::where('customer_id', $customer->id)->where('current_status', COMPLETED)->count() : 0,
                'check_in' => $q->check_in->toDateString(), 'check_out' => $q->check_out->toDateString(), 'nights' => $q->nights(),
                'guests' => $q->guests, 'rooms' => $q->rooms, 'arrival' => $q->arrival, 'price' => $q->price,
                'room_type_id' => $q->room_type_id, 'room_type' => $q->roomType?->name,
                'kept' => $kept->map(fn($s) => ['id' => $s->id, 'room' => $s->room?->number, 'status' => $s->status, 'until' => $s->kept_until?->toIso8601String()])->values()->all(),
                'on_the_way' => $this->inquiries->onTheWay($q),
                'free_rooms' => $free->map(fn(HotelRoom $r) => ['id' => $r->id, 'number' => $r->number, 'type' => $r->room_type_id])->values()->all(),
            ],
            'messages' => $messages->map(fn(HotelMessage $m) => HotelPresenter::message($m, $names))->values()->all(),
        ];
    }

    protected function act(callable $fn): JsonResponse
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true]);
    }

    protected function find(Request $request, string $id): HotelInquiry
    {
        return HotelInquiry::with(['hotel', 'customer', 'roomType'])->where('hotel_id', $request->attributes->get('hotel')->id)->findOrFail($id);
    }
}
