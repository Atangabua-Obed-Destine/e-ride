@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  paxhitestbed admin/staff-tax-report/index (Tax Distribution Report): filters, the summary cards,
  the per-staff table, salary bands and the last six months of payrolls; PDF and Excel. It
  recomputes under the rules in force on the chosen date (what-if), with the payroll's own engine.
--}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); $q = request()->query(); @endphp
<section class="content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $title }}</h5>
            <div>
                <a href="{{ route('ledger.staff-tax-report.pdf', $q) }}" class="btn btn-sm btn-danger"><i class="far fa-file-pdf"></i> PDF</a>
                <a href="{{ route('ledger.staff-tax-report.excel', $q) }}" class="btn btn-sm btn-success"><i class="far fa-file-excel"></i> Excel</a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" action="{{ route('ledger.staff-tax-report.index') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2"><label class="form-label">@lang('ledger_payroll.field_department')</label>
                        <select class="form-select" name="department_id"><option value="">@lang('ledger_payroll.all')</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected($selected_department == $d->id)>{{ $d->title }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label">@lang('ledger_payroll.field_designation')</label>
                        <select class="form-select" name="designation_id"><option value="">@lang('ledger_payroll.all')</option>@foreach($designations as $d)<option value="{{ $d->id }}" @selected($selected_designation == $d->id)>{{ $d->title }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label">@lang('ledger_payroll.salary_min')</label><input type="number" class="form-control" name="salary_min" value="{{ $selected_salary_min }}"></div>
                    <div class="col-md-2"><label class="form-label">@lang('ledger_payroll.salary_max')</label><input type="number" class="form-control" name="salary_max" value="{{ $selected_salary_max }}"></div>
                    <div class="col-md-2"><label class="form-label">@lang('ledger_payroll.effective_date')</label><input type="date" class="form-control" name="effective_date" value="{{ $effective_date }}"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-info"><i class="fas fa-search"></i> @lang('ledger_payroll.btn_filter')</button></div>
                </div>
            </form>
            <p class="ap-muted mt-2 mb-0"><i class="fas fa-info-circle"></i> @lang('ledger_payroll.report_recomputes')</p>
        </div>
    </div>

    <div class="row">
        @foreach([['staff', $staff_count, false], ['field_basic_salary', $grand_totals['basic_salary'], true], ['field_employee_tax', $grand_totals['employee_tax_total'], true],
            ['field_employer_tax', $grand_totals['employer_tax_total'], true], ['field_net_salary', $grand_totals['net_salary'], true], ['field_total_cost', $grand_totals['total_cost'], true]] as [$label, $value, $isMoney])
        <div class="col-md-2 col-6"><div class="ap-card text-center"><div class="ap-muted">@lang('ledger_payroll.'.$label)</div><div class="fs-5 fw-bold">{{ $isMoney ? $money($value) : $value }}</div></div></div>
        @endforeach
    </div>
    <div class="ap-card"><small>@lang('ledger_payroll.effective_rates', ['avg' => $avg_effective_rate, 'min' => $min_effective_rate, 'max' => $max_effective_rate])</small></div>

    <div class="card"><div class="card-body"><div class="table-responsive">@include('ledger.payroll.staff-tax-report.table')</div></div></div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card"><div class="card-header"><h6 class="mb-0">@lang('ledger_payroll.salary_bands')</h6></div><div class="card-body">
                <table class="table table-sm ap-table"><thead><tr><th>@lang('ledger_payroll.band')</th><th class="ap-num">@lang('ledger_payroll.staff')</th><th class="ap-num">@lang('ledger_payroll.field_basic_salary')</th><th class="ap-num">@lang('ledger_payroll.field_employee_tax')</th></tr></thead>
                    <tbody>@foreach($salary_bands as $label => $b)<tr><td>{{ $label }}</td><td class="ap-num">{{ $b['count'] }}</td><td class="ap-num">{{ $money($b['total_salary']) }}</td><td class="ap-num">{{ $money($b['total_tax']) }}</td></tr>@endforeach</tbody></table>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card"><div class="card-header"><h6 class="mb-0">@lang('ledger_payroll.last_six_months')</h6></div><div class="card-body">
                <table class="table table-sm ap-table"><thead><tr><th>@lang('ledger_payroll.field_month')</th><th class="ap-num">@lang('ledger_payroll.payrolls')</th><th class="ap-num">@lang('ledger_payroll.field_gross_salary')</th><th class="ap-num">@lang('ledger_payroll.field_tax')</th><th class="ap-num">@lang('ledger_payroll.field_employer_tax')</th><th class="ap-num">@lang('ledger_payroll.field_net_salary')</th></tr></thead>
                    <tbody>@foreach($historical_data as $h)<tr><td>{{ $h['label'] }}</td><td class="ap-num">{{ $h['count'] }}</td><td class="ap-num">{{ $money($h['total_gross']) }}</td><td class="ap-num">{{ $money($h['total_tax']) }}</td><td class="ap-num">{{ $money($h['employer_tax']) }}</td><td class="ap-num">{{ $money($h['total_net']) }}</td></tr>@endforeach</tbody></table>
            </div></div>
        </div>
    </div>
</section>
@endsection
