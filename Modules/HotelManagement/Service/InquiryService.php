<?php

namespace Modules\HotelManagement\Service;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\HotelManagement\Entities\Hotel;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelMessage;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelRoomType;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\HotelManagement\Lib\HotelPush;
use Modules\HotelManagement\Lib\HotelSettings;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

/**
 * A guest's question to a hotel and the chat that follows: the request card, messages, the price
 * for the dates, a room kept until a time, and how it ends (arrived, let go, didn't come, full).
 * No money: the guest pays the hotel when they arrive.
 */
class InquiryService
{
    public function __construct(protected StayService $stays)
    {
    }

    // ------------------------------------------------------------- The guest

    /** The guest asks about a room type for some dates. One open question per hotel: asking again updates it. */
    public function ask(User $customer, Hotel $hotel, array $input): HotelInquiry
    {
        $settings = HotelSettings::all();
        if (!$hotel->isLive()) {
            throw ValidationException::withMessages(['hotel' => translate('This hotel is not taking questions in the app right now. You can still call it.')]);
        }
        $data = Validator::make($input, [
            'room_type_id' => ['required', Rule::exists('hotel_room_types', 'id')->where('hotel_id', $hotel->id)->where('active', true)],
            'check_in' => 'required|date_format:Y-m-d|after_or_equal:' . today()->toDateString() . '|before_or_equal:' . today()->addDays($settings['days_ahead'])->toDateString(),
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'guests' => 'required|integer|min:1|max:40',
            'rooms' => 'required|integer|min:1|max:' . $settings['max_rooms'],
            'arrival' => ['nullable', 'string', 'max:10', function ($attr, $value, $fail) {
                if ($value !== null && !in_array($value, HotelInquiry::ARRIVALS, true) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
                    $fail(translate('Choose when you arrive.'));
                }
            }],
            'question' => 'nullable|string|max:500',
        ])->validate();
        $in = Carbon::parse($data['check_in']);
        $out = Carbon::parse($data['check_out']);
        if ($in->diffInDays($out) > $settings['max_nights']) {
            throw ValidationException::withMessages(['check_out' => translate(key: 'At most {n} nights. Ask the reception about longer stays.', replace: ['n' => $settings['max_nights']])]);
        }

        return DB::transaction(function () use ($customer, $hotel, $data, $settings) {
            $inquiry = HotelInquiry::where('hotel_id', $hotel->id)->where('customer_id', $customer->id)
                ->whereIn('status', [HotelInquiry::OPEN, HotelInquiry::ANSWERED])->lockForUpdate()->first();
            if (!$inquiry) {
                $open = HotelInquiry::where('customer_id', $customer->id)->whereIn('status', HotelInquiry::ACTIVE)->count();
                if ($open >= $settings['max_open']) {
                    throw ValidationException::withMessages(['hotel' => translate(key: 'You already have {n} questions going. Close one you no longer need first.', replace: ['n' => $open])]);
                }
            }
            $fields = [
                'room_type_id' => $data['room_type_id'], 'check_in' => $data['check_in'], 'check_out' => $data['check_out'],
                'guests' => $data['guests'], 'rooms' => $data['rooms'], 'arrival' => $data['arrival'] ?? null,
            ];
            if ($inquiry) {
                $inquiry->update($fields + ['status' => HotelInquiry::OPEN, 'price' => null]);
            } else {
                $inquiry = HotelInquiry::create($fields + ['hotel_id' => $hotel->id, 'customer_id' => $customer->id, 'status' => HotelInquiry::OPEN]);
            }
            $type = HotelRoomType::find($data['room_type_id']);
            $this->post($inquiry, HotelMessage::GUEST, 'request', trim((string)($data['question'] ?? '')) ?: null, [
                'room_type' => $type?->name, 'check_in' => $data['check_in'], 'check_out' => $data['check_out'], 'nights' => $inquiry->nights(),
                'guests' => (int)$data['guests'], 'rooms' => (int)$data['rooms'], 'arrival' => $data['arrival'] ?? null,
                'free' => $hotel->usesPos()
                    ? ($this->stays->freeCounts($hotel, Carbon::parse($data['check_in']), Carbon::parse($data['check_out']))[(string)$data['room_type_id']] ?? null)
                    : $this->stays->freeRooms($hotel, $data['room_type_id'], Carbon::parse($data['check_in']), Carbon::parse($data['check_out']))->count(),
            ], $customer->id);
            return $inquiry->fresh();
        });
    }

