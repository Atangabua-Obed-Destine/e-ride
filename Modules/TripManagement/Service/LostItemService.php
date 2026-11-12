<?php

namespace Modules\TripManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\TripManagement\Entities\LostItem;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\LostItemSettings;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Lib\SupportDesk;
use Throwable;

/**
 * Lost and found. After a ride, the rider reports something left in the car; the
 * driver answers Found it or Not in my car. Once found, both see each other's number
 * and the rider chooses to collect it or have it brought (a return fee, cash to the
 * driver). The rider confirms it is back. E-Ride support takes over when the driver
 * does not answer in time, when it was not found and the rider asks, or when a found
 * item is not back after some days; staff can act on any case from the admin.
 */
class LostItemService
{
    public const PUSH_ACTION = 'lost_item';

    /** The rider's trip if a lost item can be reported for it, else the reason why not. */
    public function reportableTrip(string $tripId, string $customerId): TripRequest|string
    {
        if (!LostItemSettings::enabled()) {
            return translate('Lost and found is not available right now. Contact E-Ride support.');
        }
        $trip = TripRequest::with(['tripStatus', 'driver'])->whereKey($tripId)->where('customer_id', $customerId)->first();
        if (!$trip || $trip->type !== RIDE_REQUEST || $trip->current_status !== COMPLETED || !$trip->driver_id) {
            return translate('You can report a lost item for a completed ride.');
        }
        $ended = $trip->tripStatus?->completed ? Carbon::parse($trip->tripStatus->completed) : $trip->updated_at;
        $days = LostItemSettings::all()['report_days'];
        if ($ended->lt(now()->subDays($days))) {
            return translate(key: 'This ride ended more than {days} days ago. Contact E-Ride support.', replace: ['days' => $days]);
        }
        if (LostItem::where('trip_request_id', $trip->id)->exists()) {
            return translate('You already reported a lost item for this ride. Open it from the trip.');
        }
        return $trip;
    }

    public function report(TripRequest $trip, string $description, ?UploadedFile $photo): LostItem
    {
        $item = LostItem::create([
            'trip_request_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'description' => $description,
            'photo' => $photo ? fileUploader(LostItem::PHOTO_FOLDER, APPLICATION_IMAGE_FORMAT, $photo) : null,
            'status' => LostItem::REPORTED,
        ]);
        $item->setRelation('trip', $trip);
        $this->toDriver($item, 'A rider left something in your car',
            '{rider} left this in your car on trip #{ref}: {item}. Open Lost items and tell them if you found it.');
        return $item;
    }

    /** The driver checked the car. Found or not; a driver who said not found can still find it later. */
    public function answer(LostItem $item, bool $found, ?string $note, string $by = 'driver'): void
    {
        $item->update([
            'status' => $found ? LostItem::FOUND : LostItem::NOT_FOUND,
            'driver_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : $item->driver_note,
            'answered_at' => now(),
            // A driver who answers ends the "no answer" escalation; a not-found one stays with support if the rider asked.
            'escalated_at' => $found ? null : $item->escalated_at,
        ]);
        if ($found) {
            $this->toCustomer($item, 'Your driver found your item',
                $by === 'admin' ? 'E-Ride confirmed that {driver} has your item. Open the trip to choose how to get it back.'
                    : '{driver} found it. Open the trip to choose how to get it back.');
        } else {
            $this->toCustomer($item, 'Not found in the car',
                '{driver} did not find your item in the car. If you are sure you left it there, you can ask E-Ride support to help.');
        }
    }

    public function chooseReturn(LostItem $item, string $method): void
    {
        $fee = $method === LostItem::DELIVER ? LostItemSettings::all()['return_fee'] : 0;
        $item->update(['return_method' => $method, 'return_fee' => $fee]);
        $method === LostItem::DELIVER
            ? $this->toDriver($item, 'Please bring the item back', '{rider} asks you to bring it to them. They pay you {fee} in cash when they get it. Call them to agree on a time.')
            : $this->toDriver($item, 'The rider will collect the item', '{rider} will collect it from you. Call them to agree on where and when.');
    }

