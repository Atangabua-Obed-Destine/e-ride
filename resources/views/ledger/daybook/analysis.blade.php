@extends('layouts.app')
@section('title', $title)

@php
    $M = fn ($v) => \App\Ledger\Ledger::money($v);
    $a = $analysis;
    $pacing = $a['pacing'];
    $months = $a['months'];
    $chip = ['good' => 'chip-good', 'warning' => 'chip-warning', 'critical' => 'chip-critical', 'none' => 'chip-none'];
    $icon = ['good' => 'fa-check-circle', 'warning' => 'fa-exclamation-triangle', 'critical' => 'fa-times-circle'];

    $worst = 'good';
    foreach ($checks as $c) {
        if ($c['state'] === 'critical') { $worst = 'critical'; break; }
        if ($c['state'] === 'warning') { $worst = 'warning'; }
    }

    $cash = $a['cash_position'];
    $closing = count($cash) ? end($cash)['balance'] : $a['opening_balance'];
    $lowest = count($cash) ? min(array_column($cash, 'balance')) : 0;
    $lowestLabel = '';
    foreach ($cash as $p) { if ($p['balance'] === $lowest) { $lowestLabel = $p['label']; break; } }
@endphp

@section('content')
@include('ledger.partials.style')
@include('ledger.daybook.partials.style')
@include('ledger.daybook.partials.analysis-style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_daybook.trends_analysis') }}</h1>
</section>
<section class="content viz">

    @include('ledger.daybook._toolbar', ['route' => 'ledger.daybook.analysis'])

    {{-- 0. Can these figures be presented? --}}
    <div class="conf {{ $worst === 'good' ? '' : 'has-'.$worst }}" id="db-confidence">
        <button class="conf-head" type="button" data-toggle="collapse" data-target="#dbConfidence">
            <i class="fas {{ $icon[$worst] }}" style="color: var(--{{ $worst }});"></i>
            <span>
                @if($allClear)
                    {{ __('ledger_daybook.ready_to_present') }}
                @else
                    {{ trans_choice('ledger_daybook.things_to_settle', $concerns, ['count' => $concerns]) }}
                @endif
            </span>
            <span class="count status-chip {{ $allClear ? 'chip-good' : ($worst === 'critical' ? 'chip-critical' : 'chip-warning') }}">
                {{ $allClear ? __('ledger_daybook.all_clear') : __('ledger_daybook.review') }}
            </span>
        </button>
        {{-- A clean book collapses to the single line above and opens on request. --}}
        <div class="collapse {{ $allClear ? '' : 'in' }}" id="dbConfidence">
            <div class="conf-list">
                @foreach($checks as $c)
                    <div class="conf-row" data-check="{{ $c['key'] }}" data-state="{{ $c['state'] }}">
                        <div class="ico {{ $c['state'] }}"><i class="fas {{ $icon[$c['state']] }}"></i></div>
                        <div class="name">{{ $c['label'] }}</div>
                        <div class="fig">{{ $c['figure'] }}</div>
                        <div class="says">
                            {{ $c['detail'] }}
                            @if(! empty($c['action']))<span class="todo">{{ $c['action'] }}</span>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="db-guide">
        <button class="db-guide-toggle" type="button" data-toggle="collapse" data-target="#dbAnalysisGuide">
            <span class="grow"><i class="fas fa-lightbulb"></i> {{ __('ledger_daybook.guide_page_for') }}</span>
            <i class="fas fa-chevron-up"></i>
        </button>
        <div class="collapse" id="dbAnalysisGuide">
            <div class="db-guide-body">
                <p>{{ __('ledger_daybook.guide_page_for_p') }}</p>
                <h6>{{ __('ledger_daybook.guide_questions') }}</h6>
                <ul style="margin-bottom: 0;">
                    <li><strong>{{ __('ledger_daybook.q_stand') }}</strong> {{ __('ledger_daybook.q_stand_a') }}</li>
                    <li><strong>{{ __('ledger_daybook.q_got_here') }}</strong> {{ __('ledger_daybook.q_got_here_a') }}</li>
                    <li><strong>{{ __('ledger_daybook.q_driving') }}</strong> {{ __('ledger_daybook.q_driving_a') }}</li>
                    <li><strong>{{ __('ledger_daybook.q_coming') }}</strong> {{ __('ledger_daybook.q_coming_a') }}</li>
                    <li><strong>{{ __('ledger_daybook.q_trust') }}</strong> {{ __('ledger_daybook.q_trust_a') }}</li>
                </ul>
                <p style="margin-top: 8px;">{{ __('ledger_daybook.transfers_left_out') }}</p>
            </div>
        </div>
    </div>

    @if(! $a['has_data'])
        <div class="alert alert-info">{{ __('ledger_daybook.nothing_recorded') }}</div>
    @else

    <div class="tiles">
        <div class="tile in">
            <div class="label">{{ __('ledger_daybook.money_in_ytd') }}</div>
            <div class="value" id="an-in">{{ $M($a['totals']['in']) }}</div>
            <div class="note">{{ __('ledger_daybook.money_in_ytd_note') }}</div>
        </div>
        <div class="tile out">
            <div class="label">{{ __('ledger_daybook.money_out_ytd') }}</div>
            <div class="value" id="an-out">{{ $M($a['totals']['out']) }}</div>
            <div class="note">{{ __('ledger_daybook.money_out_ytd_note') }}</div>
        </div>
        <div class="tile {{ $closing < 0 ? 'deficit' : '' }}">
            <div class="label">{{ __('ledger_daybook.closing_cash_position') }}</div>
            <div class="value" id="an-closing">{{ $M($closing) }}</div>
            <div class="note">
                {{ __('ledger_daybook.closing_cash_note') }}
                @if($lowest < 0)
                    <span class="status-chip chip-critical">
                        <i class="fas fa-exclamation-triangle"></i>
                        {{ __('ledger_daybook.lowest_in', ['amount' => $M($lowest), 'month' => $lowestLabel]) }}
                    </span>
                @endif
            </div>
        </div>
        <div class="tile">
            <div class="label">{{ __('ledger_daybook.spending_pace') }}</div>
            @if($pacing['status'] === 'none')
                {{-- Honest rather than a fabricated percentage: with no budget there is nothing to pace against. --}}
                <div class="value" style="font-size:1.15rem;">{{ __('ledger_daybook.no_budget_set') }}</div>
                <div class="note">
                    {{ __('ledger_daybook.pacing_none_note', ['pct' => number_format($pacing['elapsed'], 0)]) }}
                </div>
            @else
                <div class="value">{{ number_format($pacing['consumed'], 0) }}%</div>
                <div class="note">
                    {{ __('ledger_daybook.of_budget_spent') }} <strong>{{ number_format($pacing['elapsed'], 0) }}%</strong> {{ __('ledger_daybook.of_year_gone') }}
                    <span class="status-chip {{ $chip[$pacing['status']] }}">
                        {{ $pacing['gap'] > 0 ? number_format($pacing['gap'], 0).'% '.__('ledger_daybook.ahead_of_year') : number_format(abs($pacing['gap']), 0).'% '.__('ledger_daybook.behind_year') }}
                    </span>
                </div>
            @endif
        </div>
    </div>

    {{-- 1. Where do we stand: the cash curve --}}
    <div class="viz-card">
        <div class="viz-head">
            <h5>{{ __('ledger_daybook.cash_position_title') }}</h5>
            <p class="sub">{{ __('ledger_daybook.cash_position_sub', ['amount' => $M($a['opening_balance'])]) }}</p>
        </div>
        <div class="viz-body"><div class="viz-canvas" id="chartCash"></div></div>
        <div class="viz-read">
            <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.cash_read') }}</p></div>
            <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.cash_do') }}</p></div>
        </div>
        <details class="numbers">
            <summary>{{ __('ledger_daybook.show_numbers') }}</summary>
            <div class="viz-body" style="padding-top:0">
                <table>
                    <thead><tr><th>{{ __('ledger_daybook.month') }}</th><th class="n">{{ __('ledger_daybook.net') }}</th><th class="n">{{ __('ledger_daybook.balance_month_end') }}</th></tr></thead>
                    <tbody>
                    @foreach($cash as $p)
                        <tr>
                            <td>{{ $p['label'] }}</td>
                            <td class="n {{ $p['net'] >= 0 ? 'mv-up' : 'mv-down' }}">{{ $M($p['net']) }}</td>
                            <td class="n">{{ $M($p['balance']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    {{-- 2. How did we get here: in and out --}}
    <div class="viz-card">
        <div class="viz-head">
            <h5>{{ __('ledger_daybook.in_out_title') }}</h5>
            <p class="sub">{{ __('ledger_daybook.in_out_sub', ['year' => $year->name]) }}</p>
        </div>
        <div class="viz-body"><div class="viz-canvas" id="chartMonthly"></div></div>
        <div class="viz-read">
            <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.in_out_read') }}</p></div>
            <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.in_out_do') }}</p></div>
        </div>
        <details class="numbers">
            <summary>{{ __('ledger_daybook.show_numbers') }}</summary>
            <div class="viz-body" style="padding-top:0">
                <table id="an-months">
                    <thead><tr><th>{{ __('ledger_daybook.month') }}</th><th class="n">{{ __('ledger_daybook.in') }}</th><th class="n">{{ __('ledger_daybook.out') }}</th><th class="n">{{ __('ledger_daybook.net') }}</th></tr></thead>
                    <tbody>
                    @foreach($months as $mo)
                        <tr>
                            <td>{{ $mo['label'] }}</td>
                            <td class="n">{{ $M($mo['in']) }}</td>
                            <td class="n">{{ $M($mo['out']) }}</td>
                            <td class="n {{ $mo['net'] >= 0 ? 'mv-up' : 'mv-down' }}">{{ $M($mo['net']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    {{-- 3. Which months paid for themselves --}}
    <div class="viz-card">
        <div class="viz-head">
            <h5>{{ __('ledger_daybook.surplus_title') }}</h5>
            <p class="sub">{{ __('ledger_daybook.surplus_sub') }}</p>
        </div>
        <div class="key">
            <span><i style="background: var(--series-in)"></i>{{ __('ledger_daybook.surplus') }}</span>
            <span><i style="background: var(--series-out)"></i>{{ __('ledger_daybook.deficit') }}</span>
        </div>
        <div class="viz-body"><div class="viz-canvas" id="chartNet"></div></div>
        <div class="viz-read">
            <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.surplus_read') }}</p></div>
            <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.surplus_do') }}</p></div>
        </div>
    </div>

    {{-- 4. Pacing --}}
    <div class="viz-card">
        <div class="viz-head">
            <h5>{{ __('ledger_daybook.pacing_title') }}</h5>
            <p class="sub">{{ __('ledger_daybook.pacing_sub') }}</p>
        </div>
        <div class="viz-body"><div class="viz-canvas" id="chartPacing"></div></div>
        <div class="viz-read">
            <div>
                <h6>{{ __('ledger_daybook.how_to_read') }}</h6>
                <p>
                    {{ __('ledger_daybook.pacing_read') }}
                    @if($pacing['status'] !== 'none')
                        {{ __('ledger_daybook.pacing_read_budget') }}
                    @else
                        {{ __('ledger_daybook.pacing_read_none') }}
                    @endif
                </p>
            </div>
            <div>
                <h6>{{ __('ledger_daybook.what_to_do') }}</h6>
                <p>
                    @if($pacing['status'] === 'none')
                        {{ __('ledger_daybook.pacing_do_none') }}
                    @else
                        {{ __('ledger_daybook.pacing_do') }}
                        @if($pacing['projected'] > 0 && $pacing['budgeted'] > 0)
                            <br><strong>{{ __('ledger_daybook.at_current_rate', ['amount' => $M($pacing['projected'])]) }}</strong>
                            {{ __('ledger_daybook.budget_colon') }} {{ $M($pacing['budgeted']) }}.
                        @endif
                    @endif
                </p>
            </div>
        </div>
    </div>

    {{-- 5. Where income comes from --}}
    @if(count($a['top_income']))
        @php $largestIn = $a['top_income'][0]['amount'] ?: 1; @endphp
        <div class="viz-card" id="an-top-income">
            <div class="viz-head">
                <h5>{{ __('ledger_daybook.comes_from_title') }}</h5>
                <p class="sub">
                    {{ __('ledger_daybook.comes_from_sub', ['n' => $a['concentration']['income']['count']]) }}
                    <strong>{{ number_format($a['concentration']['income']['share'], 0) }}%</strong>
                    {{ __('ledger_daybook.of_everything_received') }}
                </p>
            </div>
            <div class="viz-body">
                <table class="rank-table">
                    @foreach($a['top_income'] as $row)
                        <tr data-code="{{ $row['code'] }}">
                            <td style="width:60px" class="text-muted">{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td style="width:32%"><div class="rank-track"><div class="rank-bar in" style="width: {{ max(2, ($row['amount'] / $largestIn) * 100) }}%"></div></div></td>
                            <td style="width:56px" class="rank-share">{{ number_format($row['share'], 0) }}%</td>
                            <td style="width:130px" class="rank-num">{{ $M($row['amount']) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
            <div class="viz-read">
                <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.comes_from_read') }}</p></div>
                <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.comes_from_do') }}</p></div>
            </div>
        </div>
    @endif

    {{-- 6. Where it goes --}}
    @if(count($a['top_expenditure']))
        @php $largest = $a['top_expenditure'][0]['amount'] ?: 1; @endphp
        <div class="viz-card" id="an-top-out">
            <div class="viz-head">
                <h5>{{ __('ledger_daybook.goes_title') }}</h5>
                <p class="sub">
                    {{ __('ledger_daybook.goes_sub', ['n' => $a['concentration']['expenditure']['count']]) }}
                    <strong>{{ number_format($a['concentration']['expenditure']['share'], 0) }}%</strong>
                    {{ __('ledger_daybook.of_everything_spent') }}
                </p>
            </div>
            <div class="viz-body">
                <table class="rank-table">
                    @foreach($a['top_expenditure'] as $row)
                        <tr data-code="{{ $row['code'] }}">
                            <td style="width:60px" class="text-muted">{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td style="width:32%"><div class="rank-track"><div class="rank-bar" style="width: {{ max(2, ($row['amount'] / $largest) * 100) }}%"></div></div></td>
                            <td style="width:56px" class="rank-share">{{ number_format($row['share'], 0) }}%</td>
                            <td style="width:130px" class="rank-num">{{ $M($row['amount']) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
            <div class="viz-read">
                <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.goes_read') }}</p></div>
                <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.goes_do') }}</p></div>
            </div>
        </div>
    @endif

    {{-- 7. What changed since last month --}}
    @if(! empty($a['movers']['rows']))
        <div class="viz-card">
            <div class="viz-head">
                <h5>{{ __('ledger_daybook.changed_title', ['from' => $a['movers']['from'], 'to' => $a['movers']['to']]) }}</h5>
                <p class="sub">{{ __('ledger_daybook.changed_sub') }}</p>
            </div>
            <div class="viz-body">
                <table class="rank-table">
                    <thead><tr>
                        <th>{{ __('ledger_daybook.line') }}</th>
                        <th class="rank-num">{{ $a['movers']['from'] }}</th>
                        <th class="rank-num">{{ $a['movers']['to'] }}</th>
                        <th class="rank-num">{{ __('ledger_daybook.change') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($a['movers']['rows'] as $row)
                        <tr>
                            <td>{{ $row['line'] }}</td>
                            <td class="rank-num">{{ $M($row['previous']) }}</td>
                            <td class="rank-num">{{ $M($row['current']) }}</td>
                            <td class="rank-num {{ $row['direction'] === 'up' ? 'mv-up' : 'mv-down' }}">
                                <i class="fas {{ $row['direction'] === 'up' ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i> {{ $M(abs($row['change'])) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="viz-read">
                <div><h6>{{ __('ledger_daybook.how_to_read') }}</h6><p>{{ __('ledger_daybook.changed_read') }}</p></div>
                <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.changed_do') }}</p></div>
            </div>
        </div>
    @endif

    {{-- 8. The biggest single movements --}}
    @if(count($a['largest_movements']))
        <div class="viz-card">
            <div class="viz-head">
                <h5>{{ __('ledger_daybook.largest_title') }}</h5>
                <p class="sub">{{ __('ledger_daybook.largest_sub') }}</p>
            </div>
            <div class="viz-body">
                <table class="rank-table">
                    <thead><tr>
                        <th style="width:92px">{{ __('ledger_daybook.date') }}</th>
                        <th style="width:110px">{{ __('ledger_daybook.ref') }}</th>
                        <th>{{ __('ledger_daybook.description') }}</th>
                        <th>{{ __('ledger_daybook.line') }}</th>
                        <th class="rank-num" style="width:130px">{{ __('ledger_daybook.amount') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($a['largest_movements'] as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td class="text-muted">{{ $row['ref'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="{{ $row['line'] ? '' : 'text-danger' }}">{{ $row['line'] ?: __('ledger_daybook.no_line') }}</td>
                            <td class="rank-num {{ $row['direction'] === 'in' ? 'mv-up' : 'mv-down' }}">{{ $M($row['amount']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 9. Money nobody has classified yet --}}
    @if($a['unallocated']['count'] > 0)
        <div class="viz-card" style="border-left: 4px solid var(--critical);" id="an-unallocated">
            <div class="viz-head">
                <h5>{{ __('ledger_daybook.unallocated_title') }}</h5>
                <p class="sub">
                    <strong>{{ $M($a['unallocated']['amount']) }}</strong>
                    {{ __('ledger_daybook.unallocated_sub', ['n' => $a['unallocated']['count'], 'pct' => number_format($a['unallocated']['share'], 1)]) }}
                </p>
            </div>
            <div class="viz-body">
                <table class="rank-table">
                    <thead><tr>
                        <th style="width:92px">{{ __('ledger_daybook.date') }}</th>
                        <th style="width:110px">{{ __('ledger_daybook.ref') }}</th>
                        <th>{{ __('ledger_daybook.description') }}</th>
                        <th class="rank-num" style="width:130px">{{ __('ledger_daybook.amount') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($a['unallocated']['rows'] as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td class="text-muted">{{ $row['ref'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td class="rank-num {{ $row['direction'] === 'in' ? 'mv-up' : 'mv-down' }}">{{ $M($row['amount']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="viz-read">
                <div><h6>{{ __('ledger_daybook.why_matters') }}</h6><p>{{ __('ledger_daybook.unallocated_why') }}</p></div>
                <div><h6>{{ __('ledger_daybook.what_to_do') }}</h6><p>{{ __('ledger_daybook.unallocated_do') }}</p></div>
            </div>
        </div>
    @endif

    @endif

    {{-- Who has paid: outside the has_data branch on purpose; it needs no cash to have moved. --}}
    @include('ledger.daybook.partials.collection')

    {{-- The pack: a POST, because the charts travel with it as images this page captures itself. --}}
    <form method="post" action="{{ route('ledger.daybook.analysis.pdf') }}" id="packForm" style="margin-bottom: 24px;">
        @csrf
        <input type="hidden" name="fiscal_year_id" value="{{ $year->id }}">
        <input type="hidden" name="period_id" value="{{ $period->id }}">
        @if($account)<input type="hidden" name="account_id" value="{{ $account->id }}">@endif
        <div id="packCharts"></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> {{ __('ledger_daybook.download_pack') }}</button>
        <p class="pack-note">{{ __('ledger_daybook.pack_note') }}</p>
    </form>
</section>
@endsection

@section('javascript')
@include('ledger.daybook.partials.analysis-script')
@endsection