    public function guestWrite(HotelInquiry $inquiry, User $customer, string $body): HotelMessage
    {
        $this->guestOwns($inquiry, $customer);
        $this->mustBeActive($inquiry);
        return $this->post($inquiry, HotelMessage::GUEST, 'text', $this->clean($body), null, $customer->id);
    }

    /** The guest no longer needs it: rooms kept are free again and the question closes. */
    public function guestRelease(HotelInquiry $inquiry, User $customer): HotelInquiry
    {
        $this->guestOwns($inquiry, $customer);
        if (!$inquiry->isActive()) {
            return $inquiry;
        }
        if ($inquiry->status === HotelInquiry::ARRIVED) {
            throw ValidationException::withMessages(['inquiry' => translate('You are checked in. Talk to the reception about leaving.')]);
        }
        DB::transaction(function () use ($inquiry) {
            $this->lockActive($inquiry);
            $this->endHeld($inquiry, 'guest_let_go');
            $inquiry->update(['status' => HotelInquiry::CLOSED, 'close_reason' => 'guest']);
            $this->post($inquiry, HotelMessage::SYSTEM, 'released', null, ['reason' => 'guest']);
        });
        $inquiry->hotel?->touchBoard();
        return $inquiry->fresh();
    }

    public function markReadByGuest(HotelInquiry $inquiry): void
    {
        if ($inquiry->unread_guest > 0) {
            $inquiry->forceFill(['unread_guest' => 0])->save();
        }
    }

    // ------------------------------------------------------------- The reception

    public function hotelWrite(HotelInquiry $inquiry, string $userId, string $body): HotelMessage
    {
        $this->mustBeActive($inquiry);
        $message = $this->post($inquiry, HotelMessage::HOTEL, 'text', $this->clean($body), null, $userId);
        $this->answered($inquiry);
        HotelPush::send($inquiry->customer, 'hotel_message', '{hotel} answered', '{text}',
            ['hotel' => $inquiry->hotel->name, 'text' => Str::limit($message->body, 140)], ['hotel_inquiry_id' => $inquiry->id]);
        return $message;
    }

    /** The price for the guest's dates, as a card. Paid at the hotel. */
    public function sendPrice(HotelInquiry $inquiry, string $userId, int $amount): HotelMessage
    {
        $this->mustBeActive($inquiry);
        if ($amount < 500 || $amount > 50000000) {
            throw ValidationException::withMessages(['price' => translate('Enter the total price in FCFA.')]);
        }
        $inquiry->update(['price' => $amount]);
        $message = $this->post($inquiry, HotelMessage::HOTEL, 'price', null, [
            'amount' => $amount, 'nights' => $inquiry->nights(), 'room_type' => $inquiry->roomType?->name, 'rooms' => $inquiry->rooms,
        ], $userId);
        $this->answered($inquiry);
        HotelPush::send($inquiry->customer, 'hotel_price', '{hotel} sent you a price', 'FCFA {amount} for {nights} night(s). You pay at the hotel.',
            ['hotel' => $inquiry->hotel->name, 'amount' => number_format($amount), 'nights' => $inquiry->nights()], ['hotel_inquiry_id' => $inquiry->id]);
        return $message;
    }

