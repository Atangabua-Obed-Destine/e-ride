@extends('marketmanagement::web._layout')

@section('title', str_replace(':job', $job->title, $t['get_title_job']))
@section('description', $t['get_body_job'])

@section('body')
<div class="bar">
    <a class="brand" href="{{ $jobUrl }}">&#8249; {{ $t['back_job'] }}</a>
</div>
<div style="padding:24px 20px;display:flex;flex-direction:column;gap:16px">
    <div aria-hidden="true" style="width:68px;height:68px;border-radius:20px;background:#E02020;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900">E</div>
    <h1 style="margin:0;font-size:23px;line-height:1.25">{{ str_replace(':job', $job->title, $t['get_title_job']) }}</h1>
    <p style="margin:0;color:#48615E">{{ $t['get_body_job'] }}</p>
    <div style="border:1px solid #E8ECEB;border-radius:14px;padding:10px">
        <div style="font-weight:600">{{ $job->title }}</div>
        <div class="muted">{{ $job->employer?->name }}</div>
        <div class="price">{{ $pay }}</div>
    </div>
    <div>
        <div style="font-weight:700;margin-bottom:6px">{{ $t['get_also'] }}</div>
        <div>&#8226; {{ $t['get_also_alerts'] }}</div>
        <div>&#8226; {{ $t['get_also_rides'] }}</div>
        <div>&#8226; {{ $t['get_shop'] }}</div>
    </div>
</div>
<a class="btn dark" href="{{ $playUrl }}">{{ $t['get_play'] }}</a>
<p style="text-align:center;margin:14px 16px 28px"><a href="{{ $openApp }}" style="font-weight:600;text-decoration:none">{{ $t['get_have_job'] }}</a></p>
@endsection
