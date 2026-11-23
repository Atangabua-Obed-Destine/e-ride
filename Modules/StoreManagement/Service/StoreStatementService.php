<?php

namespace Modules\StoreManagement\Service;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Gateways\Entities\PaymentRequest;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StoreStatement;
use Modules\StoreManagement\Lib\StoreSettings;
use Modules\UserManagement\Entities\User;

/**
 * Weekly statements of businesses paid into their own Fapshi: what each owes E-Ride for the orders
 * delivered Monday to Sunday (E-Ride's riders' delivery fees, resellers, own-delivery share, store covers).
 * Made every Monday morning, due after StoreSettings statement_due_days, paid by Mobile Money into
 * E-Ride's account (or marked paid by an admin). Late by statement_pause_days: app orders pause until paid.
 */
class StoreStatementService
{
    use Payment;

    public const HOOK = 'storeStatementPaid';
    public const ATTRIBUTE = 'store_statement';

    /** Makes the statements of every finished week not made yet. Returns how many. */
    public function generate(?CarbonInterface $now = null): int
    {
        $now = Carbon::instance($now ?? now());
        $weekStart = $now->copy()->startOfWeek();
        $made = 0;
        $brandIds = StoreOrder::where('paid_to', StoreOrder::PAID_TO_SHOP)->whereNull('statement_id')
            ->where('status', StoreOrder::DELIVERED)->where('delivered_at', '<', $weekStart)
            ->join('stores', 'stores.id', '=', 'store_orders.store_id')
            ->selectRaw('COALESCE(stores.parent_id, stores.id) AS brand_id')->distinct()->pluck('brand_id');
        foreach ($brandIds as $brandId) {
            $brand = Store::find($brandId);
            if ($brand) {
                $made += $this->buildFor($brand, $weekStart);
            }
        }
        return $made;
    }

    /** One statement per finished week with orders, for one business. */
    protected function buildFor(Store $brand, Carbon $before): int
    {
        $kitchens = $brand->kitchens()->pluck('id');
        $orders = StoreOrder::whereIn('store_id', $kitchens)->where('paid_to', StoreOrder::PAID_TO_SHOP)->whereNull('statement_id')
            ->where('status', StoreOrder::DELIVERED)->where('delivered_at', '<', $before)->orderBy('delivered_at')->get();
        $made = 0;
        foreach ($orders->groupBy(fn(StoreOrder $o) => $o->delivered_at->copy()->startOfWeek()->toDateString()) as $from => $week) {
            DB::transaction(function () use ($brand, $from, $week, &$made) {
                $start = Carbon::parse($from);
                $statement = StoreStatement::firstOrCreate(
                    ['store_id' => $brand->id, 'period_from' => $start->toDateString()],
                    ['period_to' => $start->copy()->endOfWeek()->toDateString(), 'due_at' => now()->addDays(StoreSettings::all()['statement_due_days'])->endOfDay()]
                );
                if ($statement->status === StoreStatement::PAID) {
                    return; // a paid week never changes; late orders go on the next one
                }
                StoreOrder::whereIn('id', $week->pluck('id'))->whereNull('statement_id')->update(['statement_id' => $statement->id]);
                $lines = StoreOrder::where('statement_id', $statement->id)->get();
                $delivery = (float)$lines->filter(fn($o) => !$o->byStore())->sum('delivery_fee');
                $total = round((float)$lines->sum('owed_to_eride'), 2);
                $statement->update(['orders_count' => $lines->count(), 'delivery_fees' => min($delivery, $total),
                    'other' => max(0, round($total - min($delivery, $total), 2)), 'amount' => $total]);
                if ($total <= 0) {
                    $statement->update(['status' => StoreStatement::PAID, 'paid_at' => now(), 'reference' => 'Nothing owed']);
                }
                $made++;
            });
        }
        return $made;
    }

    /** The checkout link at E-Ride's own Fapshi, for the owner. */
    public function payLink(StoreStatement $statement, User $owner): string
    {
        if ($statement->status !== StoreStatement::DUE) {
            throw ValidationException::withMessages(['statement' => translate('This statement is already paid.')]);
        }
        $info = new PaymentInfo(
            hook: self::HOOK,
            currencyCode: businessConfig('currency_code')?->value ?? 'XAF',
            paymentMethod: 'fapshi',
            paymentPlatform: 'web',
            payerId: $owner->id,
            receiverId: '100',
            additionalData: [
                'business_name' => businessConfig('business_name')?->value,
                'business_logo' => dynamicStorage('storage/app/public/business') . '/' . businessConfig('header_logo')?->value,
            ],
            paymentAmount: (float)ceil($statement->amount),
            externalRedirectLink: route('partner.payments'),
            attribute: self::ATTRIBUTE,
            attributeId: $statement->id,
        );
        $payer = new Payer(name: $owner->first_name, email: $owner->email, phone: $owner->phone ?? '', address: '');
        $link = $this->generate_link($payer, $info, new Receiver('receiver_name', 'example.png'));
        if (!$link) {
            throw ValidationException::withMessages(['statement' => translate('Mobile Money is not available right now. Try again later.')]);
        }
        return $link;
    }

    /** The payment hook, once E-Ride's Fapshi confirmed the money. */
    public function onGatewayPaid(PaymentRequest $paymentRequest): void
    {
        if (!(int)$paymentRequest->is_paid) {
            return;
        }
        $statement = StoreStatement::find($paymentRequest->attribute_id);
        if ($statement && (float)$paymentRequest->payment_amount + 0.5 >= (float)$statement->amount) {
            $this->markPaid($statement, 'Fapshi ' . ($paymentRequest->transaction_id ?? $paymentRequest->id), null);
        }
    }

    /** Paid (online, or marked by an admin). Lifts the pause when nothing else is late. */
    public function markPaid(StoreStatement $statement, string $reference, ?string $byUserId): StoreStatement
    {
        DB::transaction(function () use ($statement, $reference, $byUserId) {
            $s = StoreStatement::whereKey($statement->id)->lockForUpdate()->first();
            if ($s->status !== StoreStatement::PAID) {
                $s->update(['status' => StoreStatement::PAID, 'paid_at' => now(), 'reference' => Str::limit(trim($reference), 110, '') ?: null, 'paid_by' => $byUserId]);
            }
        });
        $brand = $statement->store()->first();
        if ($brand?->statement_paused_at && !$this->late($brand)->exists()) {
            $brand->forceFill(['statement_paused_at' => null])->save();
        }
        return $statement->fresh();
    }

    protected function late(Store $brand, ?CarbonInterface $now = null)
    {
        $days = StoreSettings::all()['statement_pause_days'];
        return StoreStatement::where('store_id', $brand->id)->where('status', StoreStatement::DUE)
            ->where('due_at', '<', Carbon::instance($now ?? now())->subDays($days));
    }

    /** Statements late beyond the allowed days pause app orders of that business. Returns how many paused. */
    public function pauseLate(?CarbonInterface $now = null): int
    {
        $paused = 0;
        StoreStatement::where('status', StoreStatement::DUE)->pluck('store_id')->unique()->each(function ($brandId) use ($now, &$paused) {
            $brand = Store::find($brandId);
            if ($brand && !$brand->statement_paused_at && $this->late($brand, $now)->exists()) {
                $brand->forceFill(['statement_paused_at' => now()])->save();
                $paused++;
            }
        });
        return $paused;
    }
}
