<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StoreStatement;
use Modules\StoreManagement\Lib\StoreSettings;
use Modules\StoreManagement\Service\StorePaymentService;
use Modules\StoreManagement\Service\StoreStatementService;

/**
 * The owner's Mobile Money page: connect the business's own Fapshi so customers pay it directly,
 * the weekly E-Ride statements, and money to send back to customers. Owner only (not team logins).
 */
class PartnerPaymentsController extends Controller
{
    public function __construct(protected StorePaymentService $payments, protected StoreStatementService $statements)
    {
    }

    protected function brand(Request $request): Store
    {
        return $request->attributes->get('partnerStore')->brand();
    }

    public function index(Request $request): View
    {
        $store = $request->attributes->get('partnerStore');
        $brand = $store->brand();
        $kitchens = $brand->kitchens()->pluck('id');
        return view('storemanagement::partner.payments', [
            'store' => $store,
            'brand' => $brand,
            'isBranch' => $store->isBranch(),
            'enabled' => StoreSettings::all()['own_payments_status'],
            'keys' => $brand->fapshiValues(),
            'webhookUrl' => $brand->fapshi_code ? route('store.fapshi.webhook', ['code' => $brand->fapshi_code]) : null,
            'statements' => $brand->statements()->latest('period_from')->limit(12)->get(),
            'sendBack' => StoreOrder::whereIn('store_id', $kitchens)->where('paid_to', StoreOrder::PAID_TO_SHOP)
                ->where('shop_refund_due', '>', 0)->whereNull('shop_refund_sent_at')->with('customer')->latest('updated_at')->limit(30)->get(),
            'pauseDays' => StoreSettings::all()['statement_pause_days'],
        ]);
    }

    public function saveKeys(Request $request): RedirectResponse
    {
        abort_unless(StoreSettings::all()['own_payments_status'], 403);
        try {
            $this->payments->saveKeys($this->brand($request), $request->only(['api_user', 'api_key', 'webhook_secret', 'mode']));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput($request->except(['api_key', 'webhook_secret']));
        }
        return back()->with('success', translate('Keys saved. Now press Test the connection.'));
    }

    public function test(Request $request): RedirectResponse
    {
        [$ok, $message] = $this->payments->testKeys($this->brand($request));
        return back()->with($ok ? 'success' : 'fapshi_error', $message);
    }

    /** On: customers pay the business's Fapshi from the next order. Off: E-Ride collects again. */
    public function toggle(Request $request): RedirectResponse
    {
        $brand = $this->brand($request);
        $on = $request->boolean('on');
        if ($on && (!$brand->fapshi_ok_at || !$brand->fapshiValues())) {
            return back()->with('fapshi_error', translate('Test the connection first: it must say Connected.'));
        }
        $brand->forceFill(['own_payments' => $on])->save();
        return back()->with('success', $on
            ? translate('From the next order, customers pay straight into your Fapshi account.')
            : translate('From the next order, customers pay E-Ride again and E-Ride pays you out.'));
    }

    public function payStatement(Request $request, string $statement): RedirectResponse
    {
        $row = StoreStatement::where('store_id', $this->brand($request)->id)->findOrFail($statement);
        try {
            return redirect()->away($this->statements->payLink($row, $request->user()));
        } catch (ValidationException $e) {
            return back()->with('fapshi_error', collect($e->errors())->flatten()->first());
        }
    }

    /** The owner sent the money back to the customer (Mobile Money), with its reference. */
    public function sentBack(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['reference' => 'required|string|max:80']);
        $brand = $this->brand($request);
        $row = StoreOrder::whereIn('store_id', $brand->kitchens()->pluck('id'))->where('paid_to', StoreOrder::PAID_TO_SHOP)->findOrFail($order);
        $row->forceFill(['shop_refund_sent_at' => now(), 'shop_refund_ref' => Str::limit(trim($data['reference']), 80, '')])->save();
        return back()->with('success', translate('Marked as sent back.'));
    }
}