    /**
     * Keeps room(s) for the guest until a time: the guest's rooms count, of a type (a given room
     * number when one room). Keeping again moves the time. Nothing to pay.
     */
    public function keep(HotelInquiry $inquiry, string $userId, array $input): HotelInquiry
    {
        $this->mustBeActive($inquiry);
        $hotel = $inquiry->hotel;
        if ($hotel->usesPos()) {
            throw ValidationException::withMessages(['inquiry' => translate('Your rooms are in POS Innova: keep the room there, from the E-Ride requests on the hotel home.')]);
        }
        $data = Validator::make($input, [
            'room_type_id' => ['required', Rule::exists('hotel_room_types', 'id')->where('hotel_id', $hotel->id)],
            'room_id' => ['nullable', Rule::exists('hotel_rooms', 'id')->where('hotel_id', $hotel->id)->where('active', true)],
            'until' => 'required|date_format:Y-m-d H:i',
        ], [], ['until' => translate('kept until')])->validate();
        $data['room_id'] = $inquiry->rooms === 1 ? ($data['room_id'] ?? null) : null;
        if ($data['room_id']) {
            // A room number chosen: its type is the one kept.
            $data['room_type_id'] = HotelRoom::whereKey($data['room_id'])->value('room_type_id');
        }
        $until = Carbon::parse($data['until']);
        $limit = $inquiry->check_in->isSameDay(today())
            ? now()->addHours($hotel->keep_max_hours)
            : $inquiry->check_in->copy()->setTime(23, 59);
        if ($until->lt(now()->addMinutes(10))) {
            throw ValidationException::withMessages(['until' => translate('Keep the room at least 10 minutes from now.')]);
        }
        if ($until->gt($limit)) {
            throw ValidationException::withMessages(['until' => $inquiry->check_in->isSameDay(today())
                ? translate(key: 'For today, a room is kept at most {n} hours.', replace: ['n' => $hotel->keep_max_hours])
                : translate('A room is kept at most until the evening of the arrival day.')]);
        }

        DB::transaction(function () use ($inquiry, $hotel, $data, $until, $userId) {
            // One keep at a time per guest: two receptionists keeping at once, or a
            // keep landing just after the guest let the room go, would hold too much.
            $this->lockActive($inquiry);
            $kept = $inquiry->stays()->where('status', HotelStay::KEPT)->lockForUpdate()->get();
            if ($kept->isNotEmpty()) {
                // Already kept: only the time moves.
                $kept->each(fn(HotelStay $s) => $s->update(['kept_until' => $until]));
            } else {
                $from = $inquiry->check_in->copy();
                $to = $inquiry->check_out->copy();
                $rooms = $data['room_id']
                    ? HotelRoom::whereKey($data['room_id'])->get()
                    : $this->stays->freeRooms($hotel, $data['room_type_id'], $from, $to)->take($inquiry->rooms);
                if ($rooms->count() < $inquiry->rooms) {
                    throw ValidationException::withMessages(['room_type_id' => translate(key: 'Only {n} room(s) of this type are free on those nights.', replace: ['n' => $rooms->count()])]);
                }
                foreach ($rooms as $room) {
                    $this->stays->take($hotel, $room, $from, $to, [
                        'inquiry_id' => $inquiry->id, 'source' => HotelStay::ERIDE, 'status' => HotelStay::KEPT, 'kept_until' => $until,
                        'guests' => $inquiry->guests, 'guest_name' => $inquiry->customer?->first_name, 'created_by' => $userId,
                    ]);
                }
            }
            $inquiry->update(['status' => HotelInquiry::KEPT, 'room_type_id' => $data['room_type_id']]);
            $numbers = $inquiry->stays()->where('status', HotelStay::KEPT)->with('room')->get()->map(fn($s) => $s->room?->number)->filter()->values()->all();
            $this->post($inquiry, HotelMessage::HOTEL, 'kept', null, [
                'until' => $until->toIso8601String(), 'room_type' => HotelRoomType::find($data['room_type_id'])?->name, 'rooms' => count($numbers),
                'check_in' => $inquiry->check_in->toDateString(), 'check_out' => $inquiry->check_out->toDateString(),
            ], $userId);
        });
        $this->answered($inquiry);
        $hotel->touchBoard();
        HotelPush::send($inquiry->customer, 'hotel_kept', 'Room kept for you until {time}', '{hotel} keeps it for you. Ride there with E-Ride.',
            ['hotel' => $hotel->name, 'time' => $until->format('H:i')], ['hotel_inquiry_id' => $inquiry->id]);
        return $inquiry->fresh();
    }

