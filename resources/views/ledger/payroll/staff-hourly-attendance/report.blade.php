@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/staff-hourly-attendance/report: the month, staff by day (P / A / L / H), the totals, the hours and the attendance rate. --}}
@section('content')
@php
    $letter = [1 => ['P', 'text-primary'], 2 => ['A', 'text-danger'], 3 => ['L', 'text-success'], 4 => ['H', 'text-warning']];
    $hoursOf = fn ($list) => round($list->where('attendance', 1)->sum(fn ($a) => $a->hours() ?? 0), 2);
@endphp
<section class="content">
    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
        <div class="card-body">
            <form method="get" action="{{ route($route.'.report') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label">@lang('ledger_payroll.field_department')</label>
                        <select class="form-select" name="department"><option value="">@lang('ledger_payroll.all')</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected($selected_department == $d->id)>{{ $d->title }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">@lang('ledger_payroll.field_designation')</label>
                        <select class="form-select" name="designation"><option value="">@lang('ledger_payroll.all')</option>@foreach($designations as $d)<option value="{{ $d->id }}" @selected($selected_designation == $d->id)>{{ $d->title }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label">@lang('ledger_payroll.field_month') <span class="text-danger">*</span></label>
                        <select class="form-select" name="month">@for($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected($selected_month == $m)>{{ \Carbon\Carbon::createFromDate(2000, $m, 1)->translatedFormat('F') }}</option>@endfor</select></div>
                    <div class="col-md-2"><label class="form-label">@lang('ledger_payroll.field_year') <span class="text-danger">*</span></label>
                        <select class="form-select" name="year">@for($y = date('Y'); $y >= date('Y') - 9; $y--)<option value="{{ $y }}" @selected($selected_year == $y)>{{ $y }}</option>@endfor</select></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-info"><i class="fas fa-search"></i> @lang('ledger_payroll.btn_search')</button></div>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="small">@lang('ledger_payroll.attendance_present'): <span class="text-primary">P</span> | @lang('ledger_payroll.attendance_absent'): <span class="text-danger">A</span> | @lang('ledger_payroll.attendance_leave'): <span class="text-success">L</span> | @lang('ledger_payroll.attendance_holiday'): <span class="text-warning">H</span></p>
            <div class="table-responsive">
                <table class="table table-bordered table-sm ap-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger_payroll.field_staff_id')</th>
                            <th>@lang('ledger_payroll.field_name')</th>
                            @for($d = 1; $d <= $days; $d++)<th class="text-center">{{ $d }}</th>@endfor
                            <th>P</th><th>A</th><th>L</th><th>H</th><th>%</th><th class="ap-num">@lang('ledger_payroll.hours')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        @php $mine = $attendances->get($row->id, collect()); $total = max(1, $mine->count()); @endphp
                        <tr>
                            <td>#<a href="{{ route($route.'.report.details', ['id' => $row->id, 'month' => $selected_month, 'year' => $selected_year]) }}">{{ $row->staff_no }}</a></td>
                            <td>{{ $row->name }}</td>
                            @for($d = 1; $d <= $days; $d++)
                            <td class="text-center">@foreach($mine->filter(fn ($a) => $a->date->day === $d) as $a)<div class="{{ $letter[$a->attendance][1] ?? '' }}">{{ $letter[$a->attendance][0] ?? '' }}</div>@endforeach</td>
                            @endfor
                            <td>{{ $mine->where('attendance', 1)->count() }}</td>
                            <td>{{ $mine->where('attendance', 2)->count() }}</td>
                            <td>{{ $mine->where('attendance', 3)->count() }}</td>
                            <td>{{ $mine->where('attendance', 4)->count() }}</td>
                            <td>{{ $mine->count() ? round($mine->whereIn('attendance', [1, 4])->count() / $total * 100) : 0 }}</td>
                            <td class="ap-num">{{ rtrim(rtrim(number_format($hoursOf($mine), 2, ',', ''), '0'), ',') ?: '0' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="{{ $days + 8 }}" class="text-center text-muted">@lang('ledger_payroll.no_hourly_staff')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
