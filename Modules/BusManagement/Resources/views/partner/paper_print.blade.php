<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ translate('Seat sheet') }} · {{ $d->leavesAt()->format('d/m H:i') }} · {{ $d->destination?->city }}</title>
    <style>
        body { margin: 0; padding: 12mm; font: 12px/1.35 -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; }
        .bar { margin-bottom: 10px; } .bar button { font: inherit; font-weight: 700; padding: 8px 18px; border-radius: 8px; border: 0; background: #e02020; color: #fff; cursor: pointer; }
        h1 { font-size: 18px; margin: 0; } .meta { display: flex; flex-wrap: wrap; gap: 4px 18px; margin: 6px 0 8px; color: #333; }
        .rules { border: 2px solid #111; border-radius: 6px; padding: 6px 10px; margin-bottom: 10px; font-weight: 700; }
        .legend { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 10px; font-size: 11px; }
        .legend i { display: inline-block; width: 16px; height: 12px; border: 1px solid #333; vertical-align: -2px; margin-right: 4px; }
        .sp { --sp-w: 76px; display: inline-flex; flex-direction: column; gap: 4px; border: 2px solid #333; border-radius: 6px; padding: 8px; }
        .sp-row { display: flex; gap: 4px; justify-content: center; }
        .sp-cell { width: var(--sp-w); height: 48px; border: 1px solid #333; border-radius: 4px; background: #fff; font: inherit; color: #111; padding: 2px 4px;
            display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-start; position: relative; flex: none; }
        .sp-cell b { font-size: 13px; }
        .sp-cell small { font-size: 9px; line-height: 1.1; overflow: hidden; max-height: 22px; }
        .sp-aisle { width: 18px; border: 0; }
        .sp-driver { border-radius: 50%; width: 48px; align-items: center; justify-content: center; font-size: 9px; }
        .sp-door { border-style: dashed; align-items: center; justify-content: center; font-size: 9px; }
        .sp-cabin, .sp-deck { font-weight: 700; text-align: center; margin: 2px 0; }
        .sp-cabin { display: flex; justify-content: space-between; }
        .sold { background: repeating-linear-gradient(135deg, #fff 0 6px, #d1d5db 6px 7px); }
        .sp-cell.sold::after { content: "\2715"; position: absolute; top: 1px; right: 4px; font-size: 14px; font-weight: 800; }
        .sp-cell small { background: rgba(255, 255, 255, .85); padding: 0 2px; }
        .kept { background: #e5e7eb; }
        .held { margin-top: 10px; border: 2px dashed #9a4a06; border-radius: 6px; padding: 6px 10px; }
        .foot { margin-top: 12px; font-size: 11px; color: #333; }
        @media print { .bar { display: none; } body { padding: 0; } @page { margin: 8mm; } * { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
<div class="bar"><button onclick="window.print()">{{ translate('Print') }}</button></div>
<h1>{{ $agency->name }} · {{ translate('Seat sheet') }}</h1>
<div class="meta">
    <span><b>{{ $agency->origin_city }} &rarr; {{ $d->destination?->city }}</b></span>
    <span>{{ $d->leavesAt()->translatedFormat('l j F Y, H:i') }}</span>
    <span>{{ $class }} · {{ translate('Bus') }}: <b>{{ $d->vehicle?->registration }}</b></span>
    <span>{{ translate('Printed') }}: {{ now()->format('d/m/Y H:i') }}</span>
</div>
<div class="rules">{{ translate('Mark in pencil. Erase when E-Ride frees a seat. Check this sheet against E-Ride before selling on paper.') }}</div>
<div class="legend">
    <span><i class="sold"></i>{{ translate('Sold in E-Ride when printed') }}</span>
    <span><i class="kept"></i>{{ translate('Kept for the counter') }}</span>
    <span><i></i>{{ translate('Free when printed') }}</span>
</div>
<div id="map"></div>
@if($held)
    <div class="held"><b>{{ translate('Pencil these seats now') }}:</b> {{ implode(', ', $held) }} · {{ translate('Accepted, waiting for payment. Erase them if E-Ride frees them.') }}</div>
@endif
<div class="foot">{{ translate('Paper sales: write the name and phone in the seat box. Type them into E-Ride as soon as it is back (Quick sell).') }}</div>

@include('busmanagement::partner._seatplan_js')
<script>
(function () {
    var sold = @json(collect($sold)->map(fn($s) => $s['name'])), kept = {};
    @json($counterKept).forEach(function (s) { kept[s] = true; });
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    busSeatPlanRender(document.getElementById('map'), @json($d->seat_plan), {seat: function (c, el) {
        el.innerHTML = '<b>' + esc(c.n) + '</b>' + (sold[c.n] !== undefined ? '<small>' + esc(sold[c.n] || '') + '</small>' : '');
        if (sold[c.n] !== undefined) el.classList.add('sold');
        else if (kept[c.n]) el.classList.add('kept');
    }});
})();
</script>
</body>
</html>
