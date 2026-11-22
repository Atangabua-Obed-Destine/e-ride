<?php

namespace Modules\AdminModule\Lib;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The admin panel's map: every page, grouped by business area, with who may see it.
 *
 * One source for the sidebar, the desktop menu bar, "Go to…" (Ctrl+K) and the phone
 * menu, so they always agree and follow the same permissions.
 *
 * A section: key, title, icon, items. An item: title, route (+ params), icon, gate
 * (a permission prefix such as "trip", an exact ability, or a list: any of them),
 * active (URL patterns, as Request::is), words (more words "Go to…" finds it by),
 * badge (a count key, see badges()), children (a sub-menu).
 */
class AdminNav
{
    private static ?array $cache = null;
    private static array $badgeCache = [];

    /** The whole map, before permissions. */
    public static function map(): array
    {
        $trip = 'trip';
        return [
            ['key' => 'overview', 'title' => 'Overview', 'icon' => 'bi-speedometer2', 'items' => [
                ['title' => 'Business overview', 'route' => 'admin.dashboard', 'icon' => 'bi-grid-1x2-fill', 'active' => ['admin'], 'words' => 'dashboard home today pulse attention'],
                ['title' => 'Heat map', 'route' => 'admin.heat-map', 'icon' => 'bi-pin-map', 'gate' => 'dashboard', 'active' => ['admin/heat-map*'], 'words' => 'demand where requests'],
                ['title' => 'Fleet view', 'route' => 'admin.fleet-map', 'params' => ['all-driver'], 'icon' => 'bi-map-fill', 'gate' => 'dashboard', 'active' => ['admin/fleet-map/*'], 'words' => 'drivers online live map'],
                ['title' => 'Settings center', 'route' => 'admin.settings-center', 'icon' => 'bi-sliders2', 'active' => ['admin/settings-center*'], 'words' => 'configuration setup how to configure'],
            ]],
            ['key' => 'rides', 'title' => 'Rides & deliveries', 'icon' => 'bi-car-front', 'items' => [
                ['title' => 'Trips', 'icon' => 'bi-car-front', 'gate' => $trip, 'words' => 'rides parcels deliveries', 'active' => ['admin/trip/list/*', 'admin/trip/details/*', 'admin/trip/log'], 'children' => [
                    ['title' => 'All trips', 'route' => 'admin.trip.index', 'params' => ['all'], 'active' => ['admin/trip/list/all'], 'badge' => 'trip:all'],
                    ['title' => 'Pending', 'route' => 'admin.trip.index', 'params' => ['pending'], 'active' => ['admin/trip/list/pending'], 'badge' => 'trip:pending', 'words' => 'waiting driver'],
                    ['title' => 'Scheduled', 'route' => 'admin.trip.index', 'params' => ['scheduled'], 'active' => ['admin/trip/list/scheduled'], 'badge' => 'trip:scheduled', 'words' => 'later booked'],
                    ['title' => 'Accepted', 'route' => 'admin.trip.index', 'params' => ['accepted'], 'active' => ['admin/trip/list/accepted'], 'badge' => 'trip:accepted'],
                    ['title' => 'Ongoing', 'route' => 'admin.trip.index', 'params' => ['ongoing'], 'active' => ['admin/trip/list/ongoing'], 'badge' => 'trip:ongoing', 'words' => 'live now'],
                    ['title' => 'Completed', 'route' => 'admin.trip.index', 'params' => ['completed'], 'active' => ['admin/trip/list/completed'], 'badge' => 'trip:completed'],
                    ['title' => 'Cancelled', 'route' => 'admin.trip.index', 'params' => ['cancelled'], 'active' => ['admin/trip/list/cancelled'], 'badge' => 'trip:cancelled'],
                    ['title' => 'Returning', 'route' => 'admin.trip.index', 'params' => ['returning'], 'active' => ['admin/trip/list/returning'], 'badge' => 'trip:returning', 'words' => 'parcel return'],
                    ['title' => 'Returned', 'route' => 'admin.trip.index', 'params' => ['returned'], 'active' => ['admin/trip/list/returned'], 'badge' => 'trip:returned'],
                ]],
                ['title' => 'Parcel refunds', 'icon' => 'bi-arrow-counterclockwise', 'gate' => $trip, 'active' => ['admin/trip/refund/*'], 'children' => [
                    ['title' => 'Waiting', 'route' => 'admin.trip.refund.index', 'params' => ['pending'], 'active' => ['admin/trip/refund/list/pending'], 'badge' => 'refund:pending'],
                    ['title' => 'Approved', 'route' => 'admin.trip.refund.index', 'params' => ['approved'], 'active' => ['admin/trip/refund/list/approved']],
                    ['title' => 'Denied', 'route' => 'admin.trip.refund.index', 'params' => ['denied'], 'active' => ['admin/trip/refund/list/denied']],
                    ['title' => 'Refunded', 'route' => 'admin.trip.refund.index', 'params' => ['refunded'], 'active' => ['admin/trip/refund/list/refunded']],
                ]],
                ['title' => 'Emergency rides', 'route' => 'admin.health-ride.index', 'icon' => 'bi-heart-pulse', 'gate' => $trip, 'active' => ['admin/health-rides*'], 'words' => 'urgence hospital health'],
                ['title' => 'E-Ride Move', 'icon' => 'bi-truck', 'gate' => $trip, 'active' => ['admin/move*'], 'words' => 'trucks moving goods', 'children' => [
                    ['title' => 'Moves', 'route' => 'admin.move.index', 'active' => ['admin/move']],
                    ['title' => 'Deposits', 'route' => 'admin.move.deposits', 'active' => ['admin/move/deposits*']],
                    ['title' => 'Truck directory', 'route' => 'admin.move.directory', 'active' => ['admin/move/directory*']],
                    ['title' => 'Move settings', 'route' => 'admin.move.settings', 'active' => ['admin/move/settings*'], 'setting' => true],
                ]],
                ['title' => 'Booking deposits', 'route' => 'admin.booking-deposit.index', 'icon' => 'bi-wallet2', 'gate' => $trip, 'active' => ['admin/booking-deposit*'], 'words' => 'deposit book later'],
                ['title' => 'Ways to pay', 'route' => 'admin.payment-ways.index', 'icon' => 'bi-cash-coin', 'gate' => $trip, 'active' => ['admin/payment-ways*'], 'words' => 'payment methods mobile money momo orange cash wallet'],
                ['title' => 'Safety alerts', 'route' => 'admin.safety-alert.index', 'params' => ['customer'], 'icon' => 'bi-shield-fill-check', 'gate' => $trip, 'active' => ['admin/safety-alert/*'], 'words' => 'sos solved'],
                ['title' => 'Driver reports', 'route' => 'admin.driver-report.index', 'icon' => 'bi-flag', 'gate' => $trip, 'active' => ['admin/driver-report*'], 'badge' => 'driver_reports', 'words' => 'complaints'],
                ['title' => 'Lost & found', 'route' => 'admin.lost-item.index', 'icon' => 'bi-bag-check', 'gate' => $trip, 'active' => ['admin/lost-items*'], 'badge' => 'lost_items', 'words' => 'forgotten items'],
                ['title' => 'Zones', 'route' => 'admin.zone.index', 'icon' => 'bi-map', 'gate' => 'zone', 'active' => ['admin/zone', 'admin/zone/*'], 'words' => 'areas cities coverage'],
                ['title' => 'Safety map', 'route' => 'admin.safety.index', 'icon' => 'bi-shield-exclamation', 'gate' => 'zone', 'active' => ['admin/safety*'], 'badge' => 'safety_areas', 'words' => 'danger areas'],
                ['title' => 'Parcel types', 'icon' => 'bi-box-seam', 'gate' => 'parcel', 'active' => ['admin/parcel/attribute/*'], 'children' => [
                    ['title' => 'Parcel categories', 'route' => 'admin.parcel.attribute.category.index', 'active' => ['admin/parcel/attribute/category*']],
                    ['title' => 'Parcel weights', 'route' => 'admin.parcel.attribute.weight.index', 'active' => ['admin/parcel/attribute/weight*']],
                ]],
            ]],
            ['key' => 'food', 'title' => 'Food & Shops', 'icon' => 'bi-shop', 'items' => [
                ['title' => 'Orders', 'route' => 'admin.store-order.index', 'icon' => 'bi-bag-check', 'gate' => 'store', 'active' => ['admin/store-orders*'], 'words' => 'food restaurant supermarket'],
                ['title' => 'Partner stores', 'route' => 'admin.store.index', 'icon' => 'bi-shop', 'gate' => 'store', 'active' => ['admin/stores*'], 'words' => 'restaurants shops menus payouts'],
                ['title' => 'Resellers', 'route' => 'admin.store-reseller.index', 'icon' => 'bi-people', 'gate' => 'store_edit', 'active' => ['admin/store-resellers*'], 'badge' => 'resellers_owed', 'words' => 'commission affiliate payouts'],
                ['title' => 'Food & Shops settings', 'route' => 'admin.store-setting.edit', 'icon' => 'bi-sliders', 'gate' => 'store_edit', 'active' => ['admin/store-settings*'], 'setting' => true],
            ]],
            ['key' => 'market', 'title' => 'Market & Jobs', 'icon' => 'bi-basket', 'items' => [
                ['title' => 'E-Ride Market', 'icon' => 'bi-basket', 'gate' => 'place', 'active' => ['admin/market*'], 'words' => 'sellers items marketplace zama', 'children' => [
                    ['title' => 'Moderation', 'route' => 'admin.market.moderation', 'active' => ['admin/market'], 'badge' => 'market_queue', 'words' => 'reports blocked'],
                    ['title' => 'Shops', 'route' => 'admin.market.shops', 'active' => ['admin/market/shops*'], 'words' => 'sellers verified'],
                    ['title' => 'Markets & sheds', 'route' => 'admin.market.venues', 'active' => ['admin/market/venues*'], 'words' => 'main market nkwen'],
                    ['title' => 'Orders', 'route' => 'admin.market.orders', 'active' => ['admin/market/orders*']],
                    ['title' => 'Auctions', 'route' => 'admin.market.auctions', 'active' => ['admin/market/auctions*']],
                    ['title' => 'Services', 'route' => 'admin.market.services', 'active' => ['admin/market/services*'], 'words' => 'bookings'],
                    ['title' => 'Market settings', 'route' => 'admin.market.settings', 'active' => ['admin/market/settings*'], 'setting' => true],
                ]],
                ['title' => 'E-Ride Jobs', 'icon' => 'bi-briefcase', 'gate' => 'place', 'active' => ['admin/jobs*'], 'words' => 'employment hiring', 'children' => [
                    ['title' => 'Jobs', 'route' => 'admin.jobs.index', 'active' => ['admin/jobs']],
                    ['title' => 'Employers', 'route' => 'admin.jobs.employers', 'active' => ['admin/jobs/employers*'], 'badge' => 'employers_pending', 'words' => 'verify'],
                ]],
            ]],
            ['key' => 'travel', 'title' => 'Travel & stays', 'icon' => 'bi-bus-front', 'items' => [
                ['title' => 'Bus agencies', 'route' => 'admin.bus.index', 'icon' => 'bi-bus-front', 'gate' => 'place', 'active' => ['admin/bus*'], 'words' => 'tickets travel commission statements'],
                ['title' => 'Hotel partners', 'route' => 'admin.hotel.index', 'icon' => 'bi-building', 'gate' => 'place', 'active' => ['admin/hotel-partners*'], 'words' => 'rooms guest houses'],
                ['title' => 'Homes & spaces', 'icon' => 'bi-house-door', 'gate' => 'place', 'active' => ['admin/homes*'], 'words' => 'rent houses apartments', 'children' => [
                    ['title' => 'Listings', 'route' => 'admin.listing.index', 'active' => ['admin/homes']],
                    ['title' => 'Visits', 'route' => 'admin.listing.visits', 'active' => ['admin/homes/visits*']],
                    ['title' => 'Reservations', 'route' => 'admin.listing.reservations', 'active' => ['admin/homes/reservations*']],
                    ['title' => 'Reports', 'route' => 'admin.listing.reports', 'active' => ['admin/homes/reports*']],
                    ['title' => 'Payouts', 'route' => 'admin.listing.payouts', 'active' => ['admin/homes/payouts*']],
                    ['title' => 'Homes policy', 'route' => 'admin.listing.settings', 'active' => ['admin/homes/settings*'], 'setting' => true],
                ]],
            ]],
            ['key' => 'explore', 'title' => 'Explore', 'icon' => 'bi-compass', 'items' => [
                ['title' => 'Verified places', 'route' => 'admin.place.index', 'icon' => 'bi-geo-alt', 'gate' => 'place', 'active' => ['admin/places', 'admin/places/create', 'admin/places/*/edit'], 'words' => 'listings explore bamenda'],
                ['title' => 'Tours', 'route' => 'admin.place.tours', 'icon' => 'bi-signpost-split', 'gate' => 'place', 'active' => ['admin/places/tours*'], 'words' => 'guide stops'],
                ['title' => 'Ratings', 'route' => 'admin.place.ratings', 'icon' => 'bi-star', 'gate' => 'place', 'active' => ['admin/places/ratings*'], 'words' => 'reviews'],
                ['title' => 'On-duty pharmacies', 'route' => 'admin.place.on-duty', 'icon' => 'bi-capsule', 'gate' => 'place', 'active' => ['admin/places/on-duty*'], 'words' => 'pharmacie de garde'],
                ['title' => 'Categories', 'route' => 'admin.place.categories', 'icon' => 'bi-grid', 'gate' => 'place', 'active' => ['admin/places/categories*']],
                ['title' => 'Google results', 'route' => 'admin.place.google', 'icon' => 'bi-google', 'gate' => 'place', 'active' => ['admin/places/google*']],
                ['title' => 'Landmarks', 'route' => 'admin.landmark.index', 'icon' => 'bi-signpost-2', 'gate' => 'place', 'active' => ['admin/landmarks*'], 'words' => 'known places'],
                ['title' => 'Explore settings', 'route' => 'admin.place-setting.edit', 'icon' => 'bi-sliders', 'gate' => 'place_edit', 'active' => ['admin/place-settings*'], 'setting' => true],
            ]],
            ['key' => 'people', 'title' => 'People', 'icon' => 'bi-people', 'items' => [
                ['title' => 'Customers', 'icon' => 'bi-person', 'gate' => 'user', 'active' => ['admin/customer*', 'admin/wallet-bonus*'], 'words' => 'riders users', 'children' => [
                    ['title' => 'Customer list', 'route' => 'admin.customer.index', 'active' => ['admin/customer', 'admin/customer/edit/*', 'admin/customer/show*', 'admin/customer/log*']],
                    ['title' => 'Add a customer', 'route' => 'admin.customer.create', 'active' => ['admin/customer/create']],
                    ['title' => 'Customer levels', 'route' => 'admin.customer.level.index', 'active' => ['admin/customer/level*']],
                    ['title' => 'Customer wallets', 'route' => 'admin.customer.wallet.index', 'active' => ['admin/customer/wallet*']],
                    ['title' => 'Wallet bonus', 'route' => 'admin.wallet-bonus.index', 'active' => ['admin/wallet-bonus*']],
                ]],
                ['title' => 'Drivers', 'icon' => 'bi-person-badge', 'gate' => 'user', 'active' => ['admin/driver*'], 'words' => 'riders bikers', 'children' => [
                    ['title' => 'Driver list', 'route' => 'admin.driver.index', 'active' => ['admin/driver', 'admin/driver/edit/*', 'admin/driver/log*', 'admin/driver/cash*', 'admin/driver/show*']],
                    ['title' => 'Add a driver', 'route' => 'admin.driver.create', 'active' => ['admin/driver/create']],
                    ['title' => 'Waiting approval', 'route' => 'admin.driver.verification.unverified-list', 'active' => ['admin/driver/verification*'], 'badge' => 'drivers_unverified', 'words' => 'unverified new drivers'],
                    ['title' => 'Identity updates', 'route' => 'admin.driver.profile-update-request-list', 'active' => ['admin/driver/profile-update-request-list*']],
                    ['title' => 'Driver levels', 'route' => 'admin.driver.level.index', 'active' => ['admin/driver/level*']],
                    ['title' => 'Withdraw requests', 'route' => 'admin.driver.withdraw.requests', 'active' => ['admin/driver/withdraw/request*'], 'badge' => 'withdraw_pending', 'words' => 'payout cash out'],
                    ['title' => 'Withdraw methods', 'route' => 'admin.driver.withdraw-method.index', 'active' => ['admin/driver/withdraw-method*']],
                ]],
                ['title' => 'Vehicles', 'icon' => 'bi-ev-front', 'gate' => 'vehicle', 'active' => ['admin/vehicle*'], 'words' => 'cars bikes', 'children' => [
                    ['title' => 'Vehicle list', 'route' => 'admin.vehicle.index', 'active' => ['admin/vehicle', 'admin/vehicle/show*', 'admin/vehicle/edit*', 'admin/vehicle/log']],
                    ['title' => 'New vehicle requests', 'route' => 'admin.vehicle.request.list', 'active' => ['admin/vehicle/request/*'], 'badge' => 'vehicle_requests'],
                    ['title' => 'Vehicle update requests', 'route' => 'admin.vehicle.update.list', 'active' => ['admin/vehicle/update/*']],
                    ['title' => 'Add a vehicle', 'route' => 'admin.vehicle.create', 'active' => ['admin/vehicle/create']],
                    ['title' => 'Brands, models, categories', 'route' => 'admin.vehicle.attribute-setup.brand.index', 'active' => ['admin/vehicle/attribute-setup/*']],
                ]],
                ['title' => 'ID verification', 'route' => 'admin.id-verification.index', 'icon' => 'bi-person-vcard', 'gate' => 'place', 'active' => ['admin/id-verification*'], 'badge' => 'id_pending', 'words' => 'identity card passport selfie'],
                ['title' => 'Staff & roles', 'icon' => 'bi-person-gear', 'gate' => 'user', 'active' => ['admin/employee*'], 'words' => 'employees admins permissions', 'children' => [
                    ['title' => 'Staff list', 'route' => 'admin.employee.index', 'active' => ['admin/employee', 'admin/employee/edit*', 'admin/employee/log*']],
                    ['title' => 'Add staff', 'route' => 'admin.employee.create', 'active' => ['admin/employee/create']],
                    ['title' => 'Roles', 'route' => 'admin.employee.role.index', 'active' => ['admin/employee/role*']],
                ]],
                ['title' => 'Newsletter', 'route' => 'admin.newsletter.index', 'icon' => 'bi-envelope', 'gate' => 'user', 'active' => ['admin/newsletter*'], 'words' => 'subscribers emails'],
            ]],
            ['key' => 'money', 'title' => 'Money', 'icon' => 'bi-cash-stack', 'items' => [
                ['title' => 'Transactions', 'route' => 'admin.transaction.index', 'icon' => 'bi-arrow-left-right', 'gate' => ['transaction_view', 'transaction_export'], 'active' => ['admin/transaction*']],
                ['title' => 'Earning reports', 'route' => 'admin.report.earning', 'icon' => 'bi-graph-up', 'gate' => ['transaction_view', 'transaction_export'], 'active' => ['admin/report*'], 'words' => 'revenue commission'],
                ['title' => 'Trip fares', 'route' => 'admin.fare.trip.index', 'icon' => 'bi-sign-intersection-y-fill', 'gate' => ['fare_view', 'fare_add'], 'active' => ['admin/fare/trip*'], 'words' => 'prices per km', 'setting' => true],
                ['title' => 'Parcel fares', 'route' => 'admin.fare.parcel.index', 'icon' => 'bi-box', 'gate' => ['fare_view', 'fare_add'], 'active' => ['admin/fare/parcel*'], 'setting' => true],
                ['title' => 'Surge pricing', 'route' => 'admin.fare.surge-pricing.index', 'icon' => 'bi-lightning-charge', 'gate' => ['fare_view', 'fare_add'], 'active' => ['admin/fare/surge-pricing*'], 'setting' => true],
                ['title' => 'Drop fares', 'route' => 'admin.drop-fare.index', 'icon' => 'bi-pin-map', 'gate' => ['fare_view', 'fare_add'], 'active' => ['admin/drop-fares*'], 'words' => 'fixed price places', 'setting' => true],
            ]],
            ['key' => 'growth', 'title' => 'Promotions', 'icon' => 'bi-megaphone', 'items' => [
                ['title' => 'Banners', 'route' => 'admin.promotion.banner-setup.index', 'icon' => 'bi-image', 'gate' => 'promotion', 'active' => ['admin/promotion/banner-setup*']],
                ['title' => 'Coupons', 'route' => 'admin.promotion.coupon-setup.index', 'icon' => 'bi-ticket-perforated', 'gate' => 'promotion', 'active' => ['admin/promotion/coupon-setup*'], 'words' => 'promo codes'],
                ['title' => 'Discounts', 'route' => 'admin.promotion.discount-setup.index', 'icon' => 'bi-percent', 'gate' => 'promotion', 'active' => ['admin/promotion/discount-setup*']],
                ['title' => 'Send a notification', 'route' => 'admin.promotion.send-notification.index', 'icon' => 'bi-bell', 'gate' => 'promotion', 'active' => ['admin/promotion/send-notification*'], 'words' => 'push message broadcast'],
                ['title' => 'Blog', 'route' => 'admin.blog.index', 'icon' => 'bi-layout-text-window', 'gate' => 'blog_view', 'active' => ['admin/blog*'], 'words' => 'articles posts'],
            ]],
            ['key' => 'support', 'title' => 'Support', 'icon' => 'bi-headset', 'items' => [
                ['title' => 'Help & support chat', 'route' => 'admin.chatting', 'icon' => 'bi-chat-left-dots', 'gate' => 'chatting_view', 'active' => ['admin/chatting*'], 'words' => 'messages conversations'],
                ['title' => 'Ask E-Ride assistant', 'route' => 'admin.assistant.index', 'icon' => 'bi-stars', 'gate' => 'place', 'active' => ['admin/assistant*'], 'words' => 'ai knowledge chatbot gemini'],
            ]],
            ['key' => 'settings', 'title' => 'Settings', 'icon' => 'bi-gear', 'items' => [
                ['title' => 'Settings center', 'route' => 'admin.settings-center', 'icon' => 'bi-sliders2', 'active' => ['admin/settings-center*'], 'words' => 'configuration all settings'],
                ['title' => 'Business setup', 'route' => 'admin.business.setup.info.index', 'icon' => 'bi-briefcase', 'gate' => 'business', 'active' => ['admin/business/setup*'], 'words' => 'name logo currency rules', 'setting' => true],
                ['title' => 'Pages & landing', 'icon' => 'bi-window', 'gate' => 'business', 'active' => ['admin/business/pages-media*'], 'children' => [
                    ['title' => 'Business pages', 'route' => 'admin.business.pages-media.business-page.index', 'active' => ['admin/business/pages-media/business-page*'], 'words' => 'terms privacy about'],
                    ['title' => 'Landing page', 'route' => 'admin.business.pages-media.landing-page.look.index', 'active' => ['admin/business/pages-media/landing-page/*'], 'words' => 'website home'],
                    ['title' => 'Social media links', 'route' => 'admin.business.pages-media.social-media', 'active' => ['admin/business/pages-media/social-media*']],
                    ['title' => 'Additional data', 'route' => 'admin.business.pages-media.additional-data-setup.index', 'params' => ['customer'], 'active' => ['admin/business/pages-media/additional-data-setup*']],
                ]],
                ['title' => 'Connections', 'icon' => 'bi-plug', 'gate' => 'business', 'active' => ['admin/business/configuration*'], 'words' => 'third party api keys', 'children' => [
                    ['title' => 'Payment methods', 'route' => 'admin.business.configuration.third-party.payment-method.index', 'active' => ['admin/business/configuration/third-party/payment-method*'], 'words' => 'fapshi mobile money momo mtn orange money keys', 'setting' => true],
                    ['title' => 'SMS gateway', 'route' => 'admin.business.configuration.third-party.sms-gateway.index', 'active' => ['admin/business/configuration/third-party/sms-gateway*'], 'setting' => true],
                    ['title' => 'Google Maps', 'route' => 'admin.business.configuration.third-party.google-map.index', 'active' => ['admin/business/configuration/third-party/google-map*'], 'words' => 'maps key', 'setting' => true],
                    ['title' => 'Email', 'route' => 'admin.business.configuration.third-party.email-config.index', 'active' => ['admin/business/configuration/third-party/email-config*'], 'words' => 'smtp mail', 'setting' => true],
                    ['title' => 'Social login', 'route' => 'admin.business.configuration.third-party.social-login.index', 'active' => ['admin/business/configuration/third-party/social-login*'], 'words' => 'google facebook apple', 'setting' => true],
                    ['title' => 'Firebase OTP', 'route' => 'admin.business.configuration.third-party.firebase-otp.index', 'active' => ['admin/business/configuration/third-party/firebase-otp*'], 'setting' => true],
                    ['title' => 'reCAPTCHA', 'route' => 'admin.business.configuration.third-party.recaptcha.index', 'active' => ['admin/business/configuration/third-party/recaptcha*'], 'setting' => true],
                    ['title' => 'Push notifications', 'route' => 'admin.business.configuration.notification.index', 'params' => ['type' => 'schedule-trip'], 'active' => ['admin/business/configuration/notification*'], 'words' => 'firebase messages templates', 'setting' => true],
                    ['title' => 'Face verification', 'route' => 'admin.business.configuration.face-verification-api.index', 'active' => ['admin/business/configuration/face-verification-api*'], 'words' => 'aws rekognition selfie', 'setting' => true],
                    ['title' => 'AI setup', 'route' => 'admin.business.configuration.ai-setup.index', 'active' => ['admin/business/configuration/ai-setup*'], 'setting' => true],
                    ['title' => 'Login settings', 'route' => 'admin.business.configuration.login-settings.index', 'params' => ['tab' => 'customer'], 'active' => ['admin/business/configuration/login-settings*'], 'words' => 'otp attempts', 'setting' => true],
                ]],
                ['title' => 'System', 'icon' => 'bi-hdd-stack', 'gate' => 'business', 'active' => ['admin/business/environment-setup*', 'admin/business/app-version-setup*', 'admin/business/clean-database*', 'admin/business/languages*', 'admin/business/deep-linking*'], 'children' => [
                    ['title' => 'App versions', 'route' => 'admin.business.app-version-setup.index', 'active' => ['admin/business/app-version-setup*'], 'words' => 'minimum update force', 'setting' => true],
                    ['title' => 'Languages', 'route' => 'admin.business.languages.index', 'active' => ['admin/business/languages*'], 'words' => 'french translation', 'setting' => true],
                    ['title' => 'Deep links', 'route' => 'admin.business.deep-linking.index', 'active' => ['admin/business/deep-linking*'], 'setting' => true],
                    ['title' => 'Environment', 'route' => 'admin.business.environment-setup.index', 'active' => ['admin/business/environment-setup*'], 'setting' => true],
                    ['title' => 'Clean database', 'route' => 'admin.business.clean-database.index', 'active' => ['admin/business/clean-database*'], 'words' => 'delete reset'],
                ]],
            ]],
        ];
    }

