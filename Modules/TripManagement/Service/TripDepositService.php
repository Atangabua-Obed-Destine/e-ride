<?php

namespace Modules\TripManagement\Service;

use App\Events\CustomerDepositPaidEvent;
use App\Events\CustomerTripCancelledEvent;
use App\Events\TripDepositExpiredEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\FareManagement\Entities\TripFare;
use Modules\FareManagement\Entities\ZoneWiseDefaultTripFare;
use Modules\Gateways\Entities\FapshiTransaction;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Services\FapshiPayments;
use Modules\Gateways\Traits\Payment;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\TransactionManagement\Entities\Transaction;
use Modules\TripManagement\Entities\TripDeposit;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\DepositSettings;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAccount;
use Modules\UserManagement\Entities\UserLastLocation;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;
use Throwable;

/**
 * Booking deposit. After a driver accepts a ride or parcel, the customer pays
 * a small deposit (wallet or MoMo) before the driver sets off. Every change
 * goes through here under a row lock, so the webhook, the scheduler and taps
 * in both apps can race safely.
 *
 * Money:
 *  - Paying moves nothing into the admin ledger: E-Ride just holds it.
 *  - Trip paid (completed, or cancelled during the trip): the deposit counts
 *    toward the fare ("applied"). Wallet: it goes back into the wallet just
 *    before the normal wallet payment takes the whole fare. MoMo: the gateway
 *    charges the fare minus the deposit, and the normal digital ledger books
 *    the whole fare. Cash: the driver collects the fare minus the deposit, and
 *    the deposit is moved to what E-Ride owes the driver.
 *  - Customer cancels late: forfeited, split between the driver (receivable)
 *    and E-Ride (commission) by the admin's share setting.
 *  - Driver or admin cancels, or a free cancel: refunded to the wallet.
 *  - A MoMo payment that lands after the deposit closed goes to the wallet.
 */
class TripDepositService
{
    use Payment;

    public const HOOK = 'tripDepositPaymentUpdate';
    public const ATTRIBUTE = 'trip_deposit';
    /** A MoMo payment in progress when time runs out gets this much longer. */
    public const MOMO_GRACE_SECONDS = 120;
    /** A driver this close to the pickup has arrived. */
    public const ARRIVED_METERS = 200;
    /** "We couldn't agree" needs the driver at the pickup (a little looser: trucks park further away). */
    public const NO_AGREEMENT_METERS = 300;

    // ---------------------------------------------------------------- amounts

    /** The deposit for a trip, from its zone and vehicle category. 0 means none. */
    public function amountFor(TripRequest $trip): float
    {
        if (!DepositSettings::forTrip($trip)['status'] || $this->isStoreOrderTrip($trip) || !$trip->zone_id) {
            return 0;
        }
        return $this->amountForCategory($trip->zone_id, $trip->vehicle_category_id);
    }

    public function amountForCategory(?string $zoneId, ?string $vehicleCategoryId): float
    {
        if (!$zoneId) {
            return 0;
        }
        $amount = $vehicleCategoryId
            ? TripFare::where('zone_id', $zoneId)->where('vehicle_category_id', $vehicleCategoryId)->value('booking_deposit')
            : null;
        if ($amount === null) {
            $amount = ZoneWiseDefaultTripFare::where('zone_id', $zoneId)->value('booking_deposit');
        }
        return round(max(0, (float)$amount), 2);
    }

    public function forTrip(string $tripId): ?TripDeposit
    {
        return TripDeposit::where('trip_request_id', $tripId)->first();
    }

    /** The driver may not start (or set off on) the trip yet. */
    public function blocksStart(TripRequest $trip): bool
    {
        return TripDeposit::where('trip_request_id', $trip->id)->where('status', TripDeposit::AWAITING)->exists();
    }

    /**
     * What the customer still owes when the trip is paid.
     *
     * @return array{deposit: float, applied: float, due: float}
     */
    public function amountDue(TripRequest $trip, float $total): array
    {
        $deposit = TripDeposit::where('trip_request_id', $trip->id)->where('status', TripDeposit::PAID)->first();
        $applied = $deposit ? min($deposit->amount, max(0, $total)) : 0;
        return [
            'deposit' => $deposit ? round($deposit->amount, 2) : 0,
            'applied' => round($applied, 2),
            'due' => round(max(0, $total - $applied), 2),
        ];
    }

    // ------------------------------------------------------------------ open

