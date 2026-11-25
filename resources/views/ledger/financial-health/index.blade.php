@extends('layouts.app')
@section('title', $title)

{{-- paxhitestbed admin/accounting/reports/financial-health: verdicts, the measures, twelve months, this year against last, where to look next. --}}
@section('content')
@include('ledger.partials.style')
@php
    $money = fn ($n) => \App\Ledger\Ledger::money($n);
    $date = fn ($d) => \Carbon\Carbon::parse($d)->format('d/m/Y');
    $verdict = [
        'ok' => ['label' => __('ledger_assets.fh_healthy'), 'chip' => 'paid'],
        'watch' => ['label' => __('ledger_assets.fh_watch'), 'chip' => 'part_paid'],
        'act' => ['label' => __('ledger_assets.fh_act_now'), 'chip' => 'unpaid'],
    ];
    $show = function ($i) use ($money) {
        if ($i['value'] === null) { return '—'; }
        $v = round((float) $i['value'], $i['unit'] === 'money' ? 0 : 1) + 0;
        switch ($i['unit']) {
            case 'percent': return number_format($v, 1, ',', ' ').' %';
            case 'months': return number_format($v, 1, ',', ' ').' '.__('ledger_assets.fh_months');
            default: return $money($v);
        }
    };
    $peak = max(1, collect($h['months'])->flatMap(fn ($m) => [$m['income'], $m['expenses']])->max());
    $cashPeak = max(1, collect($h['months'])->map(fn ($m) => abs($m['cash']))->max());
    $ly = $h['last_year'];
    $links = [
        [['ledger.general-ledger.income-statement', 'fh_income_statement'], ['ledger.general-ledger.balance-sheet', 'fh_balance_sheet'], ['ledger.general-ledger.trial-balance', 'fh_trial_balance']],
        [['ledger.daybook.analysis', 'fh_budget_analysis']],
        [['ledger.supplier-bill.aging', 'fh_payables_aging'], ['ledger.accounting-reports.receivables-aging', 'fh_receivables_aging']],
        [['ledger.bank-reconciliation.index', 'fh_bank_reconciliation'], ['ledger.cash-accounts', 'fh_cash_accounts'], ['ledger.ledger-health', 'fh_ledger_health']],
        [['ledger.fixed-assets.register', 'fh_asset_register']],
    ];
