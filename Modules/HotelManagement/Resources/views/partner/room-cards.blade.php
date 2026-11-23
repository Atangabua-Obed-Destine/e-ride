<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ translate('Room cards') }} · {{ $hotel->name }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { margin: 0; font-family: Arial, sans-serif; color: #16181d; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8mm; }
        .card { border: 1.5px solid #16181d; border-radius: 6px; padding: 7mm; text-align: center; break-inside: avoid; height: 82mm; box-sizing: border-box; }
        .k { font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #e11d2a; }
        .h { font-size: 15px; font-weight: 800; margin: 2mm 0; }
        .qr svg { width: 40mm; height: 40mm; }
        .room { font-size: 20px; font-weight: 800; margin-top: 2mm; }
        .s { font-size: 11px; color: #555; margin-top: 1mm; }
        .bar { margin: 6mm 0; text-align: center; }
        @media print { .bar { display: none; } }
    </style>
</head>
<body>
<div class="bar"><button onclick="window.print()">{{ translate('Print') }}</button></div>
<div class="grid">
    @foreach($codes as $c)
        <div class="card">
            <div class="k">E-Ride · {{ translate('Room service') }}</div>
            <div class="h">{{ $hotel->name }}</div>
            <div class="qr">{!! $c['qr'] !!}</div>
            <div class="room">{{ translate('Room') }} {{ $c['room'] }}</div>
            <div class="s">{{ translate('Scan to order from') }} {{ $restaurant?->displayName() ?? $hotel->name }}</div>
        </div>
    @endforeach
</div>
</body>
</html>
