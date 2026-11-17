<?php

namespace Modules\StoreManagement\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Http\Requests\MenuCategoryRequest;
use Modules\StoreManagement\Http\Requests\MenuItemRequest;
use Modules\StoreManagement\Service\Interfaces\StoreServiceInterface;

/**
 * Menu editing, shared by the admin panel (any store) and the partner portal
 * (the partner's own store). Each controller says which store it is acting on.
 */
trait HandlesStoreMenu
{
    abstract protected function menuStore(Request $request): Store;

    abstract protected function storeService(): StoreServiceInterface;

    public function saveCategory(MenuCategoryRequest $request): RedirectResponse
    {
        $this->storeService()->saveCategory($this->menuStore($request), $request->validated(), $request->route('category'));
        Toastr::success(translate('Menu section saved'));
        return back();
    }

    public function deleteCategory(Request $request): RedirectResponse
    {
        $this->storeService()->deleteCategory($this->menuStore($request), $request->route('category'));
        Toastr::success(translate('Menu section removed. Its items are now listed under Other.'));
        return back();
    }

    public function saveItem(MenuItemRequest $request): RedirectResponse
    {
        $this->storeService()->saveItem($this->menuStore($request), $request->validated() + ['image' => $request->file('image')], $request->route('item'));
        Toastr::success(translate('Menu item saved'));
        return back();
    }

    public function deleteItem(Request $request): RedirectResponse
    {
        $this->storeService()->deleteItem($this->menuStore($request), $request->route('item'));
        Toastr::success(translate('Menu item removed'));
        return back();
    }

    /** Available / Sold out today / Hidden. JSON for the in-page control, a redirect otherwise. */
    public function itemAvailability(Request $request)
    {
        $request->validate(['state' => 'required|in:' . implode(',', [StoreItem::AVAILABLE, StoreItem::SOLD_OUT, StoreItem::HIDDEN])]);
        $item = $this->storeService()->setItemState($this->menuStore($request), $request->route('item'), $request->input('state'));
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'state' => $item->switchState()]);
        }
        return back();
    }
}
