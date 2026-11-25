@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  paxhitestbed admin/staff-hourly-attendance/index: choose the staff member and the date, then the
  day's sessions with Present / Absent / Leave / Holiday (and "all" of each), the times and a note.
  E-Ride: no timetable, so each session says what it was (task) and is added by hand.
--}}
@section('content')
@php $codes = [1 => 'attendance_present', 2 => 'attendance_absent', 3 => 'attendance_leave', 4 => 'attendance_holiday']; @endphp
<section class="content">
    @include('ledger.account.errors')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $title }}</h5>
            @can('staff-hourly-attendance-report')<a href="{{ route($route.'.report') }}" class="btn btn-light btn-sm"><i class="fas fa-chart-bar"></i> @lang('ledger_payroll.hourly_report')</a>@endcan
        </div>
        <div class="card-body">
            <form method="get" action="{{ route($route.'.index') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="staff">@lang('ledger_payroll.field_staff_id') <span class="text-danger">*</span></label>
                        <select class="form-select" name="staff" id="staff" required>
                            <option value="">@lang('ledger_payroll.select')</option>
                            @foreach($users as $u)<option value="{{ $u->id }}" @selected((string) $selected_staff === (string) $u->id)>{{ $u->staff_no }} - {{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="date">@lang('ledger_payroll.field_date') <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date" id="date" value="{{ $selected_date }}" required>
                    </div>
                    <div class="col-md-3"><button type="submit" class="btn btn-info"><i class="fas fa-search"></i> @lang('ledger_payroll.btn_search')</button></div>
                </div>
            </form>
            @if($users->isEmpty())
            <p class="ap-muted mt-2 mb-0"><i class="fas fa-info-circle"></i> @lang('ledger_payroll.no_hourly_staff')</p>
            @endif
        </div>
    </div>

    @if($staff)
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
            <div>
                <h5 class="mb-0">{{ $staff->name }} <small class="text-muted">#{{ $staff->staff_no }} · {{ \Carbon\Carbon::parse($selected_date)->translatedFormat('l d F Y') }}</small></h5>
                <small class="{{ $attendances->isEmpty() ? 'text-danger' : 'text-success' }}">{{ $attendances->isEmpty() ? __('ledger_payroll.attendance_not_taken') : __('ledger_payroll.attendance_taken') }}</small>
                · <small class="text-muted">@lang('ledger_payroll.rate_per_hour', ['rate' => \App\Ledger\Ledger::money($staff->basic_salary)])</small>
            </div>
            @unless($locked)
            <div class="d-flex flex-wrap" style="gap: 12px;">
                @foreach($codes as $code => $label)
                <div class="form-check"><input class="form-check-input all-check" type="radio" name="all_check" id="all-{{ $code }}" value="{{ $code }}"><label class="form-check-label" for="all-{{ $code }}">@lang('ledger_payroll.all') @lang('ledger_payroll.'.$label)</label></div>
                @endforeach
            </div>
            @endunless
        </div>
        <div class="card-body">
            @if($locked)<div class="alert alert-warning">@lang('ledger_payroll.err_hours_locked')</div>@endif
            <form action="{{ route($route.'.store') }}" method="post" id="hourlyForm">
                @csrf
                <input type="hidden" name="staff_id" value="{{ $staff->id }}">
                <input type="hidden" name="date" value="{{ $selected_date }}">
                <div class="table-responsive">
                    <table class="table table-striped ap-table" id="sessionTable">
                        <thead>
                            <tr>
                                <th>@lang('ledger_payroll.field_task')</th>
                                <th>@lang('ledger_payroll.field_attendance')</th>
                                <th>@lang('ledger_payroll.field_note')</th>
                                <th>@lang('ledger_payroll.field_start_time')</th>
                                <th>@lang('ledger_payroll.field_end_time')</th>
                                <th class="ap-num">@lang('ledger_payroll.hours')</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attendances->isEmpty() ? [null] : $attendances as $i => $a)
                            <tr class="session-row">
                                <td><input type="text" class="form-control form-control-sm" name="tasks[{{ $i }}]" value="{{ $a->task ?? '' }}" maxlength="191" placeholder="@lang('ledger_payroll.task_eg')"></td>
                                <td class="text-nowrap">
                                    @foreach($codes as $code => $label)
                                    <div class="form-check form-check-inline"><input class="form-check-input att-radio" type="radio" name="attendance[{{ $i }}]" value="{{ $code }}" id="att-{{ $i }}-{{ $code }}" @checked(($a->attendance ?? 1) == $code)><label class="form-check-label" for="att-{{ $i }}-{{ $code }}">@lang('ledger_payroll.'.$label)</label></div>
                                    @endforeach
                                </td>
                                <td><input type="text" class="form-control form-control-sm" name="notes[{{ $i }}]" value="{{ $a->note ?? '' }}" maxlength="500" style="min-width: 100px;"></td>
                                <td><input type="time" class="form-control form-control-sm" name="start_time[{{ $i }}]" value="{{ $a && $a->start_time ? substr($a->start_time, 0, 5) : '' }}"></td>
                                <td><input type="time" class="form-control form-control-sm" name="end_time[{{ $i }}]" value="{{ $a && $a->end_time ? substr($a->end_time, 0, 5) : '' }}"></td>
                                <td class="ap-num">{{ $a && $a->hours() !== null ? rtrim(rtrim(number_format($a->hours(), 2, ',', ''), '0'), ',') : '-' }}</td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-session" title="@lang('ledger_payroll.remove')"><i class="fas fa-trash-alt"></i></button></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @unless($locked)
                <div class="d-flex" style="gap: 8px;">
                    <button type="button" class="btn btn-outline-primary" id="addSession"><i class="fas fa-plus"></i> @lang('ledger_payroll.add_session')</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_update')</button>
                </div>
                @endunless
            </form>
        </div>
    </div>
    @endif
</section>
@endsection

@section('javascript')
<script>
(function () {
    var table = document.getElementById('sessionTable');
    if (!table) { return; }
    var body = table.querySelector('tbody');
    var next = body.querySelectorAll('tr').length + 100;
    document.getElementById('addSession') && document.getElementById('addSession').addEventListener('click', function () {
        var first = body.querySelector('tr');
        var row = first.cloneNode(true);
        var i = next++;
        row.querySelectorAll('input').forEach(function (inp) {
            inp.name = inp.name.replace(/\[\d+\]/, '[' + i + ']');
            if (inp.id) { inp.id = inp.id.replace(/att-\d+-/, 'att-' + i + '-'); }
            if (inp.type === 'radio') { inp.checked = inp.value === '1'; } else { inp.value = ''; }
        });
        row.querySelectorAll('label[for]').forEach(function (l) { l.htmlFor = l.htmlFor.replace(/att-\d+-/, 'att-' + i + '-'); });
        row.querySelector('td.ap-num').textContent = '-';
        body.appendChild(row);
    });
    body.addEventListener('click', function (e) {
        var b = e.target.closest('.remove-session');
        if (!b) { return; }
        if (body.querySelectorAll('tr').length > 1) { b.closest('tr').remove(); } else { b.closest('tr').querySelectorAll('input[type=text], input[type=time]').forEach(function (i) { i.value = ''; }); }
    });
    document.querySelectorAll('.all-check').forEach(function (r) {
        r.addEventListener('change', function () {
            body.querySelectorAll('.att-radio[value="' + r.value + '"]').forEach(function (x) { x.checked = true; });
        });
    });
})();
</script>
@endsection
