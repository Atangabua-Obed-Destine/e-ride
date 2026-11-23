<?php

namespace Modules\AdminModule\Lib;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * How the business is moving, end to end: every service today, over the last 7 days and
 * against the 7 days before; and what needs someone (approvals, payouts owed, reports,
 * stuck orders), each with the page where it is handled. Same criteria as the admin pages
 * that handle them. Cached for a minute; a missing table counts as nothing.
 */
class BusinessPulse
{
    private const TTL = 60;
    private static array $memo = [];

    // ================================================================== overview

    private static function computeOverview(): array
    {
        $now = now();
        $ranges = [
            'today' => [$now->copy()->startOfDay(), $now],
            'week' => [$now->copy()->subDays(6)->startOfDay(), $now],
            'prev' => [$now->copy()->subDays(13)->startOfDay(), $now->copy()->subDays(7)->endOfDay()],
        ];
        $m = function (string $label, callable $q, bool $money = false) use ($ranges) {
            $row = ['label' => $label, 'money' => $money];
            foreach ($ranges as $k => $r) {
                try {
                    $row[$k] = (float)$q($r);
                } catch (Throwable) {
                    $row[$k] = null;
                }
            }
            return $row;
        };
        $trip = \Modules\TripManagement\Entities\TripRequest::class;
        $storeTrips = fn() => DB::table('store_orders')->whereNotNull('trip_request_id')->select('trip_request_id');
        $completed = fn(string $type, array $r) => $trip::where('type', $type)->where('current_status', COMPLETED)
            ->where('is_move', false)->where(fn($q) => $q->whereNull('is_health_ride')->orWhere('is_health_ride', 0))
            ->whereHas('tripStatus', fn($q) => $q->whereBetween('completed', $r));

        $cards = [];
        $cards[] = ['key' => 'rides', 'title' => 'Rides', 'icon' => 'bi-car-front', 'gate' => 'trip', 'route' => 'admin.trip.index', 'params' => ['completed'], 'metrics' => [
            $m('Rides done', fn($r) => $completed(RIDE_REQUEST, $r)->count()),
            $m('Fares paid', fn($r) => $completed(RIDE_REQUEST, $r)->sum('paid_fare'), true),
            $m('Cancelled', fn($r) => $trip::where('type', RIDE_REQUEST)->where('current_status', CANCELLED)->whereHas('tripStatus', fn($q) => $q->whereBetween('cancelled', $r))->count()),
            $m('Emergency rides', fn($r) => $trip::where('is_health_ride', 1)->whereBetween('created_at', $r)->count()),
        ]];
        $cards[] = ['key' => 'parcels', 'title' => 'Parcels and moves', 'icon' => 'bi-box-seam', 'gate' => 'trip', 'route' => 'admin.trip.index', 'params' => ['all'], 'metrics' => [
            $m('Parcels delivered', fn($r) => $completed(PARCEL, $r)->whereNotIn('id', $storeTrips())->count()),
            $m('Shop deliveries', fn($r) => $completed(PARCEL, $r)->whereIn('id', $storeTrips())->count()),
            $m('Moves booked', fn($r) => $trip::where('is_move', true)->whereBetween('created_at', $r)->count()),
        ]];
        $cards[] = ['key' => 'money', 'title' => 'E-Ride earned', 'icon' => 'bi-cash-stack', 'gate' => ['transaction_view', 'transaction_export'], 'route' => 'admin.report.earning', 'metrics' => [
            $m('Trip commission', fn($r) => \Modules\TripManagement\Entities\TripRequestFee::whereHas('tripRequest', fn($q) => $q->where('payment_status', PAID)->whereBetween('updated_at', $r))
                ->where(fn($q) => $q->whereNull('cancelled_by')->orWhere('cancelled_by', 'customer'))->sum('admin_commission'), true),
            $m('Move booking fees', fn($r) => DB::table('transactions')->where('attribute', 'move_booking_fee')->whereBetween('created_at', $r)->sum('credit'), true),
            $m('Bus commission', fn($r) => \Modules\BusManagement\Entities\BusBooking::where('status', 'paid')->whereBetween('paid_at', $r)->sum('commission'), true),
            $m('Homes fees', fn($r) => \Modules\PlaceManagement\Entities\ListingReservation::whereNotNull('paid_at')->whereBetween('paid_at', $r)->sum('fee'), true),
        ]];
        $cards[] = ['key' => 'food', 'title' => 'Food & Shops', 'icon' => 'bi-shop', 'gate' => 'store', 'route' => 'admin.store-order.index', 'metrics' => [
            $m('Orders placed', fn($r) => \Modules\StoreManagement\Entities\StoreOrder::whereBetween('placed_at', $r)->count()),
            $m('Delivered', fn($r) => \Modules\StoreManagement\Entities\StoreOrder::where('status', 'delivered')->whereBetween('delivered_at', $r)->count()),
            $m('Sales', fn($r) => \Modules\StoreManagement\Entities\StoreOrder::where('status', 'delivered')->whereBetween('delivered_at', $r)->sum('total'), true),
            $m('Cancelled or refused', fn($r) => \Modules\StoreManagement\Entities\StoreOrder::whereIn('status', ['cancelled', 'rejected', 'expired'])->whereBetween('cancelled_at', $r)->count()),
        ]];
        $cards[] = ['key' => 'market', 'title' => 'E-Ride Market', 'icon' => 'bi-basket', 'gate' => 'place', 'route' => 'admin.market.orders', 'metrics' => [
            $m('Items posted', fn($r) => \Modules\MarketManagement\Entities\MarketItem::whereBetween('created_at', $r)->count()),
            $m('Orders completed', fn($r) => \Modules\MarketManagement\Entities\MarketOrder::where('status', 'completed')->whereBetween('completed_at', $r)->count()),
            $m('Services done', fn($r) => \Modules\MarketManagement\Entities\MarketBooking::where('status', 'completed')->whereBetween('completed_at', $r)->count()),
            $m('Zama deals', fn($r) => \Modules\MarketManagement\Entities\MarketZamaDeal::where('status', 'done')->whereBetween('done_at', $r)->count()),
            $m('New shops', fn($r) => \Modules\MarketManagement\Entities\MarketShop::whereBetween('created_at', $r)->count()),
        ]];
        $cards[] = ['key' => 'bus', 'title' => 'Bus travel', 'icon' => 'bi-bus-front', 'gate' => 'place', 'route' => 'admin.bus.index', 'metrics' => [
            $m('Bookings paid', fn($r) => \Modules\BusManagement\Entities\BusBooking::where('status', 'paid')->whereBetween('paid_at', $r)->count()),
            $m('Seats sold', fn($r) => \Modules\BusManagement\Entities\BusTicket::whereIn('status', ['valid', 'boarded', 'no_show'])
                ->whereHas('booking', fn($q) => $q->where('status', 'paid')->whereBetween('paid_at', $r))->count()),
            $m('Ticket sales', fn($r) => \Modules\BusManagement\Entities\BusBooking::where('status', 'paid')->whereBetween('paid_at', $r)->sum('amount'), true),
        ]];
        $cards[] = ['key' => 'stays', 'title' => 'Hotels and homes', 'icon' => 'bi-building', 'gate' => 'place', 'route' => 'admin.hotel.index', 'metrics' => [
            $m('Hotel questions', fn($r) => \Modules\HotelManagement\Entities\HotelInquiry::whereBetween('created_at', $r)->count()),
            $m('Hotel arrivals', fn($r) => \Modules\HotelManagement\Entities\HotelStay::where('source', 'eride')->whereBetween('arrived_at', $r)->count()),
            $m('Home visits booked', fn($r) => \Modules\PlaceManagement\Entities\ListingVisit::whereBetween('created_at', $r)->count()),
            $m('Homes reserved', fn($r) => \Modules\PlaceManagement\Entities\ListingReservation::whereNotNull('paid_at')->whereBetween('paid_at', $r)->count()),
        ]];
        $cards[] = ['key' => 'jobs', 'title' => 'E-Ride Jobs', 'icon' => 'bi-briefcase', 'gate' => 'place', 'route' => 'admin.jobs.index', 'metrics' => [
            $m('Jobs posted', fn($r) => \Modules\MarketManagement\Entities\JobPost::whereBetween('created_at', $r)->count()),
            $m('Applications', fn($r) => \Modules\MarketManagement\Entities\JobApplication::whereBetween('created_at', $r)->count()),
            $m('Hires', fn($r) => \Modules\MarketManagement\Entities\JobApplication::whereBetween('hired_at', $r)->count()),
        ]];
        $cards[] = ['key' => 'people', 'title' => 'People', 'icon' => 'bi-people', 'gate' => 'user', 'route' => 'admin.customer.index', 'metrics' => [
            $m('New customers', fn($r) => \Modules\UserManagement\Entities\User::where('user_type', CUSTOMER)->whereBetween('created_at', $r)->count()),
            $m('New drivers', fn($r) => \Modules\UserManagement\Entities\User::where('user_type', DRIVER)->whereBetween('created_at', $r)->count()),
            $m('Customers who booked', fn($r) => $trip::whereBetween('created_at', $r)->distinct('customer_id')->count('customer_id')),
        ]];
        // Right now, not a period.
        $live = [];
        try {
            $live['drivers_online'] = \Modules\UserManagement\Entities\User::where('user_type', DRIVER)->where('is_active', 1)
                ->whereHas('driverDetails', fn($q) => $q->where('is_online', 1))->count();
        } catch (Throwable) {
        }
        try {
            $live['trips_ongoing'] = $trip::whereIn('current_status', [ACCEPTED, OUT_FOR_PICKUP, ONGOING])->count();
            $live['trips_waiting'] = $trip::where('current_status', PENDING)->where(fn($q) => $q->where('ride_request_type', 'regular')->orWhereNull('ride_request_type'))->count();
        } catch (Throwable) {
        }
        try {
            $live['food_active'] = \Modules\StoreManagement\Entities\StoreOrder::whereIn('status', \Modules\StoreManagement\Entities\StoreOrder::ACTIVE)->count();
        } catch (Throwable) {
        }
        return ['cards' => $cards, 'live' => $live, 'at' => $now->toIso8601String()];
    }

