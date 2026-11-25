@extends('layouts.app')
@section('title', __('ledger_gl.balance_sheet'))

@php
    // Negative amounts (depreciation under fixed assets, a loss in the result)
    // print in brackets, the way a balance sheet shows a deduction.
    $fmt = fn ($v) => round((float) $v) < 0 ? '('.\App\Ledger\Ledger::money(abs((float) $v)).')' : \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
    $FS = \App\Ledger\Services\FinancialStatements::class;
    $dual = fn ($a, $b) => __('ledger_gl.'.$a) === __('ledger_gl.'.$b) ? __('ledger_gl.'.$a) : __('ledger_gl.'.$a).' ('.__('ledger_gl.'.$b).')';
    $cmp = $comparative ?? null;
    $cmpLine = function (string $section, $account) use ($cmp) {
        return $cmp ? ($cmp[$section]->firstWhere('id', $account->id)->balance ?? 0) : null;
    };
    $sections = [
        'actif' => [
            ['key' => 'fixedAssets', 'label' => $dual('fixed_assets', 'actif_immobilise')],
            ['key' => 'inventory', 'label' => $dual('inventory', 'stocks')],
            ['key' => 'receivables', 'label' => $dual('receivables', 'creances')],
            ['key' => 'cashAndBanks', 'label' => $dual('cash_banks', 'tresorerie_actif')],
        ],
        'passif' => [
            ['key' => 'financialDebts', 'label' => $dual('borrowings', 'dettes_financieres')],
            ['key' => 'payables', 'label' => $dual('payables', 'passif_circulant')],
            ['key' => 'treasuryLiabilities', 'label' => $dual('overdrafts', 'tresorerie_passif')],
        ],
    ];
    $balanced = abs($difference) < 0.01;
@endphp

