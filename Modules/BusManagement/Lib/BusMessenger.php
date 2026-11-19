<?php

namespace Modules\BusManagement\Lib;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusMessage;
use Modules\BusManagement\Entities\BusTicket;
use Modules\Gateways\Services\WhatsAppCloud;
use Modules\Gateways\Traits\SmsGatewayForMessage;
use Throwable;

/**
 * Messages to travellers who booked on the website: WhatsApp first (an approved template from E-Ride's
 * business number), SMS when WhatsApp is not connected, refused, or reports the message failed later.
 * App bookings keep their pushes only.
 *
 * Each message is sent once per booking (bus_messages, unique per booking, dedupe key and channel), so
 * retries, a second tick or a repeated webhook never send it twice.
 */
class BusMessenger
{
    /**
     * WhatsApp templates to create in Meta (category Utility, English "en" and French "fr"), with a
     * "Visit website" button whose URL is https://<site>/bus/t/{{1}} (the booking code).
     * Body variables in order.
     */
    public const TEMPLATES = [
        'requested' => ['name' => 'eride_bus_requested', 'vars' => ['trip', 'agency', 'minutes'],
            'en' => 'Your seat request is sent: {{1}} with {{2}}. The agency answers within {{3}} minutes. We will message you here.',
            'fr' => 'Votre demande de place est envoyée : {{1}} avec {{2}}. L\'agence répond en {{3}} minutes. Nous vous écrirons ici.'],
        'accepted' => ['name' => 'eride_bus_accepted', 'vars' => ['agency', 'trip', 'amount', 'time'],
            'en' => '{{1}} accepted your seats: {{2}}. Pay {{3}} with MTN MoMo or Orange Money before {{4}}, or the seats go back on sale.',
            'fr' => '{{1}} a accepté vos places : {{2}}. Payez {{3}} avec MTN MoMo ou Orange Money avant {{4}}, sinon les places sont remises en vente.'],
        'paid' => ['name' => 'eride_bus_ticket', 'vars' => ['agency', 'trip'],
            'en' => 'You are booked with {{1}}: {{2}}. Show the QR code of your ticket at boarding.',
            'fr' => 'C\'est réservé avec {{1}} : {{2}}. Montrez le code QR de votre billet à l\'embarquement.'],
        'reminder' => ['name' => 'eride_bus_reminder', 'vars' => ['agency', 'when'],
            'en' => 'Reminder: your bus with {{1}} leaves {{2}}. Be at the agency 30 minutes early.',
            'fr' => 'Rappel : votre bus {{1}} part {{2}}. Soyez à l\'agence 30 minutes avant.'],
        'not_booked' => ['name' => 'eride_bus_not_booked', 'vars' => ['agency', 'trip', 'what'],
            'en' => 'Your booking with {{1}} for {{2}} did not go through: {{3}} Nothing was charged.',
            'fr' => 'Votre réservation avec {{1}} pour {{2}} n\'a pas abouti : {{3}} Rien n\'a été payé.'],
        'update' => ['name' => 'eride_bus_update', 'vars' => ['code', 'text'],
            'en' => 'News about your E-Ride bus booking {{1}}: {{2}}',
            'fr' => 'Du nouveau pour votre réservation de bus E-Ride {{1}} : {{2}}'],
    ];

    /** Pushes that also go to website travellers, and as what. Others become an "update". */
    protected const FROM_PUSH = [
        'bus_accepted' => 'accepted',
        'bus_declined' => 'declined',
        'bus_paid' => 'paid',
        'bus_request_expired' => 'request_expired',
        'bus_pay_expired' => 'pay_expired',
        // Only in the app: the waitlist is an app feature.
        'bus_seat_free' => null,
    ];

    /** Called by BusPush for every bus push: website bookings also get a WhatsApp or SMS. */
    public static function fromPush(string $action, ?string $bookingId, string $title, string $body): void
    {
        if (!$bookingId) {
            return;
        }
        if (array_key_exists($action, self::FROM_PUSH) && self::FROM_PUSH[$action] === null) {
            return;
        }
        $event = self::FROM_PUSH[$action] ?? 'update';
        self::later(function () use ($bookingId, $event, $action, $title, $body) {
            $booking = BusBooking::find($bookingId);
            if (!$booking || $booking->via !== BusBooking::WEB) {
                return;
            }
            $text = trim(rtrim($title, '.') . '. ' . $body);
            // The same push can come again later (a second delay): the text tells them apart.
            $dedupe = $event === 'update' ? $action . ':' . substr(md5($text), 0, 12) : $event;
            app(self::class)->deliver($booking, $event, ['text' => $text, 'reason' => $booking->decline_reason], $dedupe);
        });
    }

