@extends('marketmanagement::web._layout')

@section('title', str_replace(':shop', $shop->name, $t['shop_get_title']))
@section('description', $t['shop_get_body'])

@section('body')
<div class="bar">
    <a class="brand" href="{{ $shopUrl }}">&#8249; {{ $t['back_shop'] }}</a>
</div>
<div style="padding:24px 20px;display:flex;flex-direction:column;gap:16px">
    @if($shop->logoUrl())
        <img src="{{ $shop->logoUrl() }}" alt="" style="width:68px;height:68px;border-radius:8px;object-fit:cover">
    @else
        <div aria-hidden="true" style="width:68px;height:68px;border-radius:8px;background:#E02020;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900">{{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}</div>
    @endif
    <h1 style="margin:0;font-size:23px;line-height:1.25">{{ str_replace(':shop', $shop->name, $t['shop_get_title']) }}</h1>
    <p style="margin:0;color:#48615E">{{ $t['shop_get_body'] }}</p>
    @if($shop->inVenue() && $shop->venue)
        <div style="border:1px solid #F3D9B8;background:#FFF8F0;border-radius:8px;padding:10px">
            <div style="font-weight:700">{{ $shop->venue->name }}</div>
            <div style="color:#B45309;font-weight:700">{{ $shop->shedLabel() }}</div>
        </div>
    @endif
    <div>
        <div style="font-weight:700;margin-bottom:6px">{{ $t['get_also'] }}</div>
        <div>&#8226; {{ $t['get_delivery'] }}</div>
        <div>&#8226; {{ $t['get_rides'] }}</div>
        <div>&#8226; {{ $t['get_shop'] }}</div>
    </div>
</div>
<a class="btn dark" href="{{ $playUrl }}">{{ $t['get_play'] }}</a>
<p style="text-align:center;margin:14px 16px 28px"><a href="{{ $openApp }}" style="font-weight:600;text-decoration:none">{{ $t['get_have_shop'] }}</a></p>
@endsection
