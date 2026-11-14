<?php

namespace Modules\MarketManagement\Service;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\MarketBooking;
use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketMessage;
use Modules\MarketManagement\Entities\MarketReview;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\UserManagement\Entities\User;

/**
 * Services: free times, booking a time, asking for a quote, the provider's answer, moving and
 * cancelling, the code at the end, no-shows, ratings, and the minute clock (expiry, reminders).
 * Like orders, E-Ride never holds the money: the client pays the provider after the job.
 */
class BookingService
{
    /** Start times are offered every 30 minutes. */
    public const STEP_MINUTES = 30;

    public function __construct(protected MarketChatService $chats, protected MarketOrderService $orders)
    {
    }

    // ------------------------------------------------------------------ free times

    /**
     * The start times of one day, each free or taken (taken: full at that time, too soon, or past).
     *
     * @return array<int, array{time: string, free: bool}>
     */
    public function slots(MarketItem $item, CarbonInterface $day, ?string $ignoreBookingId = null, ?Collection $busy = null): array
    {
        $shop = $item->shop;
        $hours = $shop->serviceHours()[strtolower($day->format('D'))] ?? null;
        if (!$hours || !$this->withinAhead($shop, $day)) {
            return [];
        }
        $duration = max(15, (int)($item->duration_minutes ?: 60));
        $open = Carbon::parse($day->format('Y-m-d') . ' ' . $hours[0]);
        $close = Carbon::parse($day->format('Y-m-d') . ' ' . $hours[1]);
        $earliest = now()->addMinutes((int)$shop->service_notice_minutes);
        $busy ??= $this->busy($shop, $open, $close, $ignoreBookingId);
        $capacity = max(1, (int)$shop->service_capacity);
        $out = [];
        for ($start = $open->copy(); $start->copy()->addMinutes($duration)->lte($close); $start->addMinutes(self::STEP_MINUTES)) {
            $end = $start->copy()->addMinutes($duration);
            $overlap = $busy->filter(fn($b) => $b->starts_at < $end && $b->ends_at > $start)->count();
            $out[] = ['time' => $start->format('H:i'), 'free' => $start->gte($earliest) && $overlap < $capacity];
        }
        return $out;
    }

