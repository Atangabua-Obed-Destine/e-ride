<?php

namespace Modules\PlaceManagement\Service;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\Gateways\Entities\FapshiTransaction;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Services\FapshiPayments;
use Modules\Gateways\Traits\Payment;
use Modules\PlaceManagement\Entities\Listing;
use Modules\PlaceManagement\Entities\ListingPayout;
use Modules\PlaceManagement\Entities\ListingReport;
use Modules\PlaceManagement\Entities\ListingReservation;
use Modules\PlaceManagement\Entities\ListingVisit;
use Modules\PlaceManagement\Lib\ListingSettings;
use Modules\TransactionManagement\Entities\Transaction;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAccount;
use Throwable;

/**
 * Reservation money (Homes & spaces, phase 2). Every change happens here, in a
 * transaction with the reservation row locked, so the payment webhook, the
 * scheduler and taps in the app can race safely (as ride deposits do).
 *
 * Money:
 *  - Paying (wallet or MoMo) puts the amount with E-Ride; nothing is booked to
 *    anyone yet. "Held now" = reservations in HOLDING.
 *  - Refund: back to the seeker's E-Ride wallet. The seeker may then ask for it
 *    on mobile money: it leaves the wallet and becomes a payout line.
 *  - Release: E-Ride's fee goes to the admin's received balance; the owner's
 *    share to the admin's payable balance (owed) and a payout line, cleared
 *    when an admin marks it paid with the reference.
 */
class ListingReservationService
{
    use Payment;

    public const HOOK = 'listingReservationPaid';
    public const ATTRIBUTE = 'listing_reservation';
    /** A MoMo payment in progress when time runs out gets this much longer. */
    public const MOMO_GRACE_SECONDS = 120;

    // ------------------------------------------------------------- start and pay

    /** The seeker reserves after a visit the owner accepted. */
    public function start(ListingVisit $visit, User $seeker, string $moveIn): ListingReservation
    {
        return DB::transaction(function () use ($visit, $seeker, $moveIn) {
            $listing = Listing::whereKey($visit->listing_id)->lockForUpdate()->firstOrFail();
            $settings = ListingSettings::reservations();
            if ($visit->seeker_id !== $seeker->id) {
                $this->fail(translate('This visit is not yours.'));
            }
            if (!in_array($visit->status, [ListingVisit::ACCEPTED, ListingVisit::DONE], true)) {
                $this->fail(translate('You can reserve once the owner has accepted your visit.'));
            }
            if (!$listing->takesReservations()) {
                $this->fail(translate('This place cannot be reserved through E-Ride now.'));
            }
            if (ListingReservation::where('listing_id', $listing->id)->where('seeker_id', $seeker->id)->whereIn('status', ListingReservation::ACTIVE)->exists()) {
                $this->fail(translate('You already have a reservation for this place.'));
            }
            $date = Carbon::parse($moveIn)->startOfDay();
            if ($date->lt(today()) || $date->gt(today()->addDays($settings['move_in_days']))) {
                $this->fail(translate(key: 'Pick a move-in date in the next {days} days.', replace: ['days' => $settings['move_in_days']]));
            }
            return ListingReservation::create([
                'listing_id' => $listing->id, 'visit_id' => $visit->id, 'seeker_id' => $seeker->id, 'owner_id' => $listing->owner_id,
                'amount' => $listing->reservation_amount, 'status' => ListingReservation::AWAITING,
                'move_in_date' => $date->toDateString(), 'pay_due_at' => now()->addMinutes($settings['pay_minutes']),
            ]);
        });
    }

    public function payWithWallet(ListingReservation $reservation, User $seeker): ListingReservation
    {
        return DB::transaction(function () use ($reservation, $seeker) {
            $reservation = $this->locked($reservation);
            if ($reservation->seeker_id !== $seeker->id) {
                $this->fail(translate('This reservation is not yours.'));
            }
            if ($reservation->status !== ListingReservation::AWAITING) {
                return $reservation;
            }
            $this->assertPayable($reservation);
            $account = $this->lockedAccount($seeker->id);
            if ($account->wallet_balance + 0.00001 < $reservation->amount) {
                $this->fail(translate('Your wallet balance is too low for this reservation.'));
            }
            $account->wallet_balance -= $reservation->amount;
            $account->save();
            Transaction::create([
                'attribute' => 'listing_reservation_payment', 'attribute_id' => $reservation->id, 'debit' => $reservation->amount,
                'balance' => $account->wallet_balance, 'user_id' => $seeker->id, 'account' => 'wallet_balance',
                'trx_type' => 'Homes & spaces reservation paid from customer wallet',
            ]);
            return $this->markHeld($reservation, 'wallet');
        });
    }

