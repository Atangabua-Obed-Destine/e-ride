<!DOCTYPE html>
<html lang="{{ $t['lang'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $t['title'] }}{{ $card ? ' · ' . $card['hotel']['name'] : '' }} · E-Ride</title>
    <style>
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #f4f4f6; color: #16181d; }
        .w { max-width: 440px; margin: 0 auto; padding: 28px 18px; }
        .c { background: #fff; border-radius: 6px; padding: 22px 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
        .k { font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #e11d2a; }
        h1 { font-size: 24px; margin: 8px 0 2px; }
        .room { display: inline-block; margin: 12px 0; padding: 8px 16px; border-radius: 6px; background: #eef2ff; color: #3730a3; font-weight: 800; font-size: 20px; }
        p { color: #5b6170; font-size: 14px; line-height: 1.45; }
        .b { display: block; margin-top: 14px; padding: 14px; border-radius: 6px; background: #e11d2a; color: #fff; font-weight: 800; text-decoration: none; }
        .l { display: block; margin-top: 10px; color: #16181d; font-size: 14px; }
    </style>
</head>
<body>
<div class="w">
    <div class="c">
        <div class="k">E-Ride · {{ $t['title'] }}</div>
        @if($card)
            <h1>{{ $card['hotel']['name'] }}</h1>
            <div>{{ $card['store_name'] }}</div>
            <div class="room">{{ $t['room'] }} {{ $card['room'] }}</div>
            <p>{{ $t['by'] }} {{ $t['pay'] }}</p>
            <a class="b" href="{{ $openApp }}">{{ $t['open'] }}</a>
            <a class="l" href="{{ $playUrl }}">{{ $t['get'] }}</a>
        @else
            <p>{{ $t['gone'] }}</p>
        @endif
    </div>
</div>
</body>
</html>
