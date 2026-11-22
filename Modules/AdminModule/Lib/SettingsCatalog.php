<?php

namespace Modules\AdminModule\Lib;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Every configuration of E-Ride, in plain words: where it is, what it controls, and
 * (where it can be checked) whether it is set up. The Settings center shows it; "Go to…"
 * finds the same pages through AdminNav.
 *
 * A card: title, route (+ params), icon, gate (as AdminNav), about (what it controls),
 * when (when you would change it), status (a check below: on / off / missing / null).
 */
class SettingsCatalog
{
    public static function groups(): array
    {
        return [
            ['key' => 'start', 'title' => 'Start here', 'about' => 'Who you are and what the apps show first.', 'cards' => [
                ['title' => 'Business information', 'route' => 'admin.business.setup.info.index', 'icon' => 'bi-briefcase', 'gate' => 'business',
                    'about' => 'Name, logo, contact, currency, time zone, maintenance mode.', 'when' => 'Once at launch, and when contact details change.', 'status' => 'business_info'],
                ['title' => 'Zones', 'route' => 'admin.zone.index', 'icon' => 'bi-map', 'gate' => 'zone',
                    'about' => 'The areas E-Ride serves. Fares, drivers and services all work per zone.', 'when' => 'Before opening a new town or neighbourhood.', 'status' => 'zones'],
                ['title' => 'App versions', 'route' => 'admin.business.app-version-setup.index', 'icon' => 'bi-phone', 'gate' => 'business',
                    'about' => 'The lowest app version allowed, and the store links.', 'when' => 'After a release that old apps must not miss.'],
                ['title' => 'Languages', 'route' => 'admin.business.languages.index', 'icon' => 'bi-translate', 'gate' => 'business',
                    'about' => 'English and French texts of the panel and messages.', 'when' => 'When a word reads wrong.'],
            ]],
            ['key' => 'money', 'title' => 'Money and prices', 'about' => 'How people pay, and what each trip and delivery costs.', 'cards' => [
                ['title' => 'Mobile Money payments (Fapshi)', 'route' => 'admin.business.configuration.third-party.payment-method.index', 'icon' => 'bi-phone-vibrate', 'gate' => 'business',
                    'about' => 'MTN MoMo and Orange Money through Fapshi: the keys and the switch.', 'when' => 'At launch, and if Fapshi changes the keys.', 'status' => 'fapshi'],
                ['title' => 'Ways to pay', 'route' => 'admin.payment-ways.index', 'icon' => 'bi-cash-coin', 'gate' => 'trip',
                    'about' => 'Which ways to pay customers can choose for rides and deliveries.', 'when' => 'When you add or stop a way to pay.'],
                ['title' => 'Trip fares', 'route' => 'admin.fare.trip.index', 'icon' => 'bi-sign-intersection-y-fill', 'gate' => ['fare_view', 'fare_add'],
                    'about' => 'Base fare, price per km and per minute, per zone and vehicle type.', 'when' => 'When fuel or competition changes prices.'],
                ['title' => 'Parcel fares', 'route' => 'admin.fare.parcel.index', 'icon' => 'bi-box', 'gate' => ['fare_view', 'fare_add'],
                    'about' => 'Delivery prices per zone, parcel category and weight. Food & Shops deliveries use them too.', 'when' => 'When delivery prices change.'],
                ['title' => 'Surge pricing', 'route' => 'admin.fare.surge-pricing.index', 'icon' => 'bi-lightning-charge', 'gate' => ['fare_view', 'fare_add'],
                    'about' => 'Higher fares at busy times or places.', 'when' => 'For rush hours, rain, events.'],
                ['title' => 'Drop fares', 'route' => 'admin.drop-fare.index', 'icon' => 'bi-pin-map', 'gate' => ['fare_view', 'fare_add'],
                    'about' => 'Fixed prices to well-known places.', 'when' => 'When a place needs one simple price.'],
                ['title' => 'Booking deposits', 'route' => 'admin.booking-deposit.settings', 'icon' => 'bi-wallet2', 'gate' => 'trip',
                    'about' => 'The deposit to book a ride for later, and when it is kept.', 'when' => 'If too many booked rides are not taken.'],
                ['title' => 'Driver cash-out methods', 'route' => 'admin.driver.withdraw-method.index', 'icon' => 'bi-bank', 'gate' => 'user',
                    'about' => 'How drivers receive their money.', 'when' => 'When a payout channel is added.'],
                ['title' => 'Referral rewards', 'route' => 'admin.business.setup.referral-earning.index', 'icon' => 'bi-gift', 'gate' => 'business',
                    'about' => 'What people get for inviting friends.', 'when' => 'For a growth campaign.'],
            ]],
            ['key' => 'rides', 'title' => 'Rides and deliveries rules', 'about' => 'How trips work, for customers and drivers.', 'cards' => [
                ['title' => 'Trip rules', 'route' => 'admin.business.setup.trip-fare.trips', 'icon' => 'bi-car-front', 'gate' => 'business',
                    'about' => 'Bidding, waiting, scheduled trips, how far drivers are searched.', 'when' => 'When customers wait too long or drivers are too far.'],
                ['title' => 'Cancellations and penalties', 'route' => 'admin.business.setup.trip-fare.penalty', 'icon' => 'bi-x-octagon', 'gate' => 'business',
                    'about' => 'Cancellation fees, idle fees, reasons people can pick.', 'when' => 'When cancellations go up.'],
                ['title' => 'Driver rules', 'route' => 'admin.business.setup.driver.index', 'icon' => 'bi-person-badge', 'gate' => 'business',
                    'about' => 'What drivers must do and can see, reviews, their documents.', 'when' => 'When onboarding or quality rules change.'],
                ['title' => 'Customer rules', 'route' => 'admin.business.setup.customer.index', 'icon' => 'bi-person', 'gate' => 'business',
                    'about' => 'Customer reviews, wallet and loyalty.', 'when' => 'Rarely.'],
                ['title' => 'Parcel rules', 'route' => 'admin.business.setup.parcel.index', 'icon' => 'bi-box-seam', 'gate' => 'business',
                    'about' => 'Who pays for a parcel, return fees, cancellation reasons.', 'when' => 'When delivery disputes repeat.'],
                ['title' => 'Parcel refunds', 'route' => 'admin.business.setup.parcel-refund.index', 'icon' => 'bi-arrow-counterclockwise', 'gate' => 'business',
                    'about' => 'When a damaged or lost parcel is refunded, and the reasons.', 'when' => 'When the refund policy changes.'],
                ['title' => 'Safety', 'route' => 'admin.business.setup.safety-precaution.index', 'icon' => 'bi-shield-check', 'gate' => 'business',
                    'about' => 'SOS reasons, safety tips, emergency numbers.', 'when' => 'When emergency numbers or tips change.'],
                ['title' => 'Emergency rides', 'route' => 'admin.health-ride.index', 'icon' => 'bi-heart-pulse', 'gate' => 'trip',
                    'about' => 'Urgent rides to hospitals, clinics and pharmacies.', 'when' => 'When the rules for urgent rides change.'],
                ['title' => 'E-Ride Move', 'route' => 'admin.move.settings', 'icon' => 'bi-truck', 'gate' => 'trip',
                    'about' => 'Trucks for moving goods: deposits and rules.', 'when' => 'When Move prices or deposits change.'],
                ['title' => 'Lost and found', 'route' => 'admin.lost-item.index', 'icon' => 'bi-bag-check', 'gate' => 'trip',
                    'about' => 'How forgotten items are reported and returned.', 'when' => 'Rarely.'],
                ['title' => 'Vehicle types and brands', 'route' => 'admin.vehicle.attribute-setup.brand.index', 'icon' => 'bi-ev-front', 'gate' => 'vehicle',
                    'about' => 'Brands, models and categories drivers choose from.', 'when' => 'When a new kind of vehicle joins.'],
                ['title' => 'Parcel categories and weights', 'route' => 'admin.parcel.attribute.category.index', 'icon' => 'bi-boxes', 'gate' => 'parcel',
                    'about' => 'What customers can send and how heavy.', 'when' => 'When you add a kind of parcel.'],
            ]],
            ['key' => 'services', 'title' => 'Services', 'about' => 'Each service has a switch and its own rules.', 'cards' => [
                ['title' => 'Food & Shops', 'route' => 'admin.store-setting.edit', 'icon' => 'bi-shop', 'gate' => 'store_edit',
                    'about' => 'Ordering on or off, how long partners have to answer, delivery by the shop, resellers.', 'when' => 'When partners or deliveries need other timings.', 'status' => 'store'],
                ['title' => 'E-Ride Market', 'route' => 'admin.market.settings', 'icon' => 'bi-basket', 'gate' => 'place',
                    'about' => 'Market on or off, banned words, items per shop, orders, auctions, services, zama.', 'when' => 'When moderation or sellers need new rules.', 'status' => 'market'],
                ['title' => 'E-Ride Jobs', 'route' => 'admin.jobs.index', 'icon' => 'bi-briefcase', 'gate' => 'place',
                    'about' => 'How long jobs stay open, minimum pay, alerts. Settings are at the bottom of the page.', 'when' => 'When job posts need other limits.'],
                ['title' => 'Bus travel', 'route' => 'admin.bus.index', 'icon' => 'bi-bus-front', 'gate' => 'place',
                    'about' => 'Bus tickets on or off, E-Ride commission, payment and refund timings, WhatsApp. Settings are on the page.', 'when' => 'When agencies or commission rates change.', 'status' => 'bus'],
                ['title' => 'Hotels', 'route' => 'admin.hotel.index', 'icon' => 'bi-building', 'gate' => 'place',
                    'about' => 'Hotels on or off, nights and rooms a guest can ask for. Settings are on the page.', 'when' => 'Rarely.', 'status' => 'hotels'],
                ['title' => 'Homes & spaces', 'route' => 'admin.listing.settings', 'icon' => 'bi-house-door', 'gate' => 'place',
                    'about' => 'Reservation fee, answer and payment windows, neighbourhoods.', 'when' => 'When reservations need other rules.'],
                ['title' => 'Explore', 'route' => 'admin.place-setting.edit', 'icon' => 'bi-compass', 'gate' => 'place_edit',
                    'about' => 'Search distance, Google results, emergency note.', 'when' => 'When places look too far or too few.'],
                ['title' => 'Ask E-Ride assistant', 'route' => 'admin.assistant.index', 'icon' => 'bi-stars', 'gate' => 'place',
                    'about' => 'The AI providers, their order, daily limits, and what the assistant knows.', 'when' => 'When answers are wrong or a provider runs out.', 'status' => 'assistant'],
            ]],
            ['key' => 'messages', 'title' => 'Messages and sign-in', 'about' => 'How E-Ride reaches people, and how they sign in.', 'cards' => [
                ['title' => 'Push notifications', 'route' => 'admin.business.configuration.notification.index', 'params' => ['type' => 'schedule-trip'], 'icon' => 'bi-bell', 'gate' => 'business',
                    'about' => 'Firebase, and the text of each notification.', 'when' => 'When a notification reads wrong.'],
                ['title' => 'SMS', 'route' => 'admin.business.configuration.third-party.sms-gateway.index', 'icon' => 'bi-chat-dots', 'gate' => 'business',
                    'about' => 'The SMS provider for codes and messages.', 'when' => 'At launch, or if codes stop arriving.', 'status' => 'sms'],
                ['title' => 'Email', 'route' => 'admin.business.configuration.third-party.email-config.index', 'icon' => 'bi-envelope', 'gate' => 'business',
                    'about' => 'The mail server for emails.', 'when' => 'If emails stop arriving.'],
                ['title' => 'Phone codes (Firebase OTP)', 'route' => 'admin.business.configuration.third-party.firebase-otp.index', 'icon' => 'bi-shield-lock', 'gate' => 'business',
                    'about' => 'Sign-in codes by Firebase.', 'when' => 'If sign-in codes fail.'],
                ['title' => 'Sign-in limits', 'route' => 'admin.business.configuration.login-settings.index', 'params' => ['tab' => 'customer'], 'icon' => 'bi-door-open', 'gate' => 'business',
                    'about' => 'Code attempts and blocks.', 'when' => 'When people are blocked too fast.'],
                ['title' => 'Social sign-in', 'route' => 'admin.business.configuration.third-party.social-login.index', 'icon' => 'bi-google', 'gate' => 'business',
                    'about' => 'Google, Facebook and Apple sign-in.', 'when' => 'Rarely.'],
                ['title' => 'Support replies', 'route' => 'admin.business.setup.chatting-setup.index', 'icon' => 'bi-chat-left-text', 'gate' => 'business',
                    'about' => 'Saved replies and questions for the support chat.', 'when' => 'When the same question comes back often.'],
            ]],
            ['key' => 'trust', 'title' => 'Trust and verification', 'about' => 'Who is who, and keeping people safe.', 'cards' => [
                ['title' => 'Face check', 'route' => 'admin.business.configuration.face-verification-api.index', 'icon' => 'bi-person-bounding-box', 'gate' => 'business',
                    'about' => 'Matches a selfie with the ID photo (AWS).', 'when' => 'At launch, or if checks fail.', 'status' => 'face'],
                ['title' => 'Maps', 'route' => 'admin.business.configuration.third-party.google-map.index', 'icon' => 'bi-geo-alt', 'gate' => 'business',
                    'about' => 'The Google Maps keys for maps, places and routes.', 'when' => 'At launch, or if maps stop showing.', 'status' => 'maps'],
                ['title' => 'reCAPTCHA', 'route' => 'admin.business.configuration.third-party.recaptcha.index', 'icon' => 'bi-robot', 'gate' => 'business',
                    'about' => 'Stops robots on the website forms.', 'when' => 'If spam starts.'],
                ['title' => 'AI setup', 'route' => 'admin.business.configuration.ai-setup.index', 'icon' => 'bi-cpu', 'gate' => 'business',
                    'about' => 'The AI used for blog writing.', 'when' => 'Rarely.'],
            ]],
            ['key' => 'website', 'title' => 'Website and links', 'about' => 'What people see before they install.', 'cards' => [
                ['title' => 'Landing page', 'route' => 'admin.business.pages-media.landing-page.look.index', 'icon' => 'bi-window', 'gate' => 'business',
                    'about' => 'The home page of the website: look, sections, services.', 'when' => 'For campaigns or new services.'],
                ['title' => 'Business pages', 'route' => 'admin.business.pages-media.business-page.index', 'icon' => 'bi-file-text', 'gate' => 'business',
                    'about' => 'Terms, privacy, about us.', 'when' => 'When the law or your rules change.'],
                ['title' => 'Social media links', 'route' => 'admin.business.pages-media.social-media', 'icon' => 'bi-share', 'gate' => 'business',
                    'about' => 'Links shown on the website and apps.', 'when' => 'Rarely.'],
                ['title' => 'Deep links', 'route' => 'admin.business.deep-linking.index', 'icon' => 'bi-link-45deg', 'gate' => 'business',
                    'about' => 'Links that open the apps directly.', 'when' => 'When the website domain changes.'],
            ]],
            ['key' => 'system', 'title' => 'System', 'about' => 'Technical settings. Change only if you know why.', 'cards' => [
                ['title' => 'Environment', 'route' => 'admin.business.environment-setup.index', 'icon' => 'bi-hdd-stack', 'gate' => 'business',
                    'about' => 'App mode and technical values.', 'when' => 'Only with the technical team.'],
                ['title' => 'Staff and roles', 'route' => 'admin.employee.role.index', 'icon' => 'bi-person-gear', 'gate' => 'user',
                    'about' => 'Who can open which part of this panel.', 'when' => 'When someone joins or leaves the team.'],
                ['title' => 'Clean database', 'route' => 'admin.business.clean-database.index', 'icon' => 'bi-trash3', 'gate' => 'business',
                    'about' => 'Deletes data for good. For test data only.', 'when' => 'Before launch, with care.', 'danger' => true],
            ]],
        ];
    }

