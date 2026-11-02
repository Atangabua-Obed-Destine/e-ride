<?php

use Illuminate\Support\Facades\Route;
use Modules\StoreManagement\Http\Controllers\Web\Admin\StoreController;
use Modules\StoreManagement\Http\Controllers\Web\Admin\StoreOrderController;
use Modules\StoreManagement\Http\Controllers\Web\Admin\StoreSettingController;
use Modules\StoreManagement\Http\Controllers\Web\Partner\AuthController;
use Modules\StoreManagement\Http\Controllers\Web\Partner\PartnerOrderController;
use Modules\StoreManagement\Http\Controllers\Web\Partner\PortalController;
use Modules\StoreManagement\Http\Middleware\PartnerMiddleware;

/*
| Food & Shops. Admin screens live under admin/stores (sidebar "Food & Shops");
| the partner portal is a separate, phone-first site under /partner.
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'stores', 'as' => 'store.'], function () {
        Route::middleware('can:store_view')->group(function () {
            Route::get('/', [StoreController::class, 'index'])->name('index');
            Route::get('{store}/payouts', [StoreController::class, 'payouts'])->name('payouts');
        });
        Route::middleware('can:store_add')->group(function () {
            Route::get('create', [StoreController::class, 'create'])->name('create');
            Route::post('/', [StoreController::class, 'store'])->name('store');
        });
        Route::middleware('can:store_edit')->group(function () {
            Route::get('{store}/edit', [StoreController::class, 'edit'])->name('edit');
            Route::put('{store}', [StoreController::class, 'update'])->name('update');
            Route::post('{store}/status', [StoreController::class, 'status'])->name('status');
            Route::get('{store}/menu', [StoreController::class, 'menu'])->name('menu');
            Route::post('{store}/menu/category/{category?}', [StoreController::class, 'saveCategory'])->name('menu.category.save');
            Route::delete('{store}/menu/category/{category}', [StoreController::class, 'deleteCategory'])->name('menu.category.delete');
            Route::post('{store}/menu/item/{item?}', [StoreController::class, 'saveItem'])->name('menu.item.save');
            Route::delete('{store}/menu/item/{item}', [StoreController::class, 'deleteItem'])->name('menu.item.delete');
            Route::post('{store}/menu/item/{item}/availability', [StoreController::class, 'itemAvailability'])->name('menu.item.availability');
            Route::post('{store}/payouts', [StoreController::class, 'recordPayout'])->name('payouts.record');
        });
        Route::delete('{store}', [StoreController::class, 'destroy'])->name('destroy')->middleware('can:store_delete');
    });

    Route::group(['prefix' => 'store-orders', 'as' => 'store-order.', 'middleware' => 'can:store_view'], function () {
        Route::get('/', [StoreOrderController::class, 'index'])->name('index');
        Route::get('{order}', [StoreOrderController::class, 'show'])->name('show');
        Route::post('{order}/cancel', [StoreOrderController::class, 'cancel'])->name('cancel')->middleware('can:store_edit');
        Route::post('{order}/resolve', [StoreOrderController::class, 'resolve'])->name('resolve')->middleware('can:store_edit');
    });

    Route::group(['prefix' => 'store-settings', 'as' => 'store-setting.', 'middleware' => 'can:store_edit'], function () {
        Route::get('/', [StoreSettingController::class, 'edit'])->name('edit');
        Route::put('/', [StoreSettingController::class, 'update'])->name('update');
    });
});

Route::group(['prefix' => 'partner', 'as' => 'partner.'], function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(PartnerMiddleware::class)->group(function () {
        Route::get('/', [PartnerOrderController::class, 'board'])->name('orders');
        Route::get('orders/feed', [PartnerOrderController::class, 'feed'])->name('orders.feed');
        Route::get('orders/history', [PartnerOrderController::class, 'history'])->name('orders.history');
        Route::post('orders/{order}/accept', [PartnerOrderController::class, 'accept'])->name('orders.accept');
        Route::post('orders/{order}/reject', [PartnerOrderController::class, 'reject'])->name('orders.reject');
        Route::post('orders/{order}/ready', [PartnerOrderController::class, 'ready'])->name('orders.ready');

        Route::get('menu', [PortalController::class, 'menu'])->name('menu');
        Route::post('menu/category/{category?}', [PortalController::class, 'saveCategory'])->name('menu.category.save');
        Route::delete('menu/category/{category}', [PortalController::class, 'deleteCategory'])->name('menu.category.delete');
        Route::post('menu/item/{item?}', [PortalController::class, 'saveItem'])->name('menu.item.save');
        Route::delete('menu/item/{item}', [PortalController::class, 'deleteItem'])->name('menu.item.delete');
        Route::post('menu/item/{item}/availability', [PortalController::class, 'itemAvailability'])->name('menu.item.availability');

        Route::get('store', [PortalController::class, 'profile'])->name('store');
        Route::put('store', [PortalController::class, 'updateProfile'])->name('store.update');
        Route::post('store/open', [PortalController::class, 'toggleOpen'])->name('store.open');
        Route::get('earnings', [PortalController::class, 'earnings'])->name('earnings');
    });
});