    /** A signed link the app opens in its payment webview (no auth header there). */
    public function payUrl(ListingReservation $reservation, string $gateway): string
    {
        return url(URL::temporarySignedRoute('listing-reservation.pay', now()->addMinutes(30), [
            'reservation' => $reservation->id, 'payment_method' => $gateway,
        ], absolute: false));
    }

    /** The gateway's checkout link. */
    public function paymentLink(ListingReservation $reservation, string $gateway): string
    {
        $this->assertPayable($reservation);
        $seeker = User::findOrFail($reservation->seeker_id);
        $paymentInfo = new PaymentInfo(
            hook: self::HOOK,
            currencyCode: businessConfig('currency_code')?->value ?? 'XAF',
            paymentMethod: $gateway,
            paymentPlatform: 'mono',
            payerId: $seeker->id,
            receiverId: '100',
            additionalData: [
                'business_name' => businessConfig('business_name')?->value,
                'business_logo' => dynamicStorage('storage/app/public/business') . '/' . businessConfig('header_logo')?->value,
            ],
            paymentAmount: $reservation->amount,
            externalRedirectLink: null,
            attribute: self::ATTRIBUTE,
            attributeId: $reservation->id,
        );
        $payer = new Payer(name: $seeker->first_name, email: $seeker->email, phone: $seeker->phone, address: '');
        $link = $this->generate_link($payer, $paymentInfo, new Receiver('receiver_name', 'example.png'));
        if (!$link) {
            $this->fail(translate('This payment method is not available.'));
        }
        return $link;
    }

    /** The payment hook (listingReservationPaid), called by the gateways. */
    public function onGatewayPaid(PaymentRequest $paymentRequest): void
    {
        if (!(int)$paymentRequest->is_paid) {
            return;
        }
        DB::transaction(function () use ($paymentRequest) {
            $reservation = ListingReservation::whereKey($paymentRequest->attribute_id)->lockForUpdate()->first();
            if (!$reservation || $reservation->payment_request_id === $paymentRequest->id
                || Transaction::where('attribute', 'listing_reservation_late_payment')->where('trx_ref_id', $paymentRequest->id)->exists()) {
                return;
            }
            if ($reservation->status === ListingReservation::AWAITING) {
                $reservation->payment_request_id = $paymentRequest->id;
                $this->markHeld($reservation, $paymentRequest->payment_method);
                return;
            }
            // Paid after it lapsed, or paid twice: the money goes to the wallet, never lost.
            $this->creditWallet($reservation->seeker_id, (float)$paymentRequest->payment_amount, $reservation->id,
                'listing_reservation_late_payment', 'Late or duplicate reservation payment added to customer wallet', $paymentRequest->id);
            DB::afterCommit(fn() => $this->push($reservation->seeker, $reservation, 'listing_reservation_refunded',
                'Payment added to your wallet', 'Your payment for {title} arrived after the reservation closed, so {amount} is in your E-Ride wallet.',
                ['amount' => getCurrencyFormat((float)$paymentRequest->payment_amount)]));
        });
    }

    protected function assertPayable(ListingReservation $reservation): void
    {
        if ($reservation->status !== ListingReservation::AWAITING) {
            $this->fail(translate('This reservation can no longer be paid.'));
        }
        if ($reservation->pay_due_at && $reservation->pay_due_at->isPast()) {
            $this->fail(translate('The time to pay has run out. Start the reservation again.'));
        }
        if (Listing::whereKey($reservation->listing_id)->value('status') !== Listing::LIVE) {
            $this->fail(translate('This place is no longer available.'));
        }
    }

