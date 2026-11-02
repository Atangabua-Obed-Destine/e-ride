<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Http\Controllers\HandlesStoreMenu;
use Modules\StoreManagement\Http\Requests\StoreSaveRequest;
use Modules\StoreManagement\Service\Interfaces\StoreServiceInterface;

class StoreController extends Controller
{
    use HandlesStoreMenu;

    public function __construct(protected StoreServiceInterface $stores)
    {
    }

    protected function storeService(): StoreServiceInterface
    {
        return $this->stores;
    }

    protected function menuStore(Request $request): Store
    {
        return $request->route('store');
    }

    public function index(Request $request): View
    {
        $filters = ['search' => (string)$request->query('search', ''), 'status' => (string)$request->query('status', 'all')];
        $stores = $this->stores->list($filters);
        return view('storemanagement::admin.stores.index', compact('stores', 'filters'));
    }

    public function create(): View
    {
        return view('storemanagement::admin.stores.form', ['store' => new Store(['is_open' => true, 'default_prep_minutes' => 20, 'type' => 'restaurant'])]);
    }

    public function store(StoreSaveRequest $request): RedirectResponse
    {
        $store = $this->stores->createWithOwner($request->validated() + $request->only('logo', 'cover'));
        Toastr::success(translate('Store created. Share the partner login with the owner.'));
        if (!$store->zone_id) {
            Toastr::warning(translate('This location is outside every active zone. Customers cannot order from it until it is inside one.'));
        }
        return redirect()->route('admin.store.menu', $store);
    }

    public function edit(Store $store): View
    {
        $store->load('owner');
        return view('storemanagement::admin.stores.form', compact('store'));
    }

    public function update(StoreSaveRequest $request, Store $store): RedirectResponse
    {
        $store = $this->stores->updateWithOwner($store, $request->validated() + $request->only('logo', 'cover'));
        Toastr::success(translate('Store updated'));
        if (!$store->zone_id) {
            Toastr::warning(translate('This location is outside every active zone. Customers cannot order from it until it is inside one.'));
        }
        return back();
    }

    public function status(Request $request, Store $store): RedirectResponse
    {
        $store->is_active = $request->boolean('is_active');
        $store->save();
        $store->owner?->update(['is_active' => $store->is_active]);
        Toastr::success($store->is_active ? translate('Store activated') : translate('Store deactivated'));
        return back();
    }

    public function destroy(Store $store): RedirectResponse
    {
        if ($store->orders()->whereIn('status', \Modules\StoreManagement\Entities\StoreOrder::ACTIVE)->exists()) {
            Toastr::error(translate('This store has orders in progress. Finish or cancel them first.'));
            return back();
        }
        $store->owner?->update(['is_active' => 0]);
        $store->delete();
        Toastr::success(translate('Store removed'));
        return redirect()->route('admin.store.index');
    }

    public function menu(Store $store): View
    {
        $store->load(['categories', 'items']);
        return view('storemanagement::admin.stores.menu', compact('store'));
    }

    public function payouts(Store $store): View
    {
        $payouts = $store->payouts()->with('author')->latest()->paginate(20);
        $balance = $store->balance();
        $deliveredCount = $store->orders()->where('status', \Modules\StoreManagement\Entities\StoreOrder::DELIVERED)->count();
        return view('storemanagement::admin.stores.payouts', compact('store', 'payouts', 'balance', 'deliveredCount'));
    }

    public function recordPayout(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'nullable|string|max:30',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
        ]);
        $this->stores->recordPayout($store, $data, auth()->id());
        Toastr::success(translate('Payout recorded'));
        return back();
    }
}
