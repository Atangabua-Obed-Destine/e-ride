@extends('layouts.app')
@section('title', __('ledger_budget.budget_vs_actual'))

@section('content')
@include('ledger.partials.style')
@php $M = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_budget.budget_vs_actual') <small>(@lang('ledger_budget.budget_realise'))</small></h1>
</section>
<section class="content">
    @include('ledger.budget.partials.messages')
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_budget.budget_vs_actual')</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.accounting-reports.budget-vs-actual', array_merge(request()->all(), ['export' => 'excel'])) }}" class="btn btn-success btn-sm"><i class="fa fa-file-excel-o"></i> @lang('ledger_budget.export')</a>
            </div>
        </div>
        <div class="box-body">
            {{-- Which days these figures actually cover, said on the page. --}}
            @foreach($periodNotices as $notice)
                <div class="ap-note is-warn" style="margin-bottom: 8px;"><i class="fa fa-calendar"></i> {{ $notice }}</div>
            @endforeach
            <p class="ap-muted">@lang('ledger_budget.bva_measured', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y')])
                @if($budget) — <a href="{{ route('ledger.budget-sheet.show', $budget->id) }}">{{ $budget->title }}</a>@endif</p>

            <form action="{{ route('ledger.accounting-reports.budget-vs-actual') }}" method="GET" style="margin-bottom: 16px;">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label for="fiscal_year_id">@lang('ledger_budget.fiscal_year')</label>
                        <select name="fiscal_year_id" id="fiscal_year_id" class="form-control input-sm">
                            @foreach($fiscalYears as $year)
                                <option value="{{ $year->id }}" {{ $fiscalYear->id == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="period">@lang('ledger_budget.period')</label>
                        <select name="period" id="period" class="form-control input-sm">
                            <option value="ytd" {{ request('period', 'ytd') == 'ytd' ? 'selected' : '' }}>@lang('ledger_budget.year_to_date')</option>
                            <option value="monthly" {{ request('period') == 'monthly' ? 'selected' : '' }}>@lang('ledger_budget.monthly')</option>
                            <option value="quarterly" {{ request('period') == 'quarterly' ? 'selected' : '' }}>@lang('ledger_budget.quarterly')</option>
                            <option value="full" {{ request('period') == 'full' ? 'selected' : '' }}>@lang('ledger_budget.whole_period')</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="month">@lang('ledger_budget.month')</label>
                        <input type="month" name="month" id="month" class="form-control input-sm" value="{{ request('month', date('Y-m')) }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-refresh"></i> @lang('ledger_budget.generate')</button>
                    </div>
                </div>
            </form>

            <div class="row">
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-file-text-o"></i></span><div class="info-box-content">
                    <span class="info-box-text">@lang('ledger_budget.total_budget')</span><span class="info-box-number">{{ $M($totals['budget']) }}</span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-money"></i></span><div class="info-box-content">
                    <span class="info-box-text">@lang('ledger_budget.total_actual')</span><span class="info-box-number">{{ $M($totals['actual']) }}</span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-balance-scale"></i></span><div class="info-box-content">
                    <span class="info-box-text">@lang('ledger_budget.variance')</span><span class="info-box-number">{{ $M($totals['variance']) }}</span></div></div></div>
                <div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-red"><i class="fa fa-percent"></i></span><div class="info-box-content">
                    <span class="info-box-text">@lang('ledger_budget.variance_percentage')</span><span class="info-box-number">{{ number_format($totals['variance_pct'], 1) }}%</span></div></div></div>
            </div>
            <p class="ap-muted">@lang('ledger_budget.bva_boxes_note')</p>

            @foreach([['revenue', $revenue, $revenueTotals, 'total_revenue', 'fa-arrow-up', true], ['expenses', $expenses, $expenseTotals, 'total_expenses', 'fa-arrow-down', false], ['capital_expenditure', $capital, $capitalTotals, 'total_capital', 'fa-building', false]] as [$key, $items, $sum, $totalKey, $icon, $incomeSide])
                @if($key !== 'capital_expenditure' || count($items))
                <h4><i class="fa {{ $icon }}"></i> {{ __('ledger_budget.'.$key) }}</h4>
                <div class="table-responsive">
                    <table class="table table-condensed table-hover ap-table">
                        <thead>
                            <tr>
                                <th>@lang('ledger_budget.account')</th><th class="ap-num">@lang('ledger_budget.budget')</th><th class="ap-num">@lang('ledger_budget.actual')</th>
                                <th class="ap-num">@lang('ledger_budget.variance')</th><th class="ap-num">@lang('ledger_budget.percentage')</th><th width="15%">@lang('ledger_budget.progress')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                @php
                                    $good = $incomeSide ? $item['variance'] >= 0 : $item['variance'] <= 0;
                                    $pct = min(100, max(0, $item['achievement_pct']));
                                    $over = ! $incomeSide && $item['budget'] > 0 && $item['actual'] > $item['budget'];
                                @endphp
                                <tr class="{{ $over && ! $item['is_header'] ? 'danger' : '' }}" style="{{ $item['is_header'] ? 'font-weight: 600; background: #eef1f6;' : '' }}">
                                    <td style="{{ $item['is_header'] ? '' : 'padding-left: 22px;' }}"><strong>{{ $item['account_code'] }}</strong> - {{ $item['account_name'] }}</td>
                                    <td class="ap-num">{{ $M($item['budget']) }}</td>
                                    <td class="ap-num">{{ $M($item['actual']) }}</td>
                                    <td class="ap-num {{ $good ? 'text-success' : 'text-danger' }}">{{ $item['variance'] >= 0 ? '+' : '' }}{{ $M($item['variance']) }}</td>
                                    <td class="ap-num">{{ number_format($item['variance_pct'], 1) }}%</td>
                                    <td>@if($item['budget'] > 0)@include('ledger.budget.partials.progress', ['pct' => $item['achievement_pct'], 'color' => $incomeSide ? ($pct >= 100 ? 'success' : 'warning') : ($item['achievement_pct'] > 100 ? 'danger' : ($item['achievement_pct'] > 90 ? 'warning' : 'success'))])@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center ap-muted">@lang('ledger_budget.no_data_found')</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="ap-total-row">
                                <td>{{ __('ledger_budget.'.$totalKey) }}</td>
                                <td class="ap-num">{{ $M($sum['budget']) }}</td>
                                <td class="ap-num">{{ $M($sum['actual']) }}</td>
                                <td class="ap-num">{{ $M($sum['variance']) }}</td>
                                <td class="ap-num">{{ number_format($sum['variance_pct'], 1) }}%</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif
            @endforeach

            <h4><i class="fa fa-calculator"></i> @lang('ledger_budget.net_result')</h4>
            @php
                $budNet = $revenueTotals['budget'] - $expenseTotals['budget'];
                $actNet = $revenueTotals['actual'] - $expenseTotals['actual'];
                $netVariance = $actNet - $budNet;
            @endphp
            <table class="table table-bordered ap-table" style="max-width: 640px;">
                <tr><td width="50%">@lang('ledger_budget.budgeted_net_income')</td><td class="ap-num">{{ $M($budNet) }}</td></tr>
                <tr><td>@lang('ledger_budget.actual_net_income')</td><td class="ap-num">{{ $M($actNet) }}</td></tr>
                <tr class="ap-total-row"><td>@lang('ledger_budget.variance')</td>
                    <td class="ap-num {{ $netVariance >= 0 ? 'text-success' : 'text-danger' }}">{{ $netVariance >= 0 ? '+' : '' }}{{ $M($netVariance) }}</td></tr>
            </table>
        </div>
    </div>
</section>
@endsection