    protected function markHeld(ListingReservation $reservation, ?string $method): ListingReservation
    {
        $settings = ListingSettings::reservations();
        $reservation->update([
            'status' => ListingReservation::HELD, 'payment_method' => $method, 'paid_at' => now(),
            'accept_due_at' => now()->addHours($settings['accept_hours']),
        ]);
        DB::afterCommit(fn() => $this->push($reservation->owner, $reservation, 'listing_reservation_new', 'A seeker reserved your place',
            '{name} reserved {title}. E-Ride holds {amount} for you. Accept within {hours} hours in My listings.', [
                'name' => $reservation->seeker?->first_name, 'amount' => getCurrencyFormat($reservation->amount), 'hours' => $settings['accept_hours'],
            ]));
        return $reservation;
    }

    // ------------------------------------------------------------- the owner answers

    public function accept(ListingReservation $reservation, User $owner, ?string $moveIn = null): ListingReservation
    {
        return DB::transaction(function () use ($reservation, $owner, $moveIn) {
            $reservation = $this->locked($reservation);
            $this->assertOwner($reservation, $owner);
            if ($reservation->status !== ListingReservation::HELD) {
                $this->fail(translate('This reservation is not waiting for you.'));
            }
            $settings = ListingSettings::reservations();
            $date = $moveIn ? Carbon::parse($moveIn)->startOfDay() : $reservation->move_in_date->copy();
            if ($date->lt(today()) || $date->gt(today()->addDays($settings['move_in_days']))) {
                $this->fail(translate(key: 'Pick a move-in date in the next {days} days.', replace: ['days' => $settings['move_in_days']]));
            }
            $reservation->update([
                'status' => ListingReservation::RESERVED, 'accepted_at' => now(), 'move_in_date' => $date->toDateString(),
                'release_due_at' => $date->copy()->addDays($settings['release_days'])->setTime(20, 0),
            ]);
            // The visit becomes "taking it", so marking the place rented keeps this seeker.
            ListingVisit::whereKey($reservation->visit_id)->update(['taking' => true]);
            $listing = Listing::whereKey($reservation->listing_id)->lockForUpdate()->first();
            $left = max(0, $listing->units_available - 1);
            DB::afterCommit(function () use ($reservation, $listing, $left) {
                $this->push($reservation->seeker, $reservation, 'listing_reservation_accepted', 'Your reservation is accepted',
                    '{title} is reserved for you. Move in on {date}. E-Ride keeps {amount} until you confirm the move-in.', [
                        'date' => $reservation->move_in_date->format('d M'), 'amount' => getCurrencyFormat($reservation->amount),
                    ]);
                if ($left === 0) {
                    app(ListingService::class)->markRented($listing->fresh(), $reservation->visit_id);
                } else {
                    $listing->update(['units_available' => $left]);
                }
            });
            return $reservation;
        });
    }

    public function decline(ListingReservation $reservation, User $owner): ListingReservation
    {
        return DB::transaction(function () use ($reservation, $owner) {
            $reservation = $this->locked($reservation);
            $this->assertOwner($reservation, $owner);
            if ($reservation->status !== ListingReservation::HELD) {
                $this->fail(translate('This reservation is not waiting for you.'));
            }
            return $this->refund($reservation, $reservation->amount, 'declined',
                ['The owner declined', 'The owner declined your reservation of {title}. Your {amount} is back in your E-Ride wallet.']);
        });
    }

    // ------------------------------------------------------------- the seeker

    /** Before the owner accepts: free. After: a problem an admin decides. Not paid yet: just closed. */
    public function cancel(ListingReservation $reservation, User $seeker, ?string $note = null): ListingReservation
    {
        return DB::transaction(function () use ($reservation, $seeker, $note) {
            $reservation = $this->locked($reservation);
            $this->assertSeeker($reservation, $seeker);
            return match ($reservation->status) {
                ListingReservation::AWAITING => tap($reservation)->update(['status' => ListingReservation::LAPSED, 'settled_at' => now(), 'closed_reason' => 'cancelled']),
                ListingReservation::HELD => $this->refund($reservation, $reservation->amount, 'cancelled',
                    ['Reservation cancelled', 'You cancelled your reservation of {title}. Your {amount} is back in your E-Ride wallet.'], notifyOwner: true),
                ListingReservation::RESERVED => $this->openProblem($reservation, 'seeker', 'cancel', $note),
                default => $this->fail(translate('This reservation can no longer be cancelled.')),
            };
        });
    }

