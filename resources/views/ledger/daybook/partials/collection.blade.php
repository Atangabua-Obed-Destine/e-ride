{{--
    Who has paid, and who has not (paxhitestbed admin/daybook/partials/collection, for a shop:
    the year's final sales instead of the students' fees; App\Ledger\Services\CustomerCollection).
--}}
@php
    $c = $collection;
    $st = $c['sales'];
    $M = fn ($v) => \App\Ledger\Ledger::money($v);
    $pct = fn ($n) => $st['total'] > 0 ? round($n / $st['total'] * 100, 2) : 0;
    $owing = $c['outstanding'];
@endphp

<div class="viz-card" id="an-collection">
    <div class="viz-head">
        <h5>{{ __('ledger_daybook.fc_title') }}</h5>
        <p class="sub">{{ __('ledger_daybook.fc_sub', ['year' => $year->name]) }}</p>
    </div>

    @if($st['total'] === 0)
        <div class="viz-body">
            <p class="text-muted" style="margin:0;">{{ __('ledger_daybook.fc_none_yet') }}</p>
        </div>
    @else
        <div class="fc-tiles">
            <div class="fc-split">
                <div class="fc-stat fc-full">
                    <span class="label">{{ __('ledger_daybook.fc_paid_full') }}</span>
                    <div class="value" id="fc-full">{{ number_format($st['full']) }}</div>
                    <div class="of">{{ __('ledger_daybook.fc_of_sales', ['total' => number_format($st['total'])]) }}</div>
                </div>
                <div class="fc-stat fc-part">
                    <span class="label">{{ __('ledger_daybook.fc_paid_part') }}</span>
                    <div class="value" id="fc-part">{{ number_format($st['part']) }}</div>
                    <div class="of">{{ __('ledger_daybook.fc_some_money') }}</div>
                </div>
                <div class="fc-stat fc-none">
                    <span class="label">{{ __('ledger_daybook.fc_paid_nothing_at_all') }}</span>
                    <div class="value" id="fc-none">{{ number_format($st['none']) }}</div>
                    <div class="of">{{ __('ledger_daybook.fc_not_one_franc') }}</div>
                </div>
            </div>

            <div class="fc-bar" role="img" aria-label="{{ __('ledger_daybook.fc_bar_label', ['full' => $st['full'], 'part' => $st['part'], 'none' => $st['none'], 'total' => $st['total']]) }}">
                @if($st['full'] > 0)<span class="s-full" style="width: {{ $pct($st['full']) }}%"></span>@endif
                @if($st['part'] > 0)<span class="s-part" style="width: {{ $pct($st['part']) }}%"></span>@endif
                @if($st['none'] > 0)<span class="s-none" style="width: {{ $pct($st['none']) }}%"></span>@endif
            </div>
            <div class="key" style="padding: .5rem 0 0;">
                <span><i style="background: var(--paid-full)"></i>{{ __('ledger_daybook.fc_paid_full') }}</span>
                <span><i style="background: var(--paid-part)"></i>{{ __('ledger_daybook.fc_paid_part') }}</span>
                <span><i style="background: var(--paid-none)"></i>{{ __('ledger_daybook.fc_paid_nothing') }}</span>
            </div>
        </div>

        <div class="tiles" style="padding: 0 1.25rem;">
            <div class="tile">
                <div class="label">{{ __('ledger_daybook.fc_billed_year') }}</div>
                <div class="value" id="fc-billed">{{ $M($c['billed']) }}</div>
                <div class="note">{{ __('ledger_daybook.fc_billed_note', ['from' => \Carbon\Carbon::parse($c['from'])->format('d/m/Y'), 'to' => \Carbon\Carbon::parse($c['to'])->format('d/m/Y')]) }}</div>
            </div>
            <div class="tile in">
                <div class="label">{{ __('ledger_daybook.fc_collected') }}</div>
                <div class="value" id="fc-collected">{{ $M($c['collected']) }}</div>
                <div class="note">{{ __('ledger_daybook.fc_collected_note') }}</div>
            </div>
            <div class="tile {{ $owing > 0 ? 'deficit' : '' }}">
                <div class="label">{{ __('ledger_daybook.fc_still_outstanding') }}</div>
                <div class="value" id="fc-outstanding">{{ $M($owing) }}</div>
                <div class="note">{{ __('ledger_daybook.fc_outstanding_note') }}</div>
            </div>
            <div class="tile">
                <div class="label">{{ __('ledger_daybook.fc_rate') }}</div>
                <div class="value">{{ number_format($c['rate'], 0) }}%</div>
                <div class="note">{{ __('ledger_daybook.fc_rate_note') }}</div>
            </div>
        </div>

        <div class="viz-read">
            <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.fc_read') }}</p></div>
            <div>
                <h6>{{ __('ledger_daybook.what_to_do') }}</h6>
                <p>
                    @if($st['none'] > 0)
                        {{ __('ledger_daybook.fc_do_none', ['n' => number_format($st['none'])]) }}
                    @elseif($st['part'] > 0)
                        {{ __('ledger_daybook.fc_do_part') }}
                    @else
                        {{ __('ledger_daybook.fc_do_all') }}
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>

