<?php

namespace Modules\StoreManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Services\FapshiClient;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StorePayment;
use Modules\StoreManagement\Service\Interfaces\StoreOrderServiceInterface;
use Throwable;

/**
 * Customers of a business with its own Fapshi account pay straight into it (never E-Ride's).
 *
 * The same rules as the bus agencies (BusPaymentService): a payment counts only from what Fapshi's
 * authenticated payment-status endpoint says (never a webhook body or a redirect), only once, and only
 * for the right amount. The business's webhook, the customer's return and the store tick all end in sync().
 */
class StorePaymentService
{
    // ------------------------------------------------------------ the business's keys

    /** Saves the brand's Fapshi keys. An empty key or secret keeps the saved one. A change needs a new test. */
    public function saveKeys(Store $brand, array $input): Store
    {
        $data = Validator::make($input, [
            'api_user' => 'required|string|max:120',
            'api_key' => 'nullable|string|max:200',
            'webhook_secret' => 'nullable|string|max:200',
            'mode' => ['required', Rule::in(['test', 'live'])],
        ], [], ['api_user' => translate('API user'), 'api_key' => translate('API key')])->validate();
        $old = is_array($brand->fapshi) ? $brand->fapshi : [];
        $new = [
            'api_user' => trim($data['api_user']),
            'api_key' => filled($data['api_key'] ?? null) ? trim($data['api_key']) : ($old['api_key'] ?? null),
            'webhook_secret' => filled($data['webhook_secret'] ?? null) ? trim($data['webhook_secret']) : ($old['webhook_secret'] ?? null),
            'mode' => $data['mode'],
        ];
        if (!filled($new['api_key'])) {
            throw ValidationException::withMessages(['api_key' => translate('Enter the API key.')]);
        }
        if ($new != array_intersect_key($old, $new)) {
            $brand->fapshi_ok_at = null;
        }
        $brand->fapshi = $new;
        $brand->fapshi_code = $brand->fapshi_code ?: strtolower(Str::random(16));
        $brand->save();
        return $brand;
    }

    public function client(Store $store): ?FapshiClient
    {
        $values = $store->fapshiValues();
        return $values ? new FapshiClient($values, $values['mode'] ?? 'test') : null;
    }

    /** Checks the keys against Fapshi (the balance). Returns [ok, message]. */
    public function testKeys(Store $brand): array
    {
        $client = $this->client($brand);
        if (!$client) {
            return [false, translate('Save your API user and API key first.')];
        }
        if ($looksLike = $client->credentialEnvironmentMismatch()) {
            return [false, $looksLike === 'sandbox'
                ? translate('This is a sandbox (test) key, but the mode is Live.')
                : translate('This is a live key, but the mode is Test.')];
        }
        if (!filled($brand->fapshiValues()['webhook_secret'] ?? null)) {
            return [false, translate('Add the webhook secret too: E-Ride uses it to check that payment news really comes from Fapshi.')];
        }
        $host = parse_url($client->baseUrl(), PHP_URL_HOST);
        try {
            $response = $client->balance();
        } catch (Throwable) {
            return [false, translate(key: 'Could not reach {host}. Try again in a minute.', replace: ['host' => $host])];
        }
        if ($response->successful()) {
            $brand->forceFill(['fapshi_ok_at' => now()])->save();
            return [true, $client->environment() === 'live'
                ? translate('Connected to Fapshi (live). Switch on "Customers pay my Fapshi" when you are ready.')
                : translate('Connected to Fapshi (test). Payments are only pretend until you switch to Live.')];
        }
        if ($response->status() === 403) {
            return [false, translate('Fapshi refused the E-Ride server (403). In your Fapshi dashboard, allow the E-Ride server address in the IP whitelist, or ask E-Ride for it.')];
        }
        if (in_array($response->status(), [400, 401], true)) {
            return [false, translate('Fapshi rejected the API user or API key. Check that both are from the same service and mode.')];
        }
        return [false, translate(key: 'Fapshi answered {code}. Try again, or check your keys.', replace: ['code' => $response->status()])];
    }

    /** Testing only: E-Ride's own Fapshi sandbox keys, so a demo business can take pretend payments. */
    public function useEridesSandbox(Store $brand): array
    {
        $row = DB::table('settings')->where('key_name', 'fapshi')->where('settings_type', PAYMENT_CONFIG)->first();
        $values = $row ? (json_decode((string)$row->test_values, true) ?: []) : [];
        if (!filled($values['api_user'] ?? null) || !filled($values['api_key'] ?? null)) {
            return [false, translate('E-Ride has no Fapshi sandbox keys saved (Payment Methods, test values).')];
        }
        if (!str_starts_with(trim($values['api_key']), 'FAK_TEST_')) {
            return [false, translate('Refused: the saved test key is not a Fapshi sandbox key.')];
        }
        $brand->fapshi = [
            'api_user' => trim($values['api_user']),
            'api_key' => trim($values['api_key']),
            // This sandbox's webhooks go to E-Ride's own Fapshi webhook, which settles these payments too.
            'webhook_secret' => filled($values['webhook_secret'] ?? null) ? trim($values['webhook_secret']) : Str::random(32),
            'mode' => 'test',
            'eride_sandbox' => true,
        ];
        $brand->fapshi_code = $brand->fapshi_code ?: strtolower(Str::random(16));
        $brand->fapshi_ok_at = null;
        $brand->save();
        return $this->testKeys($brand->fresh());
    }

