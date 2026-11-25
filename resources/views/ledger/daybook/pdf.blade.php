@php
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $acc = fn ($id) => isset($lines[$id]) ? $lines[$id]->account_code.' '.\App\Ledger\Services\Preview::accountName($lines[$id]) : '#'.$id;
    $periodName = \App\Ledger\Models\FiscalYear::periodLabel($period);
    $many = ! $account;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('ledger_daybook.daybook') }} — {{ $periodName }}</title>
    <style>
        body { font-family: sans-serif; font-size: 8.5pt; color: #000; }
        .head { text-align: center; border-bottom: 1.5px solid #333; padding-bottom: 6px; margin-bottom: 8px; }
        .biz { font-size: 13pt; font-weight: bold; }
        h1 { font-size: 11pt; margin: 4px 0 2px; text-transform: uppercase; letter-spacing: .04em; }
        .period { font-size: 8pt; color: #444; margin: 0 0 8px; }
        table.book { width: 100%; border-collapse: collapse; }
        table.book th, table.book td { border: .5px solid #999; padding: 2.5px 4px; }
        table.book th { background: #e8ebef; font-size: 7.5pt; text-transform: uppercase; }
        .num { text-align: right; white-space: nowrap; }
        .ref { font-size: 7.5pt; color: #555; }
        .line { font-size: 7.5pt; color: #333; }
        .none { color: #8a5312; font-weight: bold; }
        .total td { border-top: 1.2px solid #333; font-weight: bold; background: #f2f4f7; }
        .warn { border: .5px solid #c98b2e; background: #fdf6ea; color: #7a5312; padding: 5px 7px; margin-bottom: 8px; font-size: 8pt; }
        .foot { margin-top: 10px; font-size: 7.5pt; color: #444; }
        .sign { margin-top: 24px; width: 100%; }
        .sign td { padding-top: 18px; border-top: .5px solid #333; font-size: 8pt; width: 33%; }
        h2 { font-size: 9.5pt; margin: 12px 0 4px; }
    </style>
</head>
<body>

<div class="head">
    <div class="biz">{{ $businessName ?? '' }}</div>
    <h1>{{ __('ledger_daybook.pdf_title') }}</h1>
</div>
<p class="period">
    {{ $periodName }}
    &nbsp;|&nbsp; {{ __('ledger_daybook.fiscal_year') }} {{ $year->name }}
    &nbsp;|&nbsp; {{ $account ? $account->label : __('ledger_daybook.all_cash_boxes') }}
    &nbsp;|&nbsp; {{ __('ledger_daybook.generated') }} {{ now()->format('d/m/Y H:i') }}
    &nbsp;|&nbsp; {{ __('ledger_daybook.amounts_in', ['currency' => session('currency')['symbol'] ?? 'FCFA']) }}
</p>

@if(count($unanalysed))
    <div class="warn">
        <strong>{{ trans_choice('ledger_daybook.unanalysed_count_pdf', count($unanalysed), ['count' => count($unanalysed)]) }}.</strong>
        {{ __('ledger_daybook.unanalysed_help_pdf') }}
    </div>
@endif

<table class="book">
    <thead>
        <tr>
            <th style="width:62px">{{ __('ledger_daybook.date') }}</th>
            <th style="width:80px">{{ __('ledger_daybook.ref') }}</th>
            <th>{{ __('ledger_daybook.description') }}</th>
            @if($many)<th style="width:120px">{{ __('ledger_daybook.cash_box') }}</th>@endif
            <th style="width:140px">{{ __('ledger_daybook.analysis_column') }}</th>
            <th style="width:74px" class="num">{{ __('ledger_daybook.debit_in') }}</th>
            <th style="width:74px" class="num">{{ __('ledger_daybook.credit_out') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr class="total">
            <td colspan="{{ $many ? 5 : 4 }}">{{ __('ledger_daybook.opening_balance') }}</td>
            <td class="num" colspan="2">{{ $m($opening) }}</td>
        </tr>
        @forelse($rows as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                <td class="ref">{{ $row['ref'] }}@if($row['ref'] !== $row['entry_number'])<br>{{ $row['entry_number'] }}@endif</td>
                <td>{{ $row['description'] }} ({{ __('ledger_daybook.src_'.$row['source']) }}{{ $row['is_reversal'] ? ', '.__('ledger_daybook.reversal') : '' }})</td>
                @if($many)<td class="line">{{ $acc($row['cash_account_id']) }}</td>@endif
                <td class="line">
                    @if(! $row['unanalysed'])
                        {{ $acc($row['line_id']) }}{{ count($row['splits']) > 1 ? ' '.__('ledger_daybook.and_n_more', ['n' => count($row['splits']) - 1]) : '' }}
                    @else
                        <span class="none">{{ __('ledger_daybook.none') }}</span>
                    @endif
                </td>
                <td class="num">{{ $row['direction'] === 'in' ? $m($row['amount']) : '' }}</td>
                <td class="num">{{ $row['direction'] === 'out' ? $m($row['amount']) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $many ? 7 : 6 }}">{{ __('ledger_daybook.no_cash_moved_period') }}</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="{{ $many ? 5 : 4 }}">{{ __('ledger_daybook.total_for') }} {{ mb_strtoupper($periodName) }}</td>
            <td class="num">{{ $m($totalIn) }}</td>
            <td class="num">{{ $m($totalOut) }}</td>
        </tr>
        <tr class="total">
            <td colspan="{{ $many ? 5 : 4 }}">{{ __('ledger_daybook.net_movement_caps') }}</td>
            <td class="num" colspan="2">{{ $m($totalIn - $totalOut) }}</td>
        </tr>
        <tr class="total">
            <td colspan="{{ $many ? 5 : 4 }}">{{ __('ledger_daybook.closing_balance') }}</td>
            <td class="num" colspan="2">{{ $m($closing) }}</td>
        </tr>
    </tbody>
</table>

<h2>{{ __('ledger_daybook.by_cash_box') }}</h2>
<table class="book">
    <thead>
        <tr>
            <th>{{ __('ledger_daybook.cash_box') }}</th>
            <th class="num">{{ __('ledger_daybook.opening_balance') }}</th>
            <th class="num">{{ __('ledger_daybook.debit_in') }}</th>
            <th class="num">{{ __('ledger_daybook.credit_out') }}</th>
            <th class="num">{{ __('ledger_daybook.closing_balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($balances as $b)
            <tr>
                <td>{{ $b['account']->label }}</td>
                <td class="num">{{ $m($b['opening']) }}</td>
                <td class="num">{{ $m($b['in']) }}</td>
                <td class="num">{{ $m($b['out']) }}</td>
                <td class="num">{{ $m($b['closing']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p class="foot">{{ __('ledger_daybook.pdf_foot') }}</p>

<table class="sign">
    <tr>
        <td>{{ __('ledger_daybook.sign_accountant') }}</td>
        <td>{{ __('ledger_daybook.sign_manager') }}</td>
        <td>{{ __('ledger_daybook.date') }}</td>
    </tr>
</table>

</body>
</html>
