<?php

namespace Modules\Gateways\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Fapshi collection API (https://docs.fapshi.com).
 *
 * HTTP only: no database writes, no decisions about money. FapshiPayments owns
 * those. Credentials come from the 'fapshi' row of the settings table, edited
 * in Configurations > 3rd party > Payment Methods. The gateway's mode picks
 * the host: test is sandbox.fapshi.com, live is live.fapshi.com.
 */
class FapshiClient
{
    public const SANDBOX_URL = 'https://sandbox.fapshi.com';
    public const LIVE_URL = 'https://live.fapshi.com';

    /** Fapshi refuses anything smaller. */
    public const MINIMUM_AMOUNT = 100;

    /** Fapshi expires an unpaid link after 24 hours. */
    public const LINK_LIFETIME_HOURS = 24;

    protected array $values = [];
    protected string $mode = 'test';
    protected bool $active = false;

    public function __construct(?array $values = null, ?string $mode = null)
    {
        if ($values === null) {
            $row = DB::table('settings')->where('key_name', 'fapshi')->where('settings_type', PAYMENT_CONFIG)->first();
            if ($row) {
                $this->mode = $row->mode === 'live' ? 'live' : 'test';
                $this->active = (int)$row->is_active === 1;
                $this->values = json_decode($this->mode === 'live' ? $row->live_values : $row->test_values, true) ?: [];
            }
        } else {
            $this->values = $values;
            $this->mode = $mode === 'live' ? 'live' : 'test';
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** sandbox | live */
    public function environment(): string
    {
        return $this->mode === 'live' ? 'live' : 'sandbox';
    }

    public function baseUrl(): string
    {
        return $this->mode === 'live' ? self::LIVE_URL : self::SANDBOX_URL;
    }

    public function isConfigured(): bool
    {
        return filled($this->values['api_user'] ?? null) && filled($this->values['api_key'] ?? null);
    }

    /** Percentage added on top of the amount to cover Fapshi's fee (0 = absorbed). */
    public function feePercent(): float
    {
        return max(0, (float)($this->values['fee_percent'] ?? 0));
    }

    /** Fixed amount, in XAF, added on top of the amount (0 = none). */
    public function feeFixed(): float
    {
        return max(0, (float)($this->values['fee_fixed'] ?? 0));
    }

    /**
     * The whole-franc surcharge on an amount, rounded up in the business's
     * favour so the figure shown is never below what Fapshi takes.
     */
    public function surchargeFor(float $amount): int
    {
        return (int)(ceil($amount * $this->feePercent() / 100) + ceil($this->feeFixed()));
    }

    /**
     * Does the API key belong to the selected environment? Fapshi prefixes a
     * sandbox key with FAK_TEST_ and a live key with FAK_ alone. The key field
     * is blank on the admin form (blank keeps the stored key), so switching to
     * Live and saving keeps a sandbox key, and the only symptom is a gateway
     * refusing credentials that look fine. This catches it.
     *
     * @return string|null the environment the key appears to belong to, or null when it matches
     */
    public function credentialEnvironmentMismatch(): ?string
    {
        $key = trim((string)($this->values['api_key'] ?? ''));
        if ($key === '') {
            return null;
        }
        $keyLooksLike = str_starts_with($key, 'FAK_TEST_') ? 'sandbox' : 'live';
        return $keyLooksLike === $this->environment() ? null : $keyLooksLike;
    }

    public function hasWebhookSecret(): bool
    {
        return filled($this->values['webhook_secret'] ?? null);
    }

    /**
     * Does a webhook carry the secret set in Fapshi's dashboard? hash_equals so
     * the comparison does not leak the secret through its timing.
     */
    public function webhookSecretMatches(?string $presented): bool
    {
        $expected = (string)($this->values['webhook_secret'] ?? '');
        if ($expected === '' || $presented === null) {
            return false;
        }
        return hash_equals($expected, $presented);
    }

    /**
     * POST /initiate-pay -> { message, link, transId, dateInitiated }
     *
     * amount: whole XAF, at least 100. externalId and userId: 1-100 letters,
     * digits, hyphens or underscores. The caller honours that; nothing is
     * corrected here, so the figure charged is always the figure shown.
     */
    public function initiatePay(array $payload): Response
    {
        return $this->http()->post($this->baseUrl() . '/initiate-pay', $payload);
    }

    /**
     * GET /payment-status/{transId} -> status CREATED | PENDING | SUCCESSFUL |
     * FAILED | EXPIRED, with amount, revenue, externalId, financialTransId,
     * medium and dateConfirmed. Limited to 6 calls a minute per transId; a 429
     * comes back as a normal response for the caller to back off on.
     */
    public function paymentStatus(string $transId): Response
    {
        return $this->http()->get($this->baseUrl() . '/payment-status/' . urlencode($transId));
    }

    /**
     * GET /balance -> { service, balance, currency }. Read-only, so it is the
     * credential test behind the admin's Test connection button.
     *   200  accepted
     *   400  "Invalid apiuser/apikey" (Fapshi does not answer 403 for this)
     *   403  the server's IP is not on the account's whitelist
     */
    public function balance(): Response
    {
        return $this->http()->get($this->baseUrl() . '/balance');
    }

    protected function http(): PendingRequest
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Fapshi API user / API key is not configured.');
        }

        return Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'apiuser' => $this->values['api_user'],
                'apikey' => $this->values['api_key'],
            ]);
    }
}