    // ------------------------------------------------------------ payments

    /**
     * A checkout link into the business's Fapshi for an order (or its later delivery fee).
     * @return array{link?: string, paid?: bool, error?: string}
     */
    public function start(StoreOrder $order, string $purpose = StorePayment::ORDER): array
    {
        $lock = 'eride:store-pay:' . $order->id . ':' . $purpose;
        $got = DB::selectOne('SELECT GET_LOCK(?, 20) AS l', [$lock]);
        if ((int)($got->l ?? 0) !== 1) {
            return ['error' => translate('This payment is starting. Please wait a moment and try again.')];
        }
        try {
            return $this->startLocked($order, $purpose);
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS l', [$lock]);
        }
    }

    protected function startLocked(StoreOrder $order, string $purpose): array
    {
        foreach ($this->openAttempts($order, $purpose) as $attempt) {
            $this->sync($attempt);
        }
        $order->refresh();
        $brand = $order->store->brand();
        $client = $this->client($brand);
        if (!$client || !$order->store->takesOwnPayments()) {
            return ['error' => translate('This store cannot take Mobile Money right now.')];
        }
        $amount = (int)ceil($purpose === StorePayment::DELIVERY_FEE ? (float)($order->switch['fee'] ?? 0) : (float)$order->total);
        if ($amount < FapshiClient::MINIMUM_AMOUNT) {
            return ['error' => translate(key: 'Mobile money payments start at {amount}.', replace: ['amount' => getCurrencyFormat(FapshiClient::MINIMUM_AMOUNT)])];
        }
        $reusable = $this->openAttempts($order, $purpose)->first(fn(StorePayment $p) => $p->payment_link && $p->amount === $amount
            && $p->environment === $client->environment()
            && $p->initiated_at && $p->initiated_at->gt(now()->subHours(FapshiClient::LINK_LIFETIME_HOURS - 1)));
        if ($reusable) {
            return ['link' => $reusable->payment_link];
        }
        $n = StorePayment::where('store_order_id', $order->id)->count() + 1;
        $payment = StorePayment::create([
            'store_order_id' => $order->id,
            'store_id' => $brand->id,
            'purpose' => $purpose,
            'environment' => $client->environment(),
            'external_id' => 'store-' . substr(str_replace('-', '', $order->id), 0, 12) . '-' . $n,
            'amount' => $amount,
            'status' => StorePayment::CREATED,
        ]);
        $payload = [
            'amount' => $amount,
            'externalId' => $payment->external_id,
            'message' => Str::limit($brand->name . ' order #' . $order->ref_id, 60, ''),
            'redirectUrl' => route('store.pay.return', ['reference' => $payment->external_id]),
        ];
        if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', (string)$order->customer_id)) {
            $payload['userId'] = (string)$order->customer_id;
        }
        if (filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL)) {
            $payload['email'] = $order->customer->email;
        }
        try {
            $response = $client->initiatePay($payload);
        } catch (Throwable $e) {
            Log::warning('Store Fapshi initiate-pay failed', ['external_id' => $payment->external_id, 'error' => $e->getMessage()]);
            $payment->update(['status' => StorePayment::FAILED, 'failure_reason' => Str::limit($e->getMessage(), 250, '')]);
            return ['error' => translate('Mobile money is not reachable right now. Please try again in a moment.')];
        }
        $body = $response->json() ?? [];
        if (!$response->successful() || empty($body['link']) || empty($body['transId'])) {
            $reason = (string)($body['message'] ?? ('HTTP ' . $response->status()));
            Log::warning('Store Fapshi initiate-pay refused', ['external_id' => $payment->external_id, 'status' => $response->status(), 'message' => $reason]);
            $payment->update(['status' => StorePayment::FAILED, 'failure_reason' => Str::limit($reason, 250, ''), 'raw_response' => ['initiate-pay' => $body]]);
            return ['error' => translate('Mobile money could not start this payment. Please try again.')];
        }
        $payment->update([
            'trans_id' => $body['transId'],
            'payment_link' => $body['link'],
            'raw_response' => ['initiate-pay' => $body],
            'initiated_at' => now(),
            'status' => StorePayment::PENDING,
        ]);
        return ['link' => $body['link']];
    }

    /** Asks the business's Fapshi where a payment stands and applies the answer (at most every $minSeconds). */
    public function sync(StorePayment $payment, int $minSeconds = 0): string
    {
        if (!in_array($payment->status, StorePayment::OPEN, true) || !$payment->trans_id) {
            return $payment->status;
        }
        if ($minSeconds > 0 && $payment->last_checked_at && $payment->last_checked_at->gt(now()->subSeconds($minSeconds))) {
            return $payment->status;
        }
        $client = $payment->store ? $this->client($payment->store) : null;
        if (!$client) {
            return $payment->status;
        }
        $payment->forceFill(['last_checked_at' => now()])->save();
        try {
            $response = $client->paymentStatus($payment->trans_id);
        } catch (Throwable $e) {
            Log::info('Store Fapshi payment-status unreachable', ['trans_id' => $payment->trans_id, 'error' => $e->getMessage()]);
            return $payment->status;
        }
        return $response->successful() ? $this->apply($payment, $response->json() ?? []) : $payment->status;
    }

    /** Applies a payment-status body. Idempotent and safe to race. */
    public function apply(StorePayment $payment, array $status): string
    {
        try {
            return DB::transaction(function () use ($payment, $status) {
                $p = StorePayment::whereKey($payment->id)->lockForUpdate()->first();
                if (!in_array($p->status, StorePayment::OPEN, true)) {
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
                        'FAILED' => StorePayment::FAILED,
                        'EXPIRED' => StorePayment::EXPIRED,
                        default => StorePayment::PENDING,
                    };
                    if ($p->status === StorePayment::PENDING && $p->initiated_at && $p->initiated_at->lt(now()->subHours(FapshiClient::LINK_LIFETIME_HOURS))) {
                        $p->status = StorePayment::EXPIRED;
                    }
                    if (!in_array($p->status, StorePayment::OPEN, true)) {
                        $p->failure_reason = Str::limit((string)($status['reason'] ?? ($p->status === StorePayment::FAILED
                            ? 'The mobile money operator reported the payment as failed.' : 'The payment link expired without being paid.')), 250, '');
                    }
                    $p->save();
                    return $p->status;
                }
                $claimed = (string)($status['externalId'] ?? '');
                $received = (int)($status['amount'] ?? 0);
                if (($claimed !== '' && $claimed !== $p->external_id) || $received < $p->amount) {
                    $p->status = StorePayment::MISMATCHED;
                    $p->failure_reason = Str::limit("Fapshi reported {$received} XAF for '{$claimed}' on {$p->external_id} ({$p->amount} XAF asked).", 250, '');
                    $p->save();
                    Log::warning('Store Fapshi payment mismatched', ['external_id' => $p->external_id]);
                    return $p->status;
                }
                $p->status = StorePayment::SUCCESSFUL;
                $p->paid_at = now();
                $p->save();
                app(StoreOrderServiceInterface::class)->onShopPaid($p);
                return $p->status;
            });
        } catch (Throwable $e) {
            Log::error('Store payment settlement failed, will retry', ['external_id' => $payment->external_id, 'error' => $e->getMessage()]);
            return $payment->fresh()->status;
        }
    }

    public function openAttempts(StoreOrder $order, ?string $purpose = null)
    {
        return StorePayment::where('store_order_id', $order->id)->whereIn('status', StorePayment::OPEN)->whereNotNull('trans_id')
            ->when($purpose, fn($q) => $q->where('purpose', $purpose))->latest('created_at')->get();
    }

    /**
     * Before an unpaid order expires: settles what is in flight and closes the links.
     * False while it must wait (paid just now, or still being approved within the grace).
     */
    public function close(StoreOrder $order, bool $graceOver): bool
    {
        foreach ($this->openAttempts($order, StorePayment::ORDER) as $attempt) {
            $status = $this->sync($attempt);
            if ($status === StorePayment::SUCCESSFUL || ($status === StorePayment::PENDING && !$graceOver)) {
                return false;
            }
            $client = $this->client($attempt->store);
            try {
                $client?->expirePay($attempt->trans_id);
                $attempt->forceFill(['status' => StorePayment::EXPIRED, 'failure_reason' => 'Closed when the time to pay ended.'])->save();
            } catch (Throwable) {
                if (!$graceOver) {
                    return false;
                }
            }
        }
        return true;
    }

    /** Settles payments whose webhook never came (Fapshi sends it once). */
    public function reconcile(int $limit = 50): int
    {
        $settled = 0;
        StorePayment::whereIn('status', StorePayment::OPEN)->whereNotNull('trans_id')
            ->where('initiated_at', '>', now()->subHours(48))
            ->where(fn($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinute()))
            ->oldest('last_checked_at')->limit($limit)->get()
            ->each(function (StorePayment $p) use (&$settled) {
                $settled += $this->sync($p) === StorePayment::SUCCESSFUL ? 1 : 0;
            });
        return $settled;
    }
}
