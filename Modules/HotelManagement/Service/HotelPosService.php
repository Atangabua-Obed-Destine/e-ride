<?php

namespace Modules\HotelManagement\Service;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\HotelManagement\Entities\Hotel;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelMessage;
use Modules\HotelManagement\Entities\HotelPosLink;
use Modules\HotelManagement\Entities\HotelPosType;
use Modules\HotelManagement\Entities\HotelRoomType;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\HotelManagement\Lib\HotelPush;

/**
 * The hotel side of the POS Innova channel (docs/posinnova-hotel-contract.md). POS owns the rooms and
 * the reservations; E-Ride keeps the guest side: hotel page, photos, the chat, and the stays it mirrors.
 */
class HotelPosService
{
    public const MAX_NIGHTS = 130;

    public function __construct(protected InquiryService $inquiries, protected StayService $stays)
    {
    }

    // ------------------------------------------------------------ the link

    /** A new key for the hotel; any older one stops working. Returns [link, key]: the key is shown once. */
    public function createLink(Hotel $hotel, ?string $byUserId): array
    {
        HotelPosLink::where('hotel_id', $hotel->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $key = 'erh_' . Str::random(40);
        $link = HotelPosLink::create(['hotel_id' => $hotel->id, 'key_hash' => hash('sha256', $key), 'key_hint' => substr($key, 0, 8), 'created_by' => $byUserId]);
        return [$link, $key];
    }

    public function revoke(HotelPosLink $link): void
    {
        $link->forceFill(['revoked_at' => now()])->save();
    }

    /** The link of a bearer key (revoked ones too, to say so), noting the call on E-Ride's clock. */
    public function authenticate(?string $bearer): ?HotelPosLink
    {
        if (!$bearer || !str_starts_with($bearer, 'erh_')) {
            return null;
        }
        $link = HotelPosLink::where('key_hash', hash('sha256', $bearer))->first();
        if ($link && !$link->revoked_at) {
            $link->forceFill(['last_seen_at' => now(), 'linked_at' => $link->linked_at ?? now()])->saveQuietly();
        }
        return $link;
    }

    public function hello(HotelPosLink $link): array
    {
        $hotel = $link->hotel;
        return [
            'hotel' => ['id' => $hotel->id, 'name' => $hotel->name],
            'linked_at' => $link->linked_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'room_types' => $hotel->roomTypes()->where('active', true)->orderBy('sort')->get()
                ->map(fn(HotelRoomType $t) => ['id' => $t->id, 'name' => $t->name, 'capacity' => (int)$t->max_guests])->values()->all(),
        ];
    }

    public function heartbeat(HotelPosLink $link): array
    {
        $link->forceFill(['last_heartbeat_at' => now()])->save();
        return ['ok' => true, 'server_time' => now()->toIso8601String()];
    }

    // ------------------------------------------------------------ availability

    public function availabilityFull(HotelPosLink $link, array $input): array
    {
        $snapshot = (string)($input['snapshot_id'] ?? '');
        if (!Str::isUuid($snapshot)) {
            throw ValidationException::withMessages(['snapshot_id' => 'snapshot_id must be a uuid']);
        }
        $day = $this->day($input['hotel_day'] ?? null, 'hotel_day');
        $seen = [];
        DB::transaction(function () use ($link, $input, $day, $snapshot, &$seen) {
            foreach ((array)($input['types'] ?? []) as $row) {
                if (!is_array($row) || !isset($row['pos_type_id'])) {
                    continue;
                }
                $type = $this->upsertType($link, $row);
                $type->forceFill(['free_from' => $day, 'free' => $this->counts($row['free'] ?? []), 'snapshot_id' => $snapshot, 'removed_at' => null])->save();
                $seen[] = $type->id;
            }
            // A type missing from a full push is gone.
            $link->types()->whereNotIn('id', $seen ?: ['-'])->whereNull('removed_at')->update(['removed_at' => now()]);
            $link->forceFill(['hotel_day' => $day, 'last_availability_at' => now()])->save();
        });
        $this->applyToRoomTypes($link);
        return ['received' => count($seen)];
    }

    public function availabilityChanges(HotelPosLink $link, array $input): array
    {
        $day = isset($input['hotel_day']) ? $this->day($input['hotel_day'], 'hotel_day') : null;
        $n = 0;
        DB::transaction(function () use ($link, $input, $day, &$n) {
            if ($day) {
                $link->forceFill(['hotel_day' => $day])->save();
            }
            foreach ((array)($input['types'] ?? []) as $row) {
                if (!is_array($row) || !isset($row['pos_type_id'])) {
                    continue;
                }
                $exists = $link->types()->where('pos_type_id', (int)$row['pos_type_id'])->exists();
                if (!$exists && !filled($row['name'] ?? null)) {
                    continue; // a type E-Ride never saw, without its name: wait for the full push
                }
                $type = $this->upsertType($link, $row);
                if (isset($row['from'], $row['free'])) {
                    $this->mergeNights($type, $this->day($row['from'], 'from'), $this->counts($row['free']), $link->hotel_day);
                }
                $type->forceFill(['removed_at' => null])->save();
                $n++;
            }
            foreach ((array)($input['removed_types'] ?? []) as $posTypeId) {
                $link->types()->where('pos_type_id', (int)$posTypeId)->whereNull('removed_at')->update(['removed_at' => now()]);
            }
            $link->forceFill(['last_availability_at' => now()])->save();
        });
        $this->applyToRoomTypes($link);
        return ['received' => $n];
    }

    protected function upsertType(HotelPosLink $link, array $row): HotelPosType
    {
        $type = HotelPosType::firstOrNew(['link_id' => $link->id, 'pos_type_id' => (int)$row['pos_type_id']]);
        if (filled($row['name'] ?? null)) {
            $type->name = Str::limit((string)$row['name'], 120, '');
        }
        foreach (['capacity' => 1, 'eride_price' => 0, 'rooms_total' => 0] as $field => $min) {
            if (isset($row[$field])) {
                $type->{$field} = max($min, (int)round((float)$row[$field]));
            }
        }
        $type->name ??= 'Room type ' . (int)$row['pos_type_id'];
        $type->save();
        return $type;
    }

    /** Writes $counts from night $from into the type's nights, dropping nights before the hotel day. */
    protected function mergeNights(HotelPosType $type, Carbon $from, array $counts, ?Carbon $hotelDay): void
    {
        $nights = [];
        if ($type->free_from) {
            foreach ($type->free ?? [] as $i => $v) {
                $nights[$type->free_from->copy()->addDays($i)->toDateString()] = $v;
            }
        }
        foreach ($counts as $i => $v) {
            $nights[$from->copy()->addDays($i)->toDateString()] = $v;
        }
        ksort($nights);
        $start = Carbon::parse(array_key_first($nights));
        if ($hotelDay && $hotelDay->gt($start)) {
            $start = $hotelDay->copy();
        }
        $last = Carbon::parse(array_key_last($nights));
        $out = [];
        for ($d = $start->copy(); $d->lte($last) && count($out) < self::MAX_NIGHTS; $d->addDay()) {
            // A gap inside the range is a night POS never sent: unknown, so 0 rather than a guess.
            $out[] = $nights[$d->toDateString()] ?? 0;
        }
        $type->forceFill(['free_from' => $start->toDateString(), 'free' => $out])->save();
    }

    protected function counts($free): array
    {
        return array_slice(array_map(fn($v) => max(0, (int)$v), array_values((array)$free)), 0, self::MAX_NIGHTS);
    }

    protected function day($value, string $field): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', (string)$value)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => $field . ' must be a date (Y-m-d)']);
        }
    }

    /** A mapped E-Ride room type takes its "from" price and max guests from POS. */
    public function applyToRoomTypes(HotelPosLink $link): void
    {
        foreach ($link->types()->whereNull('removed_at')->whereNotNull('room_type_id')->get() as $t) {
            $fill = ['max_guests' => max(1, $t->capacity)];
            if ($t->eride_price > 0) {
                $fill['price_from'] = $t->eride_price;
            }
            HotelRoomType::whereKey($t->room_type_id)->where('hotel_id', $link->hotel_id)->update($fill);
        }
    }

    /** [E-Ride room type id => free rooms for every night, or null when POS has not said for one]. */
    public function freeCounts(Hotel $hotel, Carbon $from, Carbon $to): array
    {
        $link = $hotel->posLink();
        if (!$link) {
            return [];
        }
        $out = [];
        foreach ($link->types()->whereNull('removed_at')->whereNotNull('room_type_id')->get() as $t) {
            $id = (string)$t->room_type_id;
            $n = $t->freeFor($from, $to);
            $out[$id] = !array_key_exists($id, $out) ? $n : ($out[$id] === null || $n === null ? null : $out[$id] + $n);
        }
        return $out;
    }

    /** E-Ride room types mapped to a POS type still published: the ones guests can ask for. */
    public function mappedTypeIds(Hotel $hotel): array
    {
        $link = $hotel->posLink();
        return $link ? $link->types()->whereNull('removed_at')->whereNotNull('room_type_id')->pluck('room_type_id')->map(fn($id) => (string)$id)->unique()->values()->all() : [];
    }

    // ------------------------------------------------------------ inquiries

    public static function number(HotelInquiry $q): string
    {
        return 'H-' . strtoupper(substr(str_replace('-', '', (string)$q->id), 0, 6));
    }

    public function pending(HotelPosLink $link): array
    {
        $list = HotelInquiry::with(['customer', 'roomType'])->where('hotel_id', $link->hotel_id)
            ->whereIn('status', HotelInquiry::ACTIVE)->orderBy('created_at')->limit(100)->get();
        $release = HotelStay::with('inquiry')->where('hotel_id', $link->hotel_id)->whereNotNull('pos_ref')
            ->whereNotNull('pos_release_due_at')->whereNull('pos_release_seen_at')->limit(100)->get();
        return [
            'inquiries' => $list->map(fn(HotelInquiry $q) => $this->payload($link, $q))->values()->all(),
            'to_release' => $release->map(fn(HotelStay $s) => [
                'inquiry_id' => $s->inquiry_id, 'number' => $s->inquiry ? self::number($s->inquiry) : null, 'pos_ref' => $s->pos_ref,
                'reason' => $s->pos_release_reason, 'at' => $s->pos_release_due_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function find(HotelPosLink $link, string $id): HotelInquiry
    {
        $q = Str::isUuid($id) ? HotelInquiry::with(['customer', 'roomType'])->where('hotel_id', $link->hotel_id)->find($id) : null;
        abort_if(!$q, 404);
        return $q;
    }

    public function payload(HotelPosLink $link, HotelInquiry $q): array
    {
        $posType = $q->room_type_id ? $link->types()->where('room_type_id', $q->room_type_id)->whereNull('removed_at')->first() : null;
        $request = HotelMessage::where('inquiry_id', $q->id)->where('kind', 'request')->latest('id')->first();
        $lastGuest = HotelMessage::where('inquiry_id', $q->id)->where('sender', HotelMessage::GUEST)->where('kind', 'text')->latest('id')->first();
        $customer = $q->customer;
        return [
            'inquiry_id' => $q->id,
            'number' => self::number($q),
            'status' => $q->status,
            'guest' => [
                'name' => trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? '')),
                'phone' => $customer?->phone,
            ],
            'check_in' => $q->check_in->toDateString(),
            'check_out' => $q->check_out->toDateString(),
            'nights' => $q->nights(),
            'guests' => $q->guests,
            'rooms' => $q->rooms,
            'room_type' => $q->roomType ? ['eride_id' => $q->roomType->id, 'name' => $q->roomType->name, 'pos_type_id' => $posType?->pos_type_id] : null,
            'arrival' => $q->arrival,
            'question' => $request?->body,
            'last_guest_message' => $lastGuest ? ['text' => $lastGuest->body, 'at' => $lastGuest->created_at?->toIso8601String()] : null,
            'unread' => (int)$q->unread_hotel,
            'reservations' => $q->stays()->whereNotNull('pos_ref')->orderBy('created_at')->get()
                ->map(fn(HotelStay $s) => ['pos_ref' => $s->pos_ref, 'status' => $s->status, 'until' => $s->kept_until?->toIso8601String()])->values()->all(),
            'eride_url' => route('hotel-partner.inbox', ['q' => $q->id]),
        ];
    }

    /** "Keep a room" (tentative) or booked at once (confirmed) in POS: one stay per POS reservation. */
    public function kept(HotelPosLink $link, HotelInquiry $q, array $input): array
    {
        $status = $input['status'] ?? 'tentative';
        if (!in_array($status, ['tentative', 'confirmed'], true)) {
            throw ValidationException::withMessages(['status' => 'status must be tentative or confirmed']);
        }
        $refs = collect((array)($input['pos_refs'] ?? []))->map(fn($r) => Str::limit(trim((string)$r), 60, ''))->filter()->unique()->values();
        if ($refs->isEmpty() || $refs->count() > 20) {
            throw ValidationException::withMessages(['pos_refs' => 'pos_refs must list 1 to 20 reservations']);
        }
        $until = null;
        if ($status === 'tentative') {
            try {
                $until = Carbon::parse((string)($input['until'] ?? ''));
            } catch (\Throwable) {
                throw ValidationException::withMessages(['until' => 'until is required for a tentative reservation']);
            }
            if (!filled($input['until'] ?? null)) {
                throw ValidationException::withMessages(['until' => 'until is required for a tentative reservation']);
            }
        }
        $posType = isset($input['pos_type_id']) ? $link->types()->where('pos_type_id', (int)$input['pos_type_id'])->first() : null;
        $roomTypeId = $posType?->room_type_id ?? $q->room_type_id;

        $result = DB::transaction(function () use ($q, $refs, $status, $until, $roomTypeId, $input) {
            $fresh = HotelInquiry::whereKey($q->id)->lockForUpdate()->first();
            if (!$fresh || !$fresh->isActive()) {
                return ['conflict' => 'inquiry_closed'];
            }
            $target = $status === 'confirmed' ? HotelStay::BOOKED : HotelStay::KEPT;
            $new = 0;
            foreach ($refs as $ref) {
                $stay = $fresh->stays()->where('pos_ref', $ref)->lockForUpdate()->first();
                if ($stay) {
                    if (in_array($stay->status, [HotelStay::KEPT, HotelStay::BOOKED], true)) {
                        // Same reservation again: a new time, or now confirmed (never back to kept).
                        $stay->update(['status' => $stay->status === HotelStay::BOOKED ? HotelStay::BOOKED : $target,
                            'kept_until' => $target === HotelStay::BOOKED || $stay->status === HotelStay::BOOKED ? null : $until]);
                    }
                    continue;
                }
                HotelStay::create([
                    'hotel_id' => $fresh->hotel_id, 'room_type_id' => $roomTypeId ?? $fresh->room_type_id, 'room_id' => null, 'inquiry_id' => $fresh->id,
                    'source' => HotelStay::ERIDE, 'status' => $target, 'check_in' => $fresh->check_in, 'check_out' => $fresh->check_out,
                    'guests' => $fresh->guests, 'guest_name' => $fresh->customer?->first_name, 'kept_until' => $until, 'pos_ref' => $ref,
                ]);
                $new++;
            }
            $keptLeft = $fresh->stays()->where('status', HotelStay::KEPT)->count();
            $fresh->update(['status' => $keptLeft > 0 ? HotelInquiry::KEPT : HotelInquiry::BOOKED, 'room_type_id' => $roomTypeId ?? $fresh->room_type_id]);
            $this->post($fresh, $status === 'confirmed' ? 'booked' : 'kept', [
                'until' => $until?->toIso8601String(), 'room_type' => HotelRoomType::find($roomTypeId)?->name,
                'rooms' => $fresh->stays()->whereIn('status', [HotelStay::KEPT, HotelStay::BOOKED])->count(),
                'check_in' => $fresh->check_in->toDateString(), 'check_out' => $fresh->check_out->toDateString(),
                'by' => isset($input['by']) ? Str::limit((string)$input['by'], 40, '') : null,
            ]);
            return ['ok' => true, 'new' => $new, 'inquiry' => $fresh];
        });
        if (isset($result['conflict'])) {
            return $result;
        }
        $fresh = $result['inquiry'];
        $this->answered($fresh);
        $hotel = $fresh->hotel;
        if ($status === 'confirmed') {
            HotelPush::send($fresh->customer, 'hotel_booked', 'Your room at {hotel} is booked', 'The reception booked it for you. Ride there with E-Ride.',
                ['hotel' => $hotel->name], ['hotel_inquiry_id' => $fresh->id]);
        } else {
            HotelPush::send($fresh->customer, 'hotel_kept', 'Room kept for you until {time}', '{hotel} keeps it for you. Ride there with E-Ride.',
                ['hotel' => $hotel->name, 'time' => $until->copy()->setTimezone(config('app.timezone'))->format('H:i')], ['hotel_inquiry_id' => $fresh->id]);
        }
        return ['ok' => true, 'status' => $fresh->status];
    }

    public function declined(HotelPosLink $link, HotelInquiry $q, array $input): array
    {
        $reason = ($input['reason'] ?? 'other') === 'full' ? 'full' : 'hotel';
        if (!$q->isActive()) {
            return ['ok' => true, 'status' => $q->status];
        }
        if (filled($input['text'] ?? null)) {
            $this->writeAsHotel($q, (string)$input['text'], $input['by'] ?? null);
        }
        $q = $this->inquiries->close($q->fresh(), null, $reason, true);
        return ['ok' => true, 'status' => $q->status];
    }

    public function reply(HotelPosLink $link, HotelInquiry $q, array $input): array
    {
        $text = trim((string)($input['text'] ?? ''));
        if ($text === '' || mb_strlen($text) > 500) {
            throw ValidationException::withMessages(['text' => 'text must be 1 to 500 characters']);
        }
        if (!$q->isActive()) {
            return ['conflict' => 'inquiry_closed'];
        }
        $m = $this->writeAsHotel($q, $text, $input['by'] ?? null);
        return ['ok' => true, 'message_id' => $m->id];
    }

    protected function writeAsHotel(HotelInquiry $q, string $text, $by): HotelMessage
    {
        $m = $this->inquiries->hotelWrite($q, '', Str::limit(trim($text), 500, ''));
        $m->forceFill(['user_id' => null, 'data' => ['via' => 'pos', 'by' => $by ? Str::limit((string)$by, 40, '') : null]])->save();
        return $m;
    }

    /** A reservation's life in POS: confirmed, arrived, left, no_show, released (hold lapsed), cancelled. */
    public function event(HotelPosLink $link, HotelInquiry $q, array $input): array
    {
        $event = (string)($input['event'] ?? '');
        $map = [
            'confirmed' => HotelStay::BOOKED, 'arrived' => HotelStay::IN_HOUSE, 'left' => HotelStay::LEFT,
            'no_show' => HotelStay::NO_SHOW, 'released' => HotelStay::RELEASED, 'cancelled' => HotelStay::RELEASED,
        ];
        if (!isset($map[$event])) {
            throw ValidationException::withMessages(['event' => 'event must be one of ' . implode(', ', array_keys($map))]);
        }
        $ref = trim((string)($input['pos_ref'] ?? ''));
        $stay = $ref !== '' ? $q->stays()->where('pos_ref', $ref)->first() : null;
        if (!$stay) {
            return ['conflict' => 'not_this_reservation'];
        }
        $to = $map[$event];
        $at = filled($input['at'] ?? null) ? rescue(fn() => Carbon::parse($input['at']), now(), false) : now();
        $changed = DB::transaction(function () use ($stay, $to, $at, $event) {
            $s = HotelStay::whereKey($stay->id)->lockForUpdate()->first();
            if ($s->status === $to) {
                return false; // already there: a retry
            }
            $fill = ['status' => $to];
            if ($to === HotelStay::BOOKED) {
                if ($s->status !== HotelStay::KEPT) {
                    return false;
                }
                $fill['kept_until'] = null;
            } elseif ($to === HotelStay::IN_HOUSE) {
                $fill['arrived_at'] = $at;
                $fill['kept_until'] = null;
            } else {
                $fill['ended_at'] = $at;
                if ($to === HotelStay::LEFT) {
                    $fill['left_at'] = $at;
                }
            }
            // POS ended it itself: nothing for POS to cancel any more.
            if (in_array($to, [HotelStay::RELEASED, HotelStay::NO_SHOW, HotelStay::LEFT], true)) {
                $fill['pos_release_due_at'] = null;
            }
            $s->update($fill);
            return true;
        });
        if ($changed) {
            $this->inquiries->staysChanged($q->fresh(), match ($event) {
                'confirmed' => 'booked', 'arrived' => 'arrived', 'left' => 'stayed', 'no_show' => 'didnt_come',
                'released' => 'expired', 'cancelled' => 'cancelled',
            });
            if ($event === 'confirmed' && ($fresh = $q->fresh()) && $fresh->status === HotelInquiry::BOOKED) {
                $this->post($fresh, 'booked', ['rooms' => $fresh->stays()->where('status', HotelStay::BOOKED)->count(),
                    'room_type' => $fresh->roomType?->name, 'check_in' => $fresh->check_in->toDateString(), 'check_out' => $fresh->check_out->toDateString()]);
                HotelPush::send($fresh->customer, 'hotel_booked', 'Your room at {hotel} is booked', 'The reception booked it for you. Ride there with E-Ride.',
                    ['hotel' => $fresh->hotel->name], ['hotel_inquiry_id' => $fresh->id]);
            }
        }
        return ['ok' => true, 'status' => $q->fresh()->status];
    }

    public function releaseSeen(HotelPosLink $link, HotelInquiry $q, array $input): array
    {
        $refs = collect((array)($input['pos_refs'] ?? []))->map(fn($r) => (string)$r)->filter();
        $q->stays()->whereNotNull('pos_release_due_at')->whereNull('pos_release_seen_at')
            ->when($refs->isNotEmpty(), fn($w) => $w->whereIn('pos_ref', $refs->all()))
            ->update(['pos_release_seen_at' => now()]);
        return ['ok' => true];
    }

    protected function post(HotelInquiry $q, string $kind, array $data): void
    {
        HotelMessage::create(['inquiry_id' => $q->id, 'sender' => HotelMessage::HOTEL, 'user_id' => null, 'kind' => $kind, 'body' => null, 'data' => $data + ['via' => 'pos']]);
        $q->forceFill(['last_message_at' => now(), 'unread_guest' => (int)$q->unread_guest + 1])->save();
    }

    protected function answered(HotelInquiry $q): void
    {
        $fill = [];
        if (!$q->first_answer_at) {
            $fill['first_answer_at'] = now();
        }
        if ($fill) {
            $q->forceFill($fill)->save();
        }
    }

    // ------------------------------------------------------------ the portal

    /** Maps a POS room type to an E-Ride room type of the hotel (null = not shown to guests). */
    public function mapType(HotelPosLink $link, HotelPosType $type, ?string $roomTypeId): void
    {
        if ($roomTypeId) {
            HotelRoomType::where('hotel_id', $link->hotel_id)->findOrFail($roomTypeId);
            $link->types()->where('room_type_id', $roomTypeId)->where('id', '!=', $type->id)->update(['room_type_id' => null]);
        }
        $type->forceFill(['room_type_id' => $roomTypeId])->save();
        $this->applyToRoomTypes($link);
    }

    /** A new E-Ride room type from a POS one (name, guests, price), mapped to it. Photos and amenities are added on E-Ride. */
    public function createRoomType(HotelPosLink $link, HotelPosType $type): HotelRoomType
    {
        $room = HotelRoomType::create([
            'hotel_id' => $link->hotel_id, 'name' => Str::limit($type->name, 80, ''), 'max_guests' => max(1, $type->capacity),
            'price_from' => (int)$type->eride_price, 'amenities' => [], 'photos' => [],
            'sort' => (int)HotelRoomType::where('hotel_id', $link->hotel_id)->max('sort') + 1, 'active' => true,
        ]);
        $this->mapType($link, $type, $room->id);
        return $room;
    }

    /** POS types of the link with their mapping, for the portal. */
    public function types(HotelPosLink $link): Collection
    {
        return $link->types()->with('roomType')->whereNull('removed_at')->orderBy('name')->get();
    }
}
