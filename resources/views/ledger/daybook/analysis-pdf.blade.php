@php
    $M = fn ($v) => \App\Ledger\Ledger::money($v);
    $a = $analysis;
    $cash = $a['cash_position'];
    $closing = count($cash) ? end($cash)['balance'] : $a['opening_balance'];
    $lowest = count($cash) ? min(array_column($cash, 'balance')) : 0;
    // Charts arrive in the order the page registered them: cash, in/out, net, pacing, sales receipts.
    // POSITIONS, not names: a contract with partials/analysis-script. Missing ones are simply absent.
    $chart = fn ($i) => $charts[$i] ?? null;
    $cl = $collection;
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('ledger_daybook.financial_analysis') }} — {{ $year->name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 8.5pt; color: #000; }
        .head { text-align: center; border-bottom: 1.5px solid #333; padding-bottom: 6px; margin-bottom: 8px; }
        .biz { font-size: 13pt; font-weight: bold; }
        h1 { font-size: 12pt; margin: 4px 0 2px; text-transform: uppercase; letter-spacing: .04em; }
        h2 { font-size: 10pt; margin: 14px 0 4px; color: #182b49; border-bottom: .8px solid #c3c2b7; padding-bottom: 3px; }
        .period { font-size: 8.5pt; color: #444; margin: 0 0 10px; }
        .sub { font-size: 8pt; color: #444; margin: 0 0 6px; line-height: 1.5; }
        table.g { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.g th, table.g td { border: .5px solid #999; padding: 2.5px 4px; vertical-align: top; }
        table.g th { background: #e8ebef; font-size: 7.5pt; text-transform: uppercase; }
        .num { text-align: right; white-space: nowrap; }
        .total td { border-top: 1.2px solid #333; font-weight: bold; background: #f2f4f7; }
        table.tiles { width: 100%; border-collapse: separate; border-spacing: 4px; margin-bottom: 6px; }
        table.tiles td { border: .5px solid #c9d2de; background: #fbfcfd; padding: 6px 7px; width: 25%; vertical-align: top; }
        table.tiles .lbl { font-size: 6.5pt; text-transform: uppercase; color: #70809a; }
        table.tiles .val { font-size: 12pt; font-weight: bold; }
        table.tiles .note { font-size: 7pt; color: #555; }
        .chart { width: 100%; margin: 4px 0 8px; }
        table.conf { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.conf td { border: .5px solid #999; padding: 3px 5px; font-size: 8pt; vertical-align: top; }
        .st { font-weight: bold; white-space: nowrap; }
        .st-good { color: #056b05; } .st-warning { color: #7a5312; } .st-critical { color: #8a1f1f; }
        .up { color: #1c5fa8; font-weight: bold; } .down { color: #b4491f; font-weight: bold; } .none { color: #8a1f1f; font-weight: bold; }
        .foot { margin-top: 12px; font-size: 7pt; color: #444; }
        .sign { margin-top: 20px; width: 100%; }
        .sign td { padding-top: 16px; border-top: .5px solid #333; font-size: 8pt; width: 33%; }
    </style>
</head>
<body>

<div class="head">
    <div class="biz">{{ $businessName ?? '' }}</div>
    <h1>{{ __('ledger_daybook.financial_analysis') }}</h1>
</div>
<p class="period">
    <strong>{{ __('ledger_daybook.fiscal_year') }} {{ $year->name }}</strong>
    &nbsp;·&nbsp; {{ $d($year->start_date) }} {{ __('ledger_daybook.to') }} {{ $d($year->end_date) }}
    &nbsp;·&nbsp; {{ $account ? $account->label : __('ledger_daybook.all_cash_boxes') }}
    &nbsp;·&nbsp; {{ __('ledger_daybook.prepared') }} {{ $generatedAt->format('d/m/Y H:i') }}
</p>

<h2>{{ __('ledger_daybook.before_presented') }}</h2>
<table class="conf">
    @foreach($checks as $c)
        <tr>
            <td class="st st-{{ $c['state'] }}" style="width:12%">
                {{ $c['state'] === 'good' ? __('ledger_daybook.st_ok') : ($c['state'] === 'warning' ? __('ledger_daybook.st_check') : __('ledger_daybook.st_action')) }}
            </td>
            <td style="width:24%"><strong>{{ $c['label'] }}</strong></td>
            <td class="num" style="width:14%">{{ $c['figure'] }}</td>
            <td>{{ $c['detail'] }} @if(! empty($c['action'])) <em>{{ $c['action'] }}</em> @endif</td>
        </tr>
    @endforeach
</table>

@if($a['has_data'])

<h2>{{ __('ledger_daybook.year_so_far') }}</h2>
<table class="tiles">
    <tr>
        <td>
            <div class="lbl">{{ __('ledger_daybook.money_in') }}</div>
            <div class="val">{{ $M($a['totals']['in']) }}</div>
            <div class="note">{{ __('ledger_daybook.received') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('ledger_daybook.money_out') }}</div>
            <div class="val">{{ $M($a['totals']['out']) }}</div>
            <div class="note">{{ __('ledger_daybook.spent') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('ledger_daybook.closing_cash_position') }}</div>
            <div class="val">{{ $M($closing) }}</div>
            <div class="note">{{ $lowest < 0 ? __('ledger_daybook.lowest_point', ['amount' => $M($lowest)]) : __('ledger_daybook.never_below_zero') }}</div>
        </td>
        <td>
            <div class="lbl">{{ __('ledger_daybook.spending_pace') }}</div>
            <div class="val">{{ $a['pacing']['status'] === 'none' ? '—' : number_format($a['pacing']['consumed'], 0).'%' }}</div>
            <div class="note">{{ $a['pacing']['status'] === 'none' ? __('ledger_daybook.no_budget_set') : __('ledger_daybook.pct_year_gone', ['pct' => number_format($a['pacing']['elapsed'], 0)]) }}</div>
        </td>
    </tr>
</table>

<h2>{{ __('ledger_daybook.cash_position_title') }}</h2>
@if($chart(0))<div class="chart"><img src="{{ $chart(0) }}" style="width: 100%;"></div>@endif
<table class="g">
    <thead><tr>
        <th>{{ __('ledger_daybook.month') }}</th><th class="num">{{ __('ledger_daybook.in') }}</th>
        <th class="num">{{ __('ledger_daybook.out') }}</th><th class="num">{{ __('ledger_daybook.net') }}</th>
        <th class="num">{{ __('ledger_daybook.balance_month_end') }}</th>
    </tr></thead>
    <tbody>
    @foreach($cash as $i => $p)
        <tr>
            <td>{{ isset($a['months'][$i]['period']) ? \App\Ledger\Models\FiscalYear::periodLabel($a['months'][$i]['period']) : $p['label'] }}</td>
            <td class="num">{{ $M($a['months'][$i]['in'] ?? 0) }}</td>
            <td class="num">{{ $M($a['months'][$i]['out'] ?? 0) }}</td>
            <td class="num {{ $p['net'] >= 0 ? 'up' : 'down' }}">{{ $M($p['net']) }}</td>
            <td class="num">{{ $M($p['balance']) }}</td>
        </tr>
    @endforeach
    <tr class="total">
        <td>{{ __('ledger_daybook.total') }}</td>
        <td class="num">{{ $M($a['totals']['in']) }}</td>
        <td class="num">{{ $M($a['totals']['out']) }}</td>
        <td class="num">{{ $M($a['totals']['net']) }}</td>
        <td class="num">{{ $M($closing) }}</td>
    </tr>
    </tbody>
</table>

<pagebreak />

<h2>{{ __('ledger_daybook.money_in_and_out') }}</h2>
@if($chart(1))<div class="chart"><img src="{{ $chart(1) }}" style="width: 100%;"></div>@endif

<h2>{{ __('ledger_daybook.surplus_title') }}</h2>
@if($chart(2))<div class="chart"><img src="{{ $chart(2) }}" style="width: 100%;"></div>@endif

<h2>{{ __('ledger_daybook.pacing_title') }}</h2>
@if($chart(3))<div class="chart"><img src="{{ $chart(3) }}" style="width: 100%;"></div>@endif
<p class="sub">{{ $a['pacing']['status'] === 'none' ? __('ledger_daybook.pacing_do_none') : __('ledger_daybook.pacing_do') }}</p>

@if(count($a['top_income']))
    <h2>{{ __('ledger_daybook.comes_from_title') }}</h2>
    <p class="sub">{{ __('ledger_daybook.pdf_top_income', ['n' => $a['concentration']['income']['count'], 'pct' => number_format($a['concentration']['income']['share'], 0)]) }}</p>
    <table class="g">
        <thead><tr><th style="width:60px">{{ __('ledger_daybook.code') }}</th><th>{{ __('ledger_daybook.line') }}</th><th class="num" style="width:60px">{{ __('ledger_daybook.share') }}</th><th class="num" style="width:110px">{{ __('ledger_daybook.amount') }}</th></tr></thead>
        <tbody>
        @foreach($a['top_income'] as $row)
            <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ number_format($row['share'], 0) }}%</td><td class="num">{{ $M($row['amount']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(count($a['top_expenditure']))
    <h2>{{ __('ledger_daybook.goes_title') }}</h2>
    <p class="sub">{{ __('ledger_daybook.pdf_top_out', ['n' => $a['concentration']['expenditure']['count'], 'pct' => number_format($a['concentration']['expenditure']['share'], 0)]) }}</p>
    <table class="g">
        <thead><tr><th style="width:60px">{{ __('ledger_daybook.code') }}</th><th>{{ __('ledger_daybook.line') }}</th><th class="num" style="width:60px">{{ __('ledger_daybook.share') }}</th><th class="num" style="width:110px">{{ __('ledger_daybook.amount') }}</th></tr></thead>
        <tbody>
        @foreach($a['top_expenditure'] as $row)
            <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ number_format($row['share'], 0) }}%</td><td class="num">{{ $M($row['amount']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(! empty($a['movers']['rows']))
    <h2>{{ __('ledger_daybook.changed_title', ['from' => $a['movers']['from'], 'to' => $a['movers']['to']]) }}</h2>
    <table class="g">
        <thead><tr><th>{{ __('ledger_daybook.line') }}</th><th class="num" style="width:95px">{{ $a['movers']['from'] }}</th><th class="num" style="width:95px">{{ $a['movers']['to'] }}</th><th class="num" style="width:95px">{{ __('ledger_daybook.change') }}</th></tr></thead>
        <tbody>
        @foreach($a['movers']['rows'] as $row)
            <tr>
                <td>{{ $row['line'] }}</td>
                <td class="num">{{ $M($row['previous']) }}</td>
                <td class="num">{{ $M($row['current']) }}</td>
                <td class="num {{ $row['direction'] === 'up' ? 'up' : 'down' }}">{{ $row['direction'] === 'up' ? '+' : '−' }}{{ $M(abs($row['change'])) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if(count($a['largest_movements']))
    <h2>{{ __('ledger_daybook.largest_title') }}</h2>
    <table class="g">
        <thead><tr><th style="width:62px">{{ __('ledger_daybook.date') }}</th><th style="width:75px">{{ __('ledger_daybook.ref') }}</th><th>{{ __('ledger_daybook.description') }}</th><th>{{ __('ledger_daybook.line') }}</th><th class="num" style="width:95px">{{ __('ledger_daybook.amount') }}</th></tr></thead>
        <tbody>
        @foreach($a['largest_movements'] as $row)
            <tr>
                <td>{{ $d($row['date']) }}</td><td>{{ $row['ref'] }}</td><td>{{ $row['description'] }}</td>
                <td class="{{ $row['line'] ? '' : 'none' }}">{{ $row['line'] ?: __('ledger_daybook.no_line') }}</td>
                <td class="num {{ $row['direction'] === 'in' ? 'up' : 'down' }}">{{ $M($row['amount']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($a['unallocated']['count'] > 0)
    <h2>{{ __('ledger_daybook.unallocated_title') }}</h2>
    <p class="sub"><strong>{{ $M($a['unallocated']['amount']) }}</strong> {{ __('ledger_daybook.unallocated_sub', ['n' => $a['unallocated']['count'], 'pct' => number_format($a['unallocated']['share'], 1)]) }}</p>
    <table class="g">
        <thead><tr><th style="width:62px">{{ __('ledger_daybook.date') }}</th><th style="width:75px">{{ __('ledger_daybook.ref') }}</th><th>{{ __('ledger_daybook.description') }}</th><th class="num" style="width:95px">{{ __('ledger_daybook.amount') }}</th></tr></thead>
        <tbody>
        @foreach($a['unallocated']['rows'] as $row)
            <tr><td>{{ $d($row['date']) }}</td><td>{{ $row['ref'] }}</td><td>{{ $row['description'] }}</td><td class="num">{{ $M($row['amount']) }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

@else
    <p class="sub">{{ __('ledger_daybook.nothing_recorded') }}</p>
@endif

@if($cl['sales']['total'] > 0)
    <pagebreak />
    <h2>{{ __('ledger_daybook.fc_title') }}</h2>
    <p class="sub">{{ __('ledger_daybook.fc_sub', ['year' => $year->name]) }}</p>
    <table class="tiles">
        <tr>
            <td><div class="lbl">{{ __('ledger_daybook.fc_paid_full') }}</div><div class="val">{{ number_format($cl['sales']['full']) }}</div><div class="note">{{ __('ledger_daybook.fc_of_sales', ['total' => number_format($cl['sales']['total'])]) }}</div></td>
            <td><div class="lbl">{{ __('ledger_daybook.fc_paid_part') }}</div><div class="val">{{ number_format($cl['sales']['part']) }}</div><div class="note">{{ __('ledger_daybook.fc_balance_left') }}</div></td>
            <td><div class="lbl">{{ __('ledger_daybook.fc_paid_nothing') }}</div><div class="val">{{ number_format($cl['sales']['none']) }}</div><div class="note">{{ __('ledger_daybook.fc_nothing_received') }}</div></td>
            <td><div class="lbl">{{ __('ledger_daybook.fc_rate') }}</div><div class="val">{{ number_format($cl['rate'], 0) }}%</div><div class="note">{{ __('ledger_daybook.fc_rate_note') }}</div></td>
        </tr>
    </table>
    <table class="g">
        <thead><tr><th class="num">{{ __('ledger_daybook.fc_billed') }}</th><th class="num">{{ __('ledger_daybook.fc_collected') }}</th><th class="num">{{ __('ledger_daybook.fc_outstanding') }}</th></tr></thead>
        <tbody><tr><td class="num">{{ $M($cl['billed']) }}</td><td class="num">{{ $M($cl['collected']) }}</td><td class="num">{{ $M($cl['outstanding']) }}</td></tr></tbody>
    </table>

    @if(count($cl['by_month']))
        <h2>{{ __('ledger_daybook.fc_month_title') }}</h2>
        <p class="sub">{{ __('ledger_daybook.fc_month_sub') }}</p>
        @if($chart(4))<div class="chart"><img src="{{ $chart(4) }}" style="width: 100%;"></div>@endif
        <table class="g">
            <thead><tr><th>{{ __('ledger_daybook.month') }}</th><th class="num">{{ __('ledger_daybook.fc_received') }}</th><th class="num">{{ __('ledger_daybook.fc_running_total') }}</th><th class="num">{{ __('ledger_daybook.fc_all_money_in') }}</th></tr></thead>
            <tbody>
            @foreach($cl['by_month'] as $mo)
                <tr><td>{{ $mo['label'] }}</td><td class="num">{{ $M($mo['cash']) }}</td><td class="num">{{ $M($mo['cumulative']) }}</td><td class="num">{{ $M($mo['all_in']) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if(count($cl['top_debtors']))
        <h2>{{ __('ledger_daybook.fc_debtors_title') }}</h2>
        <table class="g">
            <thead><tr><th>{{ __('ledger_daybook.fc_customer') }}</th><th class="num">{{ __('ledger_daybook.fc_billed') }}</th><th class="num">{{ __('ledger_daybook.fc_paid') }}</th><th class="num">{{ __('ledger_daybook.fc_outstanding') }}</th><th class="num">{{ __('ledger_daybook.fc_unpaid_share') }}</th></tr></thead>
            <tbody>
            @foreach($cl['top_debtors'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="num">{{ $M($row['billed']) }}</td><td class="num">{{ $M($row['paid']) }}</td><td class="num">{{ $M($row['outstanding']) }}</td><td class="num">{{ number_format($row['share'], 0) }}%</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

<p class="foot">{{ __('ledger_daybook.pdf_analysis_foot') }}</p>
<table class="sign">
    <tr>
        <td>{{ __('ledger_daybook.sign_accountant') }}</td>
        <td>{{ __('ledger_daybook.sign_manager') }}</td>
        <td>{{ __('ledger_daybook.date') }}</td>
    </tr>
</table>

</body>
</html>
