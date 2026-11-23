@extends('busmanagement::site._layout')

@section('title', $t('agency_page_title', ['agency' => $agency->name, 'city' => $agency->origin_city]))
@section('description', $t('agency_meta', ['agency' => $agency->name, 'city' => $agency->origin_city]))

@push('head')
<style>
.dep{display:flex;flex-direction:column;gap:10px;background:#fff;border:1px solid #E3E8E7;border-radius:14px;padding:14px;color:#1D2D2B;text-decoration:none}
.grid{display:grid;gap:0}
@media (min-width:900px){.grid{grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:20px;padding:20px 16px 0}.grid .sec{padding-left:0;padding-right:0}}
</style>
@endpush

@section('body')
@php($ownLook = \App\Lib\ShareLooks::page('bus', $agency->id))
@if($ownLook)
<style>
.own-band{background:{{ $ownLook['color'] }};color:{{ $ownLook['ink'] }}}
.own-band .muted,.own-band .open,.own-band .shut,.own-band .verified,.own-band .badge,.own-band a:not(.chip){color:inherit!important;opacity:.92}
.own-band .tag{background:#fff;color:#1D2D2B}
</style>
@endif
<div class="sec {{ $ownLook ? 'own-band' : '' }}" style="{{ $ownLook ? '' : 'background:#fff;' }}border-bottom:1px solid #E8ECEB">
    <div class="row" style="gap:14px;align-items:flex-start">
        @if($info['logo'])<img src="{{ $info['logo'] }}" alt="" width="56" height="56" style="border-radius:14px;object-fit:cover;flex-shrink:0">@endif
        <div class="col" style="gap:4px;min-width:0">
            <h1 style="font-size:22px">{{ $agency->name }}</h1>
            <span class="verified">@include('busmanagement::site._tick'){{ $t('verified') }} · {{ $agency->origin_city }}</span>
            @if($info['address'])<span class="sm muted">{{ $info['address'] }}</span>@endif
            @if($info['answer_minutes'])<span class="xs muted">{{ $t('answers_in', ['n' => $info['answer_minutes']]) }}</span>@endif
        </div>
    </div>
    <div class="row" style="gap:8px;margin-top:12px;flex-wrap:wrap">
        <a class="chip on" href="{{ route('bus.web.search', ['from' => $agency->origin_city]) }}">{{ $t('find_buses') }}</a>
        <button type="button" class="chip" id="share" data-url="{{ route('bus.web.agency', ['slug' => $agency->slug]) }}" data-done="{{ $t('copied') }}">{{ $t('share') }}</button>
    </div>
</div>

<div class="grid">
    <div>
        <div class="sec" style="padding-bottom:6px"><h2>{{ $t('next_departures') }}</h2></div>
        <div class="col" style="gap:10px;padding:0 16px">
            @forelse($departures as $d)
                @include('busmanagement::site._departure', ['d' => $d, 'showDay' => true])
            @empty
                <div class="card muted">{{ $t('no_departures') }}</div>
            @endforelse
        </div>
    </div>
    <div>
        <div class="sec" style="padding-bottom:6px"><h2>{{ $t('destinations') }}</h2></div>
        <div class="col" style="gap:8px;padding:0 16px">
            @foreach($destinations as $dest)
                @php($fares = $dest->dropPoints->flatMap(fn($p) => array_filter([$p->fareFor('classic'), $p->fareFor('vip'), $p->fareFor('executive')])))
                <a class="card row" style="justify-content:space-between;text-decoration:none;color:#1D2D2B" href="{{ route('bus.web.search', ['from' => $agency->origin_city, 'to' => $dest->city]) }}">
                    <span class="b sm">{{ $agency->origin_city }} → {{ $dest->city }}</span>
                    @if($fares->isNotEmpty())<span class="sm b" style="color:#E02020">{{ $t('from_fare', ['fare' => $t->money($fares->min())]) }}</span>@endif
                </a>
            @endforeach
        </div>
        @php($r = $info['rules'])
        <div class="sec" style="padding-bottom:6px"><h2>{{ $t('rules') }}</h2></div>
        <div class="card col sm" style="gap:8px;margin:0 16px">
            <span>{{ $t('rule_bags', ['n' => $r['free_bags'], 'kg' => $r['bag_kg'], 'fee' => $t->money($r['extra_bag_fee'])]) }}</span>
            @foreach($r['cancel_tiers'] as [$h, $pct])
                <span>{{ $h > 0 ? $t('rule_cancel', ['h' => $h, 'pct' => $pct]) : $t('rule_cancel_last', ['pct' => $pct]) }}</span>
            @endforeach
            @if($r['id_check'] !== 'number')<span>{{ $t('rule_id') }}</span>@endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('share').addEventListener('click', function () {
    var b = this, url = b.dataset.url;
    if (navigator.share) { navigator.share({url: url}).catch(function () {}); return; }
    if (navigator.clipboard) { navigator.clipboard.writeText(url).then(function () { b.textContent = b.dataset.done; }); }
});
</script>
@endpush
