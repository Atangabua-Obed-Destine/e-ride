@php
    $at = \Illuminate\Support\Carbon::parse($d['leaves_at']);
    $full = $d['seats_left'] === 0 && $d['closed_reason'] === null;
    $hours = $d['duration_minutes'] ? round($d['duration_minutes'] / 60, 1) : null;
@endphp
<a class="dep" href="{{ route('bus.web.departure', ['id' => $d['id']]) }}">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div class="col" style="gap:2px;min-width:0">
            <span class="b" style="font-size:15px">{{ $d['agency']['name'] ?? '' }}</span>
            <span class="verified">@include('busmanagement::site._tick'){{ $t('verified') }}</span>
        </div>
        <div class="col" style="gap:0;align-items:flex-end;text-align:right">
            @if($d['from_fare'])<span class="b" style="font-size:16px;color:{{ $d['bookable'] ? '#E02020' : '#5F6B6A' }}">{{ $t->money($d['from_fare']) }}</span>@endif
            <span class="xs muted">{{ $t->classLabel($d['class']) }}</span>
        </div>
    </div>
    <div class="row" style="gap:12px;align-items:baseline;flex-wrap:wrap">
        <span class="b" style="font-size:22px">{{ $at->format('H:i') }}</span>
        @if($showDay ?? false)<span class="sm b">{{ $t->day($at) }}</span>@endif
        <span class="xs muted">{{ $d['to'] }} · {{ $d['kind'] === 'when_full' ? $t('leaves_when_full') : $t('fixed_time') }}@if($hours) · {{ $t('about_hours', ['h' => $hours]) }}@endif</span>
    </div>
    <div class="row" style="gap:6px;flex-wrap:wrap">
        @if($d['closed_reason'])
            <span class="tag t-grey">{{ $t('closed') }}</span>
        @elseif($full)
            <span class="tag t-grey">{{ $t('full') }}</span><span class="tag t-dark">{{ $t('waitlist_in_app') }}</span>
        @else
            <span class="tag {{ $d['seats_left'] < 6 ? 't-amber' : 't-green' }}">{{ $t('seats_left', ['n' => $d['seats_left']]) }}</span>
        @endif
        @if($d['delayed'])<span class="tag t-amber">{{ $t('delayed') }}</span>@endif
        @if($d['instant'] && $d['bookable'])<span class="tag t-green">{{ $t('instant') }}</span>@endif
        @if(!empty($d['id_check']))<span class="tag t-ind">{{ $t('id_checked') }}</span>@endif
    </div>
</a>
