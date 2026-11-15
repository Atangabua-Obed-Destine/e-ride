@extends('marketmanagement::web._layout')

@section('title', $job->title . ' · ' . $pay)
@section('description', trim(($employer?->name ?? '') . ($job->area_label ? ' · ' . $job->area_label : '') . ' · ' . $t['market']))
@section('og_type', 'website')
@section('og_url', $url)
@if($employer?->logoUrl())
@section('og_image', $employer->logoUrl())
@endif

@section('body')
<div class="bar">
    <span class="brand">E-RIDE <span>JOBS</span></span>
</div>
<div style="padding:16px;display:flex;flex-direction:column;gap:12px">
    <div style="display:flex;gap:12px;align-items:center">
        @if($employer?->logoUrl())
            <img src="{{ $employer->logoUrl() }}" alt="" style="width:56px;height:56px;border-radius:12px;object-fit:cover">
        @else
            <div aria-hidden="true" style="width:56px;height:56px;border-radius:12px;background:#FDECEC;color:#E02020;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800">{{ mb_strtoupper(mb_substr($employer?->name ?? 'E', 0, 1)) }}</div>
        @endif
        <div style="min-width:0">
            <h1 style="margin:0;font-size:20px;line-height:1.25">{{ $job->title }}</h1>
            <div class="muted">{{ $employer?->name }}@if($job->area_label) · {{ $job->area_label }}@endif</div>
        </div>
    </div>
    <div class="chips">
        @if($employer?->isVerified())<span class="chip" style="background:#E9F7EF;color:#15803D">&#10003; {{ $t['verified_employer'] }}</span>@endif
        @if($employer?->isPrivate())<span class="chip" style="background:#EEF2FF;color:#3730A3">{{ $t['private_employer'] }}</span>@endif
        <span class="chip">{{ $t['type_' . $job->type] ?? $job->type }}</span>
        <span class="chip">{{ $job->places == 1 ? $t['one_place'] : str_replace(':n', $job->places, $t['places']) }}</span>
    </div>
    <div style="border:1px solid #E8ECEB;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:6px">
        <div><span class="muted">{{ $t['pay'] }}</span><div class="price" style="font-size:20px">{{ $pay }}</div></div>
        @if($job->schedule)<div><span class="muted">{{ $t['hours'] }}</span><div style="font-weight:600">{{ $job->schedule }}</div></div>@endif
        @if($job->area_label)<div><span class="muted">{{ $t['where'] }}</span><div style="font-weight:600">{{ $job->area_label }}</div></div>@endif
    </div>
    <div>
        <div style="font-weight:700;margin-bottom:4px">{{ $t['the_job'] }}</div>
        <div style="font-size:14px;white-space:pre-line">{{ $job->description }}</div>
    </div>
    @if($job->requirements || !empty($job->skills))
        <div>
            <div style="font-weight:700;margin-bottom:4px">{{ $t['you_need'] }}</div>
            @if($job->requirements)<div style="font-size:14px;white-space:pre-line">{{ $job->requirements }}</div>@endif
            @if(!empty($job->skills))<div class="chips" style="margin-top:6px">@foreach($job->skills as $s)<span class="chip">{{ $s }}</span>@endforeach</div>@endif
        </div>
    @endif
</div>
<div class="notice">{{ $t['no_fee'] }}</div>
@if($job->isLive())
    <a class="btn" href="{{ $openApp }}">{{ $t['apply'] }}</a>
    <p class="muted" style="text-align:center;margin:10px 16px 0">{{ $t['apply_note'] }}</p>
@else
    <div class="notice" style="background:#F1F3F3;color:#48615E">{{ $t['closed'] }}</div>
@endif
<div class="foot"><span class="muted">{{ $t['no_phone'] }}</span><a href="{{ $openApp }}">{{ $t['report'] }}</a></div>
@endsection
