@extends('busmanagement::site._layout')

@php($title = $to !== '' ? $t('results_title', ['from' => $from, 'to' => $to]) : $t('results_any', ['from' => $from]))
@section('title', $title . ' · ' . $t->day($date))

@push('head')
<style>
.dep{display:flex;flex-direction:column;gap:10px;background:#fff;border:1px solid #E3E8E7;border-radius:14px;padding:14px;color:#1D2D2B;text-decoration:none}
.dep:hover{border-color:#B8C1C0}
.days{display:flex;gap:8px;overflow-x:auto;padding:12px 16px 4px;scrollbar-width:none}
.day{display:flex;flex-direction:column;align-items:center;min-width:64px;padding:8px 6px;border-radius:12px;border:1.5px solid #D5DCDB;background:#fff;color:#1D2D2B;text-decoration:none;font-size:12px}
.day.on{background:#1D2D2B;border-color:#1D2D2B;color:#fff}
.day .n{font-size:11px;opacity:.75}
.grid{display:grid;gap:16px}
.side{display:none}
@media (min-width:900px){.grid{grid-template-columns:320px minmax(0,1fr);padding:20px 16px 0}.side{display:block}.top-sum{padding-left:0!important}}
</style>
@endpush

@section('body')
<div class="grid">
    <aside class="side">
        <form class="card col" method="GET" action="{{ route('bus.web.search') }}" style="gap:12px;padding:18px">
            <label><span class="lbl">{{ $t('from') }}</span><input class="in" name="from" value="{{ $from }}"></label>
            <label><span class="lbl">{{ $t('to') }}</span><input class="in" name="to" value="{{ $to }}" list="bus-cities2" placeholder="{{ $t('anywhere') }}"></label>
            <datalist id="bus-cities2">@foreach($cities as $c)<option value="{{ $c }}">@endforeach</datalist>
            <label><span class="lbl">{{ $t('date') }}</span><input class="in" type="date" name="date" value="{{ $date->toDateString() }}" min="{{ today()->toDateString() }}"></label>
            <button class="btn" type="submit">{{ $t('find_buses') }}</button>
        </form>
    </aside>
    <div style="min-width:0">
        <div class="sec top-sum" style="background:#fff;border-bottom:1px solid #E8ECEB;padding:12px 16px">
            <div class="row" style="justify-content:space-between">
                <div class="col" style="gap:0">
                    <h1 style="font-size:19px">{{ $title }}</h1>
                    <span class="xs muted">{{ $t->day($date) }}</span>
                </div>
                <a class="chip" href="{{ route('bus.web.home', ['from' => $from]) }}">{{ $t('back') }}</a>
            </div>
        </div>
        <nav class="days" aria-label="{{ $t('date') }}">
            @for($i = 0; $i < 7; $i++)
                @php($day = today()->addDays($i))
                <a class="day {{ $day->isSameDay($date) ? 'on' : '' }}" href="{{ route('bus.web.search', array_filter(['from' => $from, 'to' => $to, 'date' => $day->toDateString(), 'class' => $class])) }}" @if($day->isSameDay($date)) aria-current="date" @endif>
                    <span class="b">{{ $i === 0 ? $t('today') : ($i === 1 ? $t('tomorrow') : $t->day($day)) }}</span>
                    <span class="n">{{ $days[$day->toDateString()] ?? 0 }}</span>
                </a>
            @endfor
        </nav>
        <div class="chips" style="padding:8px 16px 4px">
            @foreach([null => 'all', 'classic' => 'classic', 'vip' => 'vip', 'executive' => 'executive'] as $value => $label)
                <a class="chip {{ $class === ($value ?: null) ? 'on' : '' }}" href="{{ route('bus.web.search', array_filter(['from' => $from, 'to' => $to, 'date' => $date->toDateString(), 'class' => $value ?: null])) }}">{{ $t($label) }}</a>
            @endforeach
        </div>
        <div class="xs muted" style="padding:6px 16px 10px">{{ $t('n_departures', ['n' => count($departures)]) }}</div>
        <div class="col" style="gap:10px;padding:0 16px">
            @forelse($departures as $d)
                @include('busmanagement::site._departure', ['d' => $d, 'showDay' => $to === ''])
            @empty
                <div class="card muted">{{ $t('no_departures') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
