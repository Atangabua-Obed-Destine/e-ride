<?php

namespace Modules\HotelManagement\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\HotelManagement\Entities\Hotel;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelRoomCode;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StorePosLink;
use Modules\UserManagement\Entities\User;

/**
 * Room service: a hotel's own restaurant (a Food & Shops store) brings orders to the rooms.
 * Who may order to which room, and whether the room bill (POS Innova) is allowed.
 */
class RoomService
{
    /** The hotel's restaurant, when it is on E-Ride and open for orders. */
    public function restaurant(Hotel $hotel): ?Store
    {
        if (!$hotel->restaurant_store_id) {
            return null;
        }
        $store = Store::where('is_active', 1)->find($hotel->restaurant_store_id);
        return $store && $store->brand()->is_active ? $store : null;
    }

    /** The guest's stay in the house at this hotel through E-Ride, if any. */
    public function inHouseStay(User $customer, Hotel $hotel): ?HotelStay
    {
        return HotelStay::where('hotel_id', $hotel->id)->where('status', HotelStay::IN_HOUSE)
            ->whereIn('inquiry_id', HotelInquiry::where('customer_id', $customer->id)->select('id'))
            ->latest('arrived_at')->first();
    }

    public static function roomOf(HotelStay $stay): ?string
    {
        return $stay->room_label ?: $stay->room?->number;
    }

    /**
     * The room bill: the hotel is on POS Innova and allows it, the guest is in the house through E-Ride
     * with a POS reservation, and the restaurant's kitchen is linked to POS (where the bill lives).
     */
    public function roomBillOk(Hotel $hotel, ?HotelStay $stay, Store $store): bool
    {
        if (!$stay || !$stay->pos_ref || $stay->status !== HotelStay::IN_HOUSE || !$hotel->pos_room_bill || !$hotel->usesPos()) {
            return false;
        }
        return StorePosLink::where('store_id', $store->id)->whereNull('revoked_at')->whereNotNull('linked_at')->exists();
    }

    /**
     * Room service context for a guest at a store: from the room's QR token, or the guest's stay at the
     * hotel. ['hotel', 'store', 'room', 'stay', 'room_bill'] or a validation error.
     */
    public function forOrder(User $customer, Store $store, array $input): array
    {
        $hotel = null;
        $room = null;
        $stay = null;
        if (filled($input['room_token'] ?? null)) {
            $code = HotelRoomCode::where('token', strtoupper(trim((string)$input['room_token'])))->whereNull('revoked_at')->first();
            if (!$code) {
                throw ValidationException::withMessages(['room' => translate('This room card is no longer valid. Ask the reception.')]);
            }
            $hotel = Hotel::find($code->hotel_id);
            $room = $code->room_label;
            $candidate = $hotel ? $this->inHouseStay($customer, $hotel) : null;
            // Booked through E-Ride and in this very room: the room bill may apply.
            $stay = $candidate && self::roomOf($candidate) === $room ? $candidate : null;
        } elseif (filled($input['hotel_id'] ?? null)) {
            $hotel = Hotel::find($input['hotel_id']);
            $stay = $hotel ? $this->inHouseStay($customer, $hotel) : null;
            if (!$stay) {
                throw ValidationException::withMessages(['room' => translate('Room service is for guests checked in at the hotel. Scan the card in your room.')]);
            }
            $room = self::roomOf($stay) ?: (filled($input['room'] ?? null) ? Str::limit(trim((string)$input['room']), 20, '') : null);
            if (!$room) {
                throw ValidationException::withMessages(['room' => translate('Say your room number.')]);
            }
        }
        if (!$hotel || !$hotel->isLive()) {
            throw ValidationException::withMessages(['room' => translate('Room service is only for guests of the hotel of this restaurant.')]);
        }
        $restaurant = $this->restaurant($hotel);
        $kitchens = $restaurant ? $restaurant->kitchens()->pluck('id')->map(fn($id) => (string)$id)->all() : [];
        if (!$restaurant || !in_array((string)$store->id, array_merge($kitchens, [(string)$restaurant->id]), true)) {
            throw ValidationException::withMessages(['room' => translate('Room service is only for guests of the hotel of this restaurant.')]);
        }
        return ['hotel' => $hotel, 'store' => $store, 'room' => $room, 'stay' => $stay, 'room_bill' => $this->roomBillOk($hotel, $stay, $store)];
    }