@if(count($c['by_month']))
<div class="viz-card">
    <div class="viz-head">
        <h5>{{ __('ledger_daybook.fc_month_title') }}</h5>
        <p class="sub">{{ __('ledger_daybook.fc_month_sub') }}</p>
    </div>
    <div class="key">
        <span><i style="background: var(--series-in)"></i>{{ __('ledger_daybook.fc_received_month') }}</span>
        <span><i style="background: var(--text-secondary)"></i>{{ __('ledger_daybook.fc_running_total') }}</span>
    </div>
    <div class="viz-body"><div class="viz-canvas" id="chartCollection"></div></div>
    <details class="numbers">
        <summary>{{ __('ledger_daybook.fc_numbers_behind') }}</summary>
        <div class="viz-body" style="padding-top:0">
            <table>
                <thead><tr>
                    <th>{{ __('ledger_daybook.month') }}</th>
                    <th class="n">{{ __('ledger_daybook.fc_received') }}</th>
                    <th class="n">{{ __('ledger_daybook.fc_running_total') }}</th>
                    <th class="n">{{ __('ledger_daybook.fc_all_money_in') }}</th>
                </tr></thead>
                <tbody>
                @foreach($c['by_month'] as $mo)
                    <tr>
                        <td>{{ $mo['label'] }}</td>
                        <td class="n">{{ $M($mo['cash']) }}</td>
                        <td class="n">{{ $M($mo['cumulative']) }}</td>
                        <td class="n">{{ $M($mo['all_in']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
    <div class="viz-read">
        <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.fc_month_read') }}</p></div>
        <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.fc_month_do') }}</p></div>
    </div>
</div>
@endif

@if(count($c['top_debtors']))
<div class="viz-card" id="an-debtors">
    <div class="viz-head">
        <h5>{{ __('ledger_daybook.fc_debtors_title') }}</h5>
        <p class="sub">{{ __('ledger_daybook.fc_debtors_sub') }}</p>
    </div>
    <div class="viz-body">
        <table class="rank-table">
            <thead><tr>
                <th>{{ __('ledger_daybook.fc_customer') }}</th>
                <th class="rank-num">{{ __('ledger_daybook.fc_sales') }}</th>
                <th class="rank-num">{{ __('ledger_daybook.fc_billed') }}</th>
                <th class="rank-num">{{ __('ledger_daybook.fc_paid') }}</th>
                <th class="rank-num">{{ __('ledger_daybook.fc_outstanding') }}</th>
                <th class="rank-num">{{ __('ledger_daybook.fc_unpaid_share') }}</th>
            </tr></thead>
            <tbody>
            @foreach($c['top_debtors'] as $row)
                <tr>
                    <td>{{ $row['name'] }} @if($row['walk_in'])<span class="fc-tag">{{ __('ledger_daybook.walk_in') }}</span>@endif</td>
                    <td class="rank-num">{{ number_format($row['sales']) }}</td>
                    <td class="rank-num">{{ $M($row['billed']) }}</td>
                    <td class="rank-num">{{ $M($row['paid']) }}</td>
                    <td class="rank-num mv-down">{{ $M($row['outstanding']) }}</td>
                    <td class="rank-num">{{ number_format($row['share'], 0) }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="viz-read">
        <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.fc_debtors_read') }}</p></div>
        <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.fc_debtors_do') }}</p></div>
    </div>
</div>
@endif
