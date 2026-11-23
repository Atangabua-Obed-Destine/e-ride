<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ translate('Waiting for your Mobile Money') }}</title>
    <style>
        body { margin: 0; font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f6f6; color: #1d2d2b; }
        .box { max-width: 420px; margin: 12vh auto 0; background: #fff; border-radius: 6px; padding: 28px 22px; text-align: center; box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .spin { width: 28px; height: 28px; margin: 0 auto 16px; border: 3px solid #f5d0a9; border-top-color: #9a4a06; border-radius: 50%; animation: s 1s linear infinite; }
        @keyframes s { to { transform: rotate(360deg); } }
        h1 { font-size: 20px; margin: 0 0 8px; } p { color: #5f6b6a; margin: 0; }
    </style>
</head>
<body>
<main class="box" role="status" aria-live="polite">
    <div class="spin" aria-hidden="true"></div>
    <h1>{{ translate('Waiting for your Mobile Money') }}</h1>
    <p>{{ translate('Approve the payment on your phone. This page moves on by itself.') }}</p>
    @if($store)<p style="margin-top:12px;font-size:13px">{{ $store }}</p>@endif
</main>
<script>
    (function poll() {
        setTimeout(function () {
            fetch(@json($checkUrl), {headers: {'Accept': 'application/json'}}).then(function (r) { return r.json(); })
                .then(function (d) { if (d.done) { window.location.replace(d.next); } else { poll(); } })
                .catch(poll);
        }, 4000);
    })();
</script>
</body>
</html>