    /** Called once a driver has accepted (or the customer accepted a bid). */
    public function open(TripRequest $trip): ?TripDeposit
    {
        $amount = $this->amountFor($trip);
        if ($amount <= 0) {
            return null;
        }
        $settings = DepositSettings::forTrip($trip);
        $minutes = $trip->ride_request_type == SCHEDULED ? $settings['scheduled_window_minutes'] : $settings['window_minutes'];
        return DB::transaction(function () use ($trip, $amount, $minutes) {
            $existing = TripDeposit::where('trip_request_id', $trip->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }
            return TripDeposit::create([
                'trip_request_id' => $trip->id,
                'customer_id' => $trip->customer_id,
                'driver_id' => $trip->driver_id,
                'zone_id' => $trip->zone_id,
                'vehicle_category_id' => $trip->vehicle_category_id,
                'amount' => $amount,
                'status' => TripDeposit::AWAITING,
                'due_at' => now()->addMinutes($minutes),
            ]);
        });
    }

    /** The "driver found" push, asking for the deposit. [title, body] in the customer's language. */
    public function acceptPush(TripRequest $trip, TripDeposit $deposit): array
    {
        $locale = $trip->customer?->current_language_key;
        $minutes = max(1, (int)ceil(now()->diffInSeconds($deposit->due_at, false) / 60));
        if ($trip->is_move) {
            return [
                translate(key: 'Truck found: confirm with the booking fee', locale: $locale),
                translate(key: 'Pay {amount} within {minutes} min so your driver sets off. You agree the price of the move with the driver.', replace: [
                    'amount' => getCurrencyFormat($deposit->amount),
                    'minutes' => $minutes,
                ], locale: $locale),
            ];
        }
        return [
            translate(key: 'Driver found: confirm with your deposit', locale: $locale),
            translate(key: 'Pay {amount} within {minutes} min so your driver sets off. It counts toward your fare.', replace: [
                'amount' => getCurrencyFormat($deposit->amount),
                'minutes' => $minutes,
            ], locale: $locale),
        ];
    }

    // ------------------------------------------------------------------- pay

    public function payWithWallet(TripDeposit $deposit, User $customer): TripDeposit
    {
        return DB::transaction(function () use ($deposit, $customer) {
            $deposit = TripDeposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($deposit->customer_id !== $customer->id) {
                throw ValidationException::withMessages(['deposit' => translate('This deposit is not yours.')]);
            }
            if ($deposit->status === TripDeposit::PAID) {
                return $deposit;
            }
            $this->assertPayable($deposit);
            $account = UserAccount::where('user_id', $customer->id)->lockForUpdate()->first();
            if (!$account || $account->wallet_balance + 0.00001 < $deposit->amount) {
                throw ValidationException::withMessages(['deposit' => translate('Your wallet balance is too low for this deposit.')]);
            }
            $account->wallet_balance -= $deposit->amount;
            $account->save();
            Transaction::create([
                'attribute' => 'trip_deposit_payment',
                'attribute_id' => $deposit->trip_request_id,
                'debit' => $deposit->amount,
                'balance' => $account->wallet_balance,
                'user_id' => $customer->id,
                'account' => 'wallet_balance',
                'trx_type' => 'Booking deposit paid from customer wallet',
            ]);
            return $this->markPaid($deposit, 'wallet');
        });
    }

    /** A signed link the app opens in its payment webview (no auth header there). */
    public function payUrl(TripDeposit $deposit, string $gateway): string
    {
        // Signed on the path only, so it survives the HTTPS proxy in front.
        return url(URL::temporarySignedRoute('trip-deposit.pay', now()->addMinutes(30), [
            'deposit' => $deposit->id,
            'payment_method' => $gateway,
        ], absolute: false));
    }

    /** The gateway's checkout link. */
    public function paymentLink(TripDeposit $deposit, string $gateway): string
    {
        $this->assertPayable($deposit);
        $customer = User::findOrFail($deposit->customer_id);
        $paymentInfo = new PaymentInfo(
            hook: self::HOOK,
            currencyCode: businessConfig('currency_code')?->value ?? 'XAF',
            paymentMethod: $gateway,
            paymentPlatform: 'mono',
            payerId: $customer->id,
            receiverId: '100',
            additionalData: [
                'business_name' => businessConfig('business_name')?->value,
                'business_logo' => dynamicStorage('storage/app/public/business') . '/' . businessConfig('header_logo')?->value,
            ],
            paymentAmount: $deposit->amount,
            externalRedirectLink: null,
            attribute: self::ATTRIBUTE,
            attributeId: $deposit->id,
        );
        $payer = new Payer(name: $customer->first_name, email: $customer->email, phone: $customer->phone, address: '');
        $link = $this->generate_link($payer, $paymentInfo, new Receiver('receiver_name', 'example.png'));
        if (!$link) {
            throw ValidationException::withMessages(['payment_method' => translate('This payment method is not available.')]);
        }
        return $link;
    }

