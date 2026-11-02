@php
    $money = fn($v) => getCurrencyFormat($v);
    $titles = [
        'waiting' => translate('Approve the payment on your phone'),
        'failed' => translate('The payment did not go through'),
        'expired' => translate('This payment link has expired'),
        'mismatched' => translate('We need to check this payment'),
        'refundable' => translate('This was already paid'),
        'unavailable' => translate('Mobile money is unavailable'),
    ];
    $bodies = [
        'waiting' => translate('A prompt was sent to your mobile money number. Enter your PIN to approve it. This page updates by itself.'),
        'failed' => translate('No money was taken. It may have been declined, timed out or the balance was too low. You can try again.'),
        'expired' => translate('No money was taken. Start a new payment to continue.'),
        'mismatched' => translate('Your payment reached us but does not match this bill. Our team will review it. Please keep your mobile money receipt and contact support.'),
        'refundable' => translate('This bill was settled before your mobile money payment arrived. Keep your receipt and contact support for a refund.'),
        'unavailable' => $message,
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ translate('Mobile Money') }} · {{ $businessName }}</title>
    <style>
        :root {
            --bg: #f5f5f7; --card: #ffffff; --ink: #141416; --muted: #6b6b76; --line: #e7e7ec;
            --red: #e02020; --red-soft: rgba(224, 32, 32, .08); --ok: #12a150; --warn: #c27a00;
            --mtn: #ffcb05; --orange: #ff7900;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0e0e10; --card: #17171a; --ink: #f4f4f6; --muted: #9a9aa6; --line: #26262c; --red: #e84c4c; --red-soft: rgba(232, 76, 76, .12); }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: var(--bg); color: var(--ink); }
        body { font: 15px/1.5 -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, sans-serif; min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .card { width: 100%; max-width: 420px; background: var(--card); border: 1px solid var(--line); border-radius: 24px; padding: 28px 22px 22px; text-align: center; }
        .brand { display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--muted); font-size: 13px; font-weight: 600; margin-bottom: 22px; }
        .brand img { height: 22px; width: auto; }
        .ops { display: inline-flex; gap: 4px; }
        .ops i { width: 10px; height: 10px; border-radius: 50%; display: block; }
        .ops i:first-child { background: var(--mtn); }
        .ops i:last-child { background: var(--orange); }

        .glyph { width: 88px; height: 88px; margin: 0 auto 18px; position: relative; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--red-soft); color: var(--red); }
        .glyph svg { width: 40px; height: 40px; }
        .glyph.is-warn { background: rgba(194, 122, 0, .1); color: var(--warn); }
        .glyph.is-ok { background: rgba(18, 161, 80, .1); color: var(--ok); }
        /* waiting: the phone is "ringing" with a prompt */
        .glyph.is-wait::before, .glyph.is-wait::after { content: ''; position: absolute; inset: 0; border-radius: 50%; border: 2px solid var(--red); opacity: 0; animation: ring 2.4s ease-out infinite; }
        .glyph.is-wait::after { animation-delay: 1.2s; }
        @keyframes ring { 0% { transform: scale(.9); opacity: .55; } 100% { transform: scale(1.5); opacity: 0; } }

        h1 { font-size: 21px; line-height: 1.25; margin: 0 0 8px; letter-spacing: -.01em; }
        p.lead { margin: 0 auto; color: var(--muted); max-width: 330px; }

        .bill { margin: 22px 0 6px; border: 1px solid var(--line); border-radius: 16px; padding: 4px 16px; text-align: left; }
        .bill div { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; font-variant-numeric: tabular-nums; }
        .bill div + div { border-top: 1px dashed var(--line); }
        .bill span { color: var(--muted); }
        .bill .total { font-weight: 800; font-size: 17px; }
        .bill .total span { color: var(--ink); }

        .hint { margin: 14px 0 0; font-size: 13px; color: var(--muted); }
        .hint code { font: 600 13px/1 ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--ink); background: var(--bg); border: 1px solid var(--line); border-radius: 6px; padding: 2px 6px; }
        .status { margin-top: 14px; font-size: 13px; color: var(--muted); display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 20px; }
        .status b { width: 7px; height: 7px; border-radius: 50%; background: var(--red); animation: blink 1.2s ease-in-out infinite; }
        @keyframes blink { 50% { opacity: .25; } }

        .actions { display: flex; flex-direction: column; gap: 10px; margin-top: 22px; }
        .btn { display: flex; align-items: center; justify-content: center; min-height: 50px; border-radius: 999px; font-weight: 700; font-size: 15.5px; text-decoration: none; border: 0; cursor: pointer; }
        .btn-primary { background: var(--red); color: #fff; }
        .btn-ghost { background: transparent; color: var(--ink); border: 1px solid var(--line); }
        .ref { margin-top: 18px; font-size: 12px; color: var(--muted); word-break: break-all; }
        [hidden] { display: none !important; }
        @media (prefers-reduced-motion: reduce) { .glyph.is-wait::before, .glyph.is-wait::after, .status b { animation: none; } }
    </style>
</head>
<body>
<main class="card" role="main">
    <div class="brand">
        @if($businessLogo)
            <img src="{{ $businessLogo }}" alt="{{ $businessName }}" onerror="this.remove()">
        @else
            <span>{{ $businessName }}</span>
        @endif
        <span aria-hidden="true">·</span>
        <span class="ops" aria-hidden="true"><i></i><i></i></span>
        <span>{{ translate('Mobile Money') }}</span>
    </div>

    @php($glyph = match ($state) { 'waiting' => 'is-wait', 'refundable' => 'is-ok', 'mismatched', 'unavailable' => 'is-warn', default => '' })
    <div class="glyph {{ $glyph }}" aria-hidden="true">
        @if($state === 'waiting')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M10.5 18.5h3"/><path d="M9.5 9.5l2 2 3.5-3.5"/></svg>
        @elseif($state === 'refundable')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>
        @elseif(in_array($state, ['mismatched', 'unavailable']))
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5L2.8 19.5h18.4z"/><path d="M12 10v4"/><path d="M12 17h.01"/></svg>
        @else
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>
        @endif
    </div>

    <h1>{{ $titles[$state] ?? $titles['failed'] }}</h1>
    <p class="lead">{{ $bodies[$state] ?? $bodies['failed'] }}</p>

    @if(in_array($state, ['waiting', 'failed', 'expired', 'unavailable']) && $quote['charge'] > 0)
        <div class="bill">
            @if($quote['fee'] > 0)
                <div><span>{{ translate('Amount') }}</span><strong>{{ $money($quote['base']) }}</strong></div>
                <div><span>{{ translate('Mobile money fee') }}</span><strong>{{ $money($quote['fee']) }}</strong></div>
            @endif
            <div class="total"><span>{{ translate('To pay') }}</span><strong>{{ $money($quote['charge']) }}</strong></div>
        </div>
    @endif

    @if($state === 'waiting')
        <p class="hint">{!! str_replace([':mtn', ':orange', '{mtn}', '{orange}'], ['<code>*126#</code>', '<code>#150*50#</code>', '<code>*126#</code>', '<code>#150*50#</code>'], e(translate('No prompt yet. MTN users can dial {mtn} and Orange users {orange} to approve pending payments.'))) !!}</p>
        <div class="status" role="status" aria-live="polite"><b></b><span data-status>{{ translate('Waiting for confirmation') }}</span></div>
    @endif

    <div class="actions">
        @if($state === 'waiting')
            <a class="btn btn-primary" href="{{ $returnUrl }}">{{ translate('I have approved it') }}</a>
            <a class="btn btn-ghost" href="{{ $retryUrl }}" data-reopen hidden>{{ translate('Open the checkout again') }}</a>
        @elseif(in_array($state, ['failed', 'expired']))
            <a class="btn btn-primary" href="{{ $retryUrl }}">{{ translate('Try again') }}</a>
        @elseif($state === 'refundable')
            <a class="btn btn-primary" href="{{ $leaveUrl }}">{{ translate('Continue') }}</a>
        @endif
        @if($state !== 'refundable')
            <a class="btn btn-ghost" href="{{ $leaveUrl }}">{{ translate('Choose another way to pay') }}</a>
        @endif
    </div>

    @if($transaction && $transaction->trans_id && in_array($state, ['mismatched', 'refundable', 'failed']))
        <div class="ref">{{ translate('Reference') }}: {{ $transaction->trans_id }}</div>
    @endif
</main>

@if($state === 'waiting' && $checkUrl)
<script>
(function () {
    var checkUrl = @json($checkUrl), returnUrl = @json($returnUrl);
    var label = document.querySelector('[data-status]'), reopen = document.querySelector('[data-reopen]');
    var started = Date.now(), busy = false;
    function tick() {
        if (busy) return;
        busy = true;
        fetch(checkUrl, {headers: {'Accept': 'application/json'}, cache: 'no-store'})
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d && d.done) { window.location.replace(returnUrl); } })
            .catch(function () {})
            .then(function () { busy = false; });
        if (Date.now() - started > 120000 && reopen) {
            reopen.hidden = false;
            label.textContent = @json(translate('Still waiting. You can open the checkout again.'));
        }
    }
    setInterval(tick, 5000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
})();
</script>
@endif
</body>
</html>
