@php
    $agency = $booking?->agency;
    $text = match ($state) {
        'paid' => [translate('Paid. Your tickets are ready.'), translate('Go back to the E-Ride app: your tickets with their QR codes are under My tickets.')],
        'late' => [translate('Your payment came too late'), translate('The seats were already released. The agency sends the money back to your Mobile Money. The details are in the E-Ride app.')],
        'failed' => [translate('The payment did not go through'), translate('Nothing was taken. Go back to the E-Ride app to try again before your time runs out.')],
        default => [translate('Waiting for your Mobile Money'), translate('Approve the payment on your phone. This page moves on by itself.')],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $text[0] }}</title>
    <style>
        body { margin: 0; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f6f6; color: #1d2d2b; }
        .box { max-width: 420px; margin: 12vh auto 0; background: #fff; border-radius: 18px; padding: 28px 22px; text-align: center; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .icon { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: 800; }
        .ok { background: #e9f7ef; color: #15803d; } .bad { background: #fdecec; color: #b91c1c; } .wait { background: #fff4e5; color: #9a4a06; }
        h1 { font-size: 20px; margin: 0 0 8px; } p { color: #5f6b6a; margin: 0; }
        .ref { margin-top: 16px; font-size: 13px; color: #5f6b6a; }
        .spin { width: 28px; height: 28px; border: 3px solid #f5d0a9; border-top-color: #9a4a06; border-radius: 50%; animation: s 1s linear infinite; }
        @keyframes s { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<main class="box" role="status" aria-live="polite">
    <div class="icon {{ $state === 'paid' ? 'ok' : ($state === 'waiting' ? 'wait' : 'bad') }}" aria-hidden="true">@if($state === 'paid')&#10003;@elseif($state === 'waiting')<span class="spin"></span>@else!@endif</div>
    <h1>{{ $text[0] }}</h1>
    <p>{{ $text[1] }}</p>
    @if($booking)
        <div class="ref">{{ $agency?->name }} · {{ $booking->departure?->destination?->city }} · {{ $booking->departure?->leavesAt()->format('d/m H:i') }} · {{ $booking->code }}</div>
    @endif
</main>
@if($state === 'waiting')
<script>
    (function poll() {
        setTimeout(function () {
            fetch(@json($checkUrl), {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); })
                .then(function (d) { if (d.done) { window.location.replace(d.next); } else { poll(); } })
                .catch(poll);
        }, 4000);
    })();
</script>
@endif
</body>
</html>