    /** The payment hook (tripDepositPaymentUpdate), called by the gateways. */
    public function onGatewayPaid(PaymentRequest $paymentRequest): void
    {
        // Some gateways call the hook on failure too. Only real money counts.
        if (!(int)$paymentRequest->is_paid) {
            return;
        }
        DB::transaction(function () use ($paymentRequest) {
            $deposit = TripDeposit::whereKey($paymentRequest->attribute_id)->lockForUpdate()->first();
            if (!$deposit) {
                return;
            }
            // The same payment reported twice: already applied.
            if ($deposit->payment_request_id === $paymentRequest->id
                || Transaction::where('attribute', 'trip_deposit_late_payment')->where('trx_ref_id', $paymentRequest->id)->exists()) {
                return;
            }
            if ($deposit->status === TripDeposit::AWAITING) {
                $deposit->payment_request_id = $paymentRequest->id;
                $this->markPaid($deposit, $paymentRequest->payment_method);
                return;
            }
            // Paid after the deposit expired or the trip was cancelled, or paid
            // twice: the money goes to the wallet, never lost.
            $this->creditWallet($deposit->customer_id, (float)$paymentRequest->payment_amount, $deposit->trip_request_id,
                'trip_deposit_late_payment', 'Late or duplicate booking deposit payment added to customer wallet', $paymentRequest->id);
            DB::afterCommit(fn() => $this->pushCustomer($deposit, 'late_payment'));
        });
    }

    protected function assertPayable(TripDeposit $deposit): void
    {
        if ($deposit->status !== TripDeposit::AWAITING) {
            throw ValidationException::withMessages(['deposit' => translate('This deposit can no longer be paid.')]);
        }
        $status = TripRequest::whereKey($deposit->trip_request_id)->value('current_status');
        if (!in_array($status, [ACCEPTED, OUT_FOR_PICKUP])) {
            throw ValidationException::withMessages(['deposit' => translate('This trip is no longer active.')]);
        }
        // A MoMo payment that started in time may still finish; a new one may not.
        if ($deposit->due_at && $deposit->due_at->isPast()) {
            throw ValidationException::withMessages(['deposit' => translate('The time to pay the deposit has run out.')]);
        }
    }

    protected function markPaid(TripDeposit $deposit, ?string $method): TripDeposit
    {
        $deposit->status = TripDeposit::PAID;
        $deposit->payment_method = $method;
        $deposit->paid_at = now();
        $deposit->save();
        DB::afterCommit(function () use ($deposit) {
            $trip = TripRequest::with(['driver', 'customer'])->find($deposit->trip_request_id);
            if (!$trip) {
                return;
            }
            try {
                checkReverbConnection() && CustomerDepositPaidEvent::broadcast($trip);
            } catch (Throwable) {
            }
            // Rings on the driver's phone (data-only push), with a button.
            $locale = $trip->driver?->current_language_key;
            $this->push($trip->driver, $trip, 'deposit_paid',
                'Deposit received: go to the pickup',
                '{customer} paid the {amount} deposit. You can set off now.',
                ['customer' => trim((string)$trip->customer?->first_name) ?: translate('The customer'), 'amount' => getCurrencyFormat($deposit->amount)],
                ['button' => translate(key: 'Go to pickup', locale: $locale)]);
        });
        return $deposit;
    }

    // ---------------------------------------------------------------- cancel

    /**
     * Whether a customer cancel would be free now, and why. The apps show
     * this before the customer confirms.
     *
     * @return array{free: bool, reason: ?string, free_until: ?Carbon}
     */
    public function cancelTerms(TripDeposit $deposit, ?TripRequest $trip = null): array
    {
        if ($deposit->status === TripDeposit::AWAITING) {
            return ['free' => true, 'reason' => 'unpaid', 'free_until' => null];
        }
        if ($deposit->status !== TripDeposit::PAID) {
            return ['free' => true, 'reason' => null, 'free_until' => null];
        }
        $freeUntil = $deposit->paid_at?->copy()->addSeconds(DepositSettings::forTrip($trip ?? $deposit->trip_request_id)['free_cancel_seconds']);
        if ($freeUntil && $freeUntil->isFuture()) {
            return ['free' => true, 'reason' => 'free_window', 'free_until' => $freeUntil];
        }
        $trip ??= TripRequest::with(['time', 'coordinate'])->find($deposit->trip_request_id);
        if ($trip && $this->driverIsLate($deposit, $trip)) {
            return ['free' => true, 'reason' => 'driver_late', 'free_until' => null];
        }
        return ['free' => false, 'reason' => null, 'free_until' => null];
    }

