<?php

namespace Modules\Gateways\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Gateways\Entities\FapshiTransaction;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\TripManagement\Entities\TripRequest;
use Throwable;

/**
 * The rules for money moving through Fapshi.
 *
 * A payment request (a trip fare, a wallet top-up, a driver's due) is settled
 * only by apply(), only from what Fapshi's authenticated payment-status
 * endpoint says (never from a webhook body or a redirect), only once, and only
 * when the right amount arrived for the right payment. The webhook, the
 * customer's return and the fapshi:reconcile sweep all end up in sync().
 */
class FapshiPayments
{
    public function __construct(protected FapshiClient $client)
    {
    }

    public function client(): FapshiClient
    {
        return $this->client;
    }

    /** What the customer will be asked to pay, in whole francs. */
    public function quote(PaymentRequest $paymentRequest): array
    {
        $base = (float)$paymentRequest->payment_amount;
        $fee = $this->client->surchargeFor($base);
        return [
            'base' => $base,
            'fee' => $fee,
            'charge' => (int)ceil($base) + $fee,
        ];
    }

    /**
     * A checkout link for this payment request: an open one if it is still
     * good for the same amount, otherwise a new one.
     *
     * @return array{link?: string, transaction?: FapshiTransaction, status?: string, error?: string}
     */
    public function start(PaymentRequest $paymentRequest): array
    {
        // Settle what is already in flight first: the customer may have paid
        // on an earlier link and come back before the webhook landed.
        foreach ($this->openAttempts($paymentRequest) as $attempt) {
            $this->sync($attempt);
        }
        $paymentRequest->refresh();
        if ($paymentRequest->is_paid) {
            return ['status' => FapshiTransaction::SUCCESSFUL];
        }

        if (strtoupper((string)$paymentRequest->currency_code) !== 'XAF') {
            return ['error' => translate('Mobile money only accepts payments in FCFA (XAF).')];
        }

        $quote = $this->quote($paymentRequest);
        if ($quote['charge'] < FapshiClient::MINIMUM_AMOUNT) {
            return ['error' => translate(key: 'Mobile money payments start at {amount}. Please choose another way to pay.', replace: ['amount' => getCurrencyFormat(FapshiClient::MINIMUM_AMOUNT)])];
        }

        $reusable = $this->openAttempts($paymentRequest)
            ->first(fn(FapshiTransaction $t) => $t->payment_link
                && $t->charged_amount === $quote['charge']
                && $t->environment === $this->client->environment()
                && $t->initiated_at && $t->initiated_at->gt(now()->subHours(FapshiClient::LINK_LIFETIME_HOURS - 1)));
        if ($reusable) {
            return ['link' => $reusable->payment_link, 'transaction' => $reusable];
        }

        $attempt = FapshiTransaction::where('payment_request_id', $paymentRequest->id)->count() + 1;
        $transaction = FapshiTransaction::create([
            'payment_request_id' => $paymentRequest->id,
            'environment' => $this->client->environment(),
            'external_id' => Str::limit($paymentRequest->id . '-' . $attempt, 100, ''),
            'base_amount' => $quote['base'],
            'fee_amount' => $quote['fee'],
            'charged_amount' => $quote['charge'],
            'currency' => 'XAF',
            'status' => FapshiTransaction::CREATED,
        ]);

        $payer = json_decode((string)$paymentRequest->payer_information, true) ?: [];
        $payload = [
            'amount' => $quote['charge'],
            'externalId' => $transaction->external_id,
            'message' => $this->describe($paymentRequest),
            'redirectUrl' => route('fapshi.return', ['reference' => $transaction->external_id]),
        ];
        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', (string)$paymentRequest->payer_id)) {
            $payload['userId'] = (string)$paymentRequest->payer_id;
        }
        if (filter_var($payer['email'] ?? null, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = $payer['email'];
        }

        try {
            $response = $this->client->initiatePay($payload);
        } catch (Throwable $e) {
            Log::warning('Fapshi initiate-pay failed', ['external_id' => $transaction->external_id, 'error' => $e->getMessage()]);
            $transaction->update(['status' => FapshiTransaction::FAILED, 'failure_reason' => Str::limit($e->getMessage(), 250, ''), 'raw_request' => $payload]);
            return ['error' => translate('Mobile money is not reachable right now. Please try again in a moment or choose another way to pay.')];
        }

        $body = $response->json() ?? [];
        if (!$response->successful() || empty($body['link']) || empty($body['transId'])) {
            $reason = (string)($body['message'] ?? ('HTTP ' . $response->status()));
            Log::warning('Fapshi initiate-pay refused', ['external_id' => $transaction->external_id, 'status' => $response->status(), 'message' => $reason]);
            $transaction->update([
                'status' => FapshiTransaction::FAILED,
                'failure_reason' => Str::limit($reason, 250, ''),
                'raw_request' => $payload,
                'raw_response' => ['initiate-pay' => $body],
            ]);
            return ['error' => translate('Mobile money could not start this payment. Please try again or choose another way to pay.')];
        }

        $transaction->update([
            'trans_id' => $body['transId'],
            'payment_link' => $body['link'],
            'raw_request' => $payload,
            'raw_response' => ['initiate-pay' => $body],
            'initiated_at' => now(),
        ]);

        return ['link' => $body['link'], 'transaction' => $transaction];
    }

