<?php

namespace Modules\BusManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusPayment;
use Modules\BusManagement\Lib\BusPush;
use Modules\Gateways\Services\FapshiClient;
use Throwable;

/**
 * Passengers pay the agency straight into its own Fapshi account (never E-Ride's).
 *
 * The same rules as FapshiPayments: a booking is paid only from what Fapshi's authenticated
 * payment-status endpoint says (never from a webhook body or a redirect), only once, and only when the
 * right amount arrived. The agency's webhook, the passenger's return and the bus:tick sweep all end in sync().
 */
class BusPaymentService
{
    public function __construct(protected AgencyService $agencies, protected BookingService $bookings)
    {
    }

    public function clientFor(BusPayment|BusBooking $of): ?FapshiClient
    {
        $agency = $of->agency;
        return $agency ? $this->agencies->fapshiClient($agency) : null;
    }

    /**
     * A checkout link for an accepted booking: an open one if still good, otherwise a new one.
     * @return array{link?: string, paid?: bool, error?: string, reference?: string}
     */
    public function start(BusBooking $booking): array
    {
        // One Pay tap at a time per booking: a second tap (or a retry after a
        // timeout) waits for the first, then finds its link and reuses it.
        $lock = 'eride:bus-pay:' . $booking->id;
        $got = DB::selectOne('SELECT GET_LOCK(?, 20) AS l', [$lock]);
        if ((int)($got->l ?? 0) !== 1) {
            return ['error' => translate('This payment is starting. Please wait a moment and try again.')];
        }
        try {
            return $this->startLocked($booking);
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS l', [$lock]);
        }
    }