    /** The reception closes the question: full on those dates, or nothing more to do. Kept rooms are let go. */
    public function close(HotelInquiry $inquiry, ?string $userId, string $reason, bool $fromPos = false): HotelInquiry
    {
        if (!in_array($reason, ['full', 'hotel'], true)) {
            $reason = 'hotel';
        }
        if (!$inquiry->isActive()) {
            return $inquiry;
        }
        if ($inquiry->status === HotelInquiry::ARRIVED) {
            throw ValidationException::withMessages(['inquiry' => translate('The guest is in the house. Mark them as left on the rooms board.')]);
        }
        DB::transaction(function () use ($inquiry, $reason, $userId, $fromPos) {
            $this->lockActive($inquiry);
            $this->endHeld($inquiry, $fromPos ? null : 'closed_on_eride');
            $inquiry->update(['status' => HotelInquiry::CLOSED, 'close_reason' => $reason]);
            $this->post($inquiry, HotelMessage::HOTEL, 'closed', null, ['reason' => $reason], $userId);
        });
        $this->answered($inquiry);
        $inquiry->hotel->touchBoard();
        HotelPush::send($inquiry->customer, 'hotel_closed', $reason === 'full' ? '{hotel} is full on your dates' : '{hotel} closed your question',
            $reason === 'full' ? 'See other hotels with free rooms in E-Ride.' : 'Open the chat to see their last message.',
            ['hotel' => $inquiry->hotel->name], ['hotel_inquiry_id' => $inquiry->id]);
        return $inquiry->fresh();
    }

    /**
     * Lets go of the rooms held for the question. Rooms held in POS Innova (pos_ref) end here too, and
     * with a $posReason POS is told to cancel its reservations (/inquiries/pending "to_release").
     */
    protected function endHeld(HotelInquiry $inquiry, ?string $posReason): void
    {
        foreach ($inquiry->stays()->whereIn('status', [HotelStay::KEPT, HotelStay::BOOKED])->get() as $stay) {
            if (!$stay->pos_ref && $stay->status !== HotelStay::KEPT) {
                continue;
            }
            if ($this->stays->end($stay, HotelStay::RELEASED, [HotelStay::KEPT, HotelStay::BOOKED]) && $stay->pos_ref && $posReason) {
                $stay->forceFill(['pos_release_due_at' => now(), 'pos_release_reason' => $posReason])->save();
            }
        }
    }

    public function markReadByHotel(HotelInquiry $inquiry): void
    {
        if ($inquiry->unread_hotel > 0) {
            $inquiry->forceFill(['unread_hotel' => 0])->save();
        }
    }

    /** The rooms board changed a stay of this question (arrived, didn't come, expired, left). */
    public function staysChanged(HotelInquiry $inquiry, string $event): void
    {
        $holding = $inquiry->stays()->whereIn('status', [HotelStay::KEPT, HotelStay::BOOKED])->count();
        $keptLeft = $inquiry->stays()->where('status', HotelStay::KEPT)->count();
        $inHouse = $inquiry->stays()->where('status', HotelStay::IN_HOUSE)->count();
        $hotel = $inquiry->hotel;
        if ($event === 'arrived' && $holding === 0 && $inquiry->status !== HotelInquiry::ARRIVED) {
            $inquiry->update(['status' => HotelInquiry::ARRIVED]);
            $this->post($inquiry, HotelMessage::SYSTEM, 'arrived', null, null);
            HotelPush::send($inquiry->customer, 'hotel_arrived', 'Welcome to {hotel}', 'The reception checked you in. Enjoy your stay.',
                ['hotel' => $hotel->name], ['hotel_inquiry_id' => $inquiry->id]);
        } elseif ($event === 'booked' && $keptLeft === 0 && $inHouse === 0 && in_array($inquiry->status, [HotelInquiry::OPEN, HotelInquiry::ANSWERED, HotelInquiry::KEPT], true)) {
            $inquiry->update(['status' => HotelInquiry::BOOKED]);
        } elseif (in_array($event, ['didnt_come', 'expired', 'cancelled'], true) && $holding === 0 && $inHouse === 0 && $inquiry->isActive()) {
            $inquiry->update(['status' => HotelInquiry::CLOSED, 'close_reason' => $event]);
            $this->post($inquiry, HotelMessage::SYSTEM, 'released', null, ['reason' => $event]);
            HotelPush::send($inquiry->customer, 'hotel_released', 'Your room at {hotel} was let go',
                match ($event) {
                    'expired' => 'You were not there by the time it was kept until. Ask again if you still need a room.',
                    'cancelled' => 'The reception cancelled it. Ask again if you still need a room.',
                    default => 'The reception marked that you did not come. Ask again if you still need a room.',
                },
                ['hotel' => $hotel->name], ['hotel_inquiry_id' => $inquiry->id]);
        } elseif ($event === 'stayed' && $holding === 0 && $inHouse === 0 && $inquiry->isActive()) {
            $inquiry->update(['status' => HotelInquiry::CLOSED, 'close_reason' => 'stayed']);
        }
    }

