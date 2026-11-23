<?php

namespace Modules\HotelManagement\Service;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\HotelManagement\Entities\Hotel;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelRoomNight;
use Modules\HotelManagement\Entities\HotelStay;

/**
 * Rooms and who has them: what is free for some dates, the rooms board, and the reception's
 * actions (walk-in, booked by phone, arrived, didn't come, left). Every night a room is taken
 * is a hotel_room_nights row, unique per room and night, so a room is never given twice.
 */
class StayService
{
    // ------------------------------------------------------------- What is free

    /**
     * Rooms (of one type, or all) free every night from $from to the night before $to.
     * Ready rooms come first, so "any free one" keeps a clean room rather than one
     * still waiting for cleaning (or out of order) when both are free.
     */
    public function freeRooms(Hotel $hotel, ?string $typeId, Carbon $from, Carbon $to): Collection
    {
        $rooms = HotelRoom::where('hotel_id', $hotel->id)->where('active', true)
            ->when($typeId, fn($q) => $q->where('room_type_id', $typeId))
            ->orderByRaw('CASE state WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', [HotelRoom::READY, HotelRoom::TO_CLEAN])
            ->orderByRaw('LENGTH(number)')->orderBy('number')->get();
        if ($rooms->isEmpty()) {
            return $rooms;
        }
        $taken = $this->takenRoomIds($rooms->pluck('id')->all(), $from, $to);
        return $rooms->reject(fn(HotelRoom $r) => isset($taken[(string)$r->id]))->values();
    }