    /** Past the promised arrival plus the allowance, and not at the pickup. */
    protected function driverIsLate(TripDeposit $deposit, TripRequest $trip): bool
    {
        $promised = $deposit->created_at->copy()
            ->addSeconds((int)round(((float)($trip->time?->driver_arrival_time ?? 0)) * 60))
            ->addMinutes(DepositSettings::all()['late_minutes']);
        if ($promised->isFuture()) {
            return false;
        }
        $pickup = $trip->coordinate?->pickup_coordinates;
        $location = $deposit->driver_id ? UserLastLocation::where('user_id', $deposit->driver_id)->first() : null;
        if (!$pickup || !$location) {
            return true;
        }
        return haversineDistance((float)$pickup->latitude, (float)$pickup->longitude, (float)$location->latitude, (float)$location->longitude) > self::ARRIVED_METERS;
    }

    /**
     * After a trip was cancelled. $by: customer, driver or admin.
     * $previousStatus is the trip's status before the cancel.
     */
    public function onTripCancelled(string $tripId, string $by, ?string $previousStatus, bool $forceFree = false): ?TripDeposit
    {
        return DB::transaction(function () use ($tripId, $by, $previousStatus, $forceFree) {
            $deposit = TripDeposit::where('trip_request_id', $tripId)->lockForUpdate()->first();
            if (!$deposit) {
                return null;
            }
            if ($deposit->status === TripDeposit::AWAITING) {
                $deposit->status = TripDeposit::EXPIRED;
                $deposit->settled_at = now();
                $deposit->note = 'Trip cancelled by the ' . $by . ' before the deposit was paid';
                $deposit->save();
                return $deposit;
            }
            if ($deposit->status !== TripDeposit::PAID) {
                return $deposit;
            }
            // Cancelled during the trip: the customer pays for the distance
            // driven, and the deposit counts toward it at payment.
            if ($previousStatus === ONGOING) {
                return $deposit;
            }
            if ($by !== 'customer') {
                return $this->refund($deposit, $by === 'driver' ? 'Driver cancelled after the deposit was paid' : 'Cancelled by E-Ride', 'refunded_driver_cancelled');
            }
            $terms = $forceFree ? ['free' => true, 'reason' => 'identity_mismatch'] : $this->cancelTerms($deposit);
            if ($terms['free']) {
                return $this->refund($deposit, match ($terms['reason']) {
                    'driver_late' => 'Customer cancelled: driver late',
                    'identity_mismatch' => 'Customer cancelled: driver or vehicle did not match',
                    default => 'Customer cancelled within the free window',
                }, 'refunded_free_cancel');
            }
            return $this->forfeit($deposit);
        });
    }

    protected function refund(TripDeposit $deposit, string $note, string $pushEvent): TripDeposit
    {
        $this->creditWallet($deposit->customer_id, $deposit->amount, $deposit->trip_request_id, 'trip_deposit_refund', 'Booking deposit refunded to customer wallet');
        $deposit->status = TripDeposit::REFUNDED;
        $deposit->refunded_amount = $deposit->amount;
        $deposit->settled_at = now();
        $deposit->note = $note;
        $deposit->save();
        DB::afterCommit(fn() => $this->pushCustomer($deposit, $pushEvent));
        return $deposit;
    }

