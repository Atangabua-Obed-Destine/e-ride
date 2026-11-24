@extends('busmanagement::site._layout')

@section('title', $t('my_trips'))

@push('head')
<style>
.trip{display:flex;align-items:center;gap:12px;padding:12px 14px;background:#fff;border:1px solid #E3E8E7;border-radius:12px;color:#1D2D2B;text-decoration:none}
.lock{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid #EEF1F0;color:#1D2D2B;text-decoration:none}
.lock:last-child{border-bottom:0}
.app{display:inline-flex;align-items:center;height:26px;padding:0 10px;border-radius:13px;background:#1D2D2B;color:#fff;font-size:12px;font-weight:700;flex-shrink:0}
</style>
@endpush

@php
    $label = function ($b) use ($t) {
        $past = $b->departure && ($b->departure->leavesAt()->isPast() || in_array($b->departure->status, ['departed'], true));
        return match (true) {
            $b->status === 'paid' && $past => [$t('status_travelled'), 't-grey'],
            $b->status === 'paid' => [$t('status_paid'), 't-green'],
            $b->status === 'requested' => [$t('status_requested'), 't-amber'],
            $b->status === 'accepted' => [$t('status_accepted'), 't-amber'],
            $b->status === 'declined' => [$t('status_declined'), 't-grey'],
            $b->status === 'expired' => [$t('status_expired'), 't-grey'],
            default => [$t('status_cancelled'), 't-grey'],
        };
    };
@endphp

@section('body')
<div class="page">
    <div class="sec col" style="gap:2px;padding-top:20px">
        <h1>{{ $t('my_trips') }}</h1>
        <span class="sm muted">{{ $me->full_name }} · {{ $me->phone }}</span>
    </div>
    @foreach([['upcoming', $upcoming], ['past', $past]] as [$key, $list])
        @if($key === 'past' && $list->isEmpty()) @continue @endif
        <div class="sec" style="padding-bottom:6px"><h2>{{ $t($key) }}</h2></div>
        <div class="col" style="gap:8px;padding:0 16px">
            @forelse($list as $b)
                @php([$text, $cls] = $label($b))
                <a class="trip" href="{{ route('bus.web.booking', ['code' => $b->code]) }}">
                    <div class="col" style="gap:2px;flex:1;min-width:0">
                        <span class="sm b">{{ $b->agency?->origin_city }} → {{ $b->departure?->destination?->city }} · {{ $b->departure ? $t->when($b->departure->leavesAt()) : '' }}</span>
                        <span class="xs muted">{{ $b->agency?->displayName() }} · {{ $b->tickets->pluck('seat')->sortBy(fn($s) => (int)$s)->implode(', ') }} · {{ $b->code }}</span>
                    </div>
                    <span class="tag {{ $cls }}">{{ $text }}</span>
                </a>
            @empty
                <div class="card col" style="gap:10px"><span class="muted">{{ $t('no_trips') }}</span><a class="btn" href="{{ route('bus.web.home') }}">{{ $t('find_a_bus') }}</a></div>
            @endforelse
        </div>
    @endforeach

    <div class="card" style="margin:18px 16px 0;padding:6px 14px">
        <div class="row" style="padding:10px 0 4px;justify-content:space-between"><span class="sm b">{{ $t('in_app') }}</span></div>
        @foreach([['bv', 'app_verify'], ['bh', 'app_cancel'], ['bh', 'app_waitlist'], ['bh', 'app_profile'], ['bh', 'app_more']] as [$to, $key])
            <a class="lock" href="{{ \Modules\BusManagement\Lib\BusWebLinks::openInApp($to) }}" data-fallback="{{ route('bus.web.app', ['to' => $to, 'back' => route('bus.web.trips')]) }}"><span class="sm" style="flex:1">{{ $t($key) }}</span><span class="app">{{ $t('open_app') }}</span></a>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
@include('busmanagement::site._open_app_js')
@endpush
