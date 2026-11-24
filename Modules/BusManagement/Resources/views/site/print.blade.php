<!doctype html>
<html lang="{{ $t->lang }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $t('booking', ['code' => $booking->code]) }} · E-Ride Bus</title>
<style>
*{box-sizing:border-box}
body{margin:0;font:14px/1.4 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#111;background:#fff}
.t{border:1.5px solid #111;border-radius:12px;margin:16px auto;max-width:640px;page-break-inside:avoid;overflow:hidden}
.h{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1.5px dashed #111}
.g{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:12px 16px}
.k{font-size:11px;color:#444}.v{font-size:15px;font-weight:700}
.q{display:flex;gap:16px;align-items:center;padding:0 16px 14px}
.q svg{width:130px;height:130px}
.bar{max-width:640px;margin:16px auto 0;display:flex;gap:10px;padding:0 16px}
.bar button,.bar a{height:44px;padding:0 16px;border-radius:10px;border:1px solid #999;background:#fff;font:inherit;font-weight:600;color:#111;text-decoration:none;display:inline-flex;align-items:center;cursor:pointer}
@media print{.bar{display:none}.t{margin:8mm auto}}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">{{ $t('print') }}</button><a href="{{ route('bus.web.booking', ['code' => $booking->code]) }}">{{ $t('back') }}</a></div>
@php($d = $booking->departure)
@php($at = $d->leavesAt())
@if($collect)
    <div class="t">
        <div class="h"><b>E-Ride Bus · {{ $booking->agency->displayName() }}</b><b>{{ $booking->code }}</b></div>
        <div class="g"><div><div class="k">{{ $t('date') }}</div><div class="v">{{ $t->day($at) }}</div></div><div><div class="k">{{ $t('leaves') }}</div><div class="v">{{ $at->format('H:i') }}</div></div><div><div class="k">{{ $t('seat') }}</div><div class="v">{{ $booking->tickets->where('status', '!=', 'cancelled')->pluck('seat')->implode(', ') }}</div></div></div>
        <div class="q">{!! $qr('ERIDE-BUS:' . $booking->code) !!}<div><b>{{ $t('collect_title') }}</b><br>{{ $t('collect_body', ['agency' => $booking->agency->displayName(), 'city' => $booking->agency->origin_city]) }}</div></div>
    </div>
@else
    @foreach($booking->tickets->whereIn('status', ['valid', 'boarded'])->sortBy(fn($x) => (int)$x->seat) as $ticket)
        <div class="t">
            <div class="h"><b>E-Ride Bus · {{ $booking->agency->displayName() }}</b><b>{{ $booking->code }}</b></div>
            <div class="g">
                <div><div class="k">{{ $t('from') }}</div><div class="v">{{ $booking->agency->origin_city }}</div></div>
                <div><div class="k">{{ $t('to') }}</div><div class="v">{{ $d->destination?->city }}</div></div>
                <div><div class="k">{{ $t('gets_off') }}</div><div class="v">{{ $booking->dropPoint?->name }}</div></div>
                <div><div class="k">{{ $t('date') }}</div><div class="v">{{ $t->day($at) }}</div></div>
                <div><div class="k">{{ $t('leaves') }}</div><div class="v">{{ $at->format('H:i') }}</div></div>
                <div><div class="k">{{ $t('seat') }}</div><div class="v">{{ $ticket->seat }}</div></div>
                <div style="grid-column:span 3"><div class="k">{{ $t('traveller') }}</div><div class="v">{{ $ticket->passenger_name }}</div></div>
            </div>
            <div class="q">{!! $qr('ERIDE-BUS:' . $ticket->code) !!}<div><b>{{ $t('show_boarding') }}</b><br>{{ $t('be_there', ['city' => $booking->agency->origin_city]) }}<br><span class="k">{{ $ticket->code }}</span></div></div>
        </div>
    @endforeach
@endif
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