    public function movedIn(ListingReservation $reservation, User $seeker): ListingReservation
    {
        return DB::transaction(function () use ($reservation, $seeker) {
            $reservation = $this->locked($reservation);
            $this->assertSeeker($reservation, $seeker);
            if ($reservation->status !== ListingReservation::RESERVED) {
                $this->fail(translate('This reservation is not waiting for a move-in.'));
            }
            return $this->release($reservation, $reservation->amount, 'moved_in');
        });
    }

    /** "Report a problem" (seeker) or "Seeker didn't come" (owner): the money freezes for an admin. */
    public function problem(ListingReservation $reservation, User $user, string $reason, ?string $note): ListingReservation
    {
        return DB::transaction(function () use ($reservation, $user, $reason, $note) {
            $reservation = $this->locked($reservation);
            if ($user->id === $reservation->seeker_id) {
                if (!in_array($reason, ListingReservation::SEEKER_PROBLEMS, true) || $reservation->status !== ListingReservation::RESERVED) {
                    $this->fail(translate('You can report a problem once the owner has accepted, and until the money is released.'));
                }
                return $this->openProblem($reservation, 'seeker', $reason, $note);
            }
            if ($user->id === $reservation->owner_id) {
                if (!in_array($reason, ListingReservation::OWNER_PROBLEMS, true) || $reservation->status !== ListingReservation::RESERVED
                    || $reservation->move_in_date->isFuture()) {
                    $this->fail(translate('You can report this from the move-in date.'));
                }
                return $this->openProblem($reservation, 'owner', $reason, $note);
            }
            $this->fail(translate('This reservation is not yours.'));
        });
    }

    /** A refund in the wallet, sent on mobile money instead: it leaves the wallet for the payout queue. */
    public function refundToMobileMoney(ListingReservation $reservation, User $seeker, string $phone): ListingPayout
    {
        return DB::transaction(function () use ($reservation, $seeker, $phone) {
            $reservation = $this->locked($reservation);
            $this->assertSeeker($reservation, $seeker);
            $amount = $reservation->refunded_amount;
            if ($amount <= 0 || $reservation->payouts()->where('kind', ListingPayout::REFUND)->exists()) {
                $this->fail(translate('There is no refund to send on mobile money.'));
            }
            $account = $this->lockedAccount($seeker->id);
            if ($account->wallet_balance + 0.00001 < $amount) {
                $this->fail(translate('The refund was already used from your wallet.'));
            }
            $account->wallet_balance -= $amount;
            $account->save();
            Transaction::create([
                'attribute' => 'listing_refund_payout', 'attribute_id' => $reservation->id, 'debit' => $amount,
                'balance' => $account->wallet_balance, 'user_id' => $seeker->id, 'account' => 'wallet_balance',
                'trx_type' => 'Reservation refund moved from wallet to a mobile money payout',
            ]);
            $this->owe($amount, $reservation->id, 'listing_refund_payout', 'Reservation refund owed to the customer on mobile money');
            return ListingPayout::create([
                'user_id' => $seeker->id, 'reservation_id' => $reservation->id, 'kind' => ListingPayout::REFUND, 'amount' => $amount,
                'phone' => $phone, 'name' => trim($seeker->first_name . ' ' . $seeker->last_name), 'status' => ListingPayout::PENDING,
            ]);
        });
    }

    // ------------------------------------------------------------- admin