    /** The map as this admin may see it: permissions applied, URLs and "active" worked out. Missing routes are skipped. */
    public static function sections(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = [];
        foreach (self::map() as $section) {
            $items = [];
            foreach ($section['items'] as $item) {
                if (!self::allowed($item['gate'] ?? null)) {
                    continue;
                }
                if (!empty($item['children'])) {
                    $children = [];
                    foreach ($item['children'] as $child) {
                        if (($child = self::resolve($child)) && self::allowed($child['gate'] ?? null)) {
                            $children[] = $child;
                        }
                    }
                    if (!$children) {
                        continue;
                    }
                    $item['children'] = $children;
                    $item['url'] = $children[0]['url'];
                    $item['is_active'] = self::is($item['active'] ?? []) || collect($children)->contains('is_active', true);
                    $item['badge_count'] = array_sum(array_column($children, 'badge_count'));
                } elseif (!($item = self::resolve($item))) {
                    continue;
                }
                $items[] = $item;
            }
            if ($items) {
                $section['items'] = $items;
                $section['is_active'] = collect($items)->contains('is_active', true);
                $out[] = $section;
            }
        }
        return self::$cache = $out;
    }

    /** Every page, flat, for "Go to…": title, url, where it sits, icon, more words. */
    public static function flat(): array
    {
        $out = [];
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                if (!empty($item['children'])) {
                    foreach ($item['children'] as $child) {
                        $out[] = ['t' => translate($child['title']), 'u' => $child['url'], 'g' => translate($section['title']) . ' › ' . translate($item['title']),
                            'i' => $item['icon'] ?? $section['icon'], 'w' => trim(($child['words'] ?? '') . ' ' . ($item['words'] ?? '')), 's' => !empty($child['setting'])];
                    }
                } else {
                    $out[] = ['t' => translate($item['title']), 'u' => $item['url'], 'g' => translate($section['title']), 'i' => $item['icon'] ?? $section['icon'],
                        'w' => $item['words'] ?? '', 's' => !empty($item['setting'])];
                }
            }
        }
        // The same page twice (Settings center in two places): once.
        $out = array_values(collect($out)->unique('u')->all());

        // Settings center cards: their plain words find the page ("mobile money" finds
        // the Fapshi keys), and a card with no menu entry becomes its own result.
        $byUrl = [];
        foreach ($out as $i => $p) {
            $byUrl[$p['u']] = $i;
        }
        foreach (SettingsCatalog::groups() as $group) {
            foreach ($group['cards'] as $card) {
                if (!self::allowed($card['gate'] ?? null) || !Route::has($card['route'])) {
                    continue;
                }
                try {
                    $url = route($card['route'], $card['params'] ?? []);
                } catch (Throwable) {
                    continue;
                }
                $words = translate($card['title']) . ' ' . translate($card['about'] ?? '') . ' ' . $card['title'] . ' ' . ($card['about'] ?? '');
                if (isset($byUrl[$url])) {
                    $out[$byUrl[$url]]['w'] = trim($out[$byUrl[$url]]['w'] . ' ' . $words);
                    continue;
                }
                $byUrl[$url] = count($out);
                $out[] = ['t' => translate($card['title']), 'u' => $url, 'g' => translate('Settings center') . ' › ' . translate($group['title']),
                    'i' => $card['icon'] ?? 'bi-gear', 'w' => $words, 's' => true];
            }
        }
        return $out;
    }

    public static function allowed(string|array|null $gate): bool
    {
        if ($gate === null) {
            return true;
        }
        try {
            $abilities = [];
            foreach ((array)$gate as $g) {
                $abilities = array_merge($abilities, str_contains($g, '_') || in_array($g, ['dashboard', 'super-admin'], true)
                    ? [$g] : array_map(fn($a) => $g . '_' . $a, ['view', 'add', 'edit', 'delete', 'log', 'export']));
            }
            return Gate::any($abilities);
        } catch (Throwable) {
            return false;
        }
    }

    /** A count for a badge (cached for a minute; 0 when the table is not there). */
    public static function badge(string $key): int
    {
        if (array_key_exists($key, self::$badgeCache)) {
            return self::$badgeCache[$key];
        }
        $count = 0;
        try {
            $count = (int)cache()->remember('admin_nav_badge_' . $key, 60, fn() => self::count($key));
        } catch (Throwable) {
        }
        return self::$badgeCache[$key] = $count;
    }

    private static function count(string $key): int
    {
        [$name, $arg] = array_pad(explode(':', $key, 2), 2, null);
        return match ($name) {
            // The same counts the trip lists always showed (pending: not scheduled; accepted: also on the way to pickup).
            'trip' => (function () use ($arg) {
                $trips = app(\Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface::class);
                return match ($arg) {
                    'all' => $trips->index()->count(),
                    'pending' => $trips->getBy(criteria: ['current_status' => PENDING], whereInCriteria: ['ride_request_type' => ['regular', null]])->count(),
                    'scheduled' => $trips->getBy(criteria: ['current_status' => PENDING, 'ride_request_type' => 'scheduled'])->count(),
                    'accepted' => $trips->getBy(whereInCriteria: ['current_status' => [ACCEPTED, OUT_FOR_PICKUP]])->count(),
                    default => $trips->getBy(criteria: ['current_status' => $arg])->count(),
                };
            })(),
            'refund' => app(\Modules\TripManagement\Service\Interfaces\ParcelRefundServiceInterface::class)->getBy(criteria: ['status' => $arg])->count(),
            'safety_areas' => \Modules\ZoneManagement\Entities\SafetyArea::where('confirmed', 0)->whereNull('lifted_at')->where('ends_at', '>', now())->count(),
            'driver_reports' => \Modules\TripManagement\Entities\TripReport::where('status', 'open')->count(),
            'lost_items' => \Modules\TripManagement\Entities\LostItem::whereIn('status', \Modules\TripManagement\Entities\LostItem::OPEN)->whereNotNull('escalated_at')->count(),
            'id_pending' => \Modules\UserManagement\Entities\IdVerification::where('status', 'pending')->count(),
            default => \Modules\AdminModule\Lib\BusinessPulse::attentionCount($name),
        };
    }

    private static function resolve(array $item): ?array
    {
        if (empty($item['route']) || !Route::has($item['route'])) {
            return null;
        }
        try {
            $item['url'] = route($item['route'], $item['params'] ?? []);
        } catch (Throwable) {
            return null;
        }
        $item['is_active'] = self::is($item['active'] ?? []);
        $item['badge_count'] = !empty($item['badge']) ? self::badge($item['badge']) : 0;
        return $item;
    }

    private static function is(array $patterns): bool
    {
        foreach ($patterns as $p) {
            if (Request::is($p)) {
                return true;
            }
        }
        return false;
    }
}