    /**
     * Ask Fapshi where a transaction stands and apply the answer.
     *
     * @param int $minSeconds skip the call when the last one was this recent
     *                        (Fapshi allows 6 status calls a minute per payment)
     */
    public function sync(FapshiTransaction $transaction, int $minSeconds = 0): string
    {
        if (!$transaction->isOpen() || !$transaction->trans_id) {
            return $transaction->status;
        }
        if ($minSeconds > 0 && $transaction->last_checked_at && $transaction->last_checked_at->gt(now()->subSeconds($minSeconds))) {
            return $transaction->status;
        }

        $transaction->forceFill(['last_checked_at' => now()])->save();
        try {
            $response = $this->client->paymentStatus($transaction->trans_id);
        } catch (Throwable $e) {
            Log::info('Fapshi payment-status unreachable', ['trans_id' => $transaction->trans_id, 'error' => $e->getMessage()]);
            return $transaction->status;
        }
        if (!$response->successful()) {
            // 429 and friends: the answer is "ask again later", not "failed".
            return $transaction->status;
        }

        return $this->apply($transaction, $response->json() ?? []);
    }

    /**
     * Apply a payment-status body. Idempotent and safe to race: the webhook
     * and the returning customer routinely arrive together.
     */
    public function apply(FapshiTransaction $transaction, array $status): string
    {
        try {
            return DB::transaction(function () use ($transaction, $status) {
                /** @var FapshiTransaction $tx */
                $tx = FapshiTransaction::whereKey($transaction->id)->lockForUpdate()->first();
                if (!$tx->isOpen()) {
                    return $tx->status;
                }

                $raw = $tx->raw_response ?? [];
                $raw['payment-status'] = $status;
                $tx->raw_response = $raw;

                $providerStatus = strtoupper((string)($status['status'] ?? ''));
                if (filled($status['financialTransId'] ?? null)) {
                    $tx->financial_trans_id = $status['financialTransId'];
                }
                if (filled($status['medium'] ?? null)) {
                    $tx->medium = Str::limit((string)$status['medium'], 30, '');
                }

                if ($providerStatus !== 'SUCCESSFUL') {
                    return $this->recordUnsuccessful($tx, $providerStatus, $status);
                }

                // Fapshi echoes the amount asked for in every state, so the
                // amount only means money moved once it is SUCCESSFUL.
                $tx->amount_received = isset($status['amount']) ? (int)$status['amount'] : null;
                $tx->revenue = isset($status['revenue']) ? (int)$status['revenue'] : null;

                $claimedExternalId = (string)($status['externalId'] ?? '');
                if ($claimedExternalId !== '' && $claimedExternalId !== $tx->external_id) {
                    return $this->flag($tx, FapshiTransaction::MISMATCHED, "Fapshi reported a payment for {$claimedExternalId} on transaction {$tx->external_id}.");
                }
                if ((int)$tx->amount_received < $tx->charged_amount) {
                    return $this->flag($tx, FapshiTransaction::MISMATCHED, "Received {$tx->amount_received} XAF against a charge of {$tx->charged_amount} XAF.");
                }

                /** @var PaymentRequest|null $paymentRequest */
                $paymentRequest = PaymentRequest::whereKey($tx->payment_request_id)->lockForUpdate()->first();
                if (!$paymentRequest) {
                    return $this->flag($tx, FapshiTransaction::REFUNDABLE, 'The payment request this belongs to no longer exists.');
                }
                if ($paymentRequest->is_paid) {
                    return $this->flag($tx, FapshiTransaction::REFUNDABLE, 'The payment request was already settled when this payment arrived (paid twice).');
                }
                if ($paymentRequest->hook === 'tripRequestUpdate') {
                    $trip = TripRequest::whereKey($paymentRequest->attribute_id)->lockForUpdate()->first();
                    if ($trip && $trip->payment_status === PAID) {
                        return $this->flag($tx, FapshiTransaction::REFUNDABLE, 'The trip was already paid another way when this payment arrived.');
                    }
                }

                $paymentRequest->forceFill([
                    'is_paid' => 1,
                    'payment_method' => 'fapshi',
                    'transaction_id' => $tx->trans_id,
                ])->save();

                $tx->status = FapshiTransaction::SUCCESSFUL;
                $tx->confirmed_at = now();
                $tx->failure_reason = null;
                $tx->save();

                // Inside the transaction on purpose: if the hook throws, the
                // payment request stays unpaid and the next sync retries it.
                if ($paymentRequest->hook && function_exists($paymentRequest->hook)) {
                    call_user_func($paymentRequest->hook, $paymentRequest->fresh());
                }

                return FapshiTransaction::SUCCESSFUL;
            });
        } catch (Throwable $e) {
            Log::error('Fapshi settlement failed, will retry', ['external_id' => $transaction->external_id, 'error' => $e->getMessage()]);
            return $transaction->fresh()->status;
        }
    }