    /** Refund, release, or split a reservation that is held. $seekerAmount: the seeker's part of a split. */
    public function decide(ListingReservation $reservation, User $admin, string $decision, string $note, ?float $seekerAmount = null, bool $strike = false): ListingReservation
    {
        $reservation = DB::transaction(function () use ($reservation, $admin, $decision, $note, $seekerAmount) {
            $reservation = $this->locked($reservation);
            if (!$reservation->isHolding()) {
                $this->fail(translate('This reservation is already settled.'));
            }
            $reservation->update(['decided_by' => $admin->id, 'decision_note' => $note]);
            $vars = ['note' => $note];
            return match ($decision) {
                'refund' => $this->refund($reservation, $reservation->amount, 'admin',
                    ['E-Ride refunded your reservation', 'E-Ride refunded {amount} for {title} to your wallet: {note}'], $vars, notifyOwner: true),
                'release' => $this->release($reservation, $reservation->amount, 'admin', $note),
                'split' => $this->split($reservation, (float)$seekerAmount, $note),
                default => $this->fail(translate('Unknown decision.')),
            };
        });
        if ($strike && $reservation->listing) {
            // A strike on the owner, counted with listing reports.
            $report = $reservation->listing->reports()->create([
                'reporter_id' => $reservation->seeker_id, 'visit_id' => $reservation->visit_id,
                'reason' => in_array($reservation->problem_reason, ['given_away', 'not_as_described'], true) ? ($reservation->problem_reason === 'given_away' ? 'taken' : 'not_as_described') : 'other',
                'note' => translate('Reservation problem decided by E-Ride: ') . $note, 'status' => ListingReport::OPEN,
            ]);
            app(ListingService::class)->decideReport($report, ListingReport::UPHELD, $admin);
        }
        return $reservation;
    }

    public function markPayoutPaid(ListingPayout $payout, User $admin, string $reference): ListingPayout
    {
        return DB::transaction(function () use ($payout, $admin, $reference) {
            $payout = ListingPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($payout->status === ListingPayout::PAID) {
                return $payout;
            }
            $payout->update(['status' => ListingPayout::PAID, 'reference' => $reference, 'paid_by' => $admin->id, 'paid_at' => now()]);
            $adminId = $this->adminUserId();
            $adminAccount = $this->lockedAccount($adminId);
            $adminAccount->payable_balance -= $payout->amount;
            $adminAccount->save();
            Transaction::create([
                'attribute' => 'listing_payout', 'attribute_id' => $payout->id, 'debit' => $payout->amount,
                'balance' => $adminAccount->payable_balance, 'user_id' => $adminId, 'account' => 'payable_balance',
                'trx_ref_id' => $reference, 'trx_type' => 'Homes & spaces payout sent on mobile money',
            ]);
            $reservation = $payout->reservation;
            DB::afterCommit(fn() => $reservation && $this->push($payout->user, $reservation, 'listing_payout_paid', 'E-Ride sent your money',
                'E-Ride sent you {amount} for {title} on {phone}, reference {reference}.', [
                    'amount' => getCurrencyFormat($payout->amount), 'phone' => $payout->phone, 'reference' => $reference,
                ]));
            return $payout;
        });
    }

    /**
     * A listing removed by E-Ride: every reservation still holding money is refunded.
     * Rented by the owner ($onlyUnaccepted): only those the owner never accepted.
     */
    public function refundAllFor(Listing $listing, string $why, bool $onlyUnaccepted = false): void
    {
        $statuses = $onlyUnaccepted ? [ListingReservation::AWAITING, ListingReservation::HELD] : ListingReservation::ACTIVE;
        ListingReservation::where('listing_id', $listing->id)->whereIn('status', $statuses)->pluck('id')
            ->each(function (string $id) use ($why) {
                DB::transaction(function () use ($id, $why) {
                    $reservation = ListingReservation::whereKey($id)->lockForUpdate()->first();
                    if ($reservation->status === ListingReservation::AWAITING) {
                        $reservation->update(['status' => ListingReservation::LAPSED, 'settled_at' => now(), 'closed_reason' => $why]);
                    } elseif ($reservation->isHolding()) {
                        $this->refund($reservation, $reservation->amount, $why,
                            ['Reservation refunded', '{title} is no longer listed, so your {amount} is back in your E-Ride wallet.']);
                    }
                });
            });
    }

    // ------------------------------------------------------------- the clock

