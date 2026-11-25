@extends('layouts.app')
@section('title', __('ledger_budget.department_expenditure_report'))

@section('content')
@include('ledger.partials.style')
@php $M = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
@include('ledger.budget.partials.report_head', ['title' => __('ledger_budget.department_expenditure_report')])
<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            <form action="{{ route('ledger.budget.reports.department') }}" method="GET">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="fiscal_year">@lang('ledger_budget.fiscal_year')</label>
                        <select name="fiscal_year" id="fiscal_year" class="form-control">
                            <option value="">@lang('ledger_budget.all_years')</option>
                            @foreach($fiscalYears as $year)
                                <option value="{{ $year }}" {{ request('fiscal_year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="status">@lang('ledger_budget.status')</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">@lang('ledger_budget.all_statuses')</option>
                            @foreach(['active', 'closed'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ \App\Ledger\Models\Budget::statusLabel($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> @lang('ledger_budget.apply_filters')</button>
                <a href="{{ route('ledger.budget.reports.department') }}" class="btn btn-default"><i class="fa fa-refresh"></i> @lang('ledger_budget.reset')</a>
                <a href="{{ route('ledger.budget.reports.department', array_merge(request()->all(), ['export' => 'pdf'])) }}" class="btn btn-danger pull-right"><i class="fa fa-download"></i> @lang('ledger_budget.export_pdf')</a>
            </form>
        </div>
    </div>
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.department_comparison')</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-hover table-bordered ap-table">
                <thead>
                    <tr>
                        <th>@lang('ledger_budget.department')</th><th class="text-center">@lang('ledger_budget.budgets')</th>
                        <th class="ap-num">@lang('ledger_budget.total_budget')</th><th class="ap-num">@lang('ledger_budget.total_spent')</th>
                        <th class="ap-num">@lang('ledger_budget.total_remaining')</th><th style="min-width: 160px;">@lang('ledger_budget.utilization')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData as $row)
                        @php $c = $row['utilization_rate'] > 90 ? 'danger' : ($row['utilization_rate'] > 75 ? 'warning' : 'success'); @endphp
                        <tr>
                            <td><strong>{{ $row['department'] }}</strong></td>
                            <td class="text-center">{{ $row['budget_count'] }}</td>
                            <td class="ap-num">{{ $M($row['total_budget']) }}</td>
                            <td class="ap-num">{{ $M($row['total_spent']) }}</td>
                            <td class="ap-num">{{ $M($row['total_remaining']) }}</td>
                            <td>@include('ledger.budget.partials.progress', ['pct' => $row['utilization_rate'], 'color' => $c])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center ap-muted">@lang('ledger_budget.no_data_available')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
