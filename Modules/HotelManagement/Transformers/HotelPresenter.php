<?php

namespace Modules\HotelManagement\Transformers;

use Carbon\Carbon;
use Modules\HotelManagement\Entities\Hotel;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelMessage;
use Modules\HotelManagement\Entities\HotelRoomType;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\HotelManagement\Service\InquiryService;
use Modules\HotelManagement\Service\PartnerService;
use Modules\HotelManagement\Service\StayService;

/** What the app and the portal's chat get about hotels, room types, questions and messages. */
class HotelPresenter
{
    public static function photo(?string $file): ?string
    {
        return $file ? dynamicStorage('storage/app/public/' . PartnerService::PHOTO_DIR . $file) : null;
    }

    /** The partner part of a hotel card or page: answer time, freshness, and free rooms for the dates. */
    public static function partner(Hotel $hotel, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $fresh = $hotel->isFresh();
        $types = $hotel->roomTypes()->where('active', true)->get();
        $counts = $fresh && $from && $to ? app(StayService::class)->freeCounts($hotel, $from, $to) : [];
        return [
            'hotel_id' => $hotel->id,
            'answer_minutes' => InquiryService::answerMinutes($hotel),
            'fresh' => $fresh,
            'updated_at' => $hotel->board_touched_at?->toIso8601String(),
            'price_from' => $types->min('price_from'),
            // Null when the counts are not trustworthy (rooms not updated lately): "Ask the reception".
            'free_rooms' => $fresh && $from ? array_sum(array_map(fn($t) => $counts[(string)$t->id] ?? 0, $types->all())) : null,
            'reception_phone' => $hotel->reception_phone,
            'check_in_from' => $hotel->check_in_from,
            'check_out_by' => $hotel->check_out_by,
            'answers_now' => self::answersNow($hotel),
        ];
    }

    /** Room types with photos, and for the dates how many are free (null when not fresh). */
    public static function roomTypes(Hotel $hotel, ?Carbon $from, ?Carbon $to): array
    {
        $fresh = $hotel->isFresh();
        $counts = $fresh && $from && $to ? app(StayService::class)->freeCounts($hotel, $from, $to) : [];
        return $hotel->roomTypes()->where('active', true)->withCount(['rooms' => fn($q) => $q->where('active', true)])->get()
            ->filter(fn(HotelRoomType $t) => $t->rooms_count > 0)
            ->map(fn(HotelRoomType $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'beds' => $t->beds,
                'max_guests' => $t->max_guests,
                'price_from' => $t->price_from,
                'amenities' => array_values(array_intersect(HotelRoomType::AMENITIES, $t->amenities ?? [])),
                'description' => $t->description,
                'photos' => array_values(array_filter(array_map([self::class, 'photo'], $t->photos ?? []))),
                'free' => $fresh && $from ? ($counts[(string)$t->id] ?? 0) : null,
            ])->values()->all();
    }

    /** Reception says it answers between two hours: is it in them now? */
    public static function answersNow(Hotel $hotel): bool
    {
        if ($hotel->answer_always || !$hotel->answer_from || !$hotel->answer_until) {
            return true;
        }
        $now = now()->format('H:i');
        return $hotel->answer_from <= $hotel->answer_until
            ? ($now >= $hotel->answer_from && $now < $hotel->answer_until)
            : ($now >= $hotel->answer_from || $now < $hotel->answer_until);
    }

    public static function message(HotelMessage $m, ?array $names = null): array
    {
        return [
            'id' => $m->id,
            'sender' => $m->sender,
            'kind' => $m->kind,
            'body' => $m->body,
            'data' => $m->data ?: null,
            'by' => $m->user_id && $names ? ($names[(string)$m->user_id] ?? null) : null,
            'at' => $m->created_at?->toIso8601String(),
        ];
    }

    /** A question as the guest sees it in their list, or with its chat. */
    public static function inquiry(HotelInquiry $q, bool $withMessages = false, ?int $after = null): array
    {
        $hotel = $q->hotel;
        $place = $hotel?->place;
        $kept = $q->status === HotelInquiry::KEPT
            ? $q->stays()->where('status', HotelStay::KEPT)->with('room')->get() : collect();
        $out = [
            'id' => $q->id,
            'status' => $q->status,
            'close_reason' => $q->close_reason,
            'hotel' => [
                'id' => $hotel?->id,
                'place_id' => $hotel?->place_id,
                'name' => $hotel?->name,
                'address' => $place?->address,
                'latitude' => $place?->latitude,
                'longitude' => $place?->longitude,
                'photo' => $place ? ($place->photoUrls()[0] ?? null) : null,
                'reception_phone' => $hotel?->reception_phone,
                'answer_minutes' => $hotel ? InquiryService::answerMinutes($hotel) : null,
                'answers_now' => $hotel ? self::answersNow($hotel) : true,
            ],
            'room_type' => $q->roomType ? ['id' => $q->roomType->id, 'name' => $q->roomType->name, 'price_from' => $q->roomType->price_from] : null,
            'check_in' => $q->check_in->toDateString(),
            'check_out' => $q->check_out->toDateString(),
            'nights' => $q->nights(),
            'guests' => $q->guests,
            'rooms' => $q->rooms,
            'arrival' => $q->arrival,
            'price' => $q->price,
            'kept_until' => $kept->isNotEmpty() ? $kept->min('kept_until')?->toIso8601String() : null,
            'kept_rooms' => $kept->count(),
            'on_the_way' => $q->status === HotelInquiry::KEPT ? app(InquiryService::class)->onTheWay($q) : false,
            'unread' => $q->unread_guest,
            'last_message_at' => $q->last_message_at?->toIso8601String(),
            'created_at' => $q->created_at?->toIso8601String(),
        ];
        if ($withMessages) {
            $messages = $q->messages()->when($after, fn($m) => $m->where('id', '>', $after))->limit(300)->get();
            $out['messages'] = $messages->map(fn($m) => self::message($m))->values()->all();
        } else {
            $last = $q->messages()->reorder('id', 'desc')->first();
            $out['last_message'] = $last ? self::message($last) : null;
        }
        return $out;
    }
}
