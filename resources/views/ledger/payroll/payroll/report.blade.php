@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/payroll/report: the month's payrolls with their figures, totals at the foot. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
        <div class="card-body">
            <form method="get" action="{{ route($route.'.report') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label" for="salary_type">@lang('ledger_payroll.field_salary_type')</label>
                        <select class="form-select" name="salary_type" id="salary_type"><option value="1">@lang('ledger_payroll.salary_type_fixed')</option></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="department">@lang('ledger_payroll.field_department')</label>
                        <select class="form-select" name="department" id="department">
                            <option value="">@lang('ledger_payroll.all')</option>
                            @foreach($departments as $d)<option value="{{ $d->id }}" @selected($selected_department == $d->id)>{{ $d->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="designation">@lang('ledger_payroll.field_designation')</label>
                        <select class="form-select" name="designation" id="designation">
                            <option value="">@lang('ledger_payroll.all')</option>
                            @foreach($designations as $d)<option value="{{ $d->id }}" @selected($selected_designation == $d->id)>{{ $d->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="month">@lang('ledger_payroll.field_month')</label>
                        <select class="form-select" name="month" id="month">
                            @for($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected($selected_month == $m)>{{ \Carbon\Carbon::createFromDate(2000, $m, 1)->translatedFormat('F') }}</option>@endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="year">@lang('ledger_payroll.field_year')</label>
                        <select class="form-select" name="year" id="year">
                            @for($y = date('Y'); $y >= date('Y') - 9; $y--)<option value="{{ $y }}" @selected($selected_year == $y)>{{ $y }}</option>@endfor
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-info"><i class="fas fa-search"></i> @lang('ledger_payroll.btn_filter')</button></div>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover ap-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('ledger_payroll.field_receipt')</th>
                            <th>@lang('ledger_payroll.field_staff_id')</th>
                            <th>@lang('ledger_payroll.field_name')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_basic_salary')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_total_earning')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_total_allowance')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_total_deduction')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_gross_salary')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_tax')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_employer_tax')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_net_salary')</th>
                            <th>@lang('ledger_payroll.field_status')</th>
                            <th>@lang('ledger_payroll.field_pay_date')</th>
                            <th>@lang('ledger_payroll.field_payment_method')</th>
                            <th>@lang('ledger_payroll.field_note')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td>{{ $print->prefix ?? '' }}{{ str_pad($row->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td>#{{ $row->staff->staff_no ?? '' }}</td>
                            <td>{{ $row->staff->name ?? '' }}</td>
                            <td class="ap-num">{{ $money($row->basic_salary) }} <small>/ @lang('ledger_payroll.salary_type_fixed')</small></td>
                            <td class="ap-num">{{ $money($row->total_earning) }}</td>
                            <td class="ap-num">{{ $money($row->total_allowance) }}</td>
                            <td class="ap-num">{{ $money($row->total_deduction) }}</td>
                            <td class="ap-num">{{ $money($row->gross_salary) }}</td>
                            <td class="ap-num">{{ $money($row->tax) }}</td>
                            <td class="ap-num">{{ $money($row->employer_tax) }}</td>
                            <td class="ap-num">{{ $money($row->net_salary) }}</td>
                            <td>@if($row->isPaid())<span class="badge bg-success">@lang('ledger_payroll.status_paid')</span>@else<span class="badge bg-danger">@lang('ledger_payroll.status_unpaid')</span>@endif</td>
                            <td>{{ $row->pay_date ? $row->pay_date->format('d/m/Y') : '' }}</td>
                            <td>{{ $row->payment_method ? ($methods[$row->payment_method] ?? $row->payment_method) : '' }}@if($row->paymentAccount)<br><small class="text-muted">{{ $row->paymentAccount->title }}</small>@endif</td>
                            <td>{{ $row->note }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="15" class="text-center text-muted">@lang('ledger_payroll.no_data')</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="ap-total-row">
                            <th></th><th></th><th>@lang('ledger_payroll.grand_total')</th><th></th>
                            <th class="ap-num">{{ $money($rows->sum('total_earning')) }}</th>
                            <th class="ap-num">{{ $money($rows->sum('total_allowance')) }}</th>
                            <th class="ap-num">{{ $money($rows->sum('total_deduction')) }}</th>
                            <th class="ap-num">{{ $money($rows->sum('gross_salary')) }}</th>
                            <th class="ap-num">{{ $money($rows->sum('tax')) }}</th>
                            <th class="ap-num">{{ $money($rows->sum('employer_tax')) }}</th>
                            <th class="ap-num">{{ $money($rows->sum('net_salary')) }}</th>
                            <th></th><th></th><th></th><th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