    /** The groups this admin may see, each card with its URL and status. */
    public static function forViewer(): array
    {
        $out = [];
        foreach (self::groups() as $group) {
            $cards = [];
            foreach ($group['cards'] as $card) {
                if (!AdminNav::allowed($card['gate'] ?? null) || !Route::has($card['route'])) {
                    continue;
                }
                try {
                    $card['url'] = route($card['route'], $card['params'] ?? []);
                } catch (Throwable) {
                    continue;
                }
                $card['state'] = isset($card['status']) ? self::status($card['status']) : null;
                $cards[] = $card;
            }
            if ($cards) {
                $group['cards'] = $cards;
                $out[] = $group;
            }
        }
        return $out;
    }

    /** on | off | missing | null (cannot tell), with a few words. */
    public static function status(string $key): ?array
    {
        try {
            return match ($key) {
                'business_info' => filled(businessConfig('business_name', 'business_information')?->value) && filled(businessConfig('currency_code', 'business_information')?->value)
                    ? ['on', 'Filled in'] : ['missing', 'Name or currency missing'],
                'zones' => ($n = DB::table('zones')->where('is_active', 1)->count()) > 0 ? ['on', $n . ' active'] : ['missing', 'No active zone'],
                'fapshi' => self::payment('fapshi'),
                'sms' => Schema::hasTable('settings') && DB::table('settings')->where('settings_type', 'sms_config')->where('is_active', 1)->exists()
                    ? ['on', 'A provider is on'] : ['off', 'No provider on'],
                'maps' => filled(data_get(businessConfig('google_map_api', 'google_map_api')?->value, 'map_api_key')) ? ['on', 'Key set'] : ['missing', 'No key'],
                'face' => DB::table('business_settings')->where('settings_type', 'face_verification_settings')->whereNotNull('value')->where('value', '!=', '')->exists()
                    ? ['on', 'Set'] : ['missing', 'Not set'],
                'store' => \Modules\StoreManagement\Lib\StoreSettings::enabled() ? ['on', 'Ordering on'] : ['off', 'Ordering off'],
                'market' => \Modules\MarketManagement\Lib\MarketSettings::enabled() ? ['on', 'Market on'] : ['off', 'Market off'],
                'bus' => self::flag('bus_settings', ['bus_enabled', 'enabled', 'bus_status']),
                'hotels' => self::flag('hotel_settings', ['hotel_enabled', 'enabled', 'hotels_enabled']),
                'assistant' => DB::table('business_settings')->where('key_name', 'like', 'assistant%')->exists() ? ['on', 'Set'] : null,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private static function payment(string $gateway): ?array
    {
        if (!Schema::hasTable('settings')) {
            return null;
        }
        $row = DB::table('settings')->where('settings_type', 'payment_config')->where('key_name', $gateway)->first();
        if (!$row) {
            return ['missing', 'Not set'];
        }
        return (int)$row->is_active === 1 ? ['on', 'Switched on'] : ['off', 'Switched off'];
    }

    private static function flag(string $type, array $keys): ?array
    {
        $row = DB::table('business_settings')->where('settings_type', $type)->whereIn('key_name', $keys)->first();
        if (!$row) {
            return null;
        }
        $v = json_decode((string)$row->value, true) ?? $row->value;
        return in_array($v, [1, '1', true, 'on'], true) ? ['on', 'On'] : ['off', 'Off'];
    }
}