    /** The driver says it is back with the rider; the rider confirms (or the case closes by itself after confirm_hours). */
    public function driverReturned(LostItem $item): void
    {
        $item->update(['driver_returned_at' => now()]);
        $this->toCustomer($item, 'Do you have your item back', '{driver} says your item is back with you. Open the trip to confirm.');
    }

    public function confirmReturned(LostItem $item, string $by = 'customer'): void
    {
        $item->update(['status' => LostItem::RETURNED, 'returned_at' => now(), 'closed_at' => now(), 'closed_by' => $by]);
        if ($by === 'customer') {
            $this->toDriver($item, 'Item returned', '{rider} confirmed they have it back. Thank you for your honesty.');
        } elseif ($by === 'system') {
            $this->toCustomer($item, 'Lost item case closed', 'We closed your lost item case as returned. If you do not have it, contact E-Ride support.');
        } else {
            $this->toBoth($item, 'Lost item case closed', 'E-Ride closed the lost item case: the item is back with the rider.');
        }
    }

    /** The rider found it themselves, or no longer needs help. */
    public function cancel(LostItem $item): void
    {
        $item->update(['status' => LostItem::CANCELLED, 'closed_at' => now(), 'closed_by' => 'customer']);
        $this->toDriver($item, 'Lost item report closed', '{rider} closed their lost item report. No need to look for it any more.');
    }

    /** Hand the case to E-Ride support. */
    public function escalate(LostItem $item, string $why): void
    {
        if ($item->escalated_at) {
            return;
        }
        $item->update(['escalated_at' => now()]);
        $item->loadMissing('trip');
        try {
            AdminNotification::create(['model' => 'lost_item', 'model_id' => $item->id, 'message' => 'Lost item needs support on trip #' . $item->trip?->ref_id]);
        } catch (Throwable $e) {
            Log::info('Lost item admin notification not saved', ['item' => $item->id, 'error' => $e->getMessage()]);
        }
        $body = match ($why) {
            'no_answer' => 'Your driver has not answered yet, so {desk} will help you get your item back. Support answers {open}.',
            'stale' => 'Your item is not back yet, so {desk} will help. Support answers {open}.',
            default => '{desk} will look into your lost item. Support answers {open}.',
        };
        $this->toCustomer($item, 'E-Ride support is on it', $body);
    }

    /** Staff action from the admin. */
    public function adminClose(LostItem $item, ?string $note): void
    {
        $item->update(['status' => LostItem::CLOSED, 'closed_at' => now(), 'closed_by' => 'admin', 'admin_note' => $note ?: $item->admin_note]);
        $this->toBoth($item, 'Lost item case closed', 'E-Ride support closed the lost item case for trip #{ref}.');
    }

    /**
     * Every few minutes: remind drivers who have not answered, hand unanswered and stuck
     * cases to support, and close cases the driver returned when the rider says nothing.
     *
     * @return array{reminded: int, escalated: int, closed: int}
     */
    public function tick(?Carbon $now = null): array
    {
        $now ??= now();
        $s = LostItemSettings::all();
        $count = ['reminded' => 0, 'escalated' => 0, 'closed' => 0];

        $halfway = $now->copy()->subMinutes((int)($s['answer_hours'] * 30));
        LostItem::with(['trip', 'customer', 'driver'])->where('status', LostItem::REPORTED)->whereNull('reminded_at')->whereNull('escalated_at')
            ->where('created_at', '<=', $halfway)->get()->each(function (LostItem $item) use ($now, &$count) {
                $item->update(['reminded_at' => $now]);
                $this->toDriver($item, 'A rider is waiting for your answer', 'Please check your car for what {rider} left on trip #{ref}, then open Lost items to answer.');
                $count['reminded']++;
            });

        LostItem::with(['trip', 'customer', 'driver'])->where('status', LostItem::REPORTED)->whereNull('escalated_at')
            ->where('created_at', '<=', $now->copy()->subHours($s['answer_hours']))->get()->each(function (LostItem $item) use (&$count) {
                $this->escalate($item, 'no_answer');
                $count['escalated']++;
            });

        LostItem::with(['trip', 'customer', 'driver'])->where('status', LostItem::FOUND)->whereNull('escalated_at')->whereNull('driver_returned_at')
            ->where('answered_at', '<=', $now->copy()->subDays($s['stale_days']))->get()->each(function (LostItem $item) use (&$count) {
                $this->escalate($item, 'stale');
                $count['escalated']++;
            });

        LostItem::with(['trip', 'customer', 'driver'])->where('status', LostItem::FOUND)->whereNotNull('driver_returned_at')
            ->where('driver_returned_at', '<=', $now->copy()->subHours($s['confirm_hours']))->get()->each(function (LostItem $item) use (&$count) {
                $this->confirmReturned($item, 'system');
                $count['closed']++;
            });

        return $count;
    }

