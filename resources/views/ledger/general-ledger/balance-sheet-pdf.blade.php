@extends('ledger.general-ledger.pdf-layout')

@php
    $fmt = fn ($v) => round((float) $v) < 0 ? '('.\App\Ledger\Ledger::money(abs((float) $v)).')' : \App\Ledger\Ledger::money($v);
    $FS = \App\Ledger\Services\FinancialStatements::class;
    $dual = fn ($a, $b) => __('ledger_gl.'.$a) === __('ledger_gl.'.$b) ? __('ledger_gl.'.$a) : __('ledger_gl.'.$a).' ('.__('ledger_gl.'.$b).')';
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
    $cmp = $comparative ?? null;
    $cmpLine = fn (string $section, $account) => $cmp ? ($cmp[$section]->firstWhere('id', $account->id)->balance ?? 0) : null;
    $actif = [
        'fixedAssets' => $dual('fixed_assets', 'actif_immobilise'),
        'inventory' => $dual('inventory', 'stocks'),
        'receivables' => $dual('receivables', 'creances'),
        'cashAndBanks' => $dual('cash_banks', 'tresorerie_actif'),
    ];
    $passif = [
        'financialDebts' => $dual('borrowings', 'dettes_financieres'),
        'payables' => $dual('payables', 'passif_circulant'),
        'treasuryLiabilities' => $dual('overdrafts', 'tresorerie_passif'),
    ];
    $cur = session('currency')['symbol'] ?? 'FCFA';
@endphp

@section('title', __('ledger_gl.balance_sheet'))
@section('report_title', $dual('balance_sheet', 'bilan'))

@section('content')
    <p class="text-center" style="margin-bottom: 14px;">
        <strong>{{ __('ledger_gl.as_of') }}:</strong> {{ $d($asOfDate) }}
        @if($fiscalYear) &middot; {{ $fiscalYear->name }} @endif
    </p>

    <div class="section-title">{{ $dual('assets', 'actif') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('ledger_gl.account') }}</th>
                <th class="text-right">{{ $d($asOfDate) }}</th>
                @if($cmp)<th class="text-right">{{ $d($comparativeDate) }}</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($actif as $key => $label)
            <tr class="font-bold"><td>{{ $label }}</td><td class="text-right">{{ $fmt(${$key}->sum('balance')) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp[$key]->sum('balance')) }}</td>@endif</tr>
            @foreach(${$key} as $account)
            <tr><td style="padding-left: 14px;">{{ $account->account_code }} - {{ $FS::name($account) }}</td><td class="text-right">{{ $fmt($account->balance) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmpLine($key, $account)) }}</td>@endif</tr>
            @endforeach
            @endforeach
            <tr class="totals-row"><td>{{ __('ledger_gl.total_assets') }}</td><td class="text-right">{{ $fmt($totalAssets) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['totalAssets']) }}</td>@endif</tr>
        </tbody>
    </table>

    <div class="section-title">{{ $dual('liabilities_equity', 'passif') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('ledger_gl.account') }}</th>
                <th class="text-right">{{ $d($asOfDate) }}</th>
                @if($cmp)<th class="text-right">{{ $d($comparativeDate) }}</th>@endif
            </tr>
        </thead>
        <tbody>
            <tr class="font-bold"><td>{{ $dual('equity_capital', 'capitaux_propres') }}</td><td class="text-right">{{ $fmt($totalEquity) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['totalEquity']) }}</td>@endif</tr>
            @foreach($equity as $account)
            <tr><td style="padding-left: 14px;">{{ $account->account_code }} - {{ $FS::name($account) }}</td><td class="text-right">{{ $fmt($account->balance) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmpLine('equity', $account)) }}</td>@endif</tr>
            @endforeach
            @if(abs($priorResult) > 0.004 || ($cmp && abs($cmp['priorResult']) > 0.004))
            <tr><td style="padding-left: 14px;">{{ __('ledger_gl.report_a_nouveau') }}</td><td class="text-right">{{ $fmt($priorResult) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['priorResult']) }}</td>@endif</tr>
            @endif
            <tr><td style="padding-left: 14px;">{{ __('ledger_gl.resultat_net_exercice') }}</td><td class="text-right">{{ $fmt($yearResult) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['yearResult']) }}</td>@endif</tr>
            @foreach($passif as $key => $label)
            <tr class="font-bold"><td>{{ $label }}</td><td class="text-right">{{ $fmt(${$key}->sum('balance')) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp[$key]->sum('balance')) }}</td>@endif</tr>
            @foreach(${$key} as $account)
            <tr><td style="padding-left: 14px;">{{ $account->account_code }} - {{ $FS::name($account) }}</td><td class="text-right">{{ $fmt($account->balance) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmpLine($key, $account)) }}</td>@endif</tr>
            @endforeach
            @endforeach
            <tr class="totals-row"><td>{{ __('ledger_gl.total_liabilities_equity') }}</td><td class="text-right">{{ $fmt($totalLiabilities) }}</td>@if($cmp)<td class="text-right">{{ $fmt($cmp['totalLiabilities']) }}</td>@endif</tr>
        </tbody>
    </table>

    <p class="{{ abs($difference) < 0.01 ? 'text-success' : 'text-danger' }}" style="margin-top: 10px;">
        @if(abs($difference) < 0.01)
            {{ __('ledger_gl.balance_sheet_is_balanced') }}: {{ __('ledger_gl.assets') }} = {{ __('ledger_gl.liabilities_equity') }}
        @else
            {{ __('ledger_gl.balance_sheet_not_balanced') }}: {{ __('ledger_gl.difference') }} {{ \App\Ledger\Ledger::money(abs($difference)) }} {{ $cur }}
        @endif
    </p>
@endsection