    /** Every minute: unpaid ones lapse, silent owners refund, move-in pushes, automatic release. */
    public function tick(?Carbon $now = null): array
    {
        $now ??= now();
        $counts = ['lapsed' => 0, 'no_answer' => 0, 'move_in' => 0, 'released' => 0];
        foreach (ListingReservation::where('status', ListingReservation::AWAITING)->where('pay_due_at', '<=', $now)->limit(100)->get() as $r) {
            if ($this->momoStillRunning($r, $now)) {
                continue;
            }
            $this->safely(function () use ($r, &$counts) {
                DB::transaction(function () use ($r, &$counts) {
                    $r = $this->locked($r);
                    if ($r->status === ListingReservation::AWAITING) {
                        $r->update(['status' => ListingReservation::LAPSED, 'settled_at' => now(), 'closed_reason' => 'not_paid']);
                        $counts['lapsed']++;
                    }
                });
            }, $r);
        }
        foreach (ListingReservation::where('status', ListingReservation::HELD)->where('accept_due_at', '<=', $now)->limit(100)->get() as $r) {
            $this->safely(function () use ($r, &$counts) {
                DB::transaction(function () use ($r, &$counts) {
                    $r = $this->locked($r);
                    if ($r->status === ListingReservation::HELD) {
                        $this->refund($r, $r->amount, 'no_answer',
                            ['The owner did not answer', 'The owner did not accept your reservation of {title} in time. Your {amount} is back in your E-Ride wallet.'], notifyOwner: true);
                        $counts['no_answer']++;
                    }
                });
            }, $r);
        }
        $today = $now->toDateString();
        foreach (ListingReservation::with(['seeker', 'listing'])->where('status', ListingReservation::RESERVED)->whereDate('move_in_date', '<=', $today)->get() as $r) {
            if ($now->gte($r->release_due_at)) {
                $this->safely(function () use ($r, &$counts) {
                    DB::transaction(function () use ($r, &$counts) {
                        $r = $this->locked($r);
                        if ($r->status === ListingReservation::RESERVED) {
                            $this->release($r, $r->amount, 'automatic');
                            $counts['released']++;
                        }
                    });
                }, $r);
                continue;
            }
            $days = ListingSettings::reservations()['release_days'];
            if (!$r->move_in_pushed_at && $now->hour >= 9) {
                $r->update(['move_in_pushed_at' => $now]);
                $this->push($r->seeker, $r, 'listing_reservation_move_in', 'Moving in today',
                    'Once you move in to {title}, confirm it is as listed in My visits, or report a problem. Without an answer, E-Ride pays the owner in {days} days.', ['days' => $days]);
                $counts['move_in']++;
            } elseif ($r->move_in_pushed_at && !$r->move_in_reminded_at && $now->gte($r->move_in_pushed_at->copy()->addDay())) {
                $r->update(['move_in_reminded_at' => $now]);
                $this->push($r->seeker, $r, 'listing_reservation_move_in', 'Is everything as listed',
                    'Confirm your move-in to {title} in My visits, or report a problem before E-Ride pays the owner on {date}.', ['date' => $r->release_due_at->format('d M')]);
                $counts['move_in']++;
            }
        }
        return $counts;
    }

    /** True while a MoMo payment started in time may still land (it is checked first). */
    protected function momoStillRunning(ListingReservation $reservation, Carbon $now): bool
    {
        $requestIds = PaymentRequest::where('attribute', self::ATTRIBUTE)->where('attribute_id', $reservation->id)->pluck('id');
        if ($requestIds->isEmpty()) {
            return false;
        }
        foreach (FapshiTransaction::whereIn('payment_request_id', $requestIds)->whereIn('status', FapshiTransaction::OPEN)->whereNotNull('trans_id')->get() as $tx) {
            try {
                app(FapshiPayments::class)->sync($tx, 10);
            } catch (Throwable) {
            }
        }
        if ($reservation->fresh()->status !== ListingReservation::AWAITING) {
            return true;
        }
        return FapshiTransaction::whereIn('payment_request_id', $requestIds)->whereIn('status', FapshiTransaction::OPEN)->whereNotNull('trans_id')->exists()
            && $reservation->pay_due_at->copy()->addSeconds(self::MOMO_GRACE_SECONDS)->gt($now);
    }

    // ------------------------------------------------------------- money moves (inside a locked transaction)

