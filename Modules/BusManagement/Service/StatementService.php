<?php

namespace Modules\BusManagement\Service;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusStatement;
use Modules\BusManagement\Entities\BusTicket;
use Modules\BusManagement\Lib\BusSettings;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\UserManagement\Entities\User;

/**
 * E-Ride's commission, once a month. On the 1st each agency gets a statement of the month before: every ticket
 * of its departures that month, app and counter, each at its own rate, on what the agency kept (refunded money
 * carries none). It is due on the statement day of the month (10th by default). The manager pays it from the
 * portal through E-Ride's own Fapshi (the busStatementPaid hook), or an E-Ride admin marks it paid. A statement
 * late by some days pauses online booking; paying it brings it back. The counter keeps working throughout.
 */
class StatementService
{
    use Payment;

    public const HOOK = 'busStatementPaid';
    public const ATTRIBUTE = 'bus_statement';
    /** Pause reasons this service sets (and so may lift). */
    public const REASON_LATE = 'statement';
    public const REASON_DECLINES = 'declines';

    public function __construct(protected AgencyService $agencies)
    {
    }

    /** Bookings of an agency's departures in a month that count: paid, or cancelled with money kept. */
    protected function bookingsOf(BusAgency $agency, CarbonInterface $month)
    {
        $start = Carbon::instance($month)->startOfMonth();
        return BusBooking::where('agency_id', $agency->id)
            ->whereIn('departure_id', BusDeparture::where('agency_id', $agency->id)
                ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->select('id'))
            ->where(fn($q) => $q->where('status', BusBooking::PAID)
                ->orWhere(fn($w) => $w->where('status', BusBooking::CANCELLED)->whereNotNull('paid_at')->where('commission', '>', 0)));
    }

    /** A month's figures so far: ['app' => [tickets, fares, commission], 'counter' => [...], 'total']. */
    public function totals(BusAgency $agency, CarbonInterface $month): array
    {
        $rows = $this->bookingsOf($agency, $month)->withCount(['tickets as seats' => fn($q) => $q->whereIn('status', [BusTicket::VALID, BusTicket::BOARDED, BusTicket::NO_SHOW])])->get();
        $sum = function (string $source) use ($rows) {
            $r = $rows->where('source', $source);
            return [
                'tickets' => (int)$r->sum('seats'),
                'fares' => round($r->sum(fn($b) => (float)$b->amount - (float)$b->refund_amount), 2),
                'commission' => round($r->sum('commission'), 2),
            ];
        };
        [$app, $counter] = [$sum(BusBooking::APP), $sum(BusBooking::COUNTER)];
        return ['app' => $app, 'counter' => $counter, 'total' => round($app['commission'] + $counter['commission'], 2)];
    }

    /** Builds (or rebuilds, while unpaid) an agency's statement of a month. */
    public function build(BusAgency $agency, CarbonInterface $month): BusStatement
    {
        $start = Carbon::instance($month)->startOfMonth();
        ['app' => $app, 'counter' => $counter] = $this->totals($agency, $start);
        $statement = BusStatement::firstOrNew(['agency_id' => $agency->id, 'month' => $start->toDateString()]);
        if ($statement->exists && $statement->status === BusStatement::PAID) {
            return $statement;
        }
        $total = round($app['commission'] + $counter['commission'], 2);
        $statement->fill([
            'app_tickets' => $app['tickets'], 'app_fares' => $app['fares'], 'app_commission' => $app['commission'],
            'counter_tickets' => $counter['tickets'], 'counter_fares' => $counter['fares'], 'counter_commission' => $counter['commission'],
            'total' => $total,
            'due_on' => $start->copy()->addMonth()->day(BusSettings::all()['statement_due_day'])->toDateString(),
            // Nothing to pay: settled at once.
            'status' => $total > 0 ? BusStatement::DUE : BusStatement::PAID,
            'paid_at' => $total > 0 ? null : now(),
            'reference' => $total > 0 ? null : 'nothing due',
        ]);
        $statement->save();
        return $statement;
    }

    /** On (or after) the 1st: last month's statement for every agency that has one to make. Returns how many were made. */
    public function generate(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $month = Carbon::instance($now)->startOfMonth()->subMonth();
        $made = 0;
        BusAgency::where('status', '!=', BusAgency::CHECKING)->get()->each(function (BusAgency $agency) use ($month, &$made) {
            if (!BusStatement::where('agency_id', $agency->id)->where('month', $month->toDateString())->exists()) {
                $this->build($agency, $month);
                $made++;
            }
        });
        return $made;
    }

    /** Every ticket of a statement, for the portal and the CSV. */
    public function lines(BusStatement $statement): Collection
    {
        return $this->bookingsOf($statement->agency, $statement->month)->with(['tickets', 'departure.destination', 'dropPoint'])->get()
            ->sortBy(fn($b) => $b->departure?->leavesAt()->timestamp)->values();
    }

    // ------------------------------------------------------------------ paying