    /** At the restaurant's confirmation: still in the house, and the hotel still allows the room bill. */
    public function roomBillStillOk(StoreOrder $order): bool
    {
        $stay = $order->hotel_stay_id ? HotelStay::find($order->hotel_stay_id) : null;
        $hotel = $order->hotel_id ? Hotel::find($order->hotel_id) : null;
        return $hotel && $order->store && $this->roomBillOk($hotel, $stay, $order->store);
    }

    /** What the guest's hotel chat offers while they are in the house: room service from the restaurant. */
    public function forInquiry(HotelInquiry $inquiry): ?array
    {
        if ($inquiry->status !== HotelInquiry::ARRIVED || !$inquiry->hotel) {
            return null;
        }
        $store = $this->restaurant($inquiry->hotel);
        $stay = $inquiry->stays()->where('status', HotelStay::IN_HOUSE)->first();
        if (!$store || !$stay) {
            return null;
        }
        return [
            'store_id' => $store->id,
            'store_name' => $store->displayName(),
            'hotel_id' => $inquiry->hotel_id,
            'room' => self::roomOf($stay),
            'room_bill' => $this->roomBillOk($inquiry->hotel, $stay, $store),
        ];
    }

    /** What a room's QR card opens: the hotel, its restaurant and the room (null when the card is not valid). */
    public function forToken(string $token, ?User $customer): ?array
    {
        $code = HotelRoomCode::where('token', strtoupper(trim($token)))->whereNull('revoked_at')->first();
        $hotel = $code ? Hotel::with('place')->find($code->hotel_id) : null;
        $store = $hotel && $hotel->isLive() ? $this->restaurant($hotel) : null;
        if (!$store) {
            return null;
        }
        $stay = $customer ? $this->inHouseStay($customer, $hotel) : null;
        $stay = $stay && self::roomOf($stay) === $code->room_label ? $stay : null;
        return [
            'token' => $code->token,
            'hotel' => ['id' => $hotel->id, 'name' => $hotel->name],
            'store_id' => $store->id,
            'store_name' => $store->displayName(),
            'room' => $code->room_label,
            'room_bill' => $this->roomBillOk($hotel, $stay, $store),
        ];
    }

    // ------------------------------------------------------------ QR cards

    /** Cards for these room numbers (existing ones kept), in the order given. */
    public function codes(Hotel $hotel, array $labels): Collection
    {
        $out = collect();
        foreach (array_unique(array_filter(array_map(fn($l) => Str::limit(trim((string)$l), 20, ''), $labels))) as $label) {
            $code = HotelRoomCode::where('hotel_id', $hotel->id)->where('room_label', $label)->whereNull('revoked_at')->first()
                ?? HotelRoomCode::create(['hotel_id' => $hotel->id, 'room_label' => $label, 'token' => $this->newToken()]);
            $out->push($code);
        }
        return $out;
    }

    /** A new card for a room: the old one stops working. */
    public function reissue(HotelRoomCode $code): HotelRoomCode
    {
        $code->forceFill(['revoked_at' => now()])->save();
        return HotelRoomCode::create(['hotel_id' => $code->hotel_id, 'room_label' => $code->room_label, 'token' => $this->newToken()]);
    }

    protected function newToken(): string
    {
        do {
            // No 0/O or 1/I: read aloud or typed from a printed card.
            $token = substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 6);
        } while (HotelRoomCode::where('token', $token)->exists());
        return $token;
    }

    /** "101-105, 201, 202" → [101..105, 201, 202]. */
    public static function parseRooms(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', trim($text)) as $part) {
            if (preg_match('/^(\d{1,5})-(\d{1,5})$/', $part, $m) && (int)$m[2] >= (int)$m[1] && (int)$m[2] - (int)$m[1] <= 200) {
                for ($i = (int)$m[1]; $i <= (int)$m[2]; $i++) {
                    $out[] = (string)$i;
                }
            } elseif ($part !== '') {
                $out[] = Str::limit($part, 20, '');
            }
        }
        return array_slice(array_values(array_unique($out)), 0, 300);
    }
}
