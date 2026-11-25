@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/staff-hourly-attendance/report-details: one staff member's month, session by session (task in place of the class). --}}
@section('content')
@php $letter = [1 => ['P', 'text-primary'], 2 => ['A', 'text-danger'], 3 => ['L', 'text-success'], 4 => ['H', 'text-warning']]; @endphp
<section class="content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $title }}: {{ $user->name }} <small class="text-muted">#{{ $user->staff_no }}</small></h5>
            <a href="{{ route($route.'.report', ['month' => $selected_month, 'year' => $selected_year]) }}" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_payroll.btn_back')</a>
        </div>
        <div class="card-body">
            <form method="get" action="{{ route($route.'.report.details', $user->id) }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label">@lang('ledger_payroll.field_month')</label>
                        <select class="form-select" name="month">@for($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected($selected_month == $m)>{{ \Carbon\Carbon::createFromDate(2000, $m, 1)->translatedFormat('F') }}</option>@endfor</select></div>
                    <div class="col-md-3"><label class="form-label">@lang('ledger_payroll.field_year')</label>
                        <select class="form-select" name="year">@for($y = date('Y'); $y >= date('Y') - 9; $y--)<option value="{{ $y }}" @selected($selected_year == $y)>{{ $y }}</option>@endfor</select></div>
                    <div class="col-md-3"><button type="submit" class="btn btn-info"><i class="fas fa-search"></i> @lang('ledger_payroll.btn_search')</button></div>
                </div>
            </form>
            <div class="ap-note mt-3">
                @lang('ledger_payroll.hours_present_desc', ['hours' => rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',') ?: '0', 'rate' => \App\Ledger\Ledger::money($user->basic_salary)])
                <strong>= {{ \App\Ledger\Ledger::money(round($hours * $user->basic_salary)) }} FCFA</strong>
                @if($without_times)<br><span class="text-warning">{{ trans_choice('ledger_payroll.note_without_times', $without_times, ['count' => $without_times]) }}</span>@endif
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped ap-table">
                    <thead><tr><th>@lang('ledger_payroll.field_date')</th><th>@lang('ledger_payroll.field_task')</th><th>@lang('ledger_payroll.field_start_time')</th><th>@lang('ledger_payroll.field_end_time')</th><th class="ap-num">@lang('ledger_payroll.hours')</th><th>@lang('ledger_payroll.field_attendance')</th><th>@lang('ledger_payroll.field_note')</th></tr></thead>
                    <tbody>
                        @forelse($attendances as $a)
                        <tr>
                            <td>{{ $a->date->translatedFormat('D d/m') }}</td>
                            <td>{{ $a->task }}</td>
                            <td>{{ $a->start_time ? substr($a->start_time, 0, 5) : '-' }}</td>
                            <td>{{ $a->end_time ? substr($a->end_time, 0, 5) : '-' }}</td>
                            <td class="ap-num">{{ $a->hours() !== null ? rtrim(rtrim(number_format($a->hours(), 2, ',', ''), '0'), ',') : '-' }}</td>
                            <td><span class="{{ $letter[$a->attendance][1] ?? '' }}">{{ $letter[$a->attendance][0] ?? '' }}</span></td>
                            <td>{{ $a->note }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted">@lang('ledger_payroll.no_data')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
