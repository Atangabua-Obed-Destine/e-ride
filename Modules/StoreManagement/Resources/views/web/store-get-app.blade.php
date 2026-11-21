@extends('marketmanagement::web._layout')

@section('title', str_replace(':store', $store->name, $t['get_title']))
@section('description', str_replace(':store', $store->name, $t['get_body']))

@section('body')
<div class="bar">
    <a class="brand" href="{{ $url }}">E-RIDE <span>{{ $store->type === 'shop' ? 'SHOPS' : 'FOOD' }}</span></a>
</div>
<div style="padding:22px 16px 8px;text-align:center">
    @if($store->logoUrl())
        <img src="{{ $store->logoUrl() }}" alt="" style="width:84px;height:84px;border-radius:8px;object-fit:cover">
    @endif
    <h1 style="font-size:20px;margin:12px 0 6px">{{ str_replace(':store', $store->name, $t['get_title']) }}</h1>
    <p class="muted" style="font-size:14px;margin:0">{{ str_replace(':store', $store->name, $t['get_body']) }}</p>
</div>
<div style="padding:12px 0">
    <a class="btn" href="{{ $playUrl }}">{{ $t['get_play'] }}</a>
    <div style="height:10px"></div>
    <a class="btn dark" href="{{ $openApp }}">{{ $t['get_have'] }}</a>
</div>
<div style="padding:8px 16px 4px;font-weight:700">{{ $t['get_also'] }}</div>
<ul style="margin:0 16px 20px;padding-left:18px;font-size:14px">
    <li>{{ $t['get_rides'] }}</li>
    <li>{{ $t['get_market'] }}</li>
    <li>{{ $t['get_parcel'] }}</li>
</ul>
<div class="foot"><a href="{{ $url }}">{{ $t['back'] }}</a></div>
@endsection