    /** The checkout link at E-Ride's own Fapshi, for the manager. */
    public function payLink(BusStatement $statement, User $manager): string
    {
        if ($statement->status !== BusStatement::DUE) {
            throw ValidationException::withMessages(['statement' => translate('This statement is already paid.')]);
        }
        $info = new PaymentInfo(
            hook: self::HOOK,
            currencyCode: businessConfig('currency_code')?->value ?? 'XAF',
            paymentMethod: 'fapshi',
            paymentPlatform: 'web',
            payerId: $manager->id,
            receiverId: '100',
            additionalData: [
                'business_name' => businessConfig('business_name')?->value,
                'business_logo' => dynamicStorage('storage/app/public/business') . '/' . businessConfig('header_logo')?->value,
            ],
            paymentAmount: (float)ceil($statement->total),
            externalRedirectLink: route('bus-partner.payments'),
            attribute: self::ATTRIBUTE,
            attributeId: $statement->id,
        );
        $payer = new Payer(name: $manager->first_name, email: $manager->email, phone: $manager->phone ?? '', address: '');
        $link = $this->generate_link($payer, $info, new Receiver('receiver_name', 'example.png'));
        if (!$link) {
            throw ValidationException::withMessages(['statement' => translate('Mobile Money is not available right now. Try again later.')]);
        }
        return $link;
    }

    /** The payment hook, called by the gateway once E-Ride's Fapshi confirmed the money. */
    public function onGatewayPaid(PaymentRequest $paymentRequest): void
    {
        if (!(int)$paymentRequest->is_paid) {
            return;
        }
        $statement = BusStatement::find($paymentRequest->attribute_id);
        if ($statement && (float)$paymentRequest->payment_amount >= (float)$statement->total) {
            $this->markPaid($statement, 'Fapshi ' . ($paymentRequest->transaction_id ?? $paymentRequest->id), null);
        }
    }

    /** Paid (online, or marked by an admin for a transfer or cash). Lifts the late pause when nothing else is late. */
    public function markPaid(BusStatement $statement, string $reference, ?string $byUserId): BusStatement
    {
        DB::transaction(function () use ($statement, $reference, $byUserId) {
            $s = BusStatement::whereKey($statement->id)->lockForUpdate()->first();
            if ($s->status === BusStatement::PAID) {
                return;
            }
            $s->update(['status' => BusStatement::PAID, 'paid_at' => now(), 'reference' => Str::limit(trim($reference), 100, '') ?: null, 'marked_by' => $byUserId]);
        });
        $agency = $statement->agency()->first();
        if ($agency->status === BusAgency::PAUSED && $agency->pause_reason === self::REASON_LATE && !$this->lateStatements($agency)->exists()) {
            $agency->update(['status' => BusAgency::SETUP, 'pause_reason' => null, 'pause_note' => null]);
            $this->agencies->refreshStatus($agency->fresh());
        }
        return $statement->fresh();
    }

    // ------------------------------------------------------------------ pauses

    protected function lateStatements(BusAgency $agency, ?CarbonInterface $now = null)
    {
        $days = BusSettings::all()['late_pause_days'];
        return BusStatement::where('agency_id', $agency->id)->where('status', BusStatement::DUE)
            ->where('due_on', '<', Carbon::instance($now ?? now())->subDays($days)->toDateString());
    }

    /** Statements late by more than the allowed days pause online booking. Returns how many agencies were paused. */
    public function pauseLate(?CarbonInterface $now = null): int
    {
        $paused = 0;
        BusAgency::whereIn('status', [BusAgency::SETUP, BusAgency::LIVE])->get()->each(function (BusAgency $agency) use ($now, &$paused) {
            $late = $this->lateStatements($agency, $now)->orderBy('month')->first();
            if ($late) {
                $agency->update(['status' => BusAgency::PAUSED, 'pause_reason' => self::REASON_LATE,
                    'pause_note' => translate(key: 'The E-Ride statement of {month} is unpaid. Pay it in Payments and online booking comes back by itself.',
                        replace: ['month' => $late->month->translatedFormat('F Y')])]);
                $paused++;
            }
        });
        return $paused;
    }

    /**
     * An agency that declines, or lets run out, more than the allowed share of the last 30 days' app requests
     * (10 at least) is paused: passengers waited for nothing. Only an E-Ride admin brings it back.
     */
    public function pauseDecliners(?CarbonInterface $now = null): int
    {
        $limit = BusSettings::all()['decline_pause_pct'];
        if ($limit <= 0) {
            return 0;
        }
        $since = Carbon::instance($now ?? now())->subDays(30);
        $paused = 0;
        BusAgency::where('status', BusAgency::LIVE)->get()->each(function (BusAgency $agency) use ($since, $limit, &$paused) {
            $asked = BusBooking::where('agency_id', $agency->id)->where('source', BusBooking::APP)->where('created_at', '>=', $since)
                ->where('status', '!=', BusBooking::REQUESTED);
            $total = (clone $asked)->where(fn($q) => $q->whereNotNull('accepted_at')->orWhereIn('status', [BusBooking::DECLINED, BusBooking::EXPIRED]))->count();
            $failed = (clone $asked)->where(fn($q) => $q->where('status', BusBooking::DECLINED)
                ->orWhere(fn($w) => $w->where('status', BusBooking::EXPIRED)->whereNull('accepted_at')))->count();
            if ($total >= 10 && $failed / $total * 100 > $limit) {
                $agency->update(['status' => BusAgency::PAUSED, 'pause_reason' => self::REASON_DECLINES,
                    'pause_note' => translate(key: '{pct}% of seat requests in the last 30 days were declined or not answered. Contact E-Ride to open online booking again.',
                        replace: ['pct' => (int)round($failed / $total * 100)])]);
                $paused++;
            }
        });
        return $paused;
    }
}
