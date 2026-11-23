<?php

namespace Modules\BusManagement\Lib;

/**
 * E-Ride Bus Travel switches and numbers (admin Bus agencies > Settings), with defaults.
 * Off until an admin turns it on.
 */
class BusSettings
{
    public const TYPE = 'bus_settings';

    /** Cancellation by the passenger, by default: [hours before departure, % back], most generous first. */
    public const DEFAULT_CANCEL_TIERS = [[18, 100], [2, 50], [0, 0]];

    public const ID_CHECKS = ['eride', 'agents', 'number'];

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            'enabled' => (bool)(int)$get('bus_enabled', 0),
            // Minutes the agency has to accept or decline a seat request.
            'answer_minutes' => min(60, max(2, (int)$get('bus_answer_minutes', 10))),
            // Minutes the passenger has to pay once accepted.
            'pay_minutes' => min(60, max(5, (int)$get('bus_pay_minutes', 15))),
            // Days ahead the timetable opens departures for booking.
            'open_days' => min(60, max(1, (int)$get('bus_open_days', 14))),
            // E-Ride's commission by default, % of the fare (each agency can have its own).
            'commission_app_pct' => min(50, max(0, (float)$get('bus_commission_app_pct', 0))),
            'commission_counter_pct' => min(50, max(0, (float)$get('bus_commission_counter_pct', 0))),
            // The monthly statement is due on this day of the next month.
            'statement_due_day' => min(28, max(1, (int)$get('bus_statement_due_day', 10))),
            // Online booking pauses when a statement is this many days late (the counter keeps working).
            'late_pause_days' => min(60, max(1, (int)$get('bus_late_pause_days', 7))),
            // Hours an agency has to send a refund it owes.
            'refund_hours' => min(168, max(6, (int)$get('bus_refund_hours', 48))),
            // An agency declining or letting expire more than this % of the last 30 days' requests is paused (0: never).
            'decline_pause_pct' => min(100, max(0, (int)$get('bus_decline_pause_pct', 30))),
            // At most this many seats in one request.
            'max_seats' => min(10, max(1, (int)$get('bus_max_seats', 6))),
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['enabled'];
    }

    /** The website (eride.../bus): its switches, on by default once bus booking itself is on. */
    public static function web(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            // Off: the pages still show departures, with "Book in the app".
            'booking' => (bool)(int)$get('bus_web_booking', 1),
            // Off: new people are sent to the app to sign up.
            'signup' => (bool)(int)$get('bus_web_signup', 1),
            // An SMS code proves the phone number when someone creates an account on the website.
            'signup_code' => (bool)(int)$get('bus_web_signup_code', 0),
            // Search engines may list the agency and route pages.
            'index' => (bool)(int)$get('bus_web_index', 1),
            // Messages go by SMS when WhatsApp is not connected or a WhatsApp message fails.
            'sms_backup' => (bool)(int)$get('bus_web_sms_backup', 1),
        ];
    }

    /** An agency's rules, with E-Ride's defaults for what it has not set. */
    public static function rules(?array $rules): array
    {
        $rules ??= [];
        $tiers = [];
        foreach ((array)($rules['cancel_tiers'] ?? self::DEFAULT_CANCEL_TIERS) as $t) {
            if (is_array($t) && count($t) === 2) {
                $tiers[] = [max(0, (int)$t[0]), min(100, max(0, (int)$t[1]))];
            }
        }
        usort($tiers, fn($a, $b) => $b[0] <=> $a[0]);
        return [
            'free_bags' => min(5, max(0, (int)($rules['free_bags'] ?? 1))),
            'bag_kg' => min(50, max(5, (int)($rules['bag_kg'] ?? 20))),
            'extra_bag_fee' => max(0, (int)($rules['extra_bag_fee'] ?? 1000)),
            // Seats of each departure only the counter can sell.
            'counter_seats' => min(80, max(0, (int)($rules['counter_seats'] ?? 0))),
            // Online booking closes this many minutes before departure.
            'online_cutoff_minutes' => min(720, max(0, (int)($rules['online_cutoff_minutes'] ?? 60))),
            'cancel_tiers' => $tiers ?: self::DEFAULT_CANCEL_TIERS,
            // The ID card number is asked for every passenger (police checkpoint lists).
            'require_id' => (bool)($rules['require_id'] ?? true),
            // How the booker's ID is checked: eride (trust E-Ride ID Verified; otherwise agents look at the photo),
            // agents (agents look at the photo every time), number (the number only).
            'id_check' => in_array($rules['id_check'] ?? null, self::ID_CHECKS, true) ? $rules['id_check'] : 'eride',
            // app (tickets in the app) | collect (printed at the counter, with the booking code).
            'tickets' => in_array($rules['tickets'] ?? null, ['app', 'collect'], true) ? $rules['tickets'] : 'app',
            // A pencil seat sheet per departure, kept in step with E-Ride, to sell on paper during a cut
            // (Service/PaperService). Requests are then always answered by an agent.
            'paper_copy' => (bool)($rules['paper_copy'] ?? true),
        ];
    }

    /** % of the fare back when the passenger cancels this many hours before departure. */
    public static function refundPercent(array $rules, float $hoursBefore): int
    {
        foreach (self::rules($rules)['cancel_tiers'] as [$hours, $pct]) {
            if ($hoursBefore >= $hours) {
                return $pct;
            }
        }
        return 0;
    }
}
