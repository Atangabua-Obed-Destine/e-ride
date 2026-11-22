<?php

use Illuminate\Support\Facades\Route;
use Modules\MarketManagement\Http\Controllers\Api\MarketChatController;
use Modules\MarketManagement\Http\Controllers\Api\MarketController;
use Modules\MarketManagement\Http\Controllers\Api\MarketOrderController;
use Modules\MarketManagement\Http\Controllers\Api\MarketAuctionController;
use Modules\MarketManagement\Http\Controllers\Api\MarketBookingController;
use Modules\MarketManagement\Http\Controllers\Api\MarketSellerController;
use Modules\MarketManagement\Http\Controllers\Api\MarketVenueController;
use Modules\MarketManagement\Http\Controllers\Api\MarketZamaController;

/*
| E-Ride Market, customer app. Shops and items are found by their public slug / code.
*/
Route::group(['prefix' => 'customer/market', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::middleware('throttle:120,1,market-read')->group(function () {
        Route::get('meta', [MarketController::class, 'meta']);
        Route::get('feed', [MarketController::class, 'feed']);
        Route::get('tags/suggest', [MarketController::class, 'tagSuggestions']);
        // Markets (Bamenda Main Market...) and their sheds.
        Route::get('venues', [MarketVenueController::class, 'index']);
        Route::get('venues/{slug}', [MarketVenueController::class, 'show'])->where('slug', '[a-z0-9-]+');
        Route::get('shops/{slug}', [MarketController::class, 'shop'])->where('slug', '[a-z0-9-]+');
        Route::get('items/{code}', [MarketController::class, 'item'])->where('code', '[a-z0-9]+');
        Route::get('shop', [MarketSellerController::class, 'myShop']);
        Route::get('chats', [MarketChatController::class, 'inbox']);
        Route::get('chats/{id}/messages', [MarketChatController::class, 'messages']);
        Route::get('orders', [MarketOrderController::class, 'index']);
        Route::get('orders/{id}', [MarketOrderController::class, 'show']);
        Route::get('shops/{slug}/reviews', [MarketOrderController::class, 'reviews'])->where('slug', '[a-z0-9-]+');
        Route::get('items/{code}/bids', [MarketAuctionController::class, 'bids'])->where('code', '[a-z0-9]+');
        Route::get('items/{code}/slots', [MarketBookingController::class, 'slots'])->where('code', '[a-z0-9]+');
        Route::get('bookings', [MarketBookingController::class, 'index']);
        Route::get('bookings/{id}', [MarketBookingController::class, 'show']);
        // Zama (reselling)
        Route::get('zama/catalogue', [MarketZamaController::class, 'catalogue']);
        Route::get('zama/items/{code}', [MarketZamaController::class, 'item'])->where('code', '[a-z0-9]+');
        Route::get('zama/deals', [MarketZamaController::class, 'deals']);
        Route::get('zama/deals/{id}', [MarketZamaController::class, 'show']);
        Route::get('zama/resellers', [MarketZamaController::class, 'resellers']);
    });
    // Services: book a time or ask for a quote; the provider answers; either side moves on.
    Route::middleware('throttle:60,1,market-bookings')->group(function () {
        Route::post('items/{code}/book', [MarketBookingController::class, 'book'])->where('code', '[a-z0-9]+');
        Route::post('items/{code}/quote', [MarketBookingController::class, 'quote'])->where('code', '[a-z0-9]+');
        Route::post('bookings/{id}/accept', [MarketBookingController::class, 'accept']);
        Route::post('bookings/{id}/decline', [MarketBookingController::class, 'decline']);
        Route::post('bookings/{id}/suggest', [MarketBookingController::class, 'suggest']);
        Route::post('bookings/{id}/send-quote', [MarketBookingController::class, 'sendQuote']);
        Route::post('bookings/{id}/agree', [MarketBookingController::class, 'agree']);
        Route::post('bookings/{id}/refuse', [MarketBookingController::class, 'refuse']);
        Route::post('bookings/{id}/reschedule', [MarketBookingController::class, 'reschedule']);
        Route::post('bookings/{id}/cancel', [MarketBookingController::class, 'cancel']);
        Route::post('bookings/{id}/no-show', [MarketBookingController::class, 'noShow']);
        Route::post('bookings/{id}/review', [MarketBookingController::class, 'review']);
        Route::post('shop/service-settings', [MarketSellerController::class, 'serviceSettings']);
    });
    Route::post('bookings/{id}/complete', [MarketBookingController::class, 'complete'])->middleware('throttle:8,1,market-code');
    // Orders: the buyer sends a cart to a shop; the seller answers, sends and completes it.
    Route::middleware('throttle:60,1,market-orders')->group(function () {
        Route::post('orders', [MarketOrderController::class, 'place']);
        Route::post('orders/{id}/respond', [MarketOrderController::class, 'respond']);
        Route::post('orders/{id}/decline', [MarketOrderController::class, 'decline']);
        Route::post('orders/{id}/agree', [MarketOrderController::class, 'agree']);
        Route::post('orders/{id}/cancel', [MarketOrderController::class, 'cancel']);
        Route::post('orders/{id}/ready', [MarketOrderController::class, 'ready']);
        Route::post('orders/{id}/trip', [MarketOrderController::class, 'trip']);
        Route::post('orders/{id}/review', [MarketOrderController::class, 'review']);
        Route::post('shop/order-settings', [MarketSellerController::class, 'orderSettings']);
        // Auctions: after the end (seller), and the next bidder's answer.
        Route::post('items/{code}/auction/offer-next', [MarketAuctionController::class, 'offerNext']);
        Route::post('items/{code}/auction/answer', [MarketAuctionController::class, 'answerOffer']);
        Route::post('items/{code}/auction/relist', [MarketAuctionController::class, 'relist']);
        Route::post('orders/{id}/payment-arranged', [MarketAuctionController::class, 'paymentArranged']);
        Route::post('orders/{id}/no-show', [MarketAuctionController::class, 'noShow']);
    });
    // Bidding: a little room for a busy last minute.
    Route::post('items/{code}/bid', [MarketAuctionController::class, 'bid'])->middleware('throttle:20,1,market-bid');
    // Typing a receive code: few tries, so a code cannot be guessed.
    Route::post('orders/{id}/complete', [MarketOrderController::class, 'complete'])->middleware('throttle:8,1,market-code');
    Route::middleware('throttle:30,1,market-write')->group(function () {
        Route::post('shop', [MarketSellerController::class, 'saveShop']);
        Route::post('shop/verification', [MarketSellerController::class, 'requestVerification']);
        Route::post('items', [MarketSellerController::class, 'storeItem']);
        Route::post('items/{code}/update', [MarketSellerController::class, 'updateItem']);
        Route::post('items/{code}/sold-out', [MarketSellerController::class, 'soldOut']);
        Route::post('items/{code}/delete', [MarketSellerController::class, 'destroyItem']);
        Route::post('shops/{slug}/follow', [MarketController::class, 'follow']);
        Route::post('shops/{slug}/block', [MarketController::class, 'block']);
        Route::post('items/{code}/chat', [MarketChatController::class, 'start']);
        Route::post('chats/{id}/block-buyer', [MarketChatController::class, 'blockBuyer']);
        Route::post('referral', [MarketController::class, 'referral']);
    });
    Route::post('chats/{id}/messages', [MarketChatController::class, 'send'])->middleware('throttle:60,1,market-chat');
    Route::middleware('throttle:60,1,market-zama')->group(function () {
        Route::post('zama/items/{code}/take', [MarketZamaController::class, 'take'])->where('code', '[a-z0-9]+');
        Route::post('zama/copies/{code}/price', [MarketZamaController::class, 'price'])->where('code', '[a-z0-9]+');
        Route::post('chats/{id}/quote', [MarketZamaController::class, 'quote']);
        Route::post('chats/{id}/offer', [MarketZamaController::class, 'offer']);
        Route::post('zama/quotes/{messageId}/answer', [MarketZamaController::class, 'answer']);
        Route::post('zama/quotes/{messageId}/deal', [MarketZamaController::class, 'startDeal']);
        Route::post('zama/deals/{id}/{action}', [MarketZamaController::class, 'act'])
            ->where('action', 'keep|decline|handed-over|cancel|bought|margin-paid|margin-received');
        Route::post('zama/resellers/{userId}/remove', [MarketZamaController::class, 'removeReseller']);
    });
    // The buyer's code at the shop: few tries.
    Route::post('zama/deals/{id}/sold', [MarketZamaController::class, 'act'])->defaults('action', 'sold')->middleware('throttle:8,1,market-code');
    Route::middleware('throttle:10,1,market-report')->group(function () {
        Route::post('items/{code}/report', [MarketController::class, 'reportItem']);
        Route::post('shops/{slug}/report', [MarketController::class, 'reportShop']);
    });
});

