@extends('marketmanagement::web._layout')

@section('title', str_replace(':shop', $shop->name, $t['get_title']))
@section('description', $t['get_body'])

@section('body')
<div class="bar">
    <a class="brand" href="{{ $itemUrl }}">&#8249; {{ $t['back_item'] }}</a>
</div>
<div style="padding:24px 20px;display:flex;flex-direction:column;gap:16px">
    <div aria-hidden="true" style="width:68px;height:68px;border-radius:20px;background:#E02020;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900">E</div>
    <h1 style="margin:0;font-size:23px;line-height:1.25">{{ str_replace(':shop', $shop->name, $t['get_title']) }}</h1>
    <p style="margin:0;color:#48615E">{{ $t['get_body'] }}</p>
    <div style="display:flex;gap:12px;align-items:center;border:1px solid #E8ECEB;border-radius:14px;padding:10px">
        @if($cover)<img src="{{ $cover->url() }}" alt="" style="width:56px;height:56px;border-radius:10px;object-fit:cover">@endif
        <div><div style="font-weight:600">{{ $item->title }}</div><div class="price">{{ $item->price !== null ? getCurrencyFormat($item->price) : $t['ask_price'] }}</div></div>
    </div>
    <div>
        <div style="font-weight:700;margin-bottom:6px">{{ $t['get_also'] }}</div>
        <div>&#8226; {{ $t['get_delivery'] }}</div>
        <div>&#8226; {{ $t['get_rides'] }}</div>
        <div>&#8226; {{ $t['get_shop'] }}</div>
    </div>
</div>
<a class="btn dark" href="{{ $playUrl }}">{{ $t['get_play'] }}</a>
<p style="text-align:center;margin:14px 16px 28px"><a href="{{ $openApp }}" style="font-weight:600;text-decoration:none">{{ $t['get_have'] }}</a></p>
@endsection
