@extends('layouts.app')
@section('title', __('ledger_budget.cashflow_projection'))

@section('content')
@include('ledger.partials.style')
@php $M = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
@include('ledger.budget.partials.report_head', ['title' => __('ledger_budget.cashflow_projection')])
<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            <form action="{{ route('ledger.budget.reports.cashflow') }}" method="GET">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="budget_id">@lang('ledger_budget.budget')</label>
                        <select name="budget_id" id="budget_id" class="form-control" required>
                            <option value="">@lang('ledger_budget.select_budget')</option>
                            @foreach($allBudgets as $bdg)
                                <option value="{{ $bdg->id }}" {{ isset($budget) && $budget->id == $bdg->id ? 'selected' : '' }}>{{ $bdg->budget_code }} - {{ $bdg->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> @lang('ledger_budget.generate')</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(isset($budget) && isset($projections))
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-line-chart"></i> {{ $budget->budget_code }} - {{ $budget->title }}</h3>
            <div class="box-tools"><a href="{{ route('ledger.budget.reports.cashflow', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-sm btn-danger"><i class="fa fa-download"></i> @lang('ledger_budget.export_pdf')</a></div>
        </div>
        <div class="box-body table-responsive">
            <p class="ap-muted">@lang('ledger_budget.cashflow_note')</p>
            <table class="table table-hover table-bordered ap-table">
                <thead>
                    <tr>
                        <th>@lang('ledger_budget.month')</th><th class="ap-num">@lang('ledger_budget.planned_spending')</th><th class="ap-num">@lang('ledger_budget.actual_spending')</th>
                        <th class="ap-num">@lang('ledger_budget.variance')</th><th class="ap-num">@lang('ledger_budget.cumulative_planned')</th>
                        <th class="ap-num">@lang('ledger_budget.cumulative_actual')</th><th class="ap-num">@lang('ledger_budget.cumulative_variance')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($projections as $p)
                        @php $v = $p['planned'] - $p['actual']; $cv = $p['cumulative_planned'] - $p['cumulative_actual']; @endphp
                        <tr>
                            <td><strong>{{ $p['month'] }}</strong></td>
                            <td class="ap-num">{{ $M($p['planned']) }}</td>
                            <td class="ap-num">{{ $M($p['actual']) }}</td>
                            <td class="ap-num {{ $v >= 0 ? 'text-success' : 'text-danger' }}">{{ $M($v) }}</td>
                            <td class="ap-num">{{ $M($p['cumulative_planned']) }}</td>
                            <td class="ap-num">{{ $M($p['cumulative_actual']) }}</td>
                            <td class="ap-num {{ $cv >= 0 ? 'text-success' : 'text-danger' }}">{{ $M($cv) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div id="cashflowChart" style="height: 300px;"></div>
        </div>
    </div>
    @endif
</section>
@endsection

@section('javascript')
@if(isset($projections))
<script>
$(function () {
    if (typeof Highcharts === 'undefined') { return; }
    var P = {!! json_encode($projections, JSON_HEX_TAG) !!};
    var L = {!! json_encode(['planned' => __('ledger_budget.planned'), 'actual' => __('ledger_budget.actual')], JSON_HEX_TAG) !!};
    Highcharts.chart('cashflowChart', { title: { text: null }, credits: { enabled: false },
        xAxis: { categories: P.map(function (p) { return p.month; }) }, yAxis: { title: { text: null }, min: 0 },
        series: [
            { type: 'spline', name: L.planned, data: P.map(function (p) { return p.planned; }), color: '#36A2EB' },
            { type: 'spline', name: L.actual, data: P.map(function (p) { return p.actual; }), color: '#FF6384' }
        ] });
});
</script>
@endif
@endsection
