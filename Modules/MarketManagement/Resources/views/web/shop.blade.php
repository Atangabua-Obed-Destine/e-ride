@extends('marketmanagement::web._layout')

@section('title', $shop->name)
@section('description', trim(($shop->area_label ? $shop->area_label . ' · ' : '') . ($shop->about ?: $t['market'])))
@section('og_url', $url)
@if($ogImage)
@section('og_image', $ogImage)
@endif

@section('body')
<div class="bar">
    <a class="brand" href="{{ $url }}">E-RIDE <span>MARKET</span></a>
    <a class="pill" href="{{ $openApp }}">{{ $t['open_app'] }}</a>
</div>
<div style="padding:16px;display:flex;gap:14px;align-items:center">
    @if($shop->logoUrl())
        <img src="{{ $shop->logoUrl() }}" alt="" style="width:72px;height:72px;border-radius:20px;object-fit:cover;background:#EEF0F0">
    @else
        <div aria-hidden="true" style="width:72px;height:72px;border-radius:20px;background:#FDECEC;color:#E02020;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900">{{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}</div>
    @endif
    <div style="min-width:0">
        <h1 style="margin:0;font-size:20px;line-height:1.25">{{ $shop->name }}</h1>
        @if($shop->isVerified())<div class="badge">&#10003; {{ $t['verified'] }}</div>@endif
        <div class="muted">{{ trim(($shop->area_label ?: '') . ($shop->area_label ? ' · ' : '') . str_replace(':date', $shop->created_at->translatedFormat('M Y'), $t['since'])) }}</div>
    </div>
</div>
@if($shop->about)
    <p style="margin:0 16px 4px;font-size:14px">{{ $shop->about }}</p>
@endif
<div class="notice">{{ $t['scam_notice'] }}</div>
<div style="padding:8px 16px;font-weight:700">{{ $items->count() === 1 ? $t['one_item'] : str_replace(':n', $items->count(), $t['items']) }}</div>
@if($items->isEmpty())
    <p class="muted" style="padding:0 16px 24px">{{ $t['no_items'] }}</p>
@else
<div class="grid">
    @foreach($items as $it)
        @php($c = $it->cover())
        <a class="card" href="{{ \Modules\MarketManagement\Lib\MarketLinks::item($it) }}">
            @if($c)<img src="{{ $c->url() }}" alt="{{ $it->title }}" loading="lazy">@else<div class="ph"></div>@endif
            <div class="in">
                <div class="t">{{ $it->title }}</div>
                <div class="price">{{ $it->price !== null ? getCurrencyFormat($it->price) : $t['ask_price'] }}</div>
                @if($it->status === 'sold_out')<div class="muted">{{ $t['sold_out'] }}</div>@endif
            </div>
        </a>
    @endforeach
</div>
@endif
<a class="btn" href="{{ $openApp }}">{{ $t['chat'] }}</a>
<div class="foot"><span class="muted">{{ $t['no_phone'] }}</span><a href="{{ $openApp }}">{{ $t['report'] }}</a></div>
@endsection