    /** A late customer cancel: the driver and E-Ride share the deposit. */
    protected function forfeit(TripDeposit $deposit, string $note = 'Customer cancelled after the free window'): TripDeposit
    {
        $isMove = (bool)TripRequest::whereKey($deposit->trip_request_id)->value('is_move');
        $adminShare = round($deposit->amount * DepositSettings::forTrip($deposit->trip_request_id)['admin_share_percent'] / 100, 2);
        $driverShare = round($deposit->amount - $adminShare, 2);
        $adminId = $this->adminUserId();

        if ($driverShare > 0 && $deposit->driver_id) {
            $driverAccount = $this->lockedAccount($deposit->driver_id);
            $driverAccount->receivable_balance += $driverShare;
            $driverAccount->save();
            Transaction::create([
                'attribute' => 'deposit_compensation',
                'attribute_id' => $deposit->trip_request_id,
                'credit' => $driverShare,
                'balance' => $driverAccount->receivable_balance,
                'user_id' => $deposit->driver_id,
                'account' => 'receivable_balance',
                'trx_type' => 'Customer cancelled: deposit compensation added to driver receivable balance',
            ]);
        }
        $adminAccount = $this->lockedAccount($adminId);
        if ($driverShare > 0 && $deposit->driver_id) {
            $adminAccount->payable_balance += $driverShare;
            Transaction::create([
                'attribute' => 'deposit_compensation',
                'attribute_id' => $deposit->trip_request_id,
                'credit' => $driverShare,
                'balance' => $adminAccount->payable_balance,
                'user_id' => $adminId,
                'account' => 'payable_balance',
                'trx_type' => 'Deposit compensation owed to the driver added to admin payable balance',
            ]);
        } else {
            // No driver to pay: E-Ride keeps it all.
            $adminShare = $deposit->amount;
            $driverShare = 0;
        }
        if ($adminShare > 0) {
            $adminAccount->received_balance += $adminShare;
            Transaction::create([
                'attribute' => 'deposit_commission',
                'attribute_id' => $deposit->trip_request_id,
                'credit' => $adminShare,
                'balance' => $adminAccount->received_balance,
                'user_id' => $adminId,
                'account' => 'received_balance',
                'trx_type' => 'Forfeited booking deposit share added to admin received balance',
            ]);
        }
        $adminAccount->save();

        $deposit->status = TripDeposit::FORFEITED;
        $deposit->driver_share = $driverShare;
        $deposit->admin_share = $adminShare;
        $deposit->settled_at = now();
        $deposit->note = $note;
        $deposit->save();

        DB::afterCommit(function () use ($deposit, $driverShare, $isMove) {
            $trip = TripRequest::find($deposit->trip_request_id);
            $driver = $deposit->driver_id ? User::find($deposit->driver_id) : null;
            if ($trip && $driver && $driverShare > 0) {
                $this->push($driver, $trip, 'deposit_compensation',
                    'You earned {amount} for this cancellation',
                    'The customer cancelled after paying the deposit. {amount} was added to your wallet.',
                    ['amount' => getCurrencyFormat($driverShare)]);
            }
            $this->pushCustomer($deposit, $isMove ? 'forfeited_move' : 'forfeited');
        });
        return $deposit;
    }

    // ------------------------------------------------------------------ move

    /**
     * A move ended (completed, or cancelled once it started): the paid
     * deposit is E-Ride's booking fee. The customer pays the driver the price
     * they agreed, outside the app. Safe to call more than once.
     */
    public function keepAsBookingFee(TripRequest $trip): ?TripDeposit
    {
        return DB::transaction(function () use ($trip) {
            $deposit = TripDeposit::where('trip_request_id', $trip->id)->lockForUpdate()->first();
            if (!$deposit || $deposit->status !== TripDeposit::PAID) {
                return $deposit;
            }
            $adminId = $this->adminUserId();
            $adminAccount = $this->lockedAccount($adminId);
            $adminAccount->received_balance += $deposit->amount;
            $adminAccount->save();
            Transaction::create([
                'attribute' => 'move_booking_fee',
                'attribute_id' => $trip->id,
                'credit' => $deposit->amount,
                'balance' => $adminAccount->received_balance,
                'user_id' => $adminId,
                'account' => 'received_balance',
                'trx_type' => 'E-Ride Move booking fee added to admin received balance',
            ]);
            $deposit->status = TripDeposit::APPLIED;
            $deposit->applied_amount = $deposit->amount;
            $deposit->admin_share = $deposit->amount;
            $deposit->settled_at = now();
            $deposit->note = 'Kept as the E-Ride Move booking fee';
            $deposit->save();
            return $deposit;
        });
    }

