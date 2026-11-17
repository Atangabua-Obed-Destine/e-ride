<?php

namespace Modules\StoreManagement\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Http\Requests\MenuCategoryRequest;
use Modules\StoreManagement\Http\Requests\MenuItemRequest;
use Modules\StoreManagement\Service\Interfaces\StoreServiceInterface;
use Modules\StoreManagement\Service\StoreService;

/**
 * Menu editing, shared by the admin panel (any store) and the partner portal
 * (the partner's own store). Each controller says which store it is acting on.
 * A branch login can't edit the brand's menu: only its own kitchen's switches.
 */
trait HandlesStoreMenu
{
    abstract protected function menuStore(Request $request): Store;

    abstract protected function storeService(): StoreServiceInterface;

    /** Kitchens whose switches this page may change: a branch only its own, the brand all of them. */
    protected function menuKitchens(Request $request): Collection
    {
        $store = $this->menuStore($request);
        return $store->isBranch() ? collect([$store]) : $store->kitchens();
    }

    /** The brand's menu itself (dishes, prices, sections) is edited from the main store only. */
    protected function guardMenuEdit(Request $request): void
    {
        abort_if($this->menuStore($request)->isBranch(), 403, translate('Only the main store can change the menu.'));
    }

    /** Everything the shared menu page needs: the brand, its kitchens, the kitchen shown. */
    protected function menuPage(Request $request): array
    {
        $own = $this->menuStore($request);
        $brand = $own->brand()->load(['categories', 'items', 'branches']);
        $kitchens = $this->menuKitchens($request);
        $kitchen = $kitchens->firstWhere('id', $request->query('kitchen')) ?? $kitchens->firstWhere('id', $own->id) ?? $kitchens->first();
        return [
            'store' => $brand,
            'kitchens' => $kitchens,
            'kitchen' => $kitchen,
            'canEditMenu' => !$own->isBranch(),
            'hasBranches' => $brand->branches->isNotEmpty(),
        ];
    }

    public function saveCategory(MenuCategoryRequest $request): RedirectResponse
    {
        $this->guardMenuEdit($request);
        $this->storeService()->saveCategory($this->menuStore($request), $request->validated(), $request->route('category'));
        Toastr::success(translate('Menu section saved'));
        return back();
    }

    public function deleteCategory(Request $request): RedirectResponse
    {
        $this->guardMenuEdit($request);
        $this->storeService()->deleteCategory($this->menuStore($request), $request->route('category'));
        Toastr::success(translate('Menu section removed. Its items are now listed under Other.'));
        return back();
    }

    public function saveItem(MenuItemRequest $request): RedirectResponse
    {
        $this->guardMenuEdit($request);
        $this->storeService()->saveItem($this->menuStore($request), $request->validated() + ['image' => $request->file('image')], $request->route('item'));
        Toastr::success(translate('Menu item saved'));
        return back();
    }

    public function deleteItem(Request $request): RedirectResponse
    {
        $this->guardMenuEdit($request);
        $this->storeService()->deleteItem($this->menuStore($request), $request->route('item'));
        Toastr::success(translate('Menu item removed'));
        return back();
    }

    /**
     * One dish's switch at one kitchen (?kitchen=, default this store): Available /
     * Sold out today / Not at this branch; or for the whole menu: Hidden / On the menu.
     * JSON for the in-page control, a redirect otherwise.
     */
    public function itemAvailability(Request $request)
    {
        $menuStates = [StoreItem::HIDDEN, StoreService::ON_MENU];
        $request->validate([
            'state' => 'required|in:' . implode(',', array_merge([StoreItem::AVAILABLE, StoreItem::SOLD_OUT, StoreItem::NOT_HERE], $menuStates)),
            'kitchen' => 'nullable|uuid',
        ]);
        $state = $request->input('state');
        if (in_array($state, $menuStates, true)) {
            $this->guardMenuEdit($request);
        }
        $kitchens = $this->menuKitchens($request);
        $kitchen = $request->filled('kitchen') ? $kitchens->firstWhere('id', $request->input('kitchen')) : $this->menuStore($request);
        abort_unless($kitchen, 404);
        $this->storeService()->setItemState($kitchen, $request->route('item'), $state);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'state' => $state]);
        }
        return back();
    }
}
