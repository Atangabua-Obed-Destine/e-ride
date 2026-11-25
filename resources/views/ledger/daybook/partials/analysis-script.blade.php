{{--
    The analysis charts (paxhitestbed draws them with Chart.js; PosInnova already ships Highcharts in
    vendor.js and not Chart.js, so the same five charts, colours and series are drawn with Highcharts).

    The meeting pack: mpdf cannot run JavaScript, so each chart is captured here as a PNG (Highcharts'
    own getSVG(), drawn onto a canvas, no network) and posted with the form. The ORDER is load-bearing:
    analysis-pdf reads charts[0..4] by position for cash, in/out, net, pacing and sales receipts.
--}}
@php
    $a = $analysis;
    $mo = $a['months'];
@endphp
<script>
(function () {
    'use strict';
    if (typeof Highcharts === 'undefined') { return; }

    var css = getComputedStyle(document.querySelector('.viz'));
    var role = function (name) { return css.getPropertyValue(name).trim(); };
    var IN = role('--series-in') || '#2a78d6';
    var OUT = role('--series-out') || '#eb6834';
    var GRID = role('--gridline') || '#e1e0d9';
    var AXIS = role('--axis') || '#c3c2b7';
    var INK = role('--text-secondary') || '#52514e';

    var labels = @json(array_column($mo, 'label'));
    var moneyIn = @json(array_map(fn ($m) => round($m['in'], 2), $mo));
    var moneyOut = @json(array_map(fn ($m) => round($m['out'], 2), $mo));
    var cumOut = @json(array_map(fn ($m) => round($m['cum_out'], 2), $mo));
    var netByMonth = @json(array_map(fn ($m) => round($m['net'], 2), $mo));
    var balance = @json(array_map(fn ($p) => round($p['balance'], 2), $a['cash_position']));
    var budgetOut = {{ (float) ($a['budget']['expenditure'] ?? 0) }};
    var fmt = new Intl.NumberFormat();
    var T = {
        balance: @json(__('ledger_daybook.balance')),
        moneyIn: @json(__('ledger_daybook.money_in')),
        moneyOut: @json(__('ledger_daybook.money_out')),
        net: @json(__('ledger_daybook.net')),
        surplus: @json(__('ledger_daybook.surplus')),
        deficit: @json(__('ledger_daybook.deficit')),
        spent: @json(__('ledger_daybook.spent_cumulative')),
        budgetEven: @json(__('ledger_daybook.budget_spread')),
        received: @json(__('ledger_daybook.fc_received_month')),
        running: @json(__('ledger_daybook.fc_running_total'))
    };

    // Money on an axis is unreadable in full; thousands are enough to judge a shape by.
    var yAxis = function (extra) {
        return Highcharts.merge({
            title: { text: null }, gridLineColor: GRID,
            labels: { style: { color: INK }, formatter: function () { var v = this.value; return v >= 1000 || v <= -1000 ? fmt.format(v / 1000) + 'k' : fmt.format(v); } },
            plotLines: [{ value: 0, color: AXIS, width: 2, zIndex: 3 }]
        }, extra || {});
    };
    var base = function (opts) {
        return Highcharts.merge({
            chart: { backgroundColor: '#ffffff', style: { fontFamily: "'Segoe UI', system-ui, sans-serif" }, spacingTop: 10 },
            title: { text: null }, credits: { enabled: false }, exporting: { enabled: false },
            xAxis: { categories: labels, lineColor: AXIS, tickLength: 0, labels: { style: { color: INK } } },
            yAxis: yAxis(),
            legend: { enabled: false },
            tooltip: { shared: true, pointFormatter: function () { return '<span style="color:' + this.color + '">●</span> ' + this.series.name + ': <b>' + fmt.format(Math.round(this.y)) + '</b><br/>'; } },
            plotOptions: { series: { animation: false }, column: { borderRadius: 4, borderColor: '#ffffff', borderWidth: 1 } }
        }, opts);
    };

    // Every chart is registered so the meeting pack can capture it, in this order.
    var captured = [];
    var keep = function (id, opts) {
        var el = document.getElementById(id);
        captured.push(el ? Highcharts.chart(el, base(opts)) : null);
    };

    /* 1. Cash position: one series, so no legend; the zero line drawn heavier. */
    keep('chartCash', {
        chart: { type: 'area' },
        series: [{ name: T.balance, data: balance, color: IN, fillColor: 'rgba(42,120,214,0.08)', lineWidth: 2,
            marker: { radius: 4, fillColor: IN, lineColor: '#fff', lineWidth: 2 } }]
    });

    /* 2. In and out, side by side. */
    keep('chartMonthly', {
        chart: { type: 'column' },
        legend: { enabled: true, align: 'right', verticalAlign: 'top', symbolRadius: 3 },
        series: [{ name: T.moneyIn, data: moneyIn, color: IN }, { name: T.moneyOut, data: moneyOut, color: OUT }]
    });

    /* 3. Surplus and deficit: diverging about zero; the key above carries the words. */
    keep('chartNet', {
        chart: { type: 'column' },
        tooltip: { shared: false, pointFormatter: function () { var v = Math.round(this.y); return (v >= 0 ? T.surplus : T.deficit) + ': <b>' + fmt.format(Math.abs(v)) + '</b>'; } },
        series: [{ name: T.net, data: netByMonth, color: IN, negativeColor: OUT }]
    });

    /* 4. Pacing: the budget guide only when a budget exists; an invented guide would be worse than none. */
    var pacing = [{ name: T.spent, data: cumOut, color: OUT, fillColor: 'rgba(235,104,52,0.08)', type: 'area', lineWidth: 2,
        marker: { radius: 3, fillColor: OUT, lineColor: '#fff', lineWidth: 2 } }];
    if (budgetOut > 0) {
        pacing.push({ name: T.budgetEven, type: 'line', dashStyle: 'Dash', color: AXIS, marker: { enabled: false },
            data: labels.map(function (_, i) { return budgetOut * ((i + 1) / labels.length); }) });
    }
    keep('chartPacing', { legend: { enabled: pacing.length > 1, align: 'right', verticalAlign: 'top' }, series: pacing });

    /* 5. Receipts on sales, month by month. Registered AFTER the four above (the pack reads by position). */
    keep('chartCollection', {
        xAxis: { categories: @json(array_column($collection['by_month'], 'label')) },
        series: [
            { type: 'column', name: T.received, data: @json(array_column($collection['by_month'], 'cash')), color: IN, zIndex: 1 },
            // One shared axis: both series are money in the same currency; a dual axis is never built.
            { type: 'line', name: T.running, data: @json(array_column($collection['by_month'], 'cumulative')), color: INK, marker: { enabled: false }, zIndex: 2 }
        ]
    });

    /* The meeting pack: each chart's SVG drawn on a canvas and posted as a PNG. If a capture fails the
       pack still goes, because every chart on the page is backed by its own table. */
    var toPng = function (chart) {
        return new Promise(function (resolve) {
            if (!chart || typeof chart.getSVG !== 'function') { return resolve(null); }
            try {
                var w = chart.chartWidth || 800, h = chart.chartHeight || 300, scale = 2;
                var svg = chart.getSVG({ chart: { width: w, height: h } });
                var img = new Image();
                img.onload = function () {
                    try {
                        var c = document.createElement('canvas');
                        c.width = w * scale; c.height = h * scale;
                        var g = c.getContext('2d');
                        g.fillStyle = '#ffffff'; g.fillRect(0, 0, c.width, c.height);
                        g.drawImage(img, 0, 0, c.width, c.height);
                        resolve(c.toDataURL('image/png'));
                    } catch (e) { resolve(null); }
                };
                img.onerror = function () { resolve(null); };
                img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
            } catch (e) { resolve(null); }
        });
    };

    var form = document.getElementById('packForm');
    var bucket = document.getElementById('packCharts');
    if (form && bucket) {
        var sending = false;
        form.addEventListener('submit', function (ev) {
            if (sending) { return; }
            ev.preventDefault();
            bucket.innerHTML = '';
            Promise.all(captured.map(toPng)).then(function (pngs) {
                pngs.forEach(function (png, i) {
                    if (!png || png.indexOf('data:image/png;base64,') !== 0) { return; }
                    var field = document.createElement('input');
                    field.type = 'hidden'; field.name = 'charts[' + i + ']'; field.value = png;
                    bucket.appendChild(field);
                });
                sending = true;
                form.submit();
                sending = false;
            });
        });
    }
})();
</script>