@section('content')
@include('ledger.partials.style')
@include('ledger.general-ledger.partials.print')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $dual('balance_sheet', 'bilan') }}</h1>
    <small class="text-muted">
        {{ __('ledger_gl.as_of') }}: {{ $d($asOfDate) }}
        @if($fiscalYear) &middot; {{ $fiscalYear->name }} @endif
    </small>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border no-print">
            <div class="box-tools pull-right">
                <a href="{{ route('ledger.general-ledger.index') }}" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> {{ __('ledger_gl.back') }}</a>
                <button type="button" class="btn btn-info btn-sm" onclick="window.print()"><i class="fas fa-print"></i> {{ __('ledger_gl.print') }}</button>
                @can('balance-sheet-export')
                <a href="{{ route('ledger.general-ledger.balance-sheet-pdf', request()->query()) }}" class="btn btn-danger btn-sm" target="_blank"><i class="fas fa-file-pdf"></i> {{ __('ledger_gl.export_pdf') }}</a>
                <a href="{{ route('ledger.general-ledger.balance-sheet-excel', request()->query()) }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> {{ __('ledger_gl.export_excel') }}</a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            <form action="{{ route('ledger.general-ledger.balance-sheet') }}" method="GET" class="form-inline no-print" style="margin-bottom: 20px;">
                <div class="form-group" style="margin-right: 15px;">
                    <label for="as_of_date" style="margin-right: 6px;">{{ __('ledger_gl.as_of_date') }}:</label>
                    <input type="date" class="form-control" id="as_of_date" name="as_of_date" value="{{ $asOfDate }}" required>
                </div>
                <div class="form-group" style="margin-right: 15px;">
                    <label for="comparative_date" style="margin-right: 6px;">{{ __('ledger_gl.comparative_date') }} ({{ __('ledger_gl.optional') }}):</label>
                    <input type="date" class="form-control" id="comparative_date" name="comparative_date" value="{{ $comparativeDate ?? '' }}">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> {{ __('ledger_gl.apply_filter') }}</button>
            </form>

            <div class="table-responsive">
                {{-- ACTIF --}}
                <table class="table table-bordered table-hover" id="bs_actif">
                    <thead class="gl-thead-dark">
                        <tr>
                            <th width="50%">{{ __('ledger_gl.assets') }}@if(__('ledger_gl.assets') !== __('ledger_gl.actif')) ({{ __('ledger_gl.actif') }})@endif</th>
                            <th width="25%" class="ap-num">{{ $d($asOfDate) }}</th>
                            @if($cmp)
                            <th width="25%" class="ap-num">{{ $d($comparativeDate) }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sections['actif'] as $section)
                        <tr class="info">
                            <td><strong>{{ $section['label'] }}</strong></td>
                            <td class="ap-num"><strong>{{ $fmt(${$section['key']}->sum('balance')) }}</strong></td>
                            @if($cmp)<td class="ap-num"><strong>{{ $fmt($cmp[$section['key']]->sum('balance')) }}</strong></td>@endif
                        </tr>
                        @foreach(${$section['key']} as $account)
                        <tr data-code="{{ $account->account_code }}">
                            <td style="padding-left: 24px;">
                                {{ $account->account_code }} - {{ $FS::name($account) }}
                                @unless($account->is_postable)<span class="label label-warning" title="{{ __('ledger_gl.heading_account_tip') }}">{{ __('ledger_gl.heading_account') }}</span>@endunless
                            </td>
                            <td class="ap-num">{{ $fmt($account->balance) }}</td>
                            @if($cmp)<td class="ap-num">{{ $fmt($cmpLine($section['key'], $account)) }}</td>@endif
                        </tr>
                        @endforeach
                        @endforeach
                        <tr class="success" id="bs_total_assets">
                            <td><strong>{{ __('ledger_gl.total_assets') }}</strong></td>
                            <td class="ap-num"><strong>{{ $fmt($totalAssets) }}</strong></td>
                            @if($cmp)<td class="ap-num"><strong>{{ $fmt($cmp['totalAssets']) }}</strong></td>@endif
                        </tr>
                    </tbody>
                </table>

                {{-- PASSIF --}}
                <table class="table table-bordered table-hover" id="bs_passif" style="margin-top: 20px;">
                    <thead class="gl-thead-dark">
                        <tr>
                            <th width="50%">{{ __('ledger_gl.liabilities_equity') }}@if(__('ledger_gl.liabilities_equity') !== __('ledger_gl.passif')) ({{ __('ledger_gl.passif') }})@endif</th>
                            <th width="25%" class="ap-num">{{ $d($asOfDate) }}</th>
                            @if($cmp)
                            <th width="25%" class="ap-num">{{ $d($comparativeDate) }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="info">
                            <td><strong>{{ $dual('equity_capital', 'capitaux_propres') }}</strong></td>
                            <td class="ap-num"><strong>{{ $fmt($totalEquity) }}</strong></td>
                            @if($cmp)<td class="ap-num"><strong>{{ $fmt($cmp['totalEquity']) }}</strong></td>@endif
                        </tr>
                        @foreach($equity as $account)
                        <tr data-code="{{ $account->account_code }}">
                            <td style="padding-left: 24px;">
                                {{ $account->account_code }} - {{ $FS::name($account) }}
                                @unless($account->is_postable)<span class="label label-warning">{{ __('ledger_gl.heading_account') }}</span>@endunless
                            </td>
                            <td class="ap-num">{{ $fmt($account->balance) }}</td>
                            @if($cmp)<td class="ap-num">{{ $fmt($cmpLine('equity', $account)) }}</td>@endif
                        </tr>
                        @endforeach
                        @if(abs($priorResult) > 0.004 || ($cmp && abs($cmp['priorResult']) > 0.004))
                        <tr>
                            <td style="padding-left: 24px;">{{ $dual('earlier_results', 'report_a_nouveau') }}</td>
                            <td class="ap-num">{{ $fmt($priorResult) }}</td>
                            @if($cmp)<td class="ap-num">{{ $fmt($cmp['priorResult']) }}</td>@endif
                        </tr>
                        @endif
                        <tr id="bs_year_result">
                            <td style="padding-left: 24px;">
                                {{ $dual('result_for_year', 'resultat_net_exercice') }}
                                <small class="text-muted" style="display: block;">{{ $yearResult >= 0 ? __('ledger_gl.profit') : __('ledger_gl.loss') }} &middot; {{ __('ledger_gl.result_note') }}</small>
                            </td>
                            <td class="ap-num {{ $yearResult < 0 ? 'text-danger' : '' }}">{{ $fmt($yearResult) }}</td>
                            @if($cmp)<td class="ap-num">{{ $fmt($cmp['yearResult']) }}</td>@endif
                        </tr>

                        @foreach($sections['passif'] as $section)
                        <tr class="info">
                            <td><strong>{{ $section['label'] }}</strong></td>
                            <td class="ap-num"><strong>{{ $fmt(${$section['key']}->sum('balance')) }}</strong></td>
                            @if($cmp)<td class="ap-num"><strong>{{ $fmt($cmp[$section['key']]->sum('balance')) }}</strong></td>@endif
                        </tr>
                        @foreach(${$section['key']} as $account)
                        <tr data-code="{{ $account->account_code }}">
                            <td style="padding-left: 24px;">
                                {{ $account->account_code }} - {{ $FS::name($account) }}
                                @unless($account->is_postable)<span class="label label-warning">{{ __('ledger_gl.heading_account') }}</span>@endunless
                            </td>
                            <td class="ap-num">{{ $fmt($account->balance) }}</td>
                            @if($cmp)<td class="ap-num">{{ $fmt($cmpLine($section['key'], $account)) }}</td>@endif
                        </tr>
                        @endforeach
                        @endforeach

                        <tr class="success" id="bs_total_liabilities">
                            <td><strong>{{ __('ledger_gl.total_liabilities_equity') }}</strong></td>
                            <td class="ap-num"><strong>{{ $fmt($totalLiabilities) }}</strong></td>
                            @if($cmp)<td class="ap-num"><strong>{{ $fmt($cmp['totalLiabilities']) }}</strong></td>@endif
                        </tr>
                    </tbody>
                </table>
            </div>

            @if(isset($unclassified) && $unclassified->isNotEmpty())
            <div class="alert alert-warning">
                {{ __('ledger_gl.unclassified_note') }}
                {{ $unclassified->map(fn ($a) => $a->account_code.' ('.$fmt($a->balance).')')->implode(', ') }}
            </div>
            @endif

            <div class="alert {{ $balanced ? 'alert-success' : 'alert-danger' }}" id="bs_verdict" data-balanced="{{ $balanced ? 1 : 0 }}">
                <h4><i class="icon fas {{ $balanced ? 'fa-check' : 'fa-exclamation-triangle' }}"></i> {{ __('ledger_gl.balance_verification') }}</h4>
                @if($balanced)
                    <p style="margin: 0;">{{ __('ledger_gl.balance_sheet_is_balanced') }}: {{ __('ledger_gl.assets') }} = {{ __('ledger_gl.liabilities_equity') }}</p>
                @else
                    <p style="margin: 0;">
                        {{ __('ledger_gl.balance_sheet_not_balanced') }}:
                        {{ __('ledger_gl.difference') }}: {{ \App\Ledger\Ledger::money(abs($difference)) }} {{ $cur }}.
                        <a href="{{ route('ledger.ledger-health') }}">{{ __('ledger_gl.ledger_not_balanced') }}</a>
                    </p>
                @endif
            </div>

            <small class="text-muted"><i class="fas fa-info-circle"></i> {{ __('ledger_gl.report_generated_at') }}: {{ now()->format('d/m/Y H:i') }}</small>
        </div>
    </div>
</section>
@endsection