    /** A website booking's own events (request sent, reminder), after the response is sent. */
    public static function send(BusBooking $booking, string $event, array $extra = []): void
    {
        if ($booking->via !== BusBooking::WEB) {
            return;
        }
        $id = $booking->id;
        self::later(function () use ($id, $event, $extra) {
            if ($booking = BusBooking::find($id)) {
                app(self::class)->deliver($booking, $event, $extra);
            }
        });
    }

    /** Runs after the page is sent to the browser (web requests); at once in commands and tests. */
    protected static function later(callable $job): void
    {
        $run = function () use ($job) {
            try {
                $job();
            } catch (Throwable $e) {
                Log::warning('Bus message failed', ['error' => $e->getMessage()]);
            }
        };
        if (app()->runningInConsole()) {
            $run();
        } else {
            app()->terminating($run);
        }
    }

    /** Sends one message now: WhatsApp when it can, else SMS. Null when nothing was sent (already sent, no phone). */
    public function deliver(BusBooking $booking, string $event, array $extra = [], ?string $dedupe = null): ?BusMessage
    {
        $booking->loadMissing(['agency', 'departure.destination', 'customer', 'tickets']);
        $to = trim((string)($booking->phone ?: $booking->customer?->phone));
        if ($to === '' || !$booking->departure) {
            return null;
        }
        $dedupe ??= $event;
        if (BusMessage::where('booking_id', $booking->id)->where('dedupe', $dedupe)->exists()) {
            return null;
        }
        $w = BusWebWords::for($booking->customer?->current_language_key === 'fr' ? 'fr' : 'en');
        $parts = $this->parts($booking, $w, $extra);
        $sms = $this->smsText($event, $parts, $w);
        $wantsWhatsApp = $booking->notify_channel !== BusMessage::SMS;

        if ($wantsWhatsApp && WhatsAppCloud::ready()) {
            $message = $this->claim($booking, $event, $dedupe, BusMessage::WHATSAPP, $to, $sms);
            if (!$message) {
                return null;
            }
            [$template, $vars] = $this->template($event, $parts, $w);
            $result = app(WhatsAppCloud::class)->sendTemplate($to, $template, $w->lang, $vars, $booking->code);
            if ($result['ok']) {
                $message->update(['status' => BusMessage::SENT, 'provider_id' => $result['id']]);
                return $message;
            }
            $message->update(['status' => BusMessage::FAILED, 'error' => $result['error'] ?? null]);
            Log::info('Bus WhatsApp not sent, trying SMS', ['booking' => $booking->code, 'event' => $event, 'error' => $result['error'] ?? null]);
            return $this->fallback($message) ?? $message;
        }

        if (!$wantsWhatsApp || BusSettings::web()['sms_backup']) {
            return $this->sms($booking, $event, $dedupe, $to, $sms);
        }
        return null;
    }

    /** A WhatsApp message failed (refused now, or reported failed by Meta later): the same message by SMS. */
    public function fallback(BusMessage $whatsapp): ?BusMessage
    {
        if ($whatsapp->fallback_id || !BusSettings::web()['sms_backup']) {
            return null;
        }
        $booking = $whatsapp->booking;
        if (!$booking) {
            return null;
        }
        $sms = $this->sms($booking, $whatsapp->event, $whatsapp->dedupe, $whatsapp->to, (string)$whatsapp->body);
        if ($sms) {
            $whatsapp->update(['fallback_id' => $sms->id]);
        }
        return $sms;
    }

    /** Meta's webhook: delivery statuses of the messages we sent. */
    public function handleStatuses(array $payload): int
    {
        $seen = 0;
        foreach ((array)($payload['entry'] ?? []) as $entry) {
            foreach ((array)($entry['changes'] ?? []) as $change) {
                foreach ((array)($change['value']['statuses'] ?? []) as $status) {
                    $id = (string)($status['id'] ?? '');
                    $state = (string)($status['status'] ?? '');
                    if ($id === '' || $state === '') {
                        continue;
                    }
                    $message = BusMessage::where('channel', BusMessage::WHATSAPP)->where('provider_id', $id)->first();
                    if (!$message) {
                        continue;
                    }
                    $seen++;
                    if ($state === 'failed') {
                        $error = $status['errors'][0] ?? [];
                        $message->update(['status' => BusMessage::FAILED,
                            'error' => mb_substr(trim(($error['code'] ?? '') . ' ' . ($error['title'] ?? $error['message'] ?? 'failed')), 0, 250)]);
                        $this->fallback($message);
                    } elseif (in_array($state, ['delivered', 'read'], true) && $message->status !== BusMessage::READ) {
                        $message->update(['status' => $state]);
                    }
                }
            }
        }
        return $seen;
    }