    /** Questions whose dates have passed, never kept, close by themselves. */
    public function tick(): int
    {
        return HotelInquiry::whereIn('status', [HotelInquiry::OPEN, HotelInquiry::ANSWERED])->where('check_out', '<=', today()->toDateString())
            ->limit(500)->update(['status' => HotelInquiry::CLOSED, 'close_reason' => 'expired', 'updated_at' => now()]);
    }

    // ------------------------------------------------------------- For both sides

    /** Average minutes to a first answer, the last 30 days. Null until there is a record. */
    public static function answerMinutes(Hotel $hotel): ?int
    {
        return Cache::remember('hotel-answer:' . $hotel->id, 600, function () use ($hotel) {
            $seconds = HotelInquiry::where('hotel_id', $hotel->id)->whereNotNull('first_answer_at')->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, first_answer_at)) as s')->value('s');
            return $seconds === null ? null : max(1, (int)round($seconds / 60));
        });
    }

    /** The guest is in an E-Ride ride heading to the hotel (destination within 300 m) while a room is kept. */
    public function onTheWay(HotelInquiry $inquiry): bool
    {
        if (!in_array($inquiry->status, [HotelInquiry::KEPT, HotelInquiry::BOOKED], true)) {
            return false;
        }
        $place = $inquiry->hotel?->place;
        if (!$place || $place->latitude === null) {
            return false;
        }
        $trip = TripRequest::with('coordinate')->where('customer_id', $inquiry->customer_id)
            ->whereIn('current_status', [PENDING, ACCEPTED, ONGOING])->where('created_at', '>=', now()->subHours(3))->latest()->first();
        $to = $trip?->coordinate?->destination_coordinates;
        if (!$to) {
            return false;
        }
        return self::metres($place->latitude, $place->longitude, $to->latitude, $to->longitude) <= 300;
    }

    public static function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }

    protected function post(HotelInquiry $inquiry, string $sender, string $kind, ?string $body, ?array $data, ?string $userId = null): HotelMessage
    {
        $message = HotelMessage::create(['inquiry_id' => $inquiry->id, 'sender' => $sender, 'user_id' => $userId, 'kind' => $kind, 'body' => $body, 'data' => $data]);
        $inquiry->forceFill([
            'last_message_at' => now(),
            'unread_hotel' => (int)$inquiry->unread_hotel + ($sender === HotelMessage::GUEST ? 1 : 0),
            'unread_guest' => (int)$inquiry->unread_guest + ($sender !== HotelMessage::GUEST ? 1 : 0),
        ])->save();
        return $message;
    }

    /** First answer time (for "answers in about N min"), and an open question becomes answered. */
    protected function answered(HotelInquiry $inquiry): void
    {
        $changes = [];
        if (!$inquiry->first_answer_at) {
            $changes['first_answer_at'] = now();
        }
        if ($inquiry->status === HotelInquiry::OPEN) {
            $changes['status'] = HotelInquiry::ANSWERED;
        }
        if ($changes) {
            $inquiry->forceFill($changes)->save();
        }
    }

    protected function clean(string $body): string
    {
        $body = trim(preg_replace('/[ \t]+/', ' ', $body));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => translate('Write a message first.')]);
        }
        return mb_substr($body, 0, 1000);
    }

    protected function mustBeActive(HotelInquiry $inquiry): void
    {
        if (!$inquiry->isActive()) {
            throw ValidationException::withMessages(['inquiry' => translate('This conversation is closed. Ask again from the hotel page.')]);
        }
    }

    protected function guestOwns(HotelInquiry $inquiry, User $customer): void
    {
        if ((string)$inquiry->customer_id !== (string)$customer->id) {
            abort(404);
        }
    }

    /** The inquiry read again under a row lock; refuses one that closed meanwhile. */
    protected function lockActive(HotelInquiry $inquiry): void
    {
        $fresh = HotelInquiry::whereKey($inquiry->id)->lockForUpdate()->first();
        if (!$fresh || !$fresh->isActive()) {
            throw ValidationException::withMessages(['inquiry' => translate('This conversation has just been closed.')]);
        }
        $inquiry->setRawAttributes($fresh->getAttributes(), true);
    }
}