    /** @param array{0:string,1:string} $message the seeker's push */
    protected function refund(ListingReservation $reservation, float $amount, string $why, array $message, array $vars = [], bool $notifyOwner = false): ListingReservation
    {
        if ($amount > 0) {
            $this->creditWallet($reservation->seeker_id, $amount, $reservation->id, 'listing_reservation_refund', 'Homes & spaces reservation refunded to customer wallet');
        }
        $reservation->update([
            'status' => ListingReservation::REFUNDED, 'refunded_amount' => $amount, 'settled_at' => now(), 'closed_reason' => $why,
        ]);
        DB::afterCommit(function () use ($reservation, $amount, $message, $vars, $notifyOwner) {
            $this->push($reservation->seeker, $reservation, 'listing_reservation_refunded', $message[0], $message[1], $vars + ['amount' => getCurrencyFormat($amount)]);
            if ($notifyOwner) {
                $this->push($reservation->owner, $reservation, 'listing_reservation_closed', 'A reservation was closed',
                    'The reservation of {title} by {name} was closed and the seeker refunded.', ['name' => $reservation->seeker?->first_name]);
            }
        });
        return $reservation;
    }

    /** The owner is paid $gross minus E-Ride's fee, through the payout queue. */
    protected function release(ListingReservation $reservation, float $gross, string $why, ?string $note = null): ListingReservation
    {
        $fee = round($gross * ListingSettings::reservations()['fee_percent'] / 100, 2);
        $ownerAmount = round($gross - $fee, 2);
        $listing = Listing::withTrashed()->find($reservation->listing_id);
        $owner = User::find($reservation->owner_id);
        $adminId = $this->adminUserId();
        $adminAccount = $this->lockedAccount($adminId);
        if ($fee > 0) {
            $adminAccount->received_balance += $fee;
            Transaction::create([
                'attribute' => 'listing_reservation_fee', 'attribute_id' => $reservation->id, 'credit' => $fee,
                'balance' => $adminAccount->received_balance, 'user_id' => $adminId, 'account' => 'received_balance',
                'trx_type' => 'Homes & spaces reservation fee added to admin received balance',
            ]);
        }
        $adminAccount->save();
        if ($ownerAmount > 0) {
            $this->owe($ownerAmount, $reservation->id, 'listing_reservation_release', 'Released reservation owed to the owner on mobile money');
            ListingPayout::create([
                'user_id' => $reservation->owner_id, 'reservation_id' => $reservation->id, 'kind' => ListingPayout::OWNER, 'amount' => $ownerAmount,
                'phone' => $listing?->payout_phone ?: (string)$owner?->phone,
                'name' => $listing?->payout_name ?: trim(($owner?->first_name ?? '') . ' ' . ($owner?->last_name ?? '')),
                'status' => ListingPayout::PENDING,
            ]);
        }
        $reservation->update([
            'status' => ListingReservation::RELEASED, 'fee' => round($reservation->fee + $fee, 2), 'owner_amount' => $ownerAmount,
            'settled_at' => now(), 'closed_reason' => $why,
        ]);
        DB::afterCommit(function () use ($reservation, $ownerAmount, $note) {
            $this->push($reservation->owner, $reservation, 'listing_reservation_released', 'Your reservation money is released',
                'E-Ride will send you {amount} for {title} on mobile money, usually the same working day.', ['amount' => getCurrencyFormat($ownerAmount)]);
            if ($note !== null) {
                $this->push($reservation->seeker, $reservation, 'listing_reservation_released', 'E-Ride decided your reservation',
                    'E-Ride paid the reservation of {title} to the owner: {note}', ['note' => $note]);
            }
        });
        return $reservation;
    }

    protected function split(ListingReservation $reservation, float $seekerAmount, string $note): ListingReservation
    {
        $seekerAmount = round(min(max($seekerAmount, 0), $reservation->amount), 2);
        if ($seekerAmount <= 0 || $seekerAmount >= $reservation->amount) {
            $this->fail(translate('For a split, give the seeker more than nothing and less than the whole amount.'));
        }
        $this->creditWallet($reservation->seeker_id, $seekerAmount, $reservation->id, 'listing_reservation_refund', 'Part of a Homes & spaces reservation refunded to customer wallet');
        $reservation->refunded_amount = $seekerAmount;
        $this->release($reservation, $reservation->amount - $seekerAmount, 'split');
        DB::afterCommit(fn() => $this->push($reservation->seeker, $reservation, 'listing_reservation_refunded', 'E-Ride decided your reservation',
            'E-Ride refunded {amount} of your reservation of {title} to your wallet: {note}', ['amount' => getCurrencyFormat($seekerAmount), 'note' => $note]));
        return $reservation;
    }

