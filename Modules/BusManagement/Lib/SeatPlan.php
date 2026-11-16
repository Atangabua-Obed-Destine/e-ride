<?php

namespace Modules\BusManagement\Lib;

/**
 * The seat plan of a bus, from the settings of its form (the same settings and the same rules as
 * the trivagocm bus form the agencies know):
 *  - seats per row, split by "aisle after seat" into a left and a right group;
 *  - the driver in a separate cabin (a row of its own, with the entry door), or in the front row,
 *    left or right, alone on that side of the aisle;
 *  - seat 1 in the front row, or in the second row (the front row then has no passenger seats);
 *  - side doors: a door takes its side of its row, the other side keeps its seats;
 *  - a back row (3 to 6 seats across the full width) on each deck;
 *  - a double decker: the upper deck's numbers follow the lower deck's.
 *
 * Cells: seat (n, window), aisle, empty, driver, door (span: the seats it replaces).
 */
class SeatPlan
{
    public const DRIVER_POSITIONS = ['separate', 'front_left', 'front_right'];
    public const FIRST_SEAT_POSITIONS = ['front_row', 'second_row'];

    /** @param array $c total_seats, driver_position, is_double_decker, upper_deck_seats, has_rear_seats, rear_seats_count, seats_per_row, aisle_position, first_seat_position, door_positions */
    public static function build(array $c): array
    {
        $total = max(0, (int)($c['total_seats'] ?? 0));
        $doubleDecker = (bool)($c['is_double_decker'] ?? false);
        $upper = $doubleDecker ? (int)($c['upper_deck_seats'] ?? 0) : 0;
        if ($doubleDecker && $upper <= 0) {
            $upper = $total - (int)floor($total / 2);
        }
        $upper = max(0, min($upper, $total));
        $lower = $total - $upper;

        $decks = [];
        $next = 1;
        $decks[] = self::deck($c, $lower, $next, true);
        $next += $lower;
        if ($doubleDecker && $upper > 0) {
            $decks[] = self::deck($c, $upper, $next, false);
        }

        $seats = [];
        foreach ($decks as $deck) {
            foreach ($deck['rows'] as $row) {
                foreach ($row as $cell) {
                    if ($cell['t'] === 'seat') {
                        $seats[] = $cell['n'];
                    }
                }
            }
        }
        return ['total' => count($seats), 'seats' => $seats, 'decks' => $decks];
    }

    /** [left, right] seats either side of the aisle; one group when the aisle is at the end. */
    public static function groups(int $seatsPerRow, int $aislePosition): array
    {
        $seatsPerRow = max(1, $seatsPerRow);
        $left = max(1, min($aislePosition, $seatsPerRow));
        $right = $seatsPerRow - $left;
        return $right > 0 ? [$left, $right] : [$left];
    }

    protected static function deck(array $c, int $deckSeats, int $start, bool $isLower): array
    {
        $groups = self::groups((int)($c['seats_per_row'] ?? 4), (int)($c['aisle_position'] ?? 2));
        $last = count($groups) - 1;
        $driver = in_array($c['driver_position'] ?? 'separate', self::DRIVER_POSITIONS, true) ? $c['driver_position'] : 'separate';
        $inlineDriver = $isLower && $driver !== 'separate';
        $skipFront = $isLower && ($c['first_seat_position'] ?? 'front_row') === 'second_row';
        $doors = $isLower ? self::doors($c['door_positions'] ?? []) : [];

        $rear = ($c['has_rear_seats'] ?? false) ? min(max(0, (int)($c['rear_seats_count'] ?? 0)), $deckSeats) : 0;
        $regular = $deckSeats - $rear;

        $rows = [];
        $n = $start;
        $placed = 0;
        $row = 0;
        $maxRows = (int)ceil($regular / max(1, array_sum($groups))) + count($doors) + 3;
        while ($placed < $regular && $row < $maxRows) {
            $row++;
            $cells = [];
            foreach ($groups as $gi => $size) {
                $isLeft = $gi === 0;
                $isRight = $gi === $last;
                if (($isLeft && isset($doors[$row]['left'])) || ($isRight && isset($doors[$row]['right']))) {
                    $cells[] = ['t' => 'door', 'span' => $size];
                } else {
                    for ($k = 0; $k < $size; $k++) {
                        $driverSide = $inlineDriver && $row === 1
                            && (($driver === 'front_left' && $isLeft) || ($driver === 'front_right' && $isRight));
                        if ($driverSide) {
                            $isDriverCell = ($driver === 'front_left' && $k === 0) || ($driver === 'front_right' && $k === $size - 1);
                            $cells[] = ['t' => $isDriverCell ? 'driver' : 'empty'];
                        } elseif ($skipFront && $row === 1) {
                            $cells[] = ['t' => 'empty'];
                        } elseif ($placed < $regular) {
                            $outer = ($isLeft && $k === 0) || ($isRight && $k === $size - 1);
                            $cells[] = ['t' => 'seat', 'n' => (string)$n, 'window' => $outer];
                            $n++;
                            $placed++;
                        } else {
                            $cells[] = ['t' => 'empty'];
                        }
                    }
                }
                if ($gi < $last) {
                    $cells[] = ['t' => 'aisle'];
                }
            }
            $rows[] = $cells;
        }
        if ($rear > 0) {
            $back = [];
            for ($k = 0; $k < $rear; $k++) {
                $back[] = ['t' => 'seat', 'n' => (string)$n, 'window' => $k === 0 || $k === $rear - 1, 'back' => true];
                $n++;
            }
            $rows[] = $back;
        }
        return [
            'deck' => $isLower ? 'lower' : 'upper',
            // The separate driver cabin: the driver on the left, the entry door on the right.
            'cabin' => $isLower && $driver === 'separate',
            'rows' => $rows,
        ];
    }

    /** [row => [side => true]] from [{row, side}] */
    protected static function doors($positions): array
    {
        $out = [];
        foreach ((array)$positions as $d) {
            $row = (int)($d['row'] ?? 0);
            $side = $d['side'] ?? null;
            if ($row >= 1 && in_array($side, ['left', 'right'], true)) {
                $out[$row][$side] = true;
            }
        }
        return $out;
    }
}
