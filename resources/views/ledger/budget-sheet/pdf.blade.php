<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>@lang('ledger_budget.ie') — {{ $budget->title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 8.5pt; color: #000; }
        .masthead { text-align: center; border-bottom: 2px solid #333; padding-bottom: 6px; margin-bottom: 10px; }
        .masthead .name { font-size: 14pt; font-weight: bold; }
        h1 { font-size: 11pt; margin: 0 0 2px; text-transform: uppercase; }
        .period { font-size: 8.5pt; color: #444; margin: 0 0 10px; }
        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet th, table.sheet td { border: 0.5px solid #999; padding: 2.5px 4px; }
        table.sheet th { background: #e8ebef; font-size: 7.5pt; text-transform: uppercase; }
        .num { text-align: right; white-space: nowrap; }
        .code { width: 40px; color: #555; }
        .section td { background: #34495e; color: #fff; font-weight: bold; text-transform: uppercase; font-size: 7.5pt; }
        .header td { background: #eef1f6; font-weight: bold; }
        .child .name { padding-left: 10px; }
        .total td { border-top: 1.2px solid #333; font-weight: bold; }
        .subtotal td { font-weight: bold; background: #f7f9fb; }
        .subtotal .name { padding-left: 10px; font-style: italic; }
        .prior { color: #555; }
        .foot { margin-top: 14px; font-size: 8pt; color: #333; }
        .sign { margin-top: 30px; width: 100%; }
        .sign td { padding-top: 24px; font-size: 8.5pt; }
    </style>
</head>
<body>
@php
    $money = fn ($v) => $v ? \App\Ledger\Ledger::money($v) : '—';
    $M = fn ($v) => \App\Ledger\Ledger::money($v);
    $totalRow = function ($label, $key, $variance) use ($totals, $previous, $priorTotals, $money, $M) {
        $tv = $totals['actual'][$key] - $totals['budget'][$key];

        return '<tr class="total"><td></td><td>'.e($label).'</td><td class="num prior">'.($previous ? $money($priorTotals[$key]) : '—').'</td>'
            .'<td class="num">'.$money($totals['budget'][$key]).'</td><td class="num">'.$money($totals['actual'][$key]).'</td>'
            .'<td class="num">'.($variance ? $M($tv) : '—').'</td></tr>';
    };
@endphp
<div class="masthead"><div class="name">{{ $businessName }}</div></div>
<h1>@lang('ledger_budget.pdf_heading')</h1>
<p class="period">
    {{ $budget->title }} &nbsp;·&nbsp; {{ $budget->start_date->format('d/m/Y') }} &ndash; {{ $budget->end_date->format('d/m/Y') }}
    &nbsp;·&nbsp; @lang('ledger_budget.status'): {{ \App\Ledger\Models\Budget::statusLabel($budget->status) }}
</p>
<table class="sheet">
    <thead>
        <tr>
            <th class="code">@lang('ledger_budget.code')</th>
            <th>@lang('ledger_budget.description')</th>
            <th class="num">{{ $previous ? $previous->title : __('ledger_budget.last_year') }}</th>
            <th class="num">@lang('ledger_budget.budget')</th>
            <th class="num">@lang('ledger_budget.actual')</th>
            <th class="num">@lang('ledger_budget.variance')</th>
        </tr>
    </thead>
    <tbody>
        <tr class="total">
            <td></td>
            <td>@lang('ledger_budget.opening_balance_caps')</td>
            <td class="num prior">{{ $previous ? $money($priorTotals['opening']) : '—' }}</td>
            <td class="num">{{ $money($budget->opening_balance) }}</td>
            <td class="num">{{ $money($totals['actual']['opening']) }}</td>
            <td class="num">—</td>
        </tr>
        @foreach(['income', 'expenditure', 'capital'] as $section)
            <tr class="section"><td colspan="6">{{ __('ledger_budget.sheet_section_'.$section) }}</td></tr>
            @foreach($lines->where('section', $section) as $line)
                @php
                    $b = $budgeted[$line->id] ?? 0;
                    $a = $actual[$line->id] ?? 0;
                @endphp
                <tr class="{{ $line->is_header ? 'header' : 'child' }}">
                    <td class="code">{{ $line->code }}</td>
                    <td class="name">{{ $line->display_name }}</td>
                    <td class="num prior">{{ $money($priorActual[$line->id] ?? 0) }}</td>
                    <td class="num">{{ $money($b) }}</td>
                    <td class="num">{{ $money($a) }}</td>
                    <td class="num">{{ $b ? $M($a - $b) : '—' }}</td>
                </tr>
                @if(isset($groupEnds[$line->id]))
                    @php $gh = $groupEnds[$line->id]; $gb = $budgeted[$gh->id] ?? 0; $ga = $actual[$gh->id] ?? 0; @endphp
                    <tr class="subtotal">
                        <td></td>
                        <td class="name">@lang('ledger_budget.total') {{ $gh->display_name }}</td>
                        <td class="num prior">{{ $money($priorActual[$gh->id] ?? 0) }}</td>
                        <td class="num">{{ $money($gb) }}</td>
                        <td class="num">{{ $money($ga) }}</td>
                        <td class="num">{{ $gb ? $M($ga - $gb) : '—' }}</td>
                    </tr>
                @endif
            @endforeach
            @if($section === 'income')
                {!! $totalRow(__('ledger_budget.total_income_caps'), 'income', true) !!}
            @elseif($section === 'expenditure')
                {!! $totalRow(__('ledger_budget.total_expenditure_caps'), 'expenditure', true) !!}
                {!! $totalRow(__('ledger_budget.closing_balance_caps'), 'closing', false) !!}
            @else
                {!! $totalRow(__('ledger_budget.total_capital_caps'), 'capital', true) !!}
            @endif
        @endforeach
    </tbody>
</table>

<div class="foot">
    <strong>@lang('ledger_budget.cash_position'):</strong>
    @lang('ledger_budget.closing_balance') {{ $money($totals['actual']['closing']) }}
    + @lang('ledger_budget.depreciation_short') {{ $money($totals['actual']['depreciation']) }}
    − @lang('ledger_budget.capital_spend') {{ $money($totals['actual']['capital']) }}
    = <strong>{{ $money($totals['actual']['cash']) }}</strong>
    &nbsp;·&nbsp;
    @if(! empty($reconciliation['agrees']))
        @lang('ledger_budget.agreed_with_gl')
    @else
        <strong>@lang('ledger_budget.not_agreed_with_gl')</strong>
    @endif
    @foreach($reconciliation['known_differences'] ?? [] as $known)
        <br>{{ $known['detail'] }}
    @endforeach
</div>

<table class="sign">
    <tr>
        <td width="50%">________________________________<br>@lang('ledger_budget.prepared_by')</td>
        <td width="50%">________________________________<br>@lang('ledger_budget.approved_by')</td>
    </tr>
</table>
</body>
</html>