    /**
     * The next days with how many free times each (for the day strip).
     *
     * @return array<int, array{date: string, open: bool, free: int}>
     */
    public function days(MarketItem $item, int $count = 14): array
    {
        $shop = $item->shop;
        $from = now()->startOfDay();
        $count = min($count, max(1, (int)$shop->service_ahead_days + 1));
        $busy = $this->busy($shop, $from, $from->copy()->addDays($count));
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $day = $from->copy()->addDays($i);
            $slots = $this->slots($item, $day, null, $busy);
            $out[] = ['date' => $day->format('Y-m-d'), 'open' => $slots !== [], 'free' => count(array_filter($slots, fn($s) => $s['free']))];
        }
        return $out;
    }

    /** The first free start time, for cards ("Free today 15:00"); null when nothing in the booking window. */
    public function nextFree(MarketItem $item): ?CarbonInterface
    {
        $shop = $item->shop;
        if (!$item->isService() || $item->isQuoteOnly() || !$shop || !$shop->taking_bookings) {
            return null;
        }
        $from = now()->startOfDay();
        $days = min(14, max(1, (int)$shop->service_ahead_days + 1));
        $busy = $this->busy($shop, $from, $from->copy()->addDays($days));
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            foreach ($this->slots($item, $day, null, $busy) as $slot) {
                if ($slot['free']) {
                    return Carbon::parse($day->format('Y-m-d') . ' ' . $slot['time']);
                }
            }
        }
        return null;
    }

    public function isFree(MarketItem $item, CarbonInterface $start, ?string $ignoreBookingId = null): bool
    {
        return $this->slotState($item, $start, $ignoreBookingId) === true;
    }

    /** true: free; false: taken (full, too soon); null: not a start time that day (closed, outside the hours, too long to fit). */
    private function slotState(MarketItem $item, CarbonInterface $start, ?string $ignoreBookingId = null): ?bool
    {
        foreach ($this->slots($item, $start->copy()->startOfDay(), $ignoreBookingId) as $slot) {
            if ($slot['time'] === $start->format('H:i')) {
                return $slot['free'];
            }
        }
        return null;
    }

    private function assertFree(MarketItem $item, CarbonInterface $start, ?string $ignoreBookingId, string $takenMessage): void
    {
        $state = $this->slotState($item, $start, $ignoreBookingId);
        if ($state === null) {
            $this->fail('starts_at', translate('This is not one of the free times. Choose a time from the list.'));
        }
        if ($state === false) {
            $this->fail('starts_at', $takenMessage);
        }
    }

    private function busy(MarketShop $shop, CarbonInterface $from, CarbonInterface $to, ?string $ignoreBookingId = null): Collection
    {
        return MarketBooking::where('shop_id', $shop->id)->whereIn('status', MarketBooking::HOLDS_TIME)
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->when($ignoreBookingId, fn($q) => $q->where('id', '!=', $ignoreBookingId))
            ->get(['id', 'starts_at', 'ends_at']);
    }

    private function withinAhead(MarketShop $shop, CarbonInterface $day): bool
    {
        $today = now()->startOfDay();
        return $day->gte($today) && $day->lte($today->copy()->addDays(max(1, (int)$shop->service_ahead_days)));
    }

    // ------------------------------------------------------------------ the client asks

    /**
     * Book a free time. $data: starts_at ("Y-m-d H:i"), extras (names), note, location, lat, lng, address.
     */
    public function book(User $client, MarketItem $item, array $data): MarketBooking
    {
        $this->canAsk($client, $item);
        if ($item->isQuoteOnly()) {
            $this->fail('item', translate('This service is priced on request: ask for a quote.'));
        }
        $start = $this->parseStart($data['starts_at'] ?? null);
        $location = $this->location($item, $data);
        $extras = $this->pickExtras($item, (array)($data['extras'] ?? []));
        $shop = $item->shop;
        $conversation = $this->conversation($client, $item);
        $settings = MarketSettings::all();
        $booking = DB::transaction(function () use ($client, $item, $data, $start, $location, $extras, $shop, $conversation, $settings) {
            // One booking at a time per free slot: lock the shop's day while checking.
            MarketShop::whereKey($shop->id)->lockForUpdate()->first();
            $this->assertFree($item, $start, null, translate('This time was just taken. Choose another time.'));
            $auto = (bool)$shop->service_auto_confirm;
            return MarketBooking::create([
                'code' => $this->newCode(), 'kind' => MarketBooking::KIND_BOOKING,
                'status' => $auto ? MarketBooking::CONFIRMED : MarketBooking::REQUESTED,
                'item_id' => $item->id, 'shop_id' => $shop->id, 'client_id' => $client->id, 'provider_id' => $shop->user_id,
                'conversation_id' => $conversation?->id,
                'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(max(15, (int)($item->duration_minutes ?: 60))),
                'location' => $location['location'], 'lat' => $location['lat'], 'lng' => $location['lng'], 'address' => $location['address'],
                'note' => $this->text($data['note'] ?? null, 600),
                'extras' => $extras ?: null, 'price_type' => $item->price_type,
                'price' => $item->price !== null ? (float)$item->price + array_sum(array_column($extras, 'price')) : null,
                'receive_code' => $auto ? $this->receiveCode() : null, 'confirmed_at' => $auto ? now() : null,
                'respond_by' => $auto ? null : $this->respondBy($start, $settings),
            ]);
        });
        $booking->load(['item', 'shop.owner', 'client']);
        if ($booking->status === MarketBooking::CONFIRMED) {
            $this->note($booking, $client, 'confirmed');
            MarketPush::send($booking->shop->owner, 'market_booking_confirmed', 'New booking: {service}', '{client}, {when}. It is confirmed: free times book at once.',
                $this->words($booking), ['booking_id' => $booking->id]);
        } else {
            $this->note($booking, $client, 'requested');
            MarketPush::send($booking->shop->owner, 'market_booking_new', 'New booking request: {service}', '{client} asks for {when}. Answer within {time}.',
                $this->words($booking) + ['time' => $this->duration($booking->respond_by)], ['booking_id' => $booking->id]);
        }
        return $booking;
    }

    /**
     * Ask for a quote. $data: note (required), when_text, location, lat, lng, address; up to 3 photos.
     *
     * @param UploadedFile[] $photos
     */
    public function requestQuote(User $client, MarketItem $item, array $data, array $photos = []): MarketBooking
    {
        $this->canAsk($client, $item);
        $note = $this->text($data['note'] ?? null, 600);
        if ($note === null) {
            $this->fail('note', translate('Say what needs doing, so the provider can give a price.'));
        }
        $open = MarketBooking::where('client_id', $client->id)->where('item_id', $item->id)
            ->whereIn('status', [MarketBooking::QUOTE_REQUESTED, MarketBooking::QUOTED])->exists();
        if ($open) {
            $this->fail('item', translate('You already asked this provider for a quote. Wait for the answer.'));
        }
        $location = $this->location($item, $data);
        $files = [];
        foreach (array_slice($photos, 0, MarketBooking::MAX_PHOTOS) as $photo) {
            if ($photo instanceof UploadedFile && ($name = fileUploader(MarketBooking::PHOTO_DIR, 'webp', $photo))) {
                $files[] = $name;
            }
        }
        $conversation = $this->conversation($client, $item);
        $settings = MarketSettings::all();
        $booking = MarketBooking::create([
            'code' => $this->newCode(), 'kind' => MarketBooking::KIND_QUOTE, 'status' => MarketBooking::QUOTE_REQUESTED,
            'item_id' => $item->id, 'shop_id' => $item->shop_id, 'client_id' => $client->id, 'provider_id' => $item->shop->user_id,
            'conversation_id' => $conversation?->id,
            'location' => $location['location'], 'lat' => $location['lat'], 'lng' => $location['lng'], 'address' => $location['address'],
            'note' => $note, 'photos' => $files ?: null, 'when_text' => $this->text($data['when_text'] ?? null, 80), 'price_type' => 'quote',
            'respond_by' => now()->addMinutes($settings['service_answer_minutes']),
        ])->load(['item', 'shop.owner', 'client']);
        $this->note($booking, $client, 'quote_requested');
        MarketPush::send($booking->shop->owner, 'market_booking_quote_request', 'Quote request: {service}', '{client} asks for a price. Answer within {time}.',
            $this->words($booking) + ['time' => $this->duration($booking->respond_by)], ['booking_id' => $booking->id]);
        return $booking;
    }

    // ------------------------------------------------------------------ the provider answers

    public function accept(User $provider, MarketBooking $booking): MarketBooking
    {
        $this->asProvider($provider, $booking);
        $this->expect($booking, [MarketBooking::REQUESTED]);
        $booking->update(['status' => MarketBooking::CONFIRMED, 'receive_code' => $this->receiveCode(), 'confirmed_at' => now(), 'respond_by' => null]);
        $this->noteReplyTime($booking);
        $this->note($booking, $provider, 'confirmed');
        MarketPush::send($booking->client, 'market_booking_confirmed', 'Booking confirmed', '{shop} confirmed {service}, {when}.', $this->words($booking),
            ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    public function decline(User $provider, MarketBooking $booking, ?string $reason): MarketBooking
    {
        $this->asProvider($provider, $booking);
        $this->expect($booking, [MarketBooking::REQUESTED, MarketBooking::QUOTE_REQUESTED]);
        $booking->update(['status' => MarketBooking::DECLINED, 'decline_reason' => $this->text($reason, 300), 'closed_at' => now(), 'respond_by' => null]);
        $this->noteReplyTime($booking);
        $this->note($booking, $provider, 'declined');
        MarketPush::send($booking->client, 'market_booking_declined', '{shop} cannot take it', '{service}: {reason}',
            $this->words($booking) + ['reason' => $booking->decline_reason ?: translate('Try another time or another provider.')], ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    /** Not free then: the provider proposes another free time; the client chooses. */
    public function suggest(User $provider, MarketBooking $booking, ?string $startsAt): MarketBooking
    {
        $this->asProvider($provider, $booking);
        $this->expect($booking, [MarketBooking::REQUESTED]);
        $start = $this->parseStart($startsAt);
        $this->assertFree($booking->item, $start, $booking->id, translate('You are not free then either. Choose a free time.'));
        $settings = MarketSettings::all();
        $booking->update(['status' => MarketBooking::SUGGESTED, 'suggested_starts_at' => $start, 'respond_by' => $this->respondBy($start, $settings)]);
        $this->noteReplyTime($booking);
        $this->note($booking, $provider, 'suggested');
        MarketPush::send($booking->client, 'market_booking_suggested', '{shop} suggests another time', '{service}: {new}. Accept it or choose another time.',
            $this->words($booking) + ['new' => $this->when($start)], ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    /** The provider's price and time for a quote request. */
    public function sendQuote(User $provider, MarketBooking $booking, array $data): MarketBooking
    {
        $this->asProvider($provider, $booking);
        $this->expect($booking, [MarketBooking::QUOTE_REQUESTED]);
        $price = (float)($data['price'] ?? 0);
        if ($price <= 0) {
            $this->fail('price', translate('Give a price.'));
        }
        $start = $this->parseStart($data['starts_at'] ?? null);
        $minutes = max(15, min(1440, (int)($data['duration_minutes'] ?? ($booking->item->duration_minutes ?: 60))));
        $settings = MarketSettings::all();
        $booking->update([
            'status' => MarketBooking::QUOTED, 'quote_price' => $price, 'quote_note' => $this->text($data['note'] ?? null, 400),
            'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($minutes), 'respond_by' => null,
            'quote_expires_at' => now()->addHours($settings['service_quote_hours'])->min($start),
        ]);
        $this->noteReplyTime($booking);
        $this->note($booking, $provider, 'quoted');
        MarketPush::send($booking->client, 'market_booking_quoted', '{shop} sent a price: {price}', '{service}, {when}. Accept within {hours} hours.',
            $this->words($booking) + ['price' => getCurrencyFormat($price), 'hours' => $settings['service_quote_hours']], ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    // ------------------------------------------------------------------ the client answers

    public function acceptSuggestion(User $client, MarketBooking $booking): MarketBooking
    {
        $this->asClient($client, $booking);
        $this->expect($booking, [MarketBooking::SUGGESTED]);
        $start = $booking->suggested_starts_at;
        if (!$this->isFree($booking->item, $start, $booking->id)) {
            $this->fail('starts_at', translate('That time was just taken. Choose another time.'));
        }
        $minutes = $booking->starts_at && $booking->ends_at ? $booking->starts_at->diffInMinutes($booking->ends_at) : ($booking->item->duration_minutes ?: 60);
        $booking->update(['status' => MarketBooking::CONFIRMED, 'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($minutes),
            'suggested_starts_at' => null, 'receive_code' => $this->receiveCode(), 'confirmed_at' => now(), 'respond_by' => null]);
        $this->note($booking, $client, 'confirmed');
        MarketPush::send($booking->provider, 'market_booking_confirmed', '{client} accepted your time', '{service}, {when}. It is confirmed.',
            $this->words($booking), ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    public function acceptQuote(User $client, MarketBooking $booking): MarketBooking
    {
        $this->asClient($client, $booking);
        $this->expect($booking, [MarketBooking::QUOTED]);
        if ($booking->quote_expires_at && $booking->quote_expires_at->isPast()) {
            $this->fail('booking', translate('This price is no longer open. Ask again.'));
        }
        $booking->update(['status' => MarketBooking::CONFIRMED, 'price' => $booking->quote_price, 'receive_code' => $this->receiveCode(),
            'confirmed_at' => now()]);
        $this->note($booking, $client, 'confirmed');
        MarketPush::send($booking->provider, 'market_booking_confirmed', '{client} accepted your price', '{service}, {when}. It is confirmed: you now see the exact place.',
            $this->words($booking), ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    /** The client says no to a suggested time or a price. */
    public function refuse(User $client, MarketBooking $booking): MarketBooking
    {
        $this->asClient($client, $booking);
        $this->expect($booking, [MarketBooking::SUGGESTED, MarketBooking::QUOTED]);
        $booking->update(['status' => MarketBooking::CANCELLED, 'cancelled_by' => 'client', 'closed_at' => now(), 'respond_by' => null]);
        $this->note($booking, $client, 'cancelled');
        MarketPush::send($booking->provider, 'market_booking_cancelled', '{client} said no thanks', '{service}: your proposal was not accepted.',
            $this->words($booking), ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    /** The client moves a booking to another free time (free until the cancel window). */
    public function reschedule(User $client, MarketBooking $booking, ?string $startsAt): MarketBooking
    {
        $this->asClient($client, $booking);
        $this->expect($booking, [MarketBooking::REQUESTED, MarketBooking::SUGGESTED, MarketBooking::CONFIRMED]);
        if ($booking->kind === MarketBooking::KIND_QUOTE) {
            $this->fail('booking', translate('Ask the provider in the chat to change the time of a quote.'));
        }
        if ($booking->status === MarketBooking::CONFIRMED && !$this->freeToChange($booking)) {
            $this->fail('booking', translate('It is too late to change the time. Talk to the provider in the chat.'));
        }
        $start = $this->parseStart($startsAt);
        $settings = MarketSettings::all();
        $shop = $booking->shop;
        DB::transaction(function () use ($booking, $start, $settings, $shop) {
            MarketShop::whereKey($shop->id)->lockForUpdate()->first();
            $this->assertFree($booking->item, $start, $booking->id, translate('This time was just taken. Choose another time.'));
            $minutes = $booking->starts_at->diffInMinutes($booking->ends_at);
            $auto = (bool)$shop->service_auto_confirm;
            $booking->update([
                'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes($minutes), 'suggested_starts_at' => null,
                'status' => $auto ? MarketBooking::CONFIRMED : MarketBooking::REQUESTED,
                'respond_by' => $auto ? null : $this->respondBy($start, $settings),
                'receive_code' => $auto ? ($booking->receive_code ?: $this->receiveCode()) : null,
                'confirmed_at' => $auto ? now() : null, 'reminded_day' => false, 'reminded_hour' => false,
            ]);
        });
        $booking->refresh();
        $this->note($booking, $client, 'moved');
        MarketPush::send($booking->provider, 'market_booking_moved', '{client} changed the time', '{service}: now {when}.' . ($booking->status === MarketBooking::REQUESTED ? ' Answer within {time}.' : ''),
            $this->words($booking) + ['time' => $this->duration($booking->respond_by)], ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    // ------------------------------------------------------------------ either side

    public function cancel(User $user, MarketBooking $booking, ?string $reason): MarketBooking
    {
        $role = $booking->roleOf($user->id) ?? $this->fail('booking', translate('This booking is not yours.'));
        $this->expect($booking, MarketBooking::OPEN);
        $late = $role === 'client' && $booking->status === MarketBooking::CONFIRMED && !$this->freeToChange($booking);
        $booking->update(['status' => MarketBooking::CANCELLED, 'cancelled_by' => $role, 'cancel_reason' => $this->text($reason, 300),
            'late_cancel' => $late, 'closed_at' => now(), 'respond_by' => null]);
        $this->note($booking, $user, 'cancelled');
        $other = $role === 'client' ? $booking->provider : $booking->client;
        MarketPush::send($other, 'market_booking_cancelled', '{service} cancelled', '{who} cancelled {when}.{reason}',
            $this->words($booking) + ['who' => $role === 'client' ? $this->firstName($booking->client) : $booking->shop->name,
                'reason' => $booking->cancel_reason ? ' "' . $booking->cancel_reason . '"' : ''], ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    /** The provider closes the job with the client's code (and the final price if something was added). */
    public function complete(User $provider, MarketBooking $booking, string $code, ?float $finalPrice): MarketBooking
    {
        $this->asProvider($provider, $booking);
        $this->expect($booking, [MarketBooking::CONFIRMED]);
        if (!hash_equals((string)$booking->receive_code, trim($code))) {
            $this->fail('code', translate('This code is not right. Ask the client for the 4 digits on their booking.'));
        }
        DB::transaction(function () use ($booking, $finalPrice) {
            $booking->update(['status' => MarketBooking::COMPLETED, 'completed_at' => now(), 'closed_at' => now(),
                'final_price' => $finalPrice !== null && $finalPrice > 0 ? $finalPrice : $booking->shownPrice()]);
            MarketShop::whereKey($booking->shop_id)->increment('bookings_completed');
            MarketItem::whereKey($booking->item_id)->increment('orders');
        });
        $this->note($booking, $provider, 'completed');
        MarketPush::send($booking->client, 'market_booking_completed', 'Done: {service}', 'How was it? Rate {shop} in one tap.', $this->words($booking),
            ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    /** The client did not come: from 30 minutes after the start. Counts towards a pause on booking. */
    public function noShow(User $provider, MarketBooking $booking): MarketBooking
    {
        $this->asProvider($provider, $booking);
        $this->expect($booking, [MarketBooking::CONFIRMED]);
        if ($booking->starts_at->copy()->addMinutes(30)->isFuture()) {
            $this->fail('booking', translate('You can mark a no-show 30 minutes after the start time.'));
        }
        $booking->update(['status' => MarketBooking::NO_SHOW, 'closed_at' => now()]);
        $this->note($booking, $provider, 'no_show');
        MarketPush::send($booking->client, 'market_booking_no_show', 'You missed {service}', '{shop} marked that you did not come. Missed bookings can pause your booking for a while.',
            $this->words($booking), ['booking_id' => $booking->id]);
        return $this->fresh($booking);
    }

    public function review(User $client, MarketBooking $booking, int $stars, array $tags, ?string $comment): MarketReview
    {
        $this->asClient($client, $booking);
        if ($booking->status !== MarketBooking::COMPLETED) {
            $this->fail('booking', translate('You can rate once the job is done.'));
        }
        if ($booking->review()->exists()) {
            $this->fail('booking', translate('You already rated this booking.'));
        }
        $review = MarketReview::create([
            'booking_id' => $booking->id, 'shop_id' => $booking->shop_id, 'buyer_id' => $client->id,
            'stars' => max(1, min(5, $stars)),
            'tags' => array_values(array_intersect(MarketReview::SERVICE_TAGS, $tags)),
            'comment' => $this->text($comment, 500),
        ]);
        $this->orders->refreshRating($booking->shop_id);
        return $review;
    }

    // ------------------------------------------------------------------ lists

    /**
     * client: upcoming | past. provider: requests | day (with $date) | upcoming | past.
     */
    public function listFor(User $user, string $role, string $tab, ?string $date = null, int $limit = 30): Collection
    {
        $q = MarketBooking::with(['item.photos', 'shop', 'client'])->where($role === 'provider' ? 'provider_id' : 'client_id', $user->id);
        match ($tab) {
            'requests' => $q->whereIn('status', [MarketBooking::REQUESTED, MarketBooking::QUOTE_REQUESTED, MarketBooking::SUGGESTED, MarketBooking::QUOTED])
                ->orderBy('respond_by'),
            'day' => $q->whereIn('status', [MarketBooking::CONFIRMED, MarketBooking::COMPLETED, MarketBooking::NO_SHOW])
                ->whereDate('starts_at', $date ?: now()->format('Y-m-d'))->orderBy('starts_at'),
            'upcoming' => $q->whereIn('status', MarketBooking::OPEN)->orderByRaw('COALESCE(starts_at, created_at)'),
            default => $q->whereNotIn('status', MarketBooking::OPEN)->orderByDesc('updated_at'),
        };
        return $q->limit($limit)->get();
    }

    /** For the app badges: requests waiting for the provider, proposals waiting for the client. */
    public function counts(User $user): array
    {
        return [
            'provider_requests' => MarketBooking::where('provider_id', $user->id)->whereIn('status', [MarketBooking::REQUESTED, MarketBooking::QUOTE_REQUESTED])->count(),
            'client_action' => MarketBooking::where('client_id', $user->id)->whereIn('status', [MarketBooking::SUGGESTED, MarketBooking::QUOTED])->count(),
            'client_upcoming' => MarketBooking::where('client_id', $user->id)->whereIn('status', MarketBooking::OPEN)->count(),
        ];
    }

    /** Bookings per day for the provider's day strip. */
    public function dayCounts(User $provider, int $days = 7): array
    {
        $from = now()->startOfDay();
        $rows = MarketBooking::where('provider_id', $provider->id)->where('status', MarketBooking::CONFIRMED)
            ->whereBetween('starts_at', [$from, $from->copy()->addDays($days)])
            ->selectRaw('DATE(starts_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd');
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->format('Y-m-d');
            $out[] = ['date' => $d, 'count' => (int)($rows[$d] ?? 0)];
        }
        return $out;
    }

    /** Why this person cannot book now (missed bookings and late cancels), or null. */
    public function cannotBookReason(User $client): ?string
    {
        $s = MarketSettings::all();
        $marks = MarketBooking::where('client_id', $client->id)->where('updated_at', '>', now()->subDays($s['service_noshow_days']))
            ->where(fn($q) => $q->where('status', MarketBooking::NO_SHOW)->orWhere('late_cancel', true))->count();
        return $marks >= $s['service_noshow_limit']
            ? translate(key: 'You missed or cancelled late {count} bookings recently. Booking is paused for a while.', replace: ['count' => $marks])
            : null;
    }

    /** The client's record, for the provider: kept and missed bookings. */
    public function record(string $clientId): array
    {
        return [
            'kept' => MarketBooking::where('client_id', $clientId)->where('status', MarketBooking::COMPLETED)->count(),
            'missed' => MarketBooking::where('client_id', $clientId)->where(fn($q) => $q->where('status', MarketBooking::NO_SHOW)->orWhere('late_cancel', true))->count(),
        ];
    }

    public function freeToChange(MarketBooking $booking): bool
    {
        return !$booking->starts_at || $booking->starts_at->copy()->subHours(MarketSettings::all()['service_cancel_hours'])->isFuture();
    }

    public function cancelFreeUntil(MarketBooking $booking): ?CarbonInterface
    {
        return $booking->starts_at?->copy()->subHours(MarketSettings::all()['service_cancel_hours']);
    }

    // ------------------------------------------------------------------ the clock

    /** Every minute: unanswered requests and prices expire; reminders the day before and 1 hour before. */
    public function tick(): array
    {
        $done = ['expired' => 0, 'reminded' => 0];
        $s = MarketSettings::all();
        MarketBooking::with(['item', 'shop.owner', 'client'])->whereIn('status', [MarketBooking::REQUESTED, MarketBooking::SUGGESTED, MarketBooking::QUOTE_REQUESTED])
            ->where('respond_by', '<', now())->limit(200)->get()
            ->each(function (MarketBooking $b) use (&$done) {
                if (!MarketBooking::whereKey($b->id)->where('status', $b->status)->update(['status' => MarketBooking::EXPIRED, 'closed_at' => now(), 'respond_by' => null])) {
                    return;
                }
                $done['expired']++;
                if ($b->status === MarketBooking::SUGGESTED) {
                    MarketPush::send($b->shop->owner, 'market_booking_expired', '{client} did not answer', 'Your suggested time for {service} closed.', $this->words($b), ['booking_id' => $b->id]);
                    return;
                }
                MarketPush::send($b->client, 'market_booking_expired', '{shop} did not answer', '{service}: your request closed. Book another time or another provider.',
                    $this->words($b), ['booking_id' => $b->id]);
                MarketPush::send($b->shop->owner, 'market_booking_expired', 'You missed a booking request', '{service} from {client} closed because nobody answered in time.',
                    $this->words($b), ['booking_id' => $b->id]);
            });
        MarketBooking::with(['item', 'shop', 'provider', 'client'])->where('status', MarketBooking::QUOTED)->where('quote_expires_at', '<', now())->limit(200)->get()
            ->each(function (MarketBooking $b) use (&$done) {
                if (MarketBooking::whereKey($b->id)->where('status', MarketBooking::QUOTED)->update(['status' => MarketBooking::EXPIRED, 'closed_at' => now()])) {
                    $done['expired']++;
                    MarketPush::send($b->provider, 'market_booking_expired', '{client} did not answer your price', '{service}: the quote closed.', $this->words($b), ['booking_id' => $b->id]);
                }
            });
        // The day before, from the reminder hour.
        if ((int)now()->format('G') >= $s['service_reminder_hour']) {
            $tomorrow = now()->addDay();
            MarketBooking::with(['item', 'shop', 'provider', 'client'])->where('status', MarketBooking::CONFIRMED)->where('reminded_day', false)
                ->whereBetween('starts_at', [$tomorrow->copy()->startOfDay(), $tomorrow->copy()->endOfDay()])->limit(500)->get()
                ->each(function (MarketBooking $b) use (&$done) {
                    $b->update(['reminded_day' => true]);
                    $done['reminded']++;
                    MarketPush::send($b->client, 'market_booking_reminder', 'Tomorrow: {service}', '{when} with {shop}. Your code is on the booking.', $this->words($b), ['booking_id' => $b->id]);
                    MarketPush::send($b->provider, 'market_booking_reminder', 'Tomorrow: {client}', '{service}, {when}.', $this->words($b), ['booking_id' => $b->id]);
                });
        }
        MarketBooking::with(['item', 'shop', 'provider', 'client'])->where('status', MarketBooking::CONFIRMED)->where('reminded_hour', false)
            ->whereBetween('starts_at', [now(), now()->addMinutes(60)])->limit(500)->get()
            ->each(function (MarketBooking $b) use (&$done) {
                $b->update(['reminded_hour' => true, 'reminded_day' => true]);
                $done['reminded']++;
                MarketPush::send($b->client, 'market_booking_reminder', 'In 1 hour: {service}', '{when} with {shop}. Need a ride? Book it from the booking.', $this->words($b), ['booking_id' => $b->id]);
                MarketPush::send($b->provider, 'market_booking_reminder', 'In 1 hour: {client}', '{service}, {when}.', $this->words($b), ['booking_id' => $b->id]);
            });
        return $done;
    }

    // ------------------------------------------------------------------ helpers

    private function canAsk(User $client, MarketItem $item): void
    {
        if (!MarketSettings::enabled()) {
            $this->fail('market', translate('E-Ride Market is not available right now.'));
        }
        if (!$item->isService() || $item->status !== MarketItem::LIVE || !$item->shop?->isLive()) {
            $this->fail('item', translate('This service is not available.'));
        }
        if ($item->shop->user_id === $client->id) {
            $this->fail('item', translate('This is your own service.'));
        }
        if (!$item->shop->taking_bookings) {
            $this->fail('item', translate('This provider is not taking bookings right now.'));
        }
        if ($reason = $this->cannotBookReason($client)) {
            $this->fail('booking', $reason);
        }
    }

    private function parseStart(?string $value): Carbon
    {
        try {
            $start = Carbon::createFromFormat('Y-m-d H:i', (string)$value);
        } catch (\Throwable) {
            $start = null;
        }
        if (!$start) {
            $this->fail('starts_at', translate('Choose a day and a time.'));
        }
        $start->setSecond(0);
        if ($start->isPast()) {
            $this->fail('starts_at', translate('This time has passed. Choose another time.'));
        }
        return $start;
    }

    /** Where: the provider's place, the client's (an address is needed), or online. */
    private function location(MarketItem $item, array $data): array
    {
        $where = $item->whereList();
        $location = in_array($data['location'] ?? null, $where, true) ? $data['location'] : $where[0];
        if ($location !== 'client') {
            return ['location' => $location, 'lat' => null, 'lng' => null, 'address' => null];
        }
        $lat = isset($data['lat']) ? (float)$data['lat'] : null;
        $lng = isset($data['lng']) ? (float)$data['lng'] : null;
        $address = $this->text($data['address'] ?? null, 191);
        if ($lat === null || $lng === null || !$address) {
            $this->fail('address', translate('Choose where the provider should come.'));
        }
        return ['location' => 'client', 'lat' => $lat, 'lng' => $lng, 'address' => $address];
    }

    /** @return array<int, array{name: string, price: float}> */
    private function pickExtras(MarketItem $item, array $names): array
    {
        $names = array_map(fn($n) => mb_strtolower(trim((string)$n)), $names);
        return array_values(array_filter($item->extrasList(), fn($e) => in_array(mb_strtolower(trim((string)$e['name'])), $names, true)));
    }

    private function conversation(User $client, MarketItem $item): ?MarketConversation
    {
        try {
            return $this->chats->start($client, $item);
        } catch (\Throwable) {
            return null;
        }
    }

    private function respondBy(CarbonInterface $start, array $settings): CarbonInterface
    {
        $by = now()->addMinutes($settings['service_answer_minutes']);
        // Never later than the start itself.
        return $by->gt($start) ? $start->copy() : $by;
    }

    /** A line in the booking's chat, so both sides see what happened. */
    private function note(MarketBooking $booking, ?User $by, string $event): void
    {
        if (!$booking->conversation_id || !$by) {
            return;
        }
        try {
            $service = $booking->item?->title ?? '';
            $text = match ($event) {
                'requested' => translate(key: 'Booking asked: {service}, {when}', replace: ['service' => $service, 'when' => $this->when($booking->starts_at)]),
                'quote_requested' => translate(key: 'Quote asked: {service}', replace: ['service' => $service]),
                'quoted' => translate(key: 'Price sent: {price}, {when}', replace: ['price' => getCurrencyFormat($booking->quote_price), 'when' => $this->when($booking->starts_at)]),
                'suggested' => translate(key: 'Another time suggested: {when}', replace: ['when' => $this->when($booking->suggested_starts_at)]),
                'confirmed' => translate(key: 'Booking confirmed: {when}', replace: ['when' => $this->when($booking->starts_at)]),
                'moved' => translate(key: 'Booking moved to {when}', replace: ['when' => $this->when($booking->starts_at)]),
                'declined' => translate(key: 'Booking declined: {service}', replace: ['service' => $service]),
                'cancelled' => translate(key: 'Booking cancelled: {service}', replace: ['service' => $service]),
                'completed' => translate(key: 'Done: {service}', replace: ['service' => $service]),
                'no_show' => translate('Marked as a no-show'),
                default => $service,
            };
            DB::transaction(function () use ($booking, $by, $event, $text) {
                MarketMessage::create(['conversation_id' => $booking->conversation_id, 'sender_id' => $by->id, 'type' => 'booking', 'body' => $text,
                    'meta' => ['booking_id' => $booking->id, 'event' => $event]]);
                MarketConversation::whereKey($booking->conversation_id)->update(['last_message_at' => now(), 'last_message' => $text]);
                MarketConversation::whereKey($booking->conversation_id)->increment($booking->roleOf($by->id) === 'client' ? 'seller_unread' : 'buyer_unread');
            });
        } catch (\Throwable) {
        }
    }

    private function noteReplyTime(MarketBooking $booking): void
    {
        $minutes = max(1, (int)round($booking->created_at->diffInMinutes(now())));
        $shop = MarketShop::find($booking->shop_id);
        $shop?->update(['reply_minutes' => $shop->reply_minutes ? (int)round(($shop->reply_minutes * 4 + $minutes) / 5) : $minutes]);
    }

    private function words(MarketBooking $booking): array
    {
        return [
            'service' => $booking->item?->title ?? '',
            'shop' => $booking->shop?->name ?? '',
            'client' => $this->firstName($booking->client),
            'when' => $this->when($booking->starts_at),
        ];
    }

    /** "Sat 14 Nov, 15:00" (in the platform's language). */
    public function when(?CarbonInterface $at): string
    {
        return $at ? $at->copy()->locale(app()->getLocale())->translatedFormat('D j M, H:i') : translate('a time to agree');
    }

    private function duration(?CarbonInterface $until): string
    {
        $minutes = $until ? max(1, (int)ceil(now()->diffInMinutes($until, false))) : 0;
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        return intdiv($minutes, 60) . ' h' . ($minutes % 60 ? ' ' . str_pad((string)($minutes % 60), 2, '0', STR_PAD_LEFT) : '');
    }

    private function firstName(?User $user): string
    {
        return trim((string)$user?->first_name) ?: translate('A client');
    }

    private function text(?string $value, int $max): ?string
    {
        $value = $value === null ? '' : trim($value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function receiveCode(): string
    {
        return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function newCode(): string
    {
        do {
            $code = 'BK-' . random_int(10000, 99999);
        } while (MarketBooking::where('code', $code)->exists());
        return $code;
    }

    private function fresh(MarketBooking $booking): MarketBooking
    {
        return $booking->fresh(['item.photos', 'shop', 'client', 'provider', 'review']);
    }

    private function expect(MarketBooking $booking, array $statuses): void
    {
        if (!in_array($booking->status, $statuses, true)) {
            $this->fail('booking', translate('This booking has changed. Pull to refresh.'));
        }
    }

    private function asProvider(User $user, MarketBooking $booking): void
    {
        if ($booking->provider_id !== $user->id) {
            $this->fail('booking', translate('This booking is not yours.'));
        }
    }

    private function asClient(User $user, MarketBooking $booking): void
    {
        if ($booking->client_id !== $user->id) {
            $this->fail('booking', translate('This booking is not yours.'));
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
