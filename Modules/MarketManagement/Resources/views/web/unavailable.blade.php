@extends('marketmanagement::web._layout')

@section('title', $message)
@section('description', $t['market'])

@section('body')
<div class="bar"><span class="brand">E-RIDE <span>MARKET</span></span></div>
<div style="padding:48px 24px;text-align:center;display:flex;flex-direction:column;gap:16px;align-items:center">
    <div aria-hidden="true" style="width:64px;height:64px;border-radius:50%;background:#EEF0F0;color:#6B7675;display:flex;align-items:center;justify-content:center;font-size:28px">?</div>
    <h1 style="margin:0;font-size:20px">{{ $message }}</h1>
    @if($shopUrl)
        <a class="btn" href="{{ $shopUrl }}" style="width:100%;max-width:320px">{{ $t['back_to_shop'] }}</a>
    @endif
</div>
@endsection
