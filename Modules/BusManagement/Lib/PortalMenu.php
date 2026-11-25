<?php

namespace Modules\BusManagement\Lib;

use Illuminate\Support\Facades\Route;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Service\PaperService;

/**
 * The menus of the bus portals, grouped: the phone's row of links, the wide
 * screen's menus and icon rail (public/assets/desk/desktop.js) and the "Go to…"
 * box are all drawn from this, so they always offer the same screens for the
 * same role. The accounting frame (resources/views/layouts/app.blade.php) adds
 * them to its own menus, so a click on "Today" from Accounting works.
 *
 * Each menu: ['key', 'label', [[route, bootstrap icon, label], ...]]; menus left
 * empty for a role are dropped.
 */
class PortalMenu
{
    /** The branch portal, for a staff role (BusStaff::MANAGER, COUNTER, BOARDING, ACCOUNTANT). */
    public static function branch(BusAgency $agency, string $role): array
    {
        $manager = $role === BusStaff::MANAGER;
        // Counter screens: the manager and counter agents (not boarding agents, not the accountant).
        $desk = in_array($role, [BusStaff::MANAGER, BusStaff::COUNTER], true);
        $has = fn (string $r) => Route::has($r);

        return self::keep([
            ['counter', __('desktop.menu_counter'), [
                $has('bus-partner.today') && $desk ? ['bus-partner.today', 'bi-bell', translate('Today')] : null,
                $has('bus-partner.sell') && $desk ? ['bus-partner.sell', 'bi-ticket-perforated', translate('Quick sell')] : null,
                $has('bus-partner.collect') && $desk && BusSettings::rules($agency->rules)['tickets'] === 'collect'
                    ? ['bus-partner.collect', 'bi-printer', translate('Collect')] : null,
                $desk && $has('bus-partner.paper') && PaperService::on($agency) ? ['bus-partner.paper', 'bi-journal-check', translate('Paper sheets')] : null,
                $has('bus-partner.boarding') && $role !== BusStaff::ACCOUNTANT ? ['bus-partner.boarding', 'bi-qr-code-scan', translate('Boarding')] : null,
            ]],
            ['trips', __('desktop.menu_trips'), [
                ['bus-partner.departures', 'bi-calendar-week', translate('Departures')],
                $manager ? ['bus-partner.destinations', 'bi-signpost-split', translate('Destinations')] : null,
                $manager ? ['bus-partner.fleet', 'bi-bus-front', translate('Fleet')] : null,
            ]],
            ['money', __('desktop.menu_money'), [
                $manager ? ['bus-partner.payments', 'bi-wallet2', translate('Payments')] : null,
                // Accounting: the accounting module of all our systems (/partner/accounting), opened on this branch's books.
                in_array($role, [BusStaff::MANAGER, BusStaff::ACCOUNTANT], true) && $has('bus-partner.accounting')
                    ? ['bus-partner.accounting', 'bi-journal-text', translate('Accounting')] : null,
            ]],
            ['team', __('desktop.menu_team'), [
                $manager ? ['bus-partner.staff', 'bi-people', translate('Staff')] : null,
                $desk ? ['bus-partner.share', 'bi-share', translate('Share')] : null,
            ]],
        ]);
    }

    /** The head office portal, for a head office role (BusCompanyStaff::DIRECTOR, REGIONAL, ACCOUNTANT). */
    public static function company(string $role): array
    {
        $money = in_array($role, [BusCompanyStaff::DIRECTOR, BusCompanyStaff::ACCOUNTANT], true);

        return self::keep([
            ['watch', __('desktop.menu_watch'), [
                ['bus-company.overview', 'bi-speedometer2', translate('Overview')],
                ['bus-company.branches', 'bi-diagram-3', translate('Branches')],
                ['bus-company.reports', 'bi-graph-up', translate('Reports')],
            ]],
            ['run', __('desktop.menu_run'), [
                ['bus-company.buses', 'bi-bus-front', translate('Buses')],
                ['bus-company.staff', 'bi-people', translate('Staff')],
                ['bus-company.rules', 'bi-card-checklist', translate('Booking rules')],
            ]],
            ['money', __('desktop.menu_money'), [
                $money ? ['bus-company.money', 'bi-wallet2', translate('Money')] : null,
                // Accounting: the accounting module of all our systems, opened on the head office's own books.
                $money ? ['bus-company.accounting', 'bi-journal-text', translate('Accounting')] : null,
            ]],
        ]);
    }

    /**
     * The Home tab of the branch portal on a wide screen: Today for the counter
     * and the manager; Boarding for a boarding agent; Departures for the
     * accountant, whose own home is Accounting (a frame of its own).
     */
    public static function branchHome(string $role): string
    {
        return match ($role) {
            BusStaff::MANAGER, BusStaff::COUNTER => 'bus-partner.today',
            BusStaff::BOARDING => 'bus-partner.boarding',
            default => 'bus-partner.departures',
        };
    }

    /** Every link of the menus, in order: [route, icon, label]. */
    public static function flat(array $menus): array
    {
        return array_merge([], ...array_map(fn ($m) => $m[2], $menus));
    }

    protected static function keep(array $menus): array
    {
        $out = [];
        foreach ($menus as [$key, $label, $links]) {
            $links = array_values(array_filter($links));
            if ($links) {
                $out[] = [$key, $label, $links];
            }
        }

        return $out;
    }
}
