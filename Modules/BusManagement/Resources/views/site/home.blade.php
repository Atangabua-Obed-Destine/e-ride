@extends('busmanagement::site._layout')

@section('title', $t('home_title'))

@push('head')
<style>
.home{display:grid;gap:0}
.hero{background:#fff;padding:20px 16px 16px}
.form{background:#fff;padding:0 16px 18px;display:flex;flex-direction:column;gap:12px}
.fields{border:1px solid #E3E8E7;border-radius:14px;overflow:hidden}
.field{display:flex;align-items:center;gap:12px;min-height:58px;padding:0 14px;border-bottom:1px solid #EEF1F0}
.field:last-child{border-bottom:0}
.field label{display:flex;flex-direction:column;flex:1;min-width:0}
.field .fl{font-size:11px;font-weight:600;color:#5F6B6A}
.field input,.field select{border:0;padding:2px 0;font-size:16px;font-weight:700;background:transparent;outline:none;width:100%;min-width:0}
.dates{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.dates input[type=radio]{position:absolute;opacity:0;width:1px;height:1px}
.dates label.chip{cursor:pointer}
.dates input[type=radio]:checked + label.chip{background:#1D2D2B;border-color:#1D2D2B;color:#fff}
.dates input[type=radio]:focus-visible + label.chip{outline:2px solid #E02020;outline-offset:2px}
.dates input[type=date]{height:36px;border:1.5px solid #D5DCDB;border-radius:18px;padding:0 10px;font-size:13px;font-weight:600;background:#fff}
.route{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border:1px solid #E3E8E7;border-radius:12px;background:#fff;color:#1D2D2B;text-decoration:none}
.list{display:flex;flex-direction:column;gap:8px}
@media (min-width:900px){
  .home{grid-template-columns:minmax(0,420px) minmax(0,1fr);gap:24px;padding:24px 16px 0;align-items:start}
  .hero,.form{border-radius:0}
  .left{background:#fff;border:1px solid #E3E8E7;border-radius:18px;overflow:hidden}
  .right{padding-top:4px}
}
</style>
@endpush

@section('body')
@if(!$busOn)
    <div class="page sec" style="padding-top:40px">
        <div class="card col" style="gap:8px;padding:20px">
            <h1>{{ $t('off_title') }}</h1>
            <p class="muted" style="margin:0">{{ $t('off_body') }}</p>
            <a class="btn dark" href="{{ route('bus.web.app') }}" style="margin-top:8px">{{ $t('get_app') }}</a>
        </div>
    </div>
@else
<div class="home">
    <div class="left">
        <div class="hero col" style="gap:6px">
            <h1>{{ $t('home_title') }}</h1>
            <p class="muted sm" style="margin:0">{{ $t('home_sub') }}</p>
        </div>
        <form class="form" method="GET" action="{{ route('bus.web.search') }}">
            <div class="fields">
                <div class="field">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#E02020" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="6"/></svg>
                    <label><span class="fl">{{ $t('from') }}</span>
                        <select name="from" aria-label="{{ $t('from') }}">
                            @forelse($origins as $o)<option value="{{ $o }}" @selected($o === $from)>{{ $o }}</option>@empty<option>Bamenda</option>@endforelse
                        </select>
                    </label>
                </div>
                <div class="field">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1D2D2B" stroke-width="2.2" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6-5.5-6-10a6 6 0 0112 0c0 4.5-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>
                    <label><span class="fl">{{ $t('to') }}</span>
                        <input name="to" list="bus-cities" placeholder="{{ $t('anywhere') }}" autocomplete="off" aria-label="{{ $t('to') }}">
                    </label>
                    <datalist id="bus-cities">@foreach(collect($routes)->flatten(1)->pluck('to')->unique()->sort() as $c)<option value="{{ $c }}">@endforeach</datalist>
                </div>
            </div>
            <fieldset class="dates" style="border:0;margin:0;padding:0">
                <legend class="sr">{{ $t('date') }}</legend>
                <input type="radio" onchange="var d=document.getElementById('dpick');d.name='';d.value=''" name="date" id="d0" value="{{ today()->toDateString() }}" checked><label class="chip" for="d0">{{ $t('today') }}</label>
                <input type="radio" onchange="var d=document.getElementById('dpick');d.name='';d.value=''" name="date" id="d1" value="{{ today()->addDay()->toDateString() }}"><label class="chip" for="d1">{{ $t('tomorrow') }}</label>
                <input type="radio" onchange="var d=document.getElementById('dpick');d.name='';d.value=''" name="date" id="d2" value="{{ today()->addDays(2)->toDateString() }}"><label class="chip" for="d2">{{ $t->day(today()->addDays(2)) }}</label>
                <label class="sr" for="dpick">{{ $t('date') }}</label>
                <input type="date" id="dpick" min="{{ today()->toDateString() }}" max="{{ today()->addDays(59)->toDateString() }}" onchange="document.querySelectorAll('.dates input[type=radio]').forEach(function(x){x.checked=false});this.name='date'">
            </fieldset>
            <button class="btn" type="submit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>{{ $t('find_buses') }}</button>
        </form>
    </div>

    <div class="right">
        @foreach($routes as $origin => $list)
            <div class="sec" style="padding-bottom:4px"><h2>{{ $t('popular_from', ['city' => $origin]) }}</h2></div>
            <div class="list" style="padding:4px 16px 8px">
                @foreach(array_slice($list, 0, 6) as $r)
                    <a class="route" href="{{ route('bus.web.search', ['from' => $origin, 'to' => $r['to']]) }}">
                        <span class="b sm">{{ $origin }} → {{ $r['to'] }}</span>
                        <span class="xs muted" style="text-align:right">{{ $t('departures_week', ['n' => $r['departures']]) }}@if($r['fare'])<br><b style="color:#E02020">{{ $t('from_fare', ['fare' => $t->money($r['fare'])]) }}</b>@endif</span>
                    </a>
                @endforeach
            </div>
        @endforeach

        @if($agencies)
            <div class="sec" style="padding-bottom:4px"><h2>{{ $t('agencies') }}</h2></div>
            <div class="list" style="padding:4px 16px 8px">
                @foreach($agencies as $a)
                    <a class="route" href="{{ route('bus.web.agency', ['slug' => $a['slug']]) }}">
                        <span class="col" style="gap:0"><span class="b sm">{{ $a['name'] }}</span><span class="verified">@include('busmanagement::site._tick'){{ $t('verified') }} · {{ $a['city'] }}</span></span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5F6B6A" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    </a>
                @endforeach
            </div>
        @endif

        <div style="padding:12px 16px 0">
            <div class="appband">
                <div class="col" style="gap:2px;flex:1"><span class="b sm">{{ $t('app_band_title') }}</span><span class="xs sub">{{ $t('app_band_sub') }}</span></div>
                <a class="pill light" href="{{ route('bus.web.app') }}">{{ $t('get_app') }}</a>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
