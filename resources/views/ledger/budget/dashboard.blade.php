@extends('layouts.app')
@section('title', __('ledger_budget.budget_dashboard'))

@section('content')
@include('ledger.partials.style')
@php $M = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_budget.budget_dashboard')</h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-blue"><div class="inner"><h3 style="font-size: 26px;">{{ $M($kpis['total_budget']) }}</h3><p>@lang('ledger_budget.total_budget')</p>
                <p style="margin: 0;"><span class="badge">{{ $kpis['active_budgets_count'] }}</span> @lang('ledger_budget.active_budgets')</p></div>
                <div class="icon"><i class="fa fa-folder-open"></i></div></div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-aqua"><div class="inner"><h3 style="font-size: 26px;">{{ $M($kpis['total_allocated']) }}</h3><p>@lang('ledger_budget.allocated')</p>
                <p style="margin: 0;"><span class="badge">{{ number_format($kpis['allocation_rate'], 1) }}%</span> @lang('ledger_budget.allocation_rate')</p></div>
                <div class="icon"><i class="fa fa-list"></i></div></div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-yellow"><div class="inner"><h3 style="font-size: 26px;">{{ $M($kpis['total_spent']) }}</h3><p>@lang('ledger_budget.spent')</p>
                <p style="margin: 0;"><span class="badge">{{ number_format($kpis['utilization_rate'], 1) }}%</span> @lang('ledger_budget.utilization_rate')</p></div>
                <div class="icon"><i class="fa fa-money"></i></div></div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-green"><div class="inner"><h3 style="font-size: 26px;">{{ $M($kpis['total_remaining']) }}</h3><p>@lang('ledger_budget.remaining')</p>
                <p style="margin: 0;"><span class="badge">{{ number_format(100 - $kpis['utilization_rate'], 1) }}%</span> @lang('ledger_budget.available')</p></div>
                <div class="icon"><i class="fa fa-database"></i></div></div>
        </div>
    </div>

    {{-- The annual sheet on its own terms: folded into the figures above it would swamp every departmental average. --}}
    @isset($annualBudget)
    @php
        $annualUtil = (float) $annualBudget->total_amount > 0 ? round((float) $annualBudget->spent_amount / (float) $annualBudget->total_amount * 100, 1) : 0;
        $annualTone = $annualUtil > 100 ? 'text-danger' : ($annualUtil > 90 ? 'text-warning' : 'text-success');
    @endphp
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_budget.annual_budget') — {{ $annualBudget->title }}</h3>
            <div class="box-tools"><a href="{{ route('ledger.budget-sheet.show', $annualBudget->id) }}" class="btn btn-sm btn-primary">@lang('ledger_budget.open_the_sheet')</a></div>
        </div>
        <div class="box-body">
            <div class="row text-center">
                <div class="col-md-3"><h5 class="ap-muted">@lang('ledger_budget.status')</h5><h4>@include('ledger.budget.partials.status', ['status' => $annualBudget->status])</h4></div>
                <div class="col-md-3"><h5 class="ap-muted">@lang('ledger_budget.budgeted_spending')</h5><h4>{{ $M($annualBudget->total_amount) }}</h4></div>
                <div class="col-md-3"><h5 class="ap-muted">@lang('ledger_budget.actual_spending_card')</h5><h4>{{ $M($annualBudget->spent_amount) }}</h4></div>
                <div class="col-md-3"><h5 class="ap-muted">@lang('ledger_budget.field_utilization')</h5><h4 class="{{ $annualTone }}">{{ $annualUtil }}%</h4></div>
            </div>
            @if((float) $annualBudget->total_amount == 0)
                <p class="ap-muted" style="margin: 10px 0 0;">@lang('ledger_budget.no_figures_yet')</p>
            @endif
        </div>
    </div>
    @endisset

    @if(count($alerts) > 0)
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bell"></i> @lang('ledger_budget.budget_alerts')</h3></div>
        <div class="box-body">
            @foreach($alerts as $alert)
                <div class="alert alert-{{ $alert['type'] }}" role="alert" style="margin-bottom: 8px;">
                    <a href="{{ route('ledger.budget.show', $alert['budget_id']) }}" class="btn btn-xs btn-default pull-right">@lang('ledger_budget.view_budget')</a>
                    <i class="{{ $alert['icon'] }}"></i> <strong>{{ $alert['title'] }}:</strong> {{ $alert['message'] }}
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-md-6"><div class="box box-solid"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-pie-chart"></i> @lang('ledger_budget.budget_utilization')</h3></div>
            <div class="box-body"><div id="utilizationChart" style="height: 300px;"></div></div></div></div>
        <div class="col-md-6"><div class="box box-solid"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-pie-chart"></i> @lang('ledger_budget.department_spending')</h3></div>
            <div class="box-body"><div id="departmentChart" style="height: 300px;"></div></div></div></div>
    </div>
    <div class="box box-solid"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-line-chart"></i> @lang('ledger_budget.monthly_spending_trend')</h3></div>
        <div class="box-body"><div id="trendChart" style="height: 260px;"></div></div></div>
    <div class="box box-solid"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bar-chart"></i> @lang('ledger_budget.top_spending_categories')</h3></div>
        <div class="box-body"><div id="categoryChart" style="height: 260px;"></div></div></div>

    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-list"></i> @lang('ledger_budget.active_budgets')</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-hover ap-table">
                <thead><tr>
                    <th>@lang('ledger_budget.code')</th><th>@lang('ledger_budget.field_title')</th><th>@lang('ledger_budget.field_type')</th>
                    <th class="ap-num">@lang('ledger_budget.field_total_amount')</th><th class="ap-num">@lang('ledger_budget.spent')</th><th class="ap-num">@lang('ledger_budget.remaining')</th>
                    <th style="min-width: 140px;">@lang('ledger_budget.field_utilization')</th><th>@lang('ledger_budget.field_action')</th>
                </tr></thead>
                <tbody>
                    @forelse($activeBudgets as $budget)
                        @php
                            $u = (float) $budget->total_amount > 0 ? (float) $budget->spent_amount / (float) $budget->total_amount * 100 : 0;
                            $c = $u > 90 ? 'danger' : ($u > 75 ? 'warning' : 'success');
                        @endphp
                        <tr>
                            <td><strong>{{ $budget->budget_code }}</strong></td>
                            <td>{{ $budget->title }}</td>
                            <td><span class="ap-chip posted">{{ __('ledger_budget.type_'.$budget->type) }}</span></td>
                            <td class="ap-num">{{ $M($budget->total_amount) }}</td>
                            <td class="ap-num">{{ $M($budget->spent_amount) }}</td>
                            <td class="ap-num">{{ $M($budget->remaining_amount) }}</td>
                            <td>@include('ledger.budget.partials.progress', ['pct' => $u, 'color' => $c])</td>
                            <td><a href="{{ route('ledger.budget.show', $budget->id) }}" class="btn btn-xs btn-primary" title="{{ __('ledger_budget.btn_view') }}"><i class="fa fa-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center ap-muted">@lang('ledger_budget.no_active_budgets')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(function () {
    if (typeof Highcharts === 'undefined') { return; }
    var money = function (v) { return typeof __currency_trans_from_en === 'function' ? __currency_trans_from_en(v, false) : v; };
    var tip = { pointFormatter: function () { return '<b>' + money(this.y) + '</b>'; } };
    var U = {!! json_encode($utilizationData, JSON_HEX_TAG) !!};
    var D = {!! json_encode($departmentData, JSON_HEX_TAG) !!};
    var T = {!! json_encode($monthlyTrendData, JSON_HEX_TAG) !!};
    var C = {!! json_encode($categoryData, JSON_HEX_TAG) !!};
    var L = {!! json_encode(['allocated' => __('ledger_budget.allocated'), 'spent' => __('ledger_budget.spent')], JSON_HEX_TAG) !!};
    Highcharts.chart('utilizationChart', { chart: { type: 'pie' }, title: { text: null }, credits: { enabled: false }, tooltip: tip,
        plotOptions: { pie: { innerSize: '55%', dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.1f}%' } } },
        series: [{ name: '', data: U.labels.map(function (l, i) { return { name: l, y: U.values[i], color: U.colors[i] }; }) }] });
    Highcharts.chart('departmentChart', { chart: { type: 'pie' }, title: { text: null }, credits: { enabled: false }, tooltip: tip,
        plotOptions: { pie: { dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.1f}%' } } },
        series: [{ name: '', data: D.labels.map(function (l, i) { return { name: l, y: D.values[i] }; }) }] });
    Highcharts.chart('trendChart', { title: { text: null }, credits: { enabled: false }, tooltip: tip, legend: { enabled: false },
        xAxis: { categories: T.labels }, yAxis: { title: { text: null }, min: 0 },
        series: [{ type: 'line', name: L.spent, data: T.values }] });
    Highcharts.chart('categoryChart', { chart: { type: 'column' }, title: { text: null }, credits: { enabled: false }, tooltip: tip,
        xAxis: { categories: C.labels }, yAxis: { title: { text: null }, min: 0 },
        series: [{ name: L.allocated, data: C.allocated, color: '#36A2EB' }, { name: L.spent, data: C.spent, color: '#FF6384' }] });
});
</script>
@endsection
