{{--
    Business overview (Modules\AdminModule\Lib\BusinessPulse): what is happening now, what needs
    someone, and every service today and over 7 days against the 7 days before. Refreshed every
    minute by the dashboard (route admin.business-pulse).
--}}
@php
    $live = $pulse['live'] ?? [];
    $at = isset($pulse['at']) ? \Illuminate\Support\Carbon::parse($pulse['at']) : now();
    $fmt = fn($v, $money) => $v === null ? '–' : ($money ? getCurrencyFormat($v) : number_format($v, 0));
    $levels = [1 => translate('Now'), 2 => translate('Today'), 3 => translate('This week')];
@endphp
<div class="ed-pulse" data-at="{{ $at->toIso8601String() }}">
    <div class="ed-pulse-top">
        <div>
            <h2 class="ed-pulse-title">{{ translate('How the business is moving') }}</h2>
            <div class="ed-pulse-sub">{{ translate(key: 'Counted at {time}, updates every minute', replace: ['time' => $at->format('H:i')]) }}</div>
        </div>
        <div class="ed-live">
            @isset($live['drivers_online'])
                <a href="{{ route('admin.fleet-map', 'all-driver') }}" class="ed-live-chip"><i class="bi bi-broadcast"></i><b>{{ $live['drivers_online'] }}</b> {{ translate('drivers online') }}</a>
            @endisset
            @isset($live['trips_ongoing'])
                <a href="{{ route('admin.trip.index', ['ongoing']) }}" class="ed-live-chip"><i class="bi bi-car-front"></i><b>{{ $live['trips_ongoing'] }}</b> {{ translate('trips on the road') }}</a>
            @endisset
            @if(($live['trips_waiting'] ?? 0) > 0)
                <a href="{{ route('admin.trip.index', ['pending']) }}" class="ed-live-chip is-warn"><i class="bi bi-hourglass-split"></i><b>{{ $live['trips_waiting'] }}</b> {{ translate('waiting for a driver') }}</a>
            @endif
            @isset($live['food_active'])
                @if(\Modules\AdminModule\Lib\AdminNav::allowed('store'))
                    <a href="{{ route('admin.store-order.index') }}" class="ed-live-chip"><i class="bi bi-bag"></i><b>{{ $live['food_active'] }}</b> {{ translate('food orders open') }}</a>
                @endif
            @endisset
        </div>
    </div>

    <div class="ed-pulse-grid">
        <section class="ed-attn" id="attention" aria-labelledby="ed-attn-title">
            <div class="ed-attn-head">
                <h3 id="ed-attn-title"><i class="bi bi-bell"></i>{{ translate('Needs attention') }}</h3>
                <span class="ed-attn-total {{ count($attention) ? 'has' : '' }}">{{ array_sum(array_column($attention, 'count')) }}</span>
            </div>
            @if(!count($attention))
                <div class="ed-attn-clear"><i class="bi bi-check2-circle"></i>
                    <div><b>{{ translate('All clear') }}</b><span>{{ translate('Nothing is waiting for you right now.') }}</span></div></div>
            @else
                @foreach(collect($attention)->groupBy('level') as $level => $group)
                    <div class="ed-attn-level">{{ $levels[$level] ?? '' }}</div>
                    @foreach($group as $a)
                        <a class="ed-attn-item lvl-{{ $a['level'] }}" href="{{ $a['url'] }}">
                            <i class="bi {{ $a['icon'] }}"></i>
                            <div class="ed-attn-text"><b>{{ translate($a['title']) }}</b><span>{{ translate($a['hint']) }}</span></div>
                            <span class="ed-attn-count">{{ $a['count'] }}</span>
                            <i class="bi bi-chevron-right ed-attn-go"></i>
                        </a>
                    @endforeach
                @endforeach
            @endif
        </section>

        <div class="ed-cards">
            @foreach($pulse['cards'] ?? [] as $card)
                <article class="ed-card">
                    <header>
                        <span class="ed-card-icon"><i class="bi {{ $card['icon'] }}"></i></span>
                        <h3>{{ translate($card['title']) }}</h3>
                        @if($card['url'])<a href="{{ $card['url'] }}" class="ed-card-open" title="{{ translate('Open') }}"><i class="bi bi-arrow-up-right"></i></a>@endif
                    </header>
                    <table>
                        <thead><tr><th></th><th>{{ translate('Today') }}</th><th>{{ translate('7 days') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($card['metrics'] as $mt)
                            @php($trend = \Modules\AdminModule\Lib\BusinessPulse::trend($mt['week'], $mt['prev']))
                            <tr>
                                <td class="ed-m-label">{{ translate($mt['label']) }}</td>
                                <td class="ed-m-today">{{ $fmt($mt['today'], $mt['money']) }}</td>
                                <td class="ed-m-week">{{ $fmt($mt['week'], $mt['money']) }}</td>
                                <td class="ed-m-trend">
                                    @if($trend !== null)
                                        <span class="{{ $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat') }}" title="{{ translate('Against the 7 days before') }}: {{ $fmt($mt['prev'], $mt['money']) }}">
                                            <i class="bi {{ $trend > 0 ? 'bi-arrow-up-short' : ($trend < 0 ? 'bi-arrow-down-short' : 'bi-dash') }}"></i>{{ abs($trend) }}%
                                        </span>
                                    @elseif(($mt['week'] ?? 0) > 0)
                                        <span class="new">{{ translate('new') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </article>
            @endforeach
        </div>
    </div>
</div>
