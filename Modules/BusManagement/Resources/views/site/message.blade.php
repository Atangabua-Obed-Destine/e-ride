@extends('busmanagement::site._layout')

@section('title', $title)

@section('body')
<div class="page">
    <div class="sec col" style="gap:12px;padding-top:40px">
        <h1 style="font-size:21px">{{ $title }}</h1>
        @if($body)<p class="muted" style="margin:0">{{ $body }}</p>@endif
        <a class="btn" href="{{ route('bus.web.home') }}" style="margin-top:8px">{{ $t('find_a_bus') }}</a>
        @if($me)<a class="btn ghost" href="{{ route('bus.web.trips') }}">{{ $t('my_trips') }}</a>
        @else<a class="btn ghost" href="{{ route('bus.web.login') }}">{{ $t('sign_in') }}</a>@endif
    </div>
</div>
@endsection
