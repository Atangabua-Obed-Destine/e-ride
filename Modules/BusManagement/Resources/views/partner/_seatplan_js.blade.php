<script>
/*
 * The seat plan, as Lib/SeatPlan.php builds it (keep the two in step): busSeatPlan(config) gives
 * {total, seats, decks:[{deck, cabin, rows:[[cell]]}]}; busSeatPlanRender(el, plan, opts) draws it.
 * opts.seat(cell, button) can style or wire each seat button.
 */
(function () {
    function groups(perRow, aisle) {
        perRow = Math.max(1, perRow);
        var left = Math.max(1, Math.min(aisle, perRow)), right = perRow - left;
        return right > 0 ? [left, right] : [left];
    }

    function deck(c, deckSeats, start, isLower) {
        var g = groups(+c.seats_per_row || 4, +c.aisle_position || 2), last = g.length - 1;
        var driver = ['separate', 'front_left', 'front_right'].indexOf(c.driver_position) >= 0 ? c.driver_position : 'separate';
        var inline = isLower && driver !== 'separate';
        var skipFront = isLower && c.first_seat_position === 'second_row';
        var doors = {};
        if (isLower) (c.door_positions || []).forEach(function (d) {
            var r = parseInt(d.row, 10);
            if (r >= 1 && (d.side === 'left' || d.side === 'right')) { doors[r] = doors[r] || {}; doors[r][d.side] = true; }
        });
        var rear = c.has_rear_seats ? Math.min(Math.max(0, +c.rear_seats_count || 0), deckSeats) : 0;
        var regular = deckSeats - rear, rows = [], n = start, placed = 0, row = 0;
        var maxRows = Math.ceil(regular / Math.max(1, g[0] + (g[1] || 0))) + Object.keys(doors).length + 3;
        while (placed < regular && row < maxRows) {
            row++;
            var cells = [];
            g.forEach(function (size, gi) {
                var isLeft = gi === 0, isRight = gi === last;
                if ((isLeft && doors[row] && doors[row].left) || (isRight && doors[row] && doors[row].right)) {
                    cells.push({t: 'door', span: size});
                } else {
                    for (var k = 0; k < size; k++) {
                        var driverSide = inline && row === 1 && ((driver === 'front_left' && isLeft) || (driver === 'front_right' && isRight));
                        if (driverSide) {
                            var isDriver = (driver === 'front_left' && k === 0) || (driver === 'front_right' && k === size - 1);
                            cells.push({t: isDriver ? 'driver' : 'empty'});
                        } else if (skipFront && row === 1) {
                            cells.push({t: 'empty'});
                        } else if (placed < regular) {
                            cells.push({t: 'seat', n: String(n), window: (isLeft && k === 0) || (isRight && k === size - 1)});
                            n++; placed++;
                        } else {
                            cells.push({t: 'empty'});
                        }
                    }
                }
                if (gi < last) cells.push({t: 'aisle'});
            });
            rows.push(cells);
        }
        if (rear > 0) {
            var back = [];
            for (var b = 0; b < rear; b++) { back.push({t: 'seat', n: String(n), window: b === 0 || b === rear - 1, back: true}); n++; }
            rows.push(back);
        }
        return {deck: isLower ? 'lower' : 'upper', cabin: isLower && driver === 'separate', rows: rows};
    }

    window.busSeatPlan = function (c) {
        var total = Math.max(0, parseInt(c.total_seats, 10) || 0);
        var dd = !!c.is_double_decker, upper = dd ? (parseInt(c.upper_deck_seats, 10) || 0) : 0;
        if (dd && upper <= 0) upper = total - Math.floor(total / 2);
        upper = Math.max(0, Math.min(upper, total));
        var lower = total - upper, decks = [deck(c, lower, 1, true)];
        if (dd && upper > 0) decks.push(deck(c, upper, 1 + lower, false));
        var seats = [];
        decks.forEach(function (d) { d.rows.forEach(function (r) { r.forEach(function (cell) { if (cell.t === 'seat') seats.push(cell.n); }); }); });
        return {total: seats.length, seats: seats, decks: decks};
    };

    window.busSeatPlanRender = function (el, plan, opts) {
        opts = opts || {};
        el.innerHTML = '';
        var double = plan.decks.length > 1;
        plan.decks.forEach(function (d) {
            if (double) {
                var t = document.createElement('div');
                t.className = 'sp-deck';
                t.textContent = d.deck === 'lower' ? @json(translate('Lower deck')) : @json(translate('Upper deck'));
                el.appendChild(t);
            }
            var box = document.createElement('div');
            box.className = 'sp';
            if (d.cabin) {
                var cab = document.createElement('div');
                cab.className = 'sp-cabin';
                cab.innerHTML = '<span>' + @json(translate('Driver')) + '</span><b>' + @json(translate('Door')) + '</b>';
                box.appendChild(cab);
            }
            d.rows.forEach(function (r) {
                var row = document.createElement('div');
                row.className = 'sp-row';
                r.forEach(function (c) {
                    var cell;
                    if (c.t === 'seat') {
                        cell = document.createElement(opts.seat ? 'button' : 'div');
                        if (opts.seat) cell.type = 'button';
                        cell.className = 'sp-cell sp-seat';
                        cell.textContent = c.n;
                        cell.dataset.seat = c.n;
                        cell.setAttribute('aria-label', @json(translate('Seat')) + ' ' + c.n + (c.window ? ', ' + @json(translate('window')) : ''));
                        if (opts.seat) opts.seat(c, cell);
                    } else {
                        cell = document.createElement('div');
                        cell.className = 'sp-cell ' + (c.t === 'aisle' ? 'sp-aisle' : c.t === 'driver' ? 'sp-driver' : c.t === 'door' ? 'sp-door' : '');
                        if (c.t === 'driver') { cell.textContent = @json(translate('Drv')); cell.setAttribute('aria-label', @json(translate('Driver'))); }
                        if (c.t === 'door') {
                            cell.textContent = @json(translate('Door'));
                            cell.style.width = 'calc(' + c.span + ' * var(--sp-w, 34px) + ' + ((c.span - 1) * 4) + 'px)';
                        }
                    }
                    row.appendChild(cell);
                });
                box.appendChild(row);
            });
            el.appendChild(box);
        });
    };
})();
</script>