    /** Overview cards for this admin, and the live numbers, and when it was counted. */
    public static function snapshot(): array
    {
        $data = self::cached('overview', fn() => self::computeOverview());
        $data['cards'] = array_values(array_filter($data['cards'] ?? [], fn($c) => AdminNav::allowed($c['gate'] ?? null)));
        foreach ($data['cards'] as &$card) {
            $card['url'] = isset($card['route']) && Route::has($card['route']) ? route($card['route'], $card['params'] ?? []) : null;
        }
        return $data;
    }

    // ================================================================== needs attention

    /** Everything waiting for someone, that this admin may handle, most important first. Only what is waiting. */
    public static function attention(): array
    {
        $all = self::cached('attention', fn() => self::computeAttention());
        $out = [];
        foreach ($all as $item) {
            if (($item['count'] ?? 0) <= 0 || !AdminNav::allowed($item['gate'] ?? null) || !Route::has($item['route'])) {
                continue;
            }
            try {
                $item['url'] = route($item['route'], $item['params'] ?? []);
            } catch (Throwable) {
                continue;
            }
            $out[] = $item;
        }
        usort($out, fn($a, $b) => [$a['level'], -$a['count']] <=> [$b['level'], -$b['count']]);
        return $out;
    }

    public static function attentionTotal(): int
    {
        try {
            return (int)array_sum(array_column(self::attention(), 'count'));
        } catch (Throwable) {
            return 0;
        }
    }