    /** What the customer or the driver app shows for a case. Numbers only while it is found and not yet back. */
    public function present(LostItem $item, string $viewer): array
    {
        $item->loadMissing(['trip.coordinate', 'trip.vehicle.model', 'customer', 'driver']);
        $trip = $item->trip;
        $s = LostItemSettings::all();
        $other = $viewer === 'customer' ? $item->driver : $item->customer;
        $showPhone = $item->status === LostItem::FOUND;
        return [
            'id' => $item->id,
            'trip_request_id' => $item->trip_request_id,
            'trip_ref_id' => $trip?->ref_id,
            'trip_date' => $trip?->created_at?->toIso8601String(),
            'pickup_address' => $trip?->coordinate?->pickup_address,
            'destination_address' => $trip?->coordinate?->destination_address,
            'description' => $item->description,
            'photo' => $item->photoUrl(),
            'status' => $item->status,
            'is_open' => $item->isOpen(),
            'needs_support' => $item->needsSupport(),
            'return_method' => $item->return_method,
            'return_fee' => $item->return_method === LostItem::DELIVER ? $item->return_fee : 0,
            'deliver_fee' => $s['return_fee'],
            'driver_note' => $item->driver_note,
            'message' => $this->message($item, $viewer),
            'other' => [
                'name' => trim(($other?->first_name ?? '') . ' ' . mb_substr((string)($other?->last_name ?? ''), 0, 1)),
                'phone' => $showPhone ? $other?->phone : null,
                'vehicle' => $viewer === 'customer' ? trim(($trip?->vehicle?->model?->name ?? '') . ' ' . ($trip?->vehicle?->licence_plate_number ?? '')) : null,
            ],
            'actions' => $this->actions($item, $viewer),
            // Who to call while E-Ride support has the case.
            'support' => $item->needsSupport() ? SupportDesk::present() : null,
            'created_at' => $item->created_at?->toIso8601String(),
            'answered_at' => $item->answered_at?->toIso8601String(),
            'driver_returned_at' => $item->driver_returned_at?->toIso8601String(),
            'returned_at' => $item->returned_at?->toIso8601String(),
            'closed_at' => $item->closed_at?->toIso8601String(),
        ];
    }

    /** What this person can do now. */
    public function actions(LostItem $item, string $viewer): array
    {
        if (!$item->isOpen()) {
            return [];
        }
        if ($viewer === 'driver') {
            return match ($item->status) {
                LostItem::REPORTED => ['found', 'not_found'],
                LostItem::NOT_FOUND => ['found'],
                LostItem::FOUND => $item->driver_returned_at ? [] : ['returned'],
                default => [],
            };
        }
        $support = $item->escalated_at ? [] : ['support'];
        return match ($item->status) {
            LostItem::REPORTED => ['cancel'],
            LostItem::NOT_FOUND => [...$support, 'cancel'],
            LostItem::FOUND => [...($item->driver_returned_at ? [] : ['collect', 'deliver']), 'returned', ...$support],
            default => [],
        };
    }

