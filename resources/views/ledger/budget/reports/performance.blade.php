@extends('layouts.app')
@section('title', __('ledger_budget.budget_performance_report'))

@section('content')
@include('ledger.partials.style')
@php $M = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
@include('ledger.budget.partials.report_head', ['title' => __('ledger_budget.budget_performance_report')])
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> @lang('ledger_budget.filters')</h3></div>
        <div class="box-body">
            <form action="{{ route('ledger.budget.reports.performance') }}" method="GET">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label for="budget_id">@lang('ledger_budget.budget')</label>
                        <select name="budget_id" id="budget_id" class="form-control">
                            <option value="">@lang('ledger_budget.all_budgets')</option>
                            @foreach($allBudgets as $b)
                                <option value="{{ $b->id }}" {{ request('budget_id') == $b->id ? 'selected' : '' }}>{{ $b->budget_code }} - {{ $b->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="status">@lang('ledger_budget.status')</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">@lang('ledger_budget.all_statuses')</option>
                            @foreach(['active', 'approved', 'closed'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ \App\Ledger\Models\Budget::statusLabel($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="budget_type">@lang('ledger_budget.budget_type')</label>
                        <select name="budget_type" id="budget_type" class="form-control">
                            <option value="">@lang('ledger_budget.all_types')</option>
                            @foreach(['annual', 'departmental', 'project'] as $t)
                                <option value="{{ $t }}" {{ request('budget_type') == $t ? 'selected' : '' }}>{{ __('ledger_budget.type_'.$t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="fiscal_year">@lang('ledger_budget.fiscal_year')</label>
                        <select name="fiscal_year" id="fiscal_year" class="form-control">
                            <option value="">@lang('ledger_budget.all_years')</option>
                            @foreach($fiscalYears as $year)
                                <option value="{{ $year }}" {{ request('fiscal_year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> @lang('ledger_budget.apply_filters')</button>
                <a href="{{ route('ledger.budget.reports.performance') }}" class="btn btn-default"><i class="fa fa-refresh"></i> @lang('ledger_budget.reset')</a>
                <a href="{{ route('ledger.budget.reports.performance', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-danger pull-right"><i class="fa fa-download"></i> @lang('ledger_budget.export_pdf')</a>
            </form>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-bar-chart"></i> @lang('ledger_budget.performance_analysis')</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-hover table-bordered ap-table">
                <thead>
                    <tr>
                        <th>@lang('ledger_budget.budget_code')</th><th>@lang('ledger_budget.budget_title')</th><th>@lang('ledger_budget.category')</th>
                        <th class="ap-num">@lang('ledger_budget.allocated')</th><th class="ap-num">@lang('ledger_budget.spent')</th><th class="ap-num">@lang('ledger_budget.remaining')</th>
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
                            <td class="ap-num">{{ $M($row['remaining']) }}</td>
                            <td class="ap-num {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $M($row['variance']) }}</td>
                            <td class="ap-num {{ $row['variance'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row['variance_percent'], 1) }}%</td>
                            <td class="text-center"><span class="ap-chip {{ $row['status'] == 'favorable' ? 'paid' : 'unpaid' }}">@lang('ledger_budget.'.$row['status'])</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center ap-muted">@lang('ledger_budget.no_data_available')</td></tr>
                    @endforelse
                </tbody>
                @if(count($reportData) > 0)
                <tfoot>
                    <tr class="ap-total-row">
                        <td colspan="3" class="text-right">@lang('ledger_budget.total')</td>
                        <td class="ap-num">{{ $M(collect($reportData)->sum('allocated')) }}</td>
                        <td class="ap-num">{{ $M(collect($reportData)->sum('spent')) }}</td>
                        <td class="ap-num">{{ $M(collect($reportData)->sum('remaining')) }}</td>
                        <td class="ap-num">{{ $M(collect($reportData)->sum('variance')) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
            <p class="ap-muted">@lang('ledger_budget.spent_from_ledger')</p>
        </div>
    </div>
</section>
@endsection