/*
| E-Ride Jobs, customer app: job seekers (feed, Work Card, apply) and employers (check, post, applicants, next step).
*/
Route::group(['prefix' => 'customer/jobs', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::middleware('throttle:120,1,jobs-read')->group(function () {
        Route::get('meta', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'meta']);
        Route::get('feed', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'feed']);
        Route::get('jobs/{code}', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'show'])->where('code', '[a-z0-9]+');
        Route::get('card', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'card']);
        Route::get('applications', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'applications']);
        Route::get('applications/{id}', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'application']);
        Route::get('alert', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'alert']);
        Route::get('employer', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'employer']);
        Route::get('employer/jobs', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'jobs']);
        Route::get('employer/jobs/{code}/applicants', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'applicants'])->where('code', '[a-z0-9]+');
        Route::get('employer/applications/{id}', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'applicant']);
    });
    Route::middleware('throttle:30,1,jobs-write')->group(function () {
        Route::post('jobs/{code}/save', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'save'])->where('code', '[a-z0-9]+');
        Route::post('jobs/{code}/apply', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'apply'])->where('code', '[a-z0-9]+');
        Route::post('card', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'saveCard']);
        Route::post('applications/{id}/withdraw', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'withdraw']);
        Route::post('alert', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'saveAlert']);
        Route::post('employer', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'saveEmployer']);
        Route::post('employer/jobs', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'store']);
        Route::post('employer/jobs/{code}/update', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'update'])->where('code', '[a-z0-9]+');
        Route::post('employer/jobs/{code}/close', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'close'])->where('code', '[a-z0-9]+');
        Route::post('employer/applications/{id}/shortlist', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'shortlist']);
        Route::post('employer/applications/{id}/not-selected', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'notSelected']);
        Route::post('employer/applications/{id}/next-step', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'nextStep']);
        Route::post('employer/applications/{id}/hire', [\Modules\MarketManagement\Http\Controllers\Api\JobEmployerController::class, 'hire']);
        Route::post('applications/{id}/hire-answer', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'answerHire']);
    });
    Route::post('jobs/{code}/report', [\Modules\MarketManagement\Http\Controllers\Api\JobController::class, 'report'])->where('code', '[a-z0-9]+')
        ->middleware('throttle:10,1,jobs-report');
});