    /**
     * "We couldn't agree": customer and driver met at the pickup but did not
     * agree on the price. Treated as a customer cancel after the driver
     * arrived: the trip is cancelled, the driver released and the deposit
     * forfeited with the move share. $by: customer or driver.
     */
    public function noAgreement(TripRequest $trip, string $by): void
    {
        if (!$trip->is_move) {
            throw ValidationException::withMessages(['trip' => translate('This is not a move.')]);
        }
        $released = DB::transaction(function () use ($trip, $by) {
            $trip = TripRequest::whereKey($trip->id)->lockForUpdate()->first();
            if (!$trip || !in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP])) {
                throw ValidationException::withMessages(['trip' => translate('This move is no longer waiting at the pickup.')]);
            }
            $deposit = TripDeposit::where('trip_request_id', $trip->id)->lockForUpdate()->first();
            if (!$deposit || $deposit->status !== TripDeposit::PAID) {
                throw ValidationException::withMessages(['trip' => translate('The booking fee has not been paid. You can simply cancel.')]);
            }
            if (!$this->driverAtPickup($trip)) {
                throw ValidationException::withMessages(['trip' => translate('This is available once the driver has arrived at the pickup.')]);
            }
            $driverId = $trip->driver_id;
            $trip->current_status = CANCELLED;
            $trip->trip_cancellation_reason = 'No agreement on the price';
            $trip->driver_id = null;
            $trip->save();
            $trip->tripStatus()->update([CANCELLED => now()]);
            $trip->fee()->update(['cancelled_by' => $by]);
            $trip->tripNavigation()->delete();
            if ($driverId) {
                app(DriverDetailServiceInterface::class)->updateAvailability(data: [
                    'user_id' => $driverId,
                    'trip_type' => RIDE_REQUEST,
                ]);
            }
            $this->forfeit($deposit, 'No agreement on the price (' . $by . ')');
            return $driverId;
        });

        $trip = TripRequest::with('customer')->find($trip->id);
        $driver = $released ? User::find($released) : null;
        try {
            if (checkReverbConnection()) {
                if ($by === 'customer' && $driver) {
                    CustomerTripCancelledEvent::broadcast($driver, $trip);
                } elseif ($by === 'driver') {
                    \App\Events\DriverTripCancelledEvent::broadcast($trip);
                }
            }
        } catch (Throwable) {
        }
        if ($by === 'customer' && $driver) {
            $this->push($driver, $trip, 'customer_canceled_trip',
                'No agreement: move cancelled',
                'The customer and you did not agree on the price, so the move was cancelled. You are free for new requests.');
        } elseif ($by === 'driver') {
            $this->push($trip->customer, $trip, 'move_no_agreement',
                'No agreement: move cancelled',
                'You and the driver did not agree on the price, so the move was cancelled. You can book another truck.');
        }
    }

    /** The driver's last location is at the pickup. */
    protected function driverAtPickup(TripRequest $trip): bool
    {
        $trip->loadMissing('coordinate');
        $pickup = $trip->coordinate?->pickup_coordinates;
        $location = $trip->driver_id ? UserLastLocation::where('user_id', $trip->driver_id)->first() : null;
        if (!$pickup || !$location) {
            return false;
        }
        return haversineDistance((float)$pickup->latitude, (float)$pickup->longitude, (float)$location->latitude, (float)$location->longitude) <= self::NO_AGREEMENT_METERS;
    }

    // --------------------------------------------------------------- payment

    /**
     * Counts the paid deposit toward the trip, as the trip is paid. Call it in
     * the payment's DB transaction: before walletTransaction for 'wallet', after
     * cashTransaction for 'cash', and before digitalPaymentTransaction for a
     * gateway. The trip's paid_fare must already include tips.
     *
     * @return float the amount of the deposit counted toward the fare
     */
    public function applyOnPayment(TripRequest $trip, string $method): float
    {
        $deposit = TripDeposit::where('trip_request_id', $trip->id)->lockForUpdate()->first();
        if (!$deposit || $deposit->status !== TripDeposit::PAID) {
            return 0;
        }
        $applied = round(min($deposit->amount, max(0, (float)$trip->paid_fare)), 2);
        $surplus = round($deposit->amount - $applied, 2);

        if ($method === 'wallet') {
            // Back into the wallet, then the normal wallet payment takes the
            // whole fare: the customer is charged the rest, and any surplus
            // simply stays in the wallet.
            $this->creditWallet($deposit->customer_id, $deposit->amount, $trip->id, 'trip_deposit_applied', 'Booking deposit counted toward the trip (credited for the wallet payment)');
        } else {
            if ($method === 'cash' && $applied > 0 && $trip->driver_id) {
                // The driver collected the fare minus the deposit in cash;
                // E-Ride holds the deposit and owes it to the driver.
                $driverAccount = $this->lockedAccount($trip->driver_id);
                $driverAccount->received_balance -= $applied;
                $driverAccount->receivable_balance += $applied;
                $driverAccount->save();
                Transaction::create([
                    'attribute' => 'trip_deposit_applied',
                    'attribute_id' => $trip->id,
                    'debit' => $applied,
                    'balance' => $driverAccount->received_balance,
                    'user_id' => $trip->driver_id,
                    'account' => 'received_balance',
                    'trx_type' => 'Booking deposit paid in the app: not collected in cash',
                ]);
                Transaction::create([
                    'attribute' => 'trip_deposit_applied',
                    'attribute_id' => $trip->id,
                    'credit' => $applied,
                    'balance' => $driverAccount->receivable_balance,
                    'user_id' => $trip->driver_id,
                    'account' => 'receivable_balance',
                    'trx_type' => 'Booking deposit paid in the app added to driver receivable balance',
                ]);
                $adminId = $this->adminUserId();
                $adminAccount = $this->lockedAccount($adminId);
                $adminAccount->payable_balance += $applied;
                $adminAccount->save();
                Transaction::create([
                    'attribute' => 'trip_deposit_applied',
                    'attribute_id' => $trip->id,
                    'credit' => $applied,
                    'balance' => $adminAccount->payable_balance,
                    'user_id' => $adminId,
                    'account' => 'payable_balance',
                    'trx_type' => 'Booking deposit owed to the driver added to admin payable balance',
                ]);
            }
            if ($surplus > 0) {
                $this->creditWallet($deposit->customer_id, $surplus, $trip->id, 'trip_deposit_refund', 'Unused part of the booking deposit refunded to customer wallet');
            }
        }

        $deposit->status = TripDeposit::APPLIED;
        $deposit->applied_amount = $applied;
        $deposit->refunded_amount = $surplus;
        $deposit->settled_at = now();
        $deposit->note = 'Counted toward the fare (' . $method . ')';
        $deposit->save();
        return $applied;
    }

    // ------------------------------------------------------------------ tick

    /** Expire unpaid deposits whose time ran out. Run by trip-deposits:tick. */
    public function tick(): array
    {
        $expired = 0;
        $waiting = 0;
        $due = TripDeposit::where('status', TripDeposit::AWAITING)->where('due_at', '<=', now())->orderBy('due_at')->limit(100)->get();
        foreach ($due as $deposit) {
            try {
                if ($this->momoStillRunning($deposit)) {
                    $waiting++;
                    continue;
                }
                $this->expire($deposit) && $expired++;
            } catch (Throwable $e) {
                Log::warning('Booking deposit expiry failed', ['deposit' => $deposit->id, 'error' => $e->getMessage()]);
            }
        }
        return ['expired' => $expired, 'waiting_for_momo' => $waiting];
    }

    /** True while a MoMo payment started in time may still land (it is checked first). */
    protected function momoStillRunning(TripDeposit $deposit): bool
    {
        $requestIds = PaymentRequest::where('attribute', self::ATTRIBUTE)->where('attribute_id', $deposit->id)->pluck('id');
        if ($requestIds->isEmpty()) {
            return false;
        }
        $open = FapshiTransaction::whereIn('payment_request_id', $requestIds)
            ->whereIn('status', FapshiTransaction::OPEN)->whereNotNull('trans_id')->get();
        foreach ($open as $tx) {
            app(FapshiPayments::class)->sync($tx, 10);
        }
        if ($deposit->fresh()->status !== TripDeposit::AWAITING) {
            return true; // it just got paid
        }
        $stillOpen = FapshiTransaction::whereIn('payment_request_id', $requestIds)
            ->whereIn('status', FapshiTransaction::OPEN)->whereNotNull('trans_id')->exists();
        return $stillOpen && $deposit->due_at->copy()->addSeconds(self::MOMO_GRACE_SECONDS)->isFuture();
    }

    /** Not paid in time: the trip is cancelled and the driver released. */
    public function expire(TripDeposit $deposit): bool
    {
        $released = DB::transaction(function () use ($deposit) {
            $deposit = TripDeposit::whereKey($deposit->id)->lockForUpdate()->first();
            if (!$deposit || $deposit->status !== TripDeposit::AWAITING) {
                return null;
            }
            $trip = TripRequest::whereKey($deposit->trip_request_id)->lockForUpdate()->first();
            $deposit->status = TripDeposit::EXPIRED;
            $deposit->settled_at = now();
            $deposit->note = 'Not paid in time';
            $deposit->save();
            if (!$trip || !in_array($trip->current_status, [ACCEPTED, OUT_FOR_PICKUP])) {
                return null;
            }
            $driverId = $trip->driver_id;
            $trip->current_status = CANCELLED;
            $trip->trip_cancellation_reason = 'Deposit not paid in time';
            $trip->driver_id = null;
            $trip->save();
            $trip->tripStatus()->update([CANCELLED => now()]);
            $trip->fee()->update(['cancelled_by' => 'customer']);
            $trip->tripNavigation()->delete();
            if ($driverId) {
                app(DriverDetailServiceInterface::class)->updateAvailability(data: [
                    'user_id' => $driverId,
                    'trip_type' => $trip->type == PARCEL ? PARCEL : ($trip->ride_request_type == SCHEDULED ? SCHEDULED : RIDE_REQUEST),
                ]);
            }
            return [$trip->id, $driverId];
        });
        if (!$released) {
            return false;
        }
        [$tripId, $driverId] = $released;
        $trip = TripRequest::with('customer')->find($tripId);
        $driver = $driverId ? User::find($driverId) : null;
        if ($trip && $driver) {
            try {
                checkReverbConnection() && CustomerTripCancelledEvent::broadcast($driver, $trip);
            } catch (Throwable) {
            }
            // The action the driver app already handles for a customer cancel.
            $this->push($driver, $trip, 'customer_canceled_trip',
                'The customer did not confirm',
                'The deposit was not paid in time, so the trip was cancelled. You are free for new requests.');
        }
        if ($trip) {
            try {
                checkReverbConnection() && TripDepositExpiredEvent::broadcast($trip);
            } catch (Throwable) {
            }
            $this->push($trip->customer, $trip, 'deposit_expired',
                'Your trip was cancelled',
                'The deposit was not paid in time, so your driver was released. You can book again.');
        }
        return true;
    }

    // ----------------------------------------------------------------- views

    /** The deposit as both apps see it, inside the trip. */
    public function present(?TripDeposit $deposit, ?TripRequest $trip = null): ?array
    {
        if (!$deposit) {
            return null;
        }
        $terms = $this->cancelTerms($deposit, $trip);
        return [
            'amount' => round($deposit->amount, 2),
            'status' => $deposit->status,
            'due_at' => $deposit->due_at?->toIso8601String(),
            'paid_at' => $deposit->paid_at?->toIso8601String(),
            'payment_method' => $deposit->payment_method,
            'applied_amount' => round($deposit->applied_amount, 2),
            'refunded_amount' => round($deposit->refunded_amount, 2),
            'driver_share' => round($deposit->driver_share, 2),
            'cancel_is_free' => $terms['free'],
            'cancel_free_reason' => $terms['reason'],
            'free_cancel_until' => $terms['free_until']?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    // --------------------------------------------------------------- helpers

    public function isStoreOrderTrip(TripRequest $trip): bool
    {
        return StoreOrder::where('trip_request_id', $trip->id)->exists();
    }

    protected function adminUserId(): string
    {
        return User::where('user_type', ADMIN_USER_TYPES[0])->value('id');
    }

    protected function lockedAccount(string $userId): UserAccount
    {
        $account = UserAccount::where('user_id', $userId)->lockForUpdate()->first();
        if (!$account) {
            $account = UserAccount::create(['user_id' => $userId]);
            $account = UserAccount::whereKey($account->id)->lockForUpdate()->first();
        }
        return $account;
    }

    protected function creditWallet(string $userId, float $amount, string $tripId, string $attribute, string $label, ?string $reference = null): void
    {
        $account = $this->lockedAccount($userId);
        $account->wallet_balance += $amount;
        $account->save();
        Transaction::create([
            'attribute' => $attribute,
            'attribute_id' => $tripId,
            'credit' => $amount,
            'balance' => $account->wallet_balance,
            'user_id' => $userId,
            'account' => 'wallet_balance',
            'trx_ref_id' => $reference,
            'trx_type' => $label,
        ]);
    }

    protected function pushCustomer(TripDeposit $deposit, string $event): void
    {
        $trip = TripRequest::with('customer')->find($deposit->trip_request_id);
        if (!$trip) {
            return;
        }
        $amount = ['amount' => getCurrencyFormat($event === 'forfeited' ? $deposit->amount : ($deposit->refunded_amount ?: $deposit->amount))];
        match ($event) {
            'refunded_driver_cancelled' => $this->push($trip->customer, $trip, 'deposit_refunded',
                'Your driver cancelled',
                'Your {amount} deposit is back in your E-Ride wallet. You can book again, or report the driver from the trip details.', $amount),
            'refunded_free_cancel' => $this->push($trip->customer, $trip, 'deposit_refunded',
                'Deposit refunded', 'Your {amount} deposit is back in your E-Ride wallet.', $amount),
            'forfeited' => $this->push($trip->customer, $trip, 'deposit_forfeited',
                'Trip cancelled', 'You cancelled after confirming, so the {amount} deposit went to your driver.', $amount),
            'forfeited_move' => $this->push($trip->customer, $trip, 'deposit_forfeited',
                'Move cancelled', 'The move was cancelled after the driver came, so the {amount} booking fee is not refunded.', $amount),
            'late_payment' => $this->push($trip->customer, $trip, 'deposit_refunded',
                'Payment added to your wallet', 'Your deposit payment arrived after the trip closed, so it was added to your E-Ride wallet.'),
            default => null,
        };
    }

    protected function push(?User $user, TripRequest $trip, string $action, string $title, string $body, array $replace = [], array $extra = []): void
    {
        if (!$user?->fcm_token) {
            return;
        }
        try {
            $locale = $user->current_language_key;
            sendDeviceNotification(
                fcm_token: $user->fcm_token,
                title: translate(key: $title, replace: $replace, locale: $locale),
                description: translate(key: $body, replace: $replace, locale: $locale),
                status: 1,
                ride_request_id: $trip->id,
                type: $trip->type,
                notification_type: $trip->type == PARCEL ? 'parcel' : 'trip',
                action: $action,
                user_id: $user->id,
                notificationData: $extra ? ['extra' => $extra] : [],
            );
        } catch (Throwable $e) {
            Log::warning('Booking deposit push failed', ['trip' => $trip->id, 'action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
