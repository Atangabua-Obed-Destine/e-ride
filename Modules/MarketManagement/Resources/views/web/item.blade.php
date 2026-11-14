@extends('marketmanagement::web._layout')

@php($price = $item->price !== null ? getCurrencyFormat($item->price) : $t['ask_price'])
@section('title', $item->title . ' · ' . $price)
@section('description', trim($shop->name . ($shop->area_label ? ' · ' . $shop->area_label : '') . ' · ' . $t['market']))
@section('og_type', 'product')
@section('og_url', $url)
@if($cover)
@section('og_image', $cover->ogUrl())
@endif

@section('body')
<div class="bar">
    <a class="brand" href="{{ $shopUrl }}">&#8249; {{ $shop->name }}</a>
    <a class="brand" href="{{ $shopUrl }}" style="font-size:13px">E-RIDE <span>MARKET</span></a>
</div>
@php($photos = $item->photos->where('preview', false)->values())
@if($photos->isNotEmpty())
    <div style="display:flex;overflow-x:auto;scroll-snap-type:x mandatory;background:#EEF0F0">
        @foreach($photos as $p)
            <img src="{{ $p->url() }}" alt="{{ $item->title }}" style="width:100%;flex:0 0 100%;aspect-ratio:1/1;object-fit:cover;scroll-snap-align:start" @if(!$loop->first) loading="lazy" @endif>
        @endforeach
    </div>
@endif
<div style="padding:16px;display:flex;flex-direction:column;gap:10px">
    <h1 style="margin:0;font-size:20px;line-height:1.25">{{ $item->title }}</h1>
    <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
        <div class="price" style="font-size:24px">{{ $price }}</div>
        @if($item->negotiable && $item->price !== null)<div class="muted">{{ $t['negotiable'] }}</div>@endif
    </div>
    <div class="chips">
        <span class="chip">{{ $t[$item->condition] ?? $item->condition }}</span>
        @if($item->status === 'sold_out')
            <span class="chip">{{ $t['sold_out'] }}</span>
        @elseif(!$item->isDigital())
            <span class="chip">{{ str_replace(':n', $item->quantity, $t['left']) }}</span>
        @endif
        @if($item->pickup)<span class="chip">{{ $shop->area_label ? str_replace(':area', $shop->area_label, $t['pickup']) : $t['pickup_plain'] }}</span>@endif
        @if($item->eride_delivery)<span class="chip">{{ $t['eride_delivery'] }}</span>@endif
        @if($item->seller_delivers)<span class="chip">{{ $t['seller_delivers'] }}</span>@endif
    </div>
    @if($item->description)<div style="font-size:14px;white-space:pre-line">{{ $item->description }}</div>@endif
    <div class="muted">{{ $shop->name }}@if($shop->isVerified()) · <span class="badge">&#10003; {{ $t['verified'] }}</span>@endif</div>
</div>
@php($previews = $item->photos->where('preview', true)->values())
@if($previews->isNotEmpty())
    <div style="padding:0 16px 8px;font-weight:700">{{ $t['preview'] }}</div>
    <div style="display:flex;gap:8px;overflow-x:auto;padding:0 16px 12px">
        @foreach($previews as $p)
            <img src="{{ $p->url() }}" alt="{{ $t['preview'] }}" loading="lazy" style="height:180px;border-radius:10px;border:1px solid #E8ECEB">
        @endforeach
    </div>
@endif
@if($strongWarning)
    <div class="danger"><b>{{ $t['digital_warning_title'] }}</b>{{ $t['digital_warning'] }}</div>
@else
    <div class="notice">{{ $t['stay_safe'] }}</div>
@endif
<a class="btn" href="{{ $openApp }}">{{ $t['chat'] }}</a>
<div class="foot"><span class="muted">{{ $t['no_phone'] }}</span><a href="{{ $openApp }}">{{ $t['report'] }}</a></div>
@endsection