    protected function openProblem(ListingReservation $reservation, string $by, string $reason, ?string $note): ListingReservation
    {
        $reservation->update([
            'problem_from' => $reservation->status, 'status' => ListingReservation::PROBLEM,
            'problem_by' => $by, 'problem_reason' => $reason, 'problem_note' => $note,
        ]);
        DB::afterCommit(function () use ($reservation, $by) {
            $other = $by === 'seeker' ? $reservation->owner : $reservation->seeker;
            $this->push($other, $reservation, 'listing_reservation_problem', 'E-Ride is looking into a reservation',
                'A problem was reported on the reservation of {title}. E-Ride keeps the money until it decides, and may call you.', []);
        });
        return $reservation;
    }

    /** E-Ride owes this on mobile money: the admin's payable balance, cleared when the payout is marked paid. */
    protected function owe(float $amount, string $reservationId, string $attribute, string $label): void
    {
        $adminId = $this->adminUserId();
        $adminAccount = $this->lockedAccount($adminId);
        $adminAccount->payable_balance += $amount;
        $adminAccount->save();
        Transaction::create([
            'attribute' => $attribute, 'attribute_id' => $reservationId, 'credit' => $amount,
            'balance' => $adminAccount->payable_balance, 'user_id' => $adminId, 'account' => 'payable_balance', 'trx_type' => $label,
        ]);
    }

    protected function creditWallet(string $userId, float $amount, string $reservationId, string $attribute, string $label, ?string $reference = null): void
    {
        $account = $this->lockedAccount($userId);
        $account->wallet_balance += $amount;
        $account->save();
        Transaction::create([
            'attribute' => $attribute, 'attribute_id' => $reservationId, 'credit' => $amount, 'balance' => $account->wallet_balance,
            'user_id' => $userId, 'account' => 'wallet_balance', 'trx_ref_id' => $reference, 'trx_type' => $label,
        ]);
    }

    // ------------------------------------------------------------- helpers

    protected function locked(ListingReservation $reservation): ListingReservation
    {
        return ListingReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
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

    protected function adminUserId(): string
    {
        return User::where('user_type', ADMIN_USER_TYPES[0])->value('id');
    }

    protected function assertSeeker(ListingReservation $reservation, User $user): void
    {
        if ($reservation->seeker_id !== $user->id) {
            $this->fail(translate('This reservation is not yours.'));
        }
    }

    protected function assertOwner(ListingReservation $reservation, User $user): void
    {
        if ($reservation->owner_id !== $user->id) {
            $this->fail(translate('This reservation is not for your place.'));
        }
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['reservation' => $message]);
    }

    protected function safely(callable $step, ListingReservation $reservation): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::warning('Listing reservation step failed', ['reservation' => $reservation->id, 'error' => $e->getMessage()]);
        }
    }

    protected function push(?User $user, ListingReservation $reservation, string $action, string $title, string $body, array $replace): void
    {
        if (!$user) {
            return;
        }
        try {
            $locale = $user->current_language_key;
            $listing = $reservation->listing ?? Listing::withTrashed()->find($reservation->listing_id);
            $replace = $replace + ['title' => $listing?->title, 'amount' => getCurrencyFormat($reservation->amount)];
            sendDeviceNotification(
                fcm_token: $user->fcm_token,
                title: translate(key: $title, replace: $replace, locale: $locale),
                description: translate(key: $body, replace: $replace, locale: $locale),
                status: 1,
                type: 'listing',
                notification_type: 'listing',
                action: $action,
                user_id: $user->id,
                notificationData: ['listing_id' => $reservation->listing_id],
            );
        } catch (Throwable $e) {
            Log::warning('Listing reservation push failed', ['reservation' => $reservation->id, 'error' => $e->getMessage()]);
        }
    }
}