    /** Free rooms per room type for the dates: [type id => count]. */
    public function freeCounts(Hotel $hotel, Carbon $from, Carbon $to): array
    {
        if ($hotel->usesPos()) {
            // [type id => count, or null when POS has not said for one of the nights].
            return app(HotelPosService::class)->freeCounts($hotel, $from, $to);
        }
        $counts = [];
        foreach ($this->freeRooms($hotel, null, $from, $to) as $room) {
            $counts[(string)$room->room_type_id] = ($counts[(string)$room->room_type_id] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * Rooms (of these) that cannot be given for the dates: a night taken, and when the stay starts
     * today, a room out of order or a guest who should have left but is still in it.
     */
    protected function takenRoomIds(array $roomIds, Carbon $from, Carbon $to): array
    {
        $taken = HotelRoomNight::whereIn('room_id', $roomIds)
            ->where('night', '>=', $from->toDateString())->where('night', '<', $to->toDateString())
            ->distinct()->pluck('room_id')->flip()->all();
        if ($from->lte(today())) {
            foreach (HotelRoom::whereIn('id', $roomIds)->where('state', HotelRoom::OUT_OF_ORDER)->pluck('id') as $id) {
                $taken[(string)$id] = true;
            }
            foreach (HotelStay::whereIn('room_id', $roomIds)->where('status', HotelStay::IN_HOUSE)
                         ->where('check_out', '<=', today()->toDateString())->pluck('room_id') as $id) {
                $taken[(string)$id] = true;
            }
        }
        return $taken;
    }

    // ------------------------------------------------------------- Taking a room

    /**
     * Gives a room for the nights from $from to the night before $to. Refused, with the night that
     * clashes, when any of them is taken (the unique key also stops two people at the same moment).
     */
    public function take(Hotel $hotel, HotelRoom $room, Carbon $from, Carbon $to, array $attributes): HotelStay
    {
        $this->own($hotel, $room->hotel_id);
        if (!$from->lt($to)) {
            throw ValidationException::withMessages(['check_out' => translate('The day of leaving must come after the day of arrival.')]);
        }
        return DB::transaction(function () use ($hotel, $room, $from, $to, $attributes) {
            if (isset($this->takenRoomIds([(string)$room->id], $from, $to)[(string)$room->id])) {
                throw ValidationException::withMessages(['room' => $this->clash($room, $from, $to)]);
            }
            $stay = HotelStay::create($attributes + [
                'hotel_id' => $hotel->id,
                'room_type_id' => $room->room_type_id,
                'room_id' => $room->id,
                'check_in' => $from->toDateString(),
                'check_out' => $to->toDateString(),
            ]);
            try {
                foreach (CarbonPeriod::create($from, $to->copy()->subDay()) as $night) {
                    HotelRoomNight::create(['stay_id' => $stay->id, 'room_id' => $room->id, 'night' => $night->toDateString()]);
                }
            } catch (QueryException $e) {
                if ((string)$e->getCode() === '23000') {
                    throw ValidationException::withMessages(['room' => $this->clash($room, $from, $to)]);
                }
                throw $e;
            }
            return $stay;
        });
    }

    protected function clash(HotelRoom $room, Carbon $from, Carbon $to): string
    {
        $night = HotelRoomNight::where('room_id', $room->id)->where('night', '>=', $from->toDateString())
            ->where('night', '<', $to->toDateString())->orderBy('night')->value('night');
        if ($night) {
            return translate(key: 'Room {room} is already taken the night of {date}.', replace: ['room' => $room->number, 'date' => Carbon::parse($night)->translatedFormat('D j M')]);
        }
        return translate(key: 'Room {room} cannot be given tonight: it is out of order or a guest is still in it.', replace: ['room' => $room->number]);
    }

    /** A guest at the desk now, staying until $to. */
    public function walkIn(Hotel $hotel, HotelRoom $room, Carbon $to, array $input, ?string $by): HotelStay
    {
        $stay = $this->take($hotel, $room, today(), $to, [
            'source' => 'walk_in', 'status' => HotelStay::IN_HOUSE, 'arrived_at' => now(),
            'guests' => max(1, (int)($input['guests'] ?? 1)), 'guest_name' => $input['guest_name'] ?? null, 'created_by' => $by,
        ]);
        if ($room->state !== HotelRoom::READY) {
            $room->update(['state' => HotelRoom::READY, 'state_note' => null, 'state_at' => now()]);
        }
        $hotel->touchBoard();
        return $stay;
    }

    /** Nights booked by phone, WhatsApp, at the desk for later, or blocked (repairs). */
    public function book(Hotel $hotel, HotelRoom $room, Carbon $from, Carbon $to, string $source, array $input, ?string $by): HotelStay
    {
        if (!in_array($source, HotelStay::SOURCES, true)) {
            throw ValidationException::withMessages(['source' => translate('Choose where the booking came from.')]);
        }
        if ($from->lt(today())) {
            throw ValidationException::withMessages(['check_in' => translate('The arrival day cannot be in the past.')]);
        }
        $stay = $this->take($hotel, $room, $from, $to, [
            'source' => $source, 'status' => HotelStay::BOOKED,
            'guests' => max(1, (int)($input['guests'] ?? 1)), 'guest_name' => $input['guest_name'] ?? null, 'created_by' => $by,
        ]);
        $hotel->touchBoard();
        return $stay;
    }

    // ------------------------------------------------------------- The desk

    /** The guest is here: the kept or booked room is now occupied. */
    public function arrive(Hotel $hotel, HotelStay $stay): HotelStay
    {
        $this->own($hotel, $stay->hotel_id);
        // Under a lock: the minute tick may be letting this kept room go right now.
        DB::transaction(function () use ($stay) {
            $fresh = $this->lockStay($stay, [HotelStay::KEPT, HotelStay::BOOKED], translate('This guest is already in, or the room was let go.'));
            $fresh->update(['status' => HotelStay::IN_HOUSE, 'arrived_at' => now(), 'kept_until' => null]);
            $stay->setRawAttributes($fresh->getAttributes(), true);
        });
        $hotel->touchBoard();
        if ($stay->inquiry_id && ($inquiry = HotelInquiry::find($stay->inquiry_id))) {
            app(InquiryService::class)->staysChanged($inquiry, 'arrived');
        }
        return $stay;
    }

    /** The guest did not come: the room is free again. */
    public function noShow(Hotel $hotel, HotelStay $stay): HotelStay
    {
        $this->own($hotel, $stay->hotel_id);
        if (!$this->end($stay, HotelStay::NO_SHOW, [HotelStay::KEPT, HotelStay::BOOKED])) {
            throw ValidationException::withMessages(['stay' => translate('This guest is already in, or the room was let go.')]);
        }
        $hotel->touchBoard();
        if ($stay->inquiry_id && ($inquiry = HotelInquiry::find($stay->inquiry_id))) {
            app(InquiryService::class)->staysChanged($inquiry, 'didnt_come');
        }
        return $stay;
    }

    /** The guest left: the room goes to cleaning, and nights not used are free again. */
    public function leave(Hotel $hotel, HotelStay $stay): HotelStay
    {
        $this->own($hotel, $stay->hotel_id);
        DB::transaction(function () use ($stay) {
            $fresh = $this->lockStay($stay, [HotelStay::IN_HOUSE], translate('This guest is not in the house.'));
            HotelRoomNight::where('stay_id', $stay->id)->where('night', '>=', today()->toDateString())->delete();
            $fresh->update(['status' => HotelStay::LEFT, 'left_at' => now(), 'ended_at' => now()]);
            $stay->setRawAttributes($fresh->getAttributes(), true);
            HotelRoom::whereKey($stay->room_id)->update(['state' => HotelRoom::TO_CLEAN, 'state_note' => null, 'state_at' => now()]);
        });
        $hotel->touchBoard();
        if ($stay->inquiry_id && ($inquiry = HotelInquiry::find($stay->inquiry_id))) {
            app(InquiryService::class)->staysChanged($inquiry, 'stayed');
        }
        return $stay;
    }

    /** Takes back a booking the reception entered (phone, WhatsApp, blocked nights). E-Ride guests' rooms go through the chat. */
    public function cancel(Hotel $hotel, HotelStay $stay): HotelStay
    {
        $this->own($hotel, $stay->hotel_id);
        if ($stay->source === HotelStay::ERIDE || !$this->end($stay, HotelStay::RELEASED, [HotelStay::BOOKED])) {
            throw ValidationException::withMessages(['stay' => translate('Only a booking you entered and that has not started can be removed here.')]);
        }
        $hotel->touchBoard();
        return $stay;
    }

    /**
     * Ends a stay that holds nights: they are free again. With $onlyFrom, only
     * if the stay (read again under a lock) is still in one of those states and
     * passes $still; returns false when it has moved on (the guest just arrived,
     * the reception kept it longer).
     */
    public function end(HotelStay $stay, string $status, ?array $onlyFrom = null, ?callable $still = null): bool
    {
        return DB::transaction(function () use ($stay, $status, $onlyFrom, $still) {
            $fresh = HotelStay::whereKey($stay->id)->lockForUpdate()->first();
            if (!$fresh || ($onlyFrom !== null && !in_array($fresh->status, $onlyFrom, true)) || ($still && !$still($fresh))) {
                return false;
            }
            HotelRoomNight::where('stay_id', $stay->id)->delete();
            $fresh->update(['status' => $status, 'ended_at' => now()]);
            $stay->setRawAttributes($fresh->getAttributes(), true);
            return true;
        });
    }

    /** The stay read again under a row lock, in one of the $allowed states. */
    protected function lockStay(HotelStay $stay, array $allowed, string $message): HotelStay
    {
        $fresh = HotelStay::whereKey($stay->id)->lockForUpdate()->first();
        if (!$fresh || !in_array($fresh->status, $allowed, true)) {
            throw ValidationException::withMessages(['stay' => $message]);
        }
        return $fresh;
    }

    public function setRoomState(Hotel $hotel, HotelRoom $room, string $state, ?string $note): HotelRoom
    {
        $this->own($hotel, $room->hotel_id);
        if (!in_array($state, HotelRoom::STATES, true)) {
            throw ValidationException::withMessages(['state' => translate('Unknown room state.')]);
        }
        $room->update(['state' => $state, 'state_note' => $state === HotelRoom::OUT_OF_ORDER ? mb_substr(trim((string)$note), 0, 120) ?: null : null, 'state_at' => now()]);
        $hotel->touchBoard();
        return $room;
    }

    // ------------------------------------------------------------- The rooms board

    /**
     * Today's board: every room with what it is now (free, kept, arriving, occupied, to clean,
     * out of order), and the lists the reception works from.
     */
    public function board(Hotel $hotel): array
    {
        $today = today();
        $rooms = HotelRoom::with('type')->where('hotel_id', $hotel->id)->where('active', true)
            ->orderByRaw('LENGTH(number)')->orderBy('number')->get();
        $tonight = HotelStay::with('inquiry.customer')->whereIn('id', HotelRoomNight::whereIn('room_id', $rooms->pluck('id'))->where('night', $today->toDateString())->select('stay_id'))
            ->get()->keyBy('room_id');
        $overstay = HotelStay::where('hotel_id', $hotel->id)->where('status', HotelStay::IN_HOUSE)->where('check_out', '<=', $today->toDateString())->get()->keyBy('room_id');
        $board = [];
        foreach ($rooms as $room) {
            $stay = $tonight[$room->id] ?? $overstay[$room->id] ?? null;
            $status = match (true) {
                $stay && $stay->status === HotelStay::IN_HOUSE => 'occupied',
                $stay && $stay->status === HotelStay::KEPT => 'kept',
                $stay && $stay->source === HotelStay::BLOCKED => 'out_of_order',
                $stay && $stay->status === HotelStay::BOOKED => 'booked',
                $room->state === HotelRoom::OUT_OF_ORDER => 'out_of_order',
                $room->state === HotelRoom::TO_CLEAN => 'to_clean',
                default => 'free',
            };
            $board[] = ['room' => $room, 'status' => $status, 'stay' => $stay];
        }
        $holding = HotelStay::with(['room', 'type', 'inquiry.customer'])->where('hotel_id', $hotel->id);
        return [
            'rooms' => $board,
            'kept' => (clone $holding)->where('status', HotelStay::KEPT)->where('check_in', '<=', $today->toDateString())->orderBy('kept_until')->get(),
            'arriving' => (clone $holding)->where('status', HotelStay::BOOKED)->where('source', '!=', HotelStay::BLOCKED)->where('check_in', '<=', $today->toDateString())->orderBy('check_in')->get(),
            'leaving' => (clone $holding)->where('status', HotelStay::IN_HOUSE)->where('check_out', '<=', $today->toDateString())->orderBy('check_out')->get(),
        ];
    }

    /** Rooms by type for the coming nights: [type => [room => [Y-m-d => stay]]] and free counts per night. */
    public function calendar(Hotel $hotel, Carbon $from, int $days): array
    {
        $to = $from->copy()->addDays($days);
        $rooms = HotelRoom::where('hotel_id', $hotel->id)->where('active', true)->orderByRaw('LENGTH(number)')->orderBy('number')->get();
        $nights = HotelRoomNight::whereIn('room_id', $rooms->pluck('id'))->where('night', '>=', $from->toDateString())->where('night', '<', $to->toDateString())->get();
        $stays = HotelStay::whereIn('id', $nights->pluck('stay_id')->unique())->get()->keyBy('id');
        $grid = [];
        foreach ($nights as $n) {
            $grid[$n->room_id][$n->night->toDateString()] = $stays[$n->stay_id] ?? null;
        }
        return ['rooms' => $rooms, 'grid' => $grid, 'days' => collect(CarbonPeriod::create($from, $to->copy()->subDay()))->map(fn($d) => $d->copy())];
    }

    // ------------------------------------------------------------- Every minute

    /** Kept rooms past their time go back on sale; phone bookings never started end. */
    public function tick(): array
    {
        $released = 0;
        HotelStay::where('status', HotelStay::KEPT)->whereNull('pos_ref')->where('kept_until', '<', now())->orderBy('kept_until')->limit(200)->get()
            ->each(function (HotelStay $stay) use (&$released) {
                // Still kept and still past its time once locked: the guest may have
                // just arrived, or the reception kept it longer.
                if (!$this->end($stay, HotelStay::RELEASED, [HotelStay::KEPT],
                    fn(HotelStay $s) => $s->kept_until && $s->kept_until->lt(now()))) {
                    return;
                }
                $released++;
                if ($stay->inquiry_id && ($inquiry = HotelInquiry::find($stay->inquiry_id))) {
                    app(InquiryService::class)->staysChanged($inquiry, 'expired');
                }
            });
        $ended = 0;
        HotelStay::where('status', HotelStay::BOOKED)->whereNull('pos_ref')->where('check_out', '<=', today()->toDateString())->limit(200)->get()
            ->each(function (HotelStay $stay) use (&$ended) {
                if ($this->end($stay, $stay->source === HotelStay::BLOCKED ? HotelStay::RELEASED : HotelStay::NO_SHOW, [HotelStay::BOOKED])) {
                    $ended++;
                }
            });
        return ['released' => $released, 'ended' => $ended];
    }

    protected function own(Hotel $hotel, ?string $hotelId): void
    {
        if ((string)$hotelId !== (string)$hotel->id) {
            abort(404);
        }
    }
}
