<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ translate('Passenger list') }} · {{ $data['label'] }}</title>
    <style>
        body { margin: 0; padding: 14mm 12mm; font: 12px/1.35 -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; }
        .bar { margin-bottom: 10px; } .bar button { font: inherit; font-weight: 700; padding: 8px 18px; border-radius: 8px; border: 0; background: #e02020; color: #fff; cursor: pointer; }
        h1 { font-size: 18px; margin: 0; } .meta { display: flex; flex-wrap: wrap; gap: 4px 18px; margin: 6px 0 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #bbb; padding: 4px 6px; text-align: left; } th { background: #f1f3f3; font-size: 11px; text-transform: uppercase; }
        td.n { width: 34px; text-align: center; font-weight: 800; } .sign { margin-top: 26px; display: flex; gap: 40px; } .sign div { flex: 1; border-top: 1px solid #333; padding-top: 4px; }
        @media print { .bar { display: none; } body { padding: 0; } @page { margin: 10mm; } tr { page-break-inside: avoid; } }
    </style>
</head>
<body>
<div class="bar"><button onclick="window.print()">{{ translate('Print') }}</button></div>
<h1>{{ $busAgency->name }} · {{ translate('Passenger list') }}</h1>
<div class="meta">
    <span><b>{{ $busAgency->origin_city }} &rarr; {{ $departure->destination?->city }}</b></span>
    <span>{{ $data['label'] }}</span>
    <span>{{ translate('Bus') }}: <b>{{ $data['bus'] }}</b></span>
    <span>{{ translate('Passengers') }}: <b>{{ $data['sold'] }}</b> / {{ $data['capacity'] }}</span>
    <span>{{ translate('Printed') }}: {{ now()->format('d/m/Y H:i') }}</span>
</div>
<table>
    <thead><tr><th>{{ translate('Seat') }}</th><th>{{ translate('Name') }}</th><th>{{ translate('ID number') }}</th><th>{{ translate('Phone') }}</th><th>{{ translate('Gets off at') }}</th><th>{{ translate('Bags') }}</th><th>{{ translate('On board') }}</th></tr></thead>
    <tbody>
    @forelse($data['tickets'] as $t)
        <tr><td class="n">{{ $t['seat'] }}</td><td>{{ $t['name'] }}@if($t['payer_only'] ?? false) <small>({{ translate('person paying') }})</small>@endif</td><td>{{ $t['id_number'] ?: ($t['booker_id'] ? $t['booker_id'] . ' (' . translate('booker') . ')' : '') }}</td><td>{{ $t['phone'] }}</td><td>{{ $t['drop_point'] }}</td><td>{{ $t['bags'] }}</td>
            <td>{{ $t['status'] === 'boarded' ? '✓ ' . $t['boarded_at'] : ($t['status'] === 'no_show' ? translate('No') : '') }}</td></tr>
    @empty
        <tr><td colspan="7">{{ translate('No passenger yet.') }}</td></tr>
    @endforelse
    </tbody>
</table>
<div class="sign"><div>{{ translate('Driver') }}</div><div>{{ translate('Agency') }}</div></div>
</body>
</html>