    protected function sms(BusBooking $booking, string $event, string $dedupe, string $to, string $text): ?BusMessage
    {
        $message = $this->claim($booking, $event, $dedupe, BusMessage::SMS, $to, $text);
        if (!$message) {
            return null;
        }
        try {
            $result = (new class {
                use SmsGatewayForMessage;
            })::send($to, $text);
        } catch (Throwable $e) {
            $result = 'error: ' . $e->getMessage();
        }
        $ok = $result === 'success';
        $message->update(['status' => $ok ? BusMessage::SENT : BusMessage::FAILED,
            'error' => $ok ? null : mb_substr($result === 'not_found' ? 'No SMS gateway is switched on' : (string)$result, 0, 250)]);
        if (!$ok) {
            Log::info('Bus SMS not sent', ['booking' => $booking->code, 'event' => $event, 'result' => $result]);
        }
        return $message;
    }

    /** The row that stands for this message; null when another process already sent it. */
    protected function claim(BusBooking $booking, string $event, string $dedupe, string $channel, string $to, string $body): ?BusMessage
    {
        try {
            return BusMessage::create(['booking_id' => $booking->id, 'event' => $event, 'dedupe' => $dedupe, 'channel' => $channel,
                'to' => mb_substr($to, 0, 30), 'status' => BusMessage::SKIPPED, 'body' => $body]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** What the messages are made of, in the traveller's language. */
    protected function parts(BusBooking $b, BusWebWords $w, array $extra): array
    {
        $d = $b->departure;
        $seats = $b->tickets->whereNotIn('status', [BusTicket::CANCELLED])->pluck('seat')->sortBy(fn($s) => (int)$s)->values();
        if ($seats->isEmpty()) {
            $seats = $b->tickets->pluck('seat')->sortBy(fn($s) => (int)$s)->values();
        }
        $when = $w->when($d->leavesAt());
        $trip = $w->get($seats->count() > 1 ? 'trip_seats' : 'trip_seat', [
            'from' => $b->agency?->origin_city, 'to' => $d->destination?->city, 'when' => $when, 'seats' => $seats->implode(', '),
        ]);
        return [
            'code' => $b->code,
            'agency' => (string)$b->agency?->name,
            'trip' => $trip,
            'when' => $when,
            'minutes' => (string)BusSettings::all()['answer_minutes'],
            'amount' => $w->money($b->amount),
            'time' => $b->pay_by?->format('H:i') ?? '',
            'link' => BusWebLinks::booking($b),
            'reason' => filled($extra['reason'] ?? null) ? rtrim((string)$extra['reason'], '. ') . '. ' : '',
            'text' => (string)($extra['text'] ?? ''),
        ];
    }

    protected function smsText(string $event, array $p, BusWebWords $w): string
    {
        $key = match ($event) {
            'requested' => 'msg_requested',
            'accepted' => 'msg_accepted',
            'paid' => 'msg_paid',
            'reminder' => 'msg_reminder',
            'declined' => 'msg_declined',
            'request_expired' => 'msg_request_expired',
            'pay_expired' => 'msg_pay_expired',
            default => 'msg_update',
        };
        return $w->get($key, ['n' => $p['minutes']] + $p);
    }

    /** @return array{0: string, 1: array} the template's name and body variables */
    protected function template(string $event, array $p, BusWebWords $w): array
    {
        $kind = match ($event) {
            'requested', 'accepted', 'paid', 'reminder' => $event,
            'declined', 'request_expired', 'pay_expired' => 'not_booked',
            default => 'update',
        };
        if ($kind === 'not_booked') {
            $p['what'] = match ($event) {
                'declined' => $p['reason'] !== '' ? $p['reason'] : $w->get('what_declined'),
                'request_expired' => $w->get('what_no_answer'),
                default => $w->get('what_pay_time'),
            };
        }
        $t = self::TEMPLATES[$kind];
        return [$t['name'], array_map(fn($var) => (string)($p[$var] ?? ''), $t['vars'])];
    }
}