@endphp
<style>
    .fh-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; }
    .fh-tile { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; background: #fff; }
    .fh-tile .fh-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .fh-tile .fh-label { color: #475569; font-size: .8rem; font-weight: 600; }
    .fh-tile .fh-value { font-size: 1.35rem; font-weight: 600; color: #0f172a; font-variant-numeric: tabular-nums; margin: 4px 0; }
    .fh-tile .fh-detail { color: #64748b; font-size: .78rem; line-height: 1.35; }
    .fh-bar { height: 8px; border-radius: 0 4px 4px 0; display: inline-block; vertical-align: middle; }
    .fh-bar.in { background: #1d4ed8; }
    .fh-bar.out { background: #b45309; }
    .fh-bar.cash { background: #0f766e; }
    .fh-bar.neg { background: #b91c1c; }
    .fh-key span { display: inline-flex; align-items: center; gap: 6px; margin-right: 14px; font-size: .8rem; color: #334155; }
    .fh-key i { width: 12px; height: 8px; display: inline-block; border-radius: 2px; }
    .fh-head-row { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px; }
</style>
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.financial_health')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border fh-head-row">
            <h3 class="box-title">@lang('ledger_assets.financial_health')
                <span class="ap-muted">@lang('ledger_assets.fh_from_books', ['date' => $date($h['as_of'])])@if($h['year']) · {{ $h['year'] }}@endif</span></h3>
            <form method="get" class="ap-filters">
                <div class="form-group">
                    <label for="as_of">@lang('ledger_assets.fh_as_at')</label>
                    <input type="date" name="as_of" id="as_of" class="form-control input-sm" value="{{ $h['as_of'] }}">
                </div>
                <div class="form-group"><button type="submit" class="btn btn-info btn-sm"><i class="fa fa-sync"></i> @lang('ledger_assets.fh_show')</button></div>
            </form>
        </div>
        <div class="box-body">
            @if($h['failed_postings'] > 0)
            <div class="ap-note is-bad" style="margin-bottom: 12px;">
                {{ trans_choice('ledger_assets.fh_failed_postings', $h['failed_postings'], ['count' => $h['failed_postings']]) }}
                <a href="{{ route('ledger.ledger-health') }}">@lang('ledger_assets.fh_ledger_health')</a>
            </div>
            @endif

            @if($h['attention'])
            <div class="ap-note is-warn" style="margin-bottom: 15px;">
                <strong>@lang('ledger_assets.fh_needs_attention')</strong>
                <ul style="margin-bottom: 0; padding-left: 18px;">
                    @foreach($h['attention'] as $i)
                    <li><strong>{{ $i['label'] }}: {{ $show($i) }}</strong> — {{ $i['detail'] }}</li>
                    @endforeach
                </ul>
            </div>
            @else
            <div class="ap-note" style="margin-bottom: 15px;">@lang('ledger_assets.fh_nothing_needs_attention')</div>
            @endif

            <div class="fh-grid" style="margin-bottom: 20px;">
                @foreach($h['indicators'] as $i)
                <div class="fh-tile" data-indicator="{{ $i['key'] }}">
                    <div class="fh-head">
                        <span class="fh-label">{{ $i['label'] }}</span>
                        <span class="ap-chip {{ $verdict[$i['state']]['chip'] }}">{{ $verdict[$i['state']]['label'] }}</span>
                    </div>
                    <div class="fh-value">{{ $show($i) }}</div>
                    <div class="fh-detail">{{ $i['detail'] }}</div>
                </div>
                @endforeach
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <h4 style="margin-top: 0;">@lang('ledger_assets.fh_twelve_months')</h4>
                    <div class="fh-key" style="margin-bottom: 8px;">
                        <span><i class="fh-bar in"></i>@lang('ledger_assets.fh_income_class7')</span>
                        <span><i class="fh-bar out"></i>@lang('ledger_assets.fh_expenses_class6')</span>
                        <span><i class="fh-bar cash"></i>@lang('ledger_assets.fh_cash_month_end')</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-condensed ap-table">
                            <thead>
                                <tr>
                                    <th>@lang('ledger_assets.fh_month')</th>
                                    <th class="ap-num">@lang('ledger_assets.fh_income')</th>
                                    <th class="ap-num">@lang('ledger_assets.fh_expenses')</th>
                                    <th style="width: 24%;"></th>
                                    <th class="ap-num">@lang('ledger_assets.fh_surplus_col')</th>
                                    <th class="ap-num">@lang('ledger_assets.fh_cash_month_end')</th>
                                    <th style="width: 14%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($h['months'] as $m)
                                <tr>
                                    <td style="white-space: nowrap;">{{ $m['label'] }}</td>
                                    <td class="ap-num">{{ $money($m['income']) }}</td>
                                    <td class="ap-num">{{ $money($m['expenses']) }}</td>
                                    <td>
                                        <div><span class="fh-bar in" style="width: {{ max(0, round($m['income'] / $peak * 100, 1)) }}%" title="{{ __('ledger_assets.fh_income') }} {{ $money($m['income']) }}"></span></div>
                                        <div><span class="fh-bar out" style="width: {{ max(0, round($m['expenses'] / $peak * 100, 1)) }}%" title="{{ __('ledger_assets.fh_expenses') }} {{ $money($m['expenses']) }}"></span></div>
                                    </td>
                                    <td class="ap-num">{{ $money($m['surplus']) }}@if($m['surplus'] < 0) <span class="ap-chip unpaid">@lang('ledger_assets.fh_deficit')</span>@endif</td>
                                    <td class="ap-num">{{ $money($m['cash']) }}@if($m['cash'] < 0) <span class="ap-chip unpaid">@lang('ledger_assets.fh_below_zero')</span>@endif</td>
                                    <td><span class="fh-bar {{ $m['cash'] < 0 ? 'neg' : 'cash' }}" style="width: {{ round(abs($m['cash']) / $cashPeak * 100, 1) }}%" title="{{ $money($m['cash']) }}"></span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(collect($h['months'])->contains(fn ($m) => $m['cash'] < 0))
                    <div class="ap-note is-warn">@lang('ledger_assets.fh_cash_negative_note')</div>
                    @endif
                </div>
                <div class="col-lg-4">
                    <h4 style="margin-top: 0;">@lang('ledger_assets.fh_this_vs_last')</h4>
                    <table class="table table-condensed ap-table">
                        <thead>
                            <tr><th></th><th class="ap-num">@lang('ledger_assets.fh_this_year')</th><th class="ap-num">@lang('ledger_assets.fh_last_year')</th><th class="ap-num">@lang('ledger_assets.fh_change')</th></tr>
                        </thead>
                        <tbody>
                            @foreach([['fh_income', $h['income'], $ly['income']], ['fh_expenses', $h['expenses'], $ly['expenses']], ['fh_surplus_col', $h['surplus'], $ly['surplus']]] as [$label, $now, $then])
                            <tr>
                                <td>@lang('ledger_assets.'.$label)</td>
                                <td class="ap-num">{{ $money($now) }}</td>
                                <td class="ap-num">{{ $money($then) }}</td>
                                <td class="ap-num">{{ abs($then) > 0.5 ? number_format(($now - $then) / abs($then) * 100, 1, ',', ' ').' %' : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="ap-muted" style="margin-bottom: 15px;">@lang('ledger_assets.fh_ranges', ['a' => $date($h['from']), 'b' => $date($h['as_of']), 'c' => $date($ly['from']), 'd' => $date($ly['to'])])</div>

                    <h4>@lang('ledger_assets.fh_where_next')</h4>
                    <ul style="padding-left: 18px; font-size: .9em;">
                        @foreach($links as $group)
                        @php $here = array_values(array_filter($group, fn ($l) => \Illuminate\Support\Facades\Route::has($l[0]))); @endphp
                        @if($here)
                        <li>@foreach($here as $n => $l)@if($n) · @endif<a href="{{ route($l[0]) }}">@lang('ledger_assets.'.$l[1])</a>@endforeach</li>
                        @endif
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