    protected function startLocked(BusBooking $booking): array
    {
        foreach ($this->openAttempts($booking) as $attempt) {
            $this->sync($attempt);
        }
        $booking->refresh();
        if ($booking->status === BusBooking::PAID) {
            return ['paid' => true];
        }
        if ($booking->status !== BusBooking::ACCEPTED) {
            return ['error' => $booking->status === BusBooking::REQUESTED
                ? translate('Wait for the agency to accept your seats first.')
                : translate('This booking cannot be paid any more.')];
        }
        if (!$booking->pay_by || $booking->pay_by->lte(now())) {
            return ['error' => translate('The time to pay has run out.')];
        }
        $client = $this->clientFor($booking);
        if (!$client || !$booking->agency->fapshi_ok_at) {
            return ['error' => translate('This agency cannot take Mobile Money right now. Speak to the agency.')];
        }
        $amount = (int)ceil((float)$booking->amount);
        if ($amount < FapshiClient::MINIMUM_AMOUNT) {
            return ['error' => translate(key: 'Mobile money payments start at {amount}.', replace: ['amount' => getCurrencyFormat(FapshiClient::MINIMUM_AMOUNT)])];
        }

        $reusable = $this->openAttempts($booking)->first(fn(BusPayment $p) => $p->payment_link && $p->amount === $amount
            && $p->environment === $client->environment()
            && $p->initiated_at && $p->initiated_at->gt(now()->subHours(FapshiClient::LINK_LIFETIME_HOURS - 1)));
        if ($reusable) {
            return ['link' => $reusable->payment_link, 'reference' => $reusable->external_id];
        }

        $attempt = BusPayment::where('booking_id', $booking->id)->count() + 1;
        $payment = BusPayment::create([
            'booking_id' => $booking->id,
            'agency_id' => $booking->agency_id,
            'environment' => $client->environment(),
            'external_id' => 'bus-' . $booking->code . '-' . $attempt,
            'amount' => $amount,
            'status' => BusPayment::CREATED,
        ]);
        $payload = [
            'amount' => $amount,
            'externalId' => $payment->external_id,
            'message' => Str::limit($booking->agency->name . ' ticket ' . $booking->code, 60, ''),
            'redirectUrl' => route('bus.pay.return', ['reference' => $payment->external_id]),
        ];
        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', (string)$booking->customer_id)) {
            $payload['userId'] = (string)$booking->customer_id;
        }
        $email = $booking->customer?->email;
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = $email;
        }

        try {
            $response = $client->initiatePay($payload);
        } catch (Throwable $e) {
            Log::warning('Bus Fapshi initiate-pay failed', ['external_id' => $payment->external_id, 'error' => $e->getMessage()]);
            $payment->update(['status' => BusPayment::FAILED, 'failure_reason' => Str::limit($e->getMessage(), 250, '')]);
            return ['error' => translate('Mobile money is not reachable right now. Please try again in a moment.')];
        }
        $body = $response->json() ?? [];
        if (!$response->successful() || empty($body['link']) || empty($body['transId'])) {
            $reason = (string)($body['message'] ?? ('HTTP ' . $response->status()));
            Log::warning('Bus Fapshi initiate-pay refused', ['external_id' => $payment->external_id, 'status' => $response->status(), 'message' => $reason]);
            $payment->update(['status' => BusPayment::FAILED, 'failure_reason' => Str::limit($reason, 250, ''), 'raw_response' => ['initiate-pay' => $body]]);
            return ['error' => translate('Mobile money could not start this payment. Please try again, or speak to the agency.')];
        }
        $payment->update([
            'trans_id' => $body['transId'],
            'payment_link' => $body['link'],
            'raw_response' => ['initiate-pay' => $body],
            'initiated_at' => now(),
            'status' => BusPayment::PENDING,
        ]);
        return ['link' => $body['link'], 'reference' => $payment->external_id];
    }

    /** Asks the agency's Fapshi where a payment stands and applies the answer (at most every $minSeconds). */
    public function sync(BusPayment $payment, int $minSeconds = 0): string
    {
        if (!in_array($payment->status, BusPayment::OPEN, true) || !$payment->trans_id) {
            return $payment->status;
        }
        if ($minSeconds > 0 && $payment->last_checked_at && $payment->last_checked_at->gt(now()->subSeconds($minSeconds))) {
            return $payment->status;
        }
        $client = $this->clientFor($payment);
        if (!$client) {
            return $payment->status;
        }
        $payment->forceFill(['last_checked_at' => now()])->save();
        try {
            $response = $client->paymentStatus($payment->trans_id);
        } catch (Throwable $e) {
            Log::info('Bus Fapshi payment-status unreachable', ['trans_id' => $payment->trans_id, 'error' => $e->getMessage()]);
            return $payment->status;
        }
        if (!$response->successful()) {
            return $payment->status;
        }
        return $this->apply($payment, $response->json() ?? []);
    }

    /** Applies a payment-status body. Idempotent and safe to race (webhook and return arrive together). */
    public function apply(BusPayment $payment, array $status): string
    {
        $outcome = null;
        try {
            $result = DB::transaction(function () use ($payment, $status, &$outcome) {
                $p = BusPayment::whereKey($payment->id)->lockForUpdate()->first();
                if (!in_array($p->status, BusPayment::OPEN, true)) {
                    return $p->status;
                }
                $raw = $p->raw_response ?? [];
                $raw['payment-status'] = $status;
                $p->raw_response = $raw;
                if (filled($status['medium'] ?? null)) {
                    $p->medium = Str::limit((string)$status['medium'], 30, '');
                }
                $provider = strtoupper((string)($status['status'] ?? ''));
                if ($provider !== 'SUCCESSFUL') {
                    $p->status = match ($provider) {
                        'FAILED' => BusPayment::FAILED,
                        'EXPIRED' => BusPayment::EXPIRED,
                        default => BusPayment::PENDING,
                    };
                    if ($p->status === BusPayment::PENDING && $p->initiated_at && $p->initiated_at->lt(now()->subHours(FapshiClient::LINK_LIFETIME_HOURS))) {
                        $p->status = BusPayment::EXPIRED;
                    }
                    if (!in_array($p->status, BusPayment::OPEN, true)) {
                        $p->failure_reason = Str::limit((string)($status['reason'] ?? ($p->status === BusPayment::FAILED
                            ? 'The mobile money operator reported the payment as failed.' : 'The payment link expired without being paid.')), 250, '');
                    }
                    $p->save();
                    return $p->status;
                }

                $claimed = (string)($status['externalId'] ?? '');
                $received = (int)($status['amount'] ?? 0);
                if (($claimed !== '' && $claimed !== $p->external_id) || $received < $p->amount) {
                    $p->status = BusPayment::MISMATCHED;
                    $p->failure_reason = Str::limit("Fapshi reported {$received} XAF for '{$claimed}' on {$p->external_id} ({$p->amount} XAF asked).", 250, '');
                    $p->save();
                    Log::warning('Bus Fapshi payment mismatched', ['external_id' => $p->external_id, 'trans_id' => $p->trans_id]);
                    return $p->status;
                }

                $booking = BusBooking::whereKey($p->booking_id)->lockForUpdate()->first();
                $outcome = $this->bookings->settlePaid($booking, $p);
                $p->status = BusPayment::SUCCESSFUL;
                $p->paid_at = now();
                $p->failure_reason = $outcome === 'refund_due' ? 'Paid after the seats were released or a second time: the agency refunds it.' : null;
                $p->save();
                return $p->status;
            });
        } catch (Throwable $e) {
            Log::error('Bus payment settlement failed, will retry', ['external_id' => $payment->external_id, 'error' => $e->getMessage()]);
            return $payment->fresh()->status;
        }

        if ($outcome !== null) {
            $booking = BusBooking::with(['agency', 'departure.destination', 'customer'])->find($payment->booking_id);
            $replace = ['agency' => $booking->agency->name, 'city' => $booking->departure->destination?->city,
                'time' => $booking->departure->leavesAt()->format('d/m H:i'), 'amount' => getCurrencyFormat($payment->amount)];
            $outcome === 'paid'
                ? BusPush::send($booking->customer, 'bus_paid', 'Your tickets to {city} are ready',
                    '{agency}, {time}. Show the QR code when you board.', $replace, ['bus_booking_id' => $booking->id])
                : BusPush::send($booking->customer, 'bus_refund_due', 'Your payment was not needed',
                    'Your seats with {agency} were no longer waiting for it (paid late, paid twice, or the trip stopped). The agency sends back {amount} to your Mobile Money.', $replace, ['bus_booking_id' => $booking->id]);
        }
        return $result;
    }

    public function openAttempts(BusBooking $booking)
    {
        return BusPayment::where('booking_id', $booking->id)->whereIn('status', BusPayment::OPEN)->whereNotNull('trans_id')->latest('id')->get();
    }

    /** Settles payments whose webhook never came (Fapshi sends it once, never again). */
    public function reconcile(int $limit = 50): int
    {
        $settled = 0;
        BusPayment::whereIn('status', BusPayment::OPEN)->whereNotNull('trans_id')
            ->where('initiated_at', '>', now()->subHours(48))
            ->where(fn($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinute()))
            ->oldest('last_checked_at')->limit($limit)->get()
            ->each(function (BusPayment $p) use (&$settled) {
                $settled += $this->sync($p) === BusPayment::SUCCESSFUL ? 1 : 0;
            });
        return $settled;
    }
}
