@extends('marketmanagement::web._layout')

@section('title', $store->name)
@section('description', trim(($store->type === 'shop' ? $t['shop'] : $t['restaurant']) . ' · ' . ($store->description ?: $store->address)))
@section('og_url', $url)
@if($ogImage)
@section('og_image', $ogImage)
@endif

@section('body')
<style>
.cover{width:100%;aspect-ratio:16/7;object-fit:cover;display:block;background:#F4F6F6}
.ways{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.way{display:inline-flex;align-items:center;gap:4px;border:1px solid #E8ECEB;border-radius:6px;padding:4px 9px;font-size:12px;font-weight:600}
.open{color:#12A150;font-weight:700;font-size:13px}
.shut{color:#6B7675;font-weight:700;font-size:13px}
.sec{padding:14px 16px 4px;font-weight:800;font-size:16px}
.row{display:flex;gap:12px;align-items:center;padding:10px 16px;border-bottom:1px solid #F0F2F2}
.row img,.row .noimg{width:64px;height:64px;border-radius:6px;object-fit:cover;flex:none;background:#F4F6F6}
.row .n{font-weight:600;font-size:14px}
.row .d{color:#6B7675;font-size:12px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.row .p{font-weight:800;color:#E02020;font-size:14px;white-space:nowrap}
.row.off{opacity:.55}
.sticky{position:sticky;bottom:0;background:#fff;padding:10px 0 14px;border-top:1px solid #E8ECEB}
.tag{display:inline-block;background:#FDECEC;color:#E02020;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700}
</style>
<div class="bar">
    <a class="brand" href="{{ $url }}">E-RIDE <span>{{ $store->type === 'shop' ? 'SHOPS' : 'FOOD' }}</span></a>
    <a class="pill" href="{{ $openApp }}">{{ $t['open_app'] }}</a>
</div>
@if($store->coverUrl())
    <img class="cover" src="{{ $store->coverUrl() }}" alt="">
@endif
<div style="padding:14px 16px;display:flex;gap:12px;align-items:center">
    @if($store->logoUrl())
        <img src="{{ $store->logoUrl() }}" alt="" style="width:64px;height:64px;border-radius:8px;object-fit:cover;background:#F4F6F6">
    @endif
    <div style="min-width:0">
        <h1 style="margin:0;font-size:20px;line-height:1.25">{{ $store->name }}</h1>
        <div><span class="tag">&#10003; {{ $t['partner'] }}</span> <span class="muted">{{ $store->type === 'shop' ? $t['shop'] : $t['restaurant'] }}@if($branches > 1) · {{ str_replace(':n', $branches, $t['branches']) }}@endif</span></div>
        @if($openNow)
            <div class="open">{{ $hours && $hours[1] !== '23:59' ? str_replace(':time', $hours[1], $t['open_until']) : $t['open_now'] }}</div>
        @else
            <div class="shut">{{ $hours ? str_replace([':from', ':to'], $hours, $t['opens']) : $t['closed_today'] }}</div>
        @endif
    </div>
</div>
@if($store->description)
    <p style="margin:0 16px 6px;font-size:14px">{{ $store->description }}</p>
@endif
<div style="padding:0 16px">
    <div class="muted">{{ $kitchen->address }}</div>
    <div class="ways">
        @foreach($ways as $w)
            <span class="way">{{ $t['way_' . $w] }}</span>
        @endforeach
    </div>
</div>
@foreach($menu as $section)
    @if(count($section['items']))
        <div class="sec">{{ $section['name'] }}</div>
        @foreach($section['items'] as $it)
            @php($off = !($it['is_available'] ?? false))
            <a class="row {{ $off ? 'off' : '' }}" href="{{ $openApp }}" style="text-decoration:none;color:inherit">
                @if($it['image'])<img src="{{ $it['image'] }}" alt="" loading="lazy">@else<div class="noimg"></div>@endif
                <div style="flex:1;min-width:0">
                    <div class="n">{{ $it['name'] }}</div>
                    @if($it['description'])<div class="d">{{ $it['description'] }}</div>@endif
                    @if($off)<div class="muted">{{ $it['state'] === 'sold_out' ? $t['sold_out'] : ($it['state'] === 'later' ? $t['later'] : $t['not_today']) }}</div>@endif
                </div>
                <div class="p">{{ getCurrencyFormat((float)$it['price']) }}</div>
            </a>
        @endforeach
    @endif
@endforeach
<div class="notice">{{ $t['pay'] }}</div>
<div class="sticky"><a class="btn" href="{{ $openApp }}">{{ $t['order_app'] }}</a></div>
@endsection