    /** One count, for a sidebar badge. */
    public static function attentionCount(string $key): int
    {
        foreach (self::cached('attention', fn() => self::computeAttention()) as $item) {
            if ($item['key'] === $key) {
                return (int)$item['count'];
            }
        }
        return 0;
    }

    /** level: 1 urgent (people waiting now), 2 today, 3 this week. */
    private static function computeAttention(): array
    {
        $c = function (callable $q) {
            try {
                return (int)$q();
            } catch (Throwable) {
                return 0;
            }
        };
        $items = [];
        $add = function (string $key, string $title, string $hint, string $route, $gate, string $icon, int $level, int $count, array $params = []) use (&$items) {
            $items[] = compact('key', 'title', 'hint', 'route', 'gate', 'icon', 'level', 'count', 'params');
        };

        // People waiting right now.
        $add('trips_waiting', 'Trips waiting for a driver', 'Customers asked for a ride or delivery and no driver has taken it yet.', 'admin.trip.index', 'trip', 'bi-hourglass-split', 1,
            $c(fn() => \Modules\TripManagement\Entities\TripRequest::where('current_status', PENDING)->where(fn($q) => $q->where('ride_request_type', 'regular')->orWhereNull('ride_request_type'))
                ->where('created_at', '<', now()->subMinutes(5))->count()), ['pending']);
        $add('food_attention', 'Food & Shops orders in trouble', 'Failed deliveries, or no rider after three calls.', 'admin.store-order.index', 'store', 'bi-exclamation-triangle', 1,
            $c(fn() => \Modules\StoreManagement\Entities\StoreOrder::where(fn($w) => $w->where(fn($x) => $x->where('status', 'delivery_failed')
                ->where(fn($y) => $y->whereNull('cancel_reason')->orWhere(fn($z) => $z->where('cancel_reason', 'not like', '%[food refunded]%')->where('cancel_reason', 'not like', '%[no food refund]%'))))
                ->orWhere(fn($x) => $x->where('status', 'rider_requested')->where('rider_attempts', '>=', 3)))->count()), ['status' => 'attention']);
        $add('safety_areas', 'Danger areas to confirm', 'Drivers reported a danger area: confirm it or lift it.', 'admin.safety.index', 'zone', 'bi-shield-exclamation', 1,
            $c(fn() => \Modules\ZoneManagement\Entities\SafetyArea::where('confirmed', 0)->whereNull('lifted_at')->where('ends_at', '>', now())->count()));
        $add('hotel_slow', 'Hotel guests not answered', 'Guests asked a hotel and the reception has not answered for a while.', 'admin.hotel.index', 'place', 'bi-building-exclamation', 1,
            $c(fn() => \Modules\HotelManagement\Entities\HotelInquiry::whereIn('status', \Modules\HotelManagement\Entities\HotelInquiry::ACTIVE)->where('unread_hotel', '>', 0)
                ->where('last_message_at', '<', now()->subHours(\Modules\HotelManagement\Lib\HotelSettings::all()['slow_hours']))->count()));
        $add('homes_problems', 'Home reservations with a problem', 'A guest or owner reported a problem with a paid reservation.', 'admin.listing.reservations', 'place', 'bi-house-exclamation', 1,
            $c(fn() => \Modules\PlaceManagement\Entities\ListingReservation::where('status', 'problem')->count()));

        // Approvals.
        $add('drivers_unverified', 'Drivers waiting approval', 'New drivers whose face or ID check needs a decision.', 'admin.driver.verification.unverified-list', 'user', 'bi-person-check', 2,
            $c(fn() => \Modules\UserManagement\Entities\DriverIdentityVerification::count()));
        $add('driver_identity', 'Driver identity updates', 'Drivers sent new ID documents to check.', 'admin.driver.profile-update-request-list', 'user', 'bi-person-vcard', 2,
            $c(fn() => \Modules\UserManagement\Entities\User::where('user_type', DRIVER)->whereNotNull('old_identification_image')->count()));
        $add('vehicle_requests', 'New vehicles to approve', 'Drivers added a vehicle that needs checking.', 'admin.vehicle.request.list', 'vehicle', 'bi-ev-front', 2,
            $c(fn() => \Modules\VehicleManagement\Entities\Vehicle::where('vehicle_request_status', PENDING)->count()));
        $add('vehicle_updates', 'Vehicle changes to approve', 'Drivers changed vehicle details.', 'admin.vehicle.update.list', 'vehicle', 'bi-pencil-square', 2,
            $c(fn() => \Modules\VehicleManagement\Entities\Vehicle::whereNotNull('draft')->count()));
        $add('id_pending', 'ID checks waiting', 'People sent an ID and a selfie for E-Ride ID Verified.', 'admin.id-verification.index', 'place', 'bi-person-vcard', 2,
            $c(fn() => \Modules\UserManagement\Entities\IdVerification::where('status', 'pending')->count()));
        $add('market_queue', 'Market items to review', 'Reported or hidden items waiting for a decision.', 'admin.market.moderation', 'place', 'bi-flag', 2,
            $c(fn() => \Modules\MarketManagement\Entities\MarketItem::withTrashed()->where(fn($q) => $q->where('status', 'hidden')
                ->orWhereIn('id', \Modules\MarketManagement\Entities\MarketReport::where('status', 'open')->select('item_id')))->count()
                + \Modules\MarketManagement\Entities\MarketReport::whereNull('item_id')->where('status', 'open')->count()));
        $add('market_verify', 'Market shops asking to be verified', 'Sellers asked for the E-Ride Verified badge: check them.', 'admin.market.shops', 'place', 'bi-patch-check', 3,
            $c(fn() => \Modules\MarketManagement\Entities\MarketShop::whereNotNull('verification_requested_at')->whereNull('verified_at')->count()), ['filter' => 'requests']);
        $add('employers_pending', 'Employers to verify', 'Businesses that want to post jobs.', 'admin.jobs.employers', 'place', 'bi-briefcase', 2,
            $c(fn() => \Modules\MarketManagement\Entities\JobEmployer::where('status', 'pending')->count()));
        $add('jobs_review', 'Jobs to review', 'Job posts waiting for a check before going live.', 'admin.jobs.index', 'place', 'bi-file-earmark-check', 2,
            $c(fn() => \Modules\MarketManagement\Entities\JobPost::whereIn('status', ['review', 'hidden'])->count()));
        $add('bus_agencies', 'Bus agencies to verify', 'Agencies waiting for E-Ride to check them.', 'admin.bus.index', 'place', 'bi-bus-front', 2,
            $c(fn() => \Modules\BusManagement\Entities\BusAgency::where('status', 'checking')->count()));
        $add('homes_review', 'Homes to review', 'New or changed listings to check.', 'admin.listing.index', 'place', 'bi-house-check', 2,
            $c(fn() => \Modules\PlaceManagement\Entities\Listing::where(fn($q) => $q->where('status', 'pending')->orWhere('needs_review', 1))->count()));
        $add('homes_visits', 'Home visits to do', 'Staff visits to check a home on the spot.', 'admin.listing.visits', 'place', 'bi-geo', 3,
            $c(fn() => \Modules\PlaceManagement\Entities\ListingVerification::whereIn('status', ['requested', 'scheduled'])->count()));

        // Money to send.
        $add('withdraw_pending', 'Driver cash-outs to pay', 'Drivers asked to withdraw their earnings.', 'admin.driver.withdraw.requests', 'user', 'bi-bank', 2,
            $c(fn() => \Modules\UserManagement\Entities\WithdrawRequest::where('status', PENDING)->count()), ['status' => 'pending']);
        $add('refunds_pending', 'Parcel refunds to decide', 'Customers asked for a refund on a parcel.', 'admin.trip.refund.index', 'trip', 'bi-arrow-counterclockwise', 2,
            $c(fn() => \Modules\TripManagement\Entities\ParcelRefund::where('status', PENDING)->count()), ['pending']);
        $add('resellers_owed', 'Resellers to pay', 'Resellers owed their commission on delivered orders.', 'admin.store-reseller.index', 'store_edit', 'bi-people', 3,
            $c(fn() => \Modules\StoreManagement\Lib\StoreResellers::owed()->filter(fn($r) => $r['owed'] >= \Modules\StoreManagement\Lib\StoreSettings::all()['reseller_min_payout'])->count()));
        $add('homes_payouts', 'Homes payouts to send', 'Owners and refunds waiting for their money.', 'admin.listing.payouts', 'place', 'bi-cash', 2,
            $c(fn() => \Modules\PlaceManagement\Entities\ListingPayout::where('status', 'pending')->count()));
        $add('bus_statements', 'Bus statements unpaid', 'Agencies that have not paid E-Ride their monthly commission yet.', 'admin.bus.index', 'place', 'bi-receipt', 3,
            $c(fn() => \Modules\BusManagement\Entities\BusStatement::where('status', 'due')->count()));
        $add('store_statements', 'Store statements late', 'Food & Shops businesses paid into their own Fapshi that have not paid their weekly E-Ride statement on time.', 'admin.store.index', 'store', 'bi-receipt', 3,
            $c(fn() => \Modules\StoreManagement\Entities\StoreStatement::where('status', 'due')->where('due_at', '<', now())->count()));

        // Reports.
        $add('driver_reports', 'Driver reports to read', 'Customers reported something about a driver or a trip.', 'admin.driver-report.index', 'trip', 'bi-flag', 2,
            $c(fn() => \Modules\TripManagement\Entities\TripReport::where('status', 'open')->count()));
        $add('lost_items', 'Lost items for support', 'Forgotten items that need E-Ride to step in.', 'admin.lost-item.index', 'trip', 'bi-bag', 2,
            $c(fn() => \Modules\TripManagement\Entities\LostItem::whereIn('status', \Modules\TripManagement\Entities\LostItem::OPEN)->whereNotNull('escalated_at')->count()));
        $add('homes_reports', 'Homes reports', 'People reported a home listing.', 'admin.listing.reports', 'place', 'bi-flag', 3,
            $c(fn() => \Modules\PlaceManagement\Entities\ListingReport::where('status', 'open')->count()));
        $add('job_reports', 'Job reports', 'People reported a job post.', 'admin.jobs.index', 'place', 'bi-flag', 3,
            $c(fn() => \Modules\MarketManagement\Entities\JobReport::where('status', 'open')->count()));

        return $items;
    }

    // ================================================================== inside

    private static function cached(string $key, callable $fn): array
    {
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }
        try {
            $value = cache()->remember('business_pulse_' . $key, self::TTL, $fn);
        } catch (Throwable) {
            $value = $fn();
        }
        return self::$memo[$key] = is_array($value) ? $value : [];
    }

    /** "+12%" against the 7 days before, or null when there is nothing to compare with. */
    public static function trend(?float $week, ?float $prev): ?int
    {
        if ($week === null || $prev === null || $prev <= 0) {
            return null;
        }
        return (int)round(($week - $prev) / $prev * 100);
    }
}