    /** Open attempts for a payment request, newest first. */
    public function openAttempts(PaymentRequest $paymentRequest)
    {
        return FapshiTransaction::where('payment_request_id', $paymentRequest->id)
            ->whereIn('status', FapshiTransaction::OPEN)
            ->whereNotNull('trans_id')
            ->latest('id')
            ->get();
    }

    /**
     * CREATED and PENDING are not outcomes (the customer is still at the
     * checkout or approving on their phone), so those stay open. FAILED and
     * EXPIRED are final; a link past its life is expired whatever Fapshi
     * last said.
     */
    protected function recordUnsuccessful(FapshiTransaction $tx, string $providerStatus, array $status): string
    {
        $mapped = match ($providerStatus) {
            'FAILED' => FapshiTransaction::FAILED,
            'EXPIRED' => FapshiTransaction::EXPIRED,
            'CREATED' => FapshiTransaction::CREATED,
            default => FapshiTransaction::PENDING,
        };
        if ($mapped === FapshiTransaction::CREATED && $tx->initiated_at && $tx->initiated_at->lt(now()->subHours(FapshiClient::LINK_LIFETIME_HOURS))) {
            $mapped = FapshiTransaction::EXPIRED;
        }

        $tx->status = $mapped;
        if (in_array($mapped, [FapshiTransaction::FAILED, FapshiTransaction::EXPIRED], true)) {
            $tx->failure_reason = Str::limit((string)($status['reason'] ?? ($mapped === FapshiTransaction::FAILED
                ? 'The mobile money operator reported the payment as failed.'
                : 'The payment link expired without being paid.')), 250, '');
            $tx->confirmed_at = now();
        }
        $tx->save();

        return $mapped;
    }

    protected function flag(FapshiTransaction $tx, string $status, string $reason): string
    {
        $tx->status = $status;
        $tx->failure_reason = Str::limit($reason, 250, '');
        $tx->confirmed_at = now();
        $tx->save();
        Log::warning('Fapshi payment flagged ' . $status, ['external_id' => $tx->external_id, 'trans_id' => $tx->trans_id, 'reason' => $reason]);
        return $status;
    }

    /** The line the payer sees on Fapshi's checkout. */
    protected function describe(PaymentRequest $paymentRequest): string
    {
        $business = businessConfig('business_name')?->value ?: 'E-Ride';
        $ref = $paymentRequest->hook === 'tripRequestUpdate'
            ? TripRequest::whereKey($paymentRequest->attribute_id)->value('ref_id')
            : null;
        $text = match ($paymentRequest->hook) {
            'tripRequestUpdate' => $business . ' trip' . ($ref ? ' #' . $ref : ''),
            'customerWalletUpdate' => $business . ' wallet top-up',
            'driverDigitalPay' => $business . ' driver payment',
            default => $business . ' payment',
        };
        return Str::limit(trim($text), 60, '');
    }
}
