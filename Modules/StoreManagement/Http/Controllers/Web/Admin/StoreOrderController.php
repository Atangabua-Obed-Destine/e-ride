<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Service\Interfaces\StoreOrderServiceInterface;

class StoreOrderController extends Controller
{
    public function __construct(protected StoreOrderServiceInterface $orders)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'status' => (string)$request->query('status', 'active'),
            'store' => (string)$request->query('store', ''),
            'search' => (string)$request->query('search', ''),
        ];
        $orders = StoreOrder::with(['store', 'customer', 'items'])
            ->when($filters['status'] === 'active', fn($q) => $q->whereIn('status', StoreOrder::ACTIVE))
            ->when($filters['status'] === 'attention', fn($q) => $q->where(function ($w) {
                $w->where('status', StoreOrder::DELIVERY_FAILED)
                    ->orWhere(fn($x) => $x->where('status', StoreOrder::RIDER_REQUESTED)->where('rider_attempts', '>=', 3));
            }))
            ->when(!in_array($filters['status'], ['active', 'attention', 'all'], true), fn($q) => $q->where('status', $filters['status']))
            ->when($filters['store'] !== '', fn($q) => $q->where('store_id', $filters['store']))
            ->when($filters['search'] !== '', fn($q) => $q->where(fn($w) => $w->where('ref_id', ltrim($filters['search'], '#'))
                ->orWhere('receiver_phone', 'like', '%' . $filters['search'] . '%')
                ->orWhere('receiver_name', 'like', '%' . $filters['search'] . '%')))
            ->latest()
            ->paginate(25)
            ->appends($filters);
        $stores = Store::orderBy('name')->get(['id', 'name']);
        return view('storemanagement::admin.orders.index', compact('orders', 'filters', 'stores'));
    }

    public function show(StoreOrder $order): View
    {
        $order->load(['store', 'customer', 'items', 'trip.driver', 'trip.tripStatus', 'review']);
        return view('storemanagement::admin.orders.show', compact('order'));
    }

    public function cancel(Request $request, StoreOrder $order): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:200', 'refund' => 'nullable|boolean']);
        try {
            $this->orders->cancelByAdmin($order, $data['reason'], $request->boolean('refund', true));
            Toastr::success(translate('Order cancelled'));
        } catch (ValidationException $e) {
            Toastr::error(collect($e->errors())->flatten()->first());
        }
        return back();
    }

    /** Hide or show a store rating (abuse, a mistake). Hidden ones leave the store average. */
    public function reviewVisibility(Request $request, StoreOrder $order): RedirectResponse
    {
        $order->review?->update(['is_hidden' => $request->boolean('hidden')]);
        Toastr::success($request->boolean('hidden') ? translate('Rating hidden') : translate('Rating shown again'));
        return back();
    }

    public function resolve(Request $request, StoreOrder $order): RedirectResponse
    {
        $data = $request->validate(['refund_food' => 'required|boolean', 'note' => 'nullable|string|max:150']);
        $this->orders->resolveFailedDelivery($order, $request->boolean('refund_food'), $data['note'] ?? null);
        Toastr::success(translate('Saved'));
        return back();
    }
}