    protected function message(LostItem $item, string $viewer): string
    {
        $r = $this->replace($item);
        $text = $viewer === 'customer'
            ? match (true) {
                $item->status === LostItem::REPORTED && $item->escalated_at !== null => 'Your driver has not answered, so {desk} is on it. Support answers {open}.',
                $item->status === LostItem::REPORTED && LostItemSettings::all()['answer_hours'] === 1 => 'We asked {driver} to check the car. You will hear back within the hour.',
                $item->status === LostItem::REPORTED => 'We asked {driver} to check the car. You will hear back within {hours} hours.',
                $item->status === LostItem::NOT_FOUND && $item->escalated_at !== null => '{desk} is looking into it. Support answers {open}.',
                $item->status === LostItem::FOUND && $item->escalated_at !== null => 'Your item is not back yet, so {desk} is helping. Support answers {open}.',
                $item->status === LostItem::NOT_FOUND => '{driver} did not find it in the car. If you are sure you left it there, ask E-Ride support to help.',
                $item->status === LostItem::FOUND && $item->driver_returned_at !== null => '{driver} says it is back with you. Confirm below, or ask for help if you do not have it.',
                $item->status === LostItem::FOUND && $item->return_method === LostItem::DELIVER => '{driver} will bring it to you. Pay them {fee} in cash when you get it.',
                $item->status === LostItem::FOUND && $item->return_method === LostItem::COLLECT => 'You will collect it from {driver}. Call them to agree on where and when.',
                $item->status === LostItem::FOUND => 'Good news: {driver} found it. Choose how to get it back, then call them to agree on a time.',
                $item->status === LostItem::RETURNED => 'Your item is back with you.',
                $item->status === LostItem::CANCELLED => 'You closed this report.',
                default => 'E-Ride support closed this case.',
            }
            : match (true) {
                $item->status === LostItem::REPORTED => '{rider} left this in your car on trip #{ref}. Check the car and answer.',
                $item->status === LostItem::NOT_FOUND => 'You said it is not in your car. If you find it later, tell {rider} here.',
                $item->status === LostItem::FOUND && $item->driver_returned_at !== null => 'Waiting for {rider} to confirm they have it.',
                $item->status === LostItem::FOUND && $item->return_method === LostItem::DELIVER => 'Please bring it to {rider}. They pay you {fee} in cash.',
                $item->status === LostItem::FOUND && $item->return_method === LostItem::COLLECT => '{rider} will collect it from you. Agree on where and when.',
                $item->status === LostItem::FOUND => 'Thank you. {rider} will choose how to get it back. Call them to agree.',
                $item->status === LostItem::RETURNED => 'Returned. Thank you for your honesty.',
                $item->status === LostItem::CANCELLED => '{rider} closed the report.',
                default => 'E-Ride support closed this case.',
            };
        return translate(key: $text, replace: $r);
    }

    protected function replace(LostItem $item, ?string $locale = null): array
    {
        $item->loadMissing(['trip', 'customer', 'driver']);
        return [
            'rider' => $item->customer?->first_name ?: translate('The rider'),
            'driver' => $item->driver?->first_name ?: translate('Your driver'),
            'ref' => $item->trip?->ref_id,
            'item' => mb_strimwidth($item->description, 0, 60, '...'),
            'fee' => getCurrencyFormat($item->return_fee ?: LostItemSettings::all()['return_fee']),
            'hours' => LostItemSettings::all()['answer_hours'],
            'desk' => SupportDesk::all()['name'],
            'open' => SupportDesk::hoursLabel($locale),
        ];
    }

    protected function toDriver(LostItem $item, string $title, string $body): void
    {
        $item->loadMissing('driver');
        $this->push($item->driver, $item, $title, $body);
    }

    protected function toCustomer(LostItem $item, string $title, string $body): void
    {
        $item->loadMissing('customer');
        $this->push($item->customer, $item, $title, $body);
    }

    protected function toBoth(LostItem $item, string $title, string $body): void
    {
        $this->toCustomer($item, $title, $body);
        $this->toDriver($item, $title, $body);
    }

    protected function push(?User $user, LostItem $item, string $title, string $body): void
    {
        if (!$user?->fcm_token) {
            return;
        }
        $locale = $user->current_language_key ?: 'en';
        try {
            sendDeviceNotification(fcm_token: $user->fcm_token, title: translate(key: $title, locale: $locale),
                description: translate(key: $body, replace: $this->replace($item, $locale), locale: $locale), status: 1,
                ride_request_id: $item->trip_request_id, type: 'lost_item', notification_type: 'lost_item',
                action: self::PUSH_ACTION, user_id: $user->id);
        } catch (Throwable $e) {
            Log::warning('Lost item push failed', ['item' => $item->id, 'error' => $e->getMessage()]);
        }
    }
}
