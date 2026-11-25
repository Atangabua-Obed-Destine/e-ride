@extends('layouts.app')
@section('title', __('ledger_budget.budget_variance_report'))

@section('content')
@include('ledger.partials.style')
@php $M = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
@include('ledger.budget.partials.report_head', ['title' => __('ledger_budget.budget_variance_report')])
<section class="content">
    <div class="row">
        <div class="col-md-3"><div class="small-box bg-gray"><div class="inner"><h3>{{ $summary['total_allocations'] }}</h3><p>@lang('ledger_budget.total_allocations')</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-green"><div class="inner"><h3>{{ $summary['favorable_count'] }}</h3><p>@lang('ledger_budget.favorable')</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-red"><div class="inner"><h3>{{ $summary['unfavorable_count'] }}</h3><p>@lang('ledger_budget.unfavorable')</p></div></div></div>
        <div class="col-md-3"><div class="small-box bg-blue"><div class="inner"><h3 style="font-size: 26px;">{{ $M($summary['total_variance']) }}</h3><p>@lang('ledger_budget.total_variance')</p></div></div></div>
    </div>
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> @lang('ledger_budget.filters')</h3></div>
        <div class="box-body">
            <form action="{{ route('ledger.budget.reports.variance') }}" method="GET">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="budget_id">@lang('ledger_budget.budget')</label>
                        <select name="budget_id" id="budget_id" class="form-control">
                            <option value="">@lang('ledger_budget.all_budgets')</option>
                            @foreach($allBudgets as $b)
                                <option value="{{ $b->id }}" {{ request('budget_id') == $b->id ? 'selected' : '' }}>{{ $b->budget_code }} - {{ $b->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="variance_type">@lang('ledger_budget.variance_type')</label>
                        <select name="variance_type" id="variance_type" class="form-control">
                            <option value="">@lang('ledger_budget.all_types')</option>
                            <option value="favorable" {{ request('variance_type') == 'favorable' ? 'selected' : '' }}>@lang('ledger_budget.favorable')</option>
                            <option value="unfavorable" {{ request('variance_type') == 'unfavorable' ? 'selected' : '' }}>@lang('ledger_budget.unfavorable')</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> @lang('ledger_budget.apply_filters')</button>
                <a href="{{ route('ledger.budget.reports.variance') }}" class="btn btn-default"><i class="fa fa-refresh"></i> @lang('ledger_budget.reset')</a>
                <a href="{{ route('ledger.budget.reports.variance', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-danger pull-right"><i class="fa fa-download"></i> @lang('ledger_budget.export_pdf')</a>
            </form>
        </div>
    </div>
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-line-chart"></i> @lang('ledger_budget.variance_analysis')</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-hover table-bordered ap-table">
                <thead>
                    <tr>
                        <th>@lang('ledger_budget.budget_code')</th><th>@lang('ledger_budget.budget_title')</th><th>@lang('ledger_budget.category')</th>
                        <th class="ap-num">@lang('ledger_budget.allocated')</th><th class="ap-num">@lang('ledger_budget.spent')</th>
                        <th class="ap-num">@lang('ledger_budget.variance')</th><th class="ap-num">@lang('ledger_budget.variance_percent')</th><th class="text-center">@lang('ledger_budget.status')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData as $row)
                        <tr>
                            <td><strong>{{ $row['budget_code'] }}</strong></td>
                            <td>{{ $row['budget_title'] }}</td>
                            <td>{{ $row['category'] }}</td>
                            <td class="ap-num">{{ $M($row['allocated']) }}</td>
                            <td class="ap-num">{{ $M($row['spent']) }}</td>
                            <td class="ap-num {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}"><strong>{{ $M($row['variance']) }}</strong></td>
                            <td class="ap-num {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}"><strong>{{ number_format($row['variance_percent'], 1) }}%</strong></td>
                            <td class="text-center"><span class="ap-chip {{ $row['status'] == 'favorable' ? 'paid' : 'unpaid' }}"><i class="fa {{ $row['status'] == 'favorable' ? 'fa-check' : 'fa-times' }}"></i> @lang('ledger_budget.'.$row['status'])</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center ap-muted">@lang('ledger_budget.no_data_available')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
