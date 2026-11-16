@php
    use Modules\BusManagement\Service\ScheduleService;
    $d = $booking->departure;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ translate('Tickets') }} {{ $booking->code }}</title>
    <style>
        body { margin: 0; font: 13px/1.4 -apple-system, "Segoe UI", Roboto, sans-serif; color: #111; background: #eee; }
        .bar { padding: 10px; text-align: center; }
        .bar button { font: inherit; font-weight: 700; padding: 8px 18px; border-radius: 8px; border: 0; background: #e02020; color: #fff; cursor: pointer; }
        .t { width: 72mm; margin: 10px auto; background: #fff; padding: 5mm; box-sizing: border-box; border-radius: 6px; page-break-after: always; }
        .t:last-child { page-break-after: auto; }
        .ag { font-weight: 800; font-size: 15px; text-align: center; }
        .sub { text-align: center; font-size: 11px; color: #444; margin-bottom: 6px; }
        .route { display: flex; justify-content: space-between; font-weight: 800; font-size: 16px; border-top: 1px dashed #999; border-bottom: 1px dashed #999; padding: 6px 0; margin: 6px 0; }
        .seat { text-align: center; font-size: 30px; font-weight: 900; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; } td { padding: 2px 0; vertical-align: top; } td:last-child { text-align: right; font-weight: 700; }
        .qr { text-align: center; margin: 8px 0 2px; } .qr svg { width: 38mm; height: 38mm; }
        .code { text-align: center; font-family: ui-monospace, Consolas, monospace; font-size: 14px; letter-spacing: 2px; font-weight: 700; }
        .foot { text-align: center; font-size: 10.5px; color: #555; margin-top: 6px; }
        .stamp { text-align: center; font-weight: 900; letter-spacing: 3px; border: 2px solid #111; border-radius: 4px; padding: 2px; margin-bottom: 4px; }
        @media print { body { background: #fff; } .bar { display: none; } .t { margin: 0 auto; border-radius: 0; } @page { margin: 4mm; } }
    </style>
</head>
<body>
<div class="bar"><button onclick="window.print()">{{ translate('Print') }}</button></div>
@foreach($booking->tickets->sortBy(fn($t) => (int)$t->seat) as $t)
    @continue(!in_array($t->status, ['valid', 'boarded'], true))
    <section class="t">
        @if($reprint[$t->id] ?? false)<div class="stamp">{{ translate('REPRINT') }} {{ now()->format('d/m H:i') }}</div>@endif
        <div class="ag">{{ $busAgency->name }}</div>
        <div class="sub">{{ $busAgency->address }}{{ $busAgency->phone ? ' · ' . $busAgency->phone : '' }}</div>
        <div class="route"><span>{{ $busAgency->origin_city }}</span><span>&rarr;</span><span>{{ $d->destination?->city }}</span></div>
        <div class="seat">{{ translate('Seat') }} {{ $t->seat }}</div>
        <table>
            <tr><td>{{ translate('Passenger') }}</td><td>{{ $t->passenger_name }}</td></tr>
            @if($t->id_number)<tr><td>{{ translate('ID') }}</td><td>{{ $t->id_number }}</td></tr>@endif
            @if($booking->booker_id_number)<tr><td>{{ translate('Booked by (ID)') }}</td><td>{{ $booking->booker_id_number }}</td></tr>@endif
            @if($booking->booker_id_number)<tr><td>{{ translate('Booked by (ID)') }}</td><td>{{ $booking->booker_id_number }}</td></tr>@endif
            @if($booking->booker_id_number)<tr><td>{{ translate('Booked by (ID)') }}</td><td>{{ $booking->booker_id_number }}</td></tr>@endif
            <tr><td>{{ translate('Leaves') }}</td><td>{{ $d->leavesAt()->translatedFormat('D j M, H:i') }}</td></tr>
            <tr><td>{{ translate('Gets off at') }}</td><td>{{ $booking->dropPoint?->name }}</td></tr>
            <tr><td>{{ translate('Bus') }}</td><td>{{ ScheduleService::classLabel($d->class) }} · {{ $d->vehicle?->registration }}</td></tr>
            <tr><td>{{ translate('Bags') }}</td><td>{{ $t->bags }}</td></tr>
            <tr><td>{{ translate('Fare') }}</td><td>{{ getCurrencyFormat($t->fare) }}</td></tr>
        </table>
        <div class="qr">{!! $qr($t->code) !!}</div>
        <div class="code">{{ $t->code }}</div>
        <div class="foot">{{ translate('Show this ticket when you board.') }} · {{ $booking->code }} · {{ $booking->paid_at?->format('d/m/Y H:i') }}<br>{{ translate('Sold with E-Ride') }}</div>
    </section>
@endforeach
</body>
</html>
