@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  paxhitestbed admin/payroll/index: the filter card, then the month's staff with their payroll state
  (not generated, generated, awaiting approval, approved, paid) and the actions; the toolbar acts on
  the ticked staff (generate, approve, pay) and makes the bank file. Paying and unpaying go through
  the review dialog. E-Ride: paid from a payment account; monthly pay only.
--}}
@section('content')
@php
    $money = fn ($v) => \App\Ledger\Ledger::money($v);
    $bulk = session('payroll_bulk_result');
    $listQuery = ['month' => $selected_month, 'year' => $selected_year];
    $monthLabel = \Carbon\Carbon::createFromDate($selected_year, $selected_month, 1)->translatedFormat('F Y');
@endphp
<section class="content">
    @include('ledger.account.errors')
    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
        <div class="card-body">
            <form method="get" action="{{ route($route.'.index') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label" for="salary_type">@lang('ledger_payroll.field_salary_type') <span class="text-danger">*</span></label>
                        <select class="form-select" name="salary_type" id="salary_type" required>
                            <option value="1" selected>@lang('ledger_payroll.salary_type_fixed')</option>
                        </select>
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
                        <label class="form-label" for="month">@lang('ledger_payroll.field_month') <span class="text-danger">*</span></label>
                        <select class="form-select" name="month" id="month" required>
                            @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($selected_month == $m)>{{ \Carbon\Carbon::createFromDate(2000, $m, 1)->translatedFormat('F') }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="year">@lang('ledger_payroll.field_year') <span class="text-danger">*</span></label>
                        <select class="form-select" name="year" id="year" required>
                            @for($y = date('Y'); $y >= date('Y') - 9; $y--)<option value="{{ $y }}" @selected($selected_year == $y)>{{ $y }}</option>@endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-info"><i class="fas fa-search"></i> @lang('ledger_payroll.btn_filter')</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if($bulk)
            <div class="alert {{ empty($bulk['skipped']) ? 'alert-success' : 'alert-warning' }}">
                <strong>{{ count($bulk['done']) }} {{ $bulk['verb'] }}@if(count($bulk['skipped'])), {{ count($bulk['skipped']) }} @lang('ledger_payroll.skipped')@endif.</strong>
                @if(count($bulk['skipped']))
                <ul class="mb-0 mt-1">@foreach($bulk['skipped'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                @endif
                @foreach($bulk['notes'] as $note)<div class="mt-1"><small>{{ $note }}</small></div>@endforeach
            </div>
            @endif

            @can('payroll-action')
            <form id="payrollBulkForm" action="{{ route($route.'.bulk') }}" method="post" class="d-none">
                @csrf
                <input type="hidden" name="action" value="">
                <input type="hidden" name="month" value="{{ $selected_month }}">
                <input type="hidden" name="year" value="{{ $selected_year }}">
            </form>
            <form id="payrollBulkPayForm" action="{{ route($route.'.bulk-pay') }}" method="post" class="d-none"
                  data-review="@lang('ledger_payroll.review_bulk_pay')" data-review-label="@lang('ledger_payroll.pay_and_post')">
                @csrf
                <input type="hidden" name="month" value="{{ $selected_month }}">
                <input type="hidden" name="year" value="{{ $selected_year }}">
            </form>

            <div class="d-flex flex-wrap align-items-center mb-3" style="gap: 8px;">
                <button type="button" class="btn btn-sm btn-primary" data-needs-pick data-bulk-action="generate" data-confirm="@lang('ledger_payroll.confirm_generate_ticked')">
                    <i class="fas fa-plus"></i> @lang('ledger_payroll.generate_ticked')
                </button>
                @if($settings->require_approval)
                @can('payroll-approve')
                <button type="button" class="btn btn-sm btn-info" data-needs-pick data-bulk-action="approve" data-confirm="@lang('ledger_payroll.confirm_approve_ticked')">
                    <i class="fas fa-check-double"></i> @lang('ledger_payroll.approve_ticked')
                </button>
                @endcan
                @endif
                <button type="button" class="btn btn-sm btn-success" data-needs-pick data-bs-toggle="modal" data-bs-target="#payrollBulkPayModal">
                    <i class="fas fa-money-check"></i> @lang('ledger_payroll.pay_ticked')
                </button>
                <div class="dropdown d-inline-block">
                    <button class="btn btn-sm btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-file-csv"></i> @lang('ledger_payroll.bank_file')
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" data-bank-file href="{{ route($route.'.bank-file', $listQuery + ['which' => 'unpaid']) }}">@lang('ledger_payroll.bank_file_unpaid')</a>
                        <a class="dropdown-item" data-bank-file href="{{ route($route.'.bank-file', $listQuery + ['which' => 'paid']) }}">@lang('ledger_payroll.bank_file_paid')</a>
                        <a class="dropdown-item" data-bank-file href="{{ route($route.'.bank-file', $listQuery + ['which' => 'all']) }}">@lang('ledger_payroll.bank_file_all')</a>
                        <div class="dropdown-divider"></div>
                        <span class="dropdown-item-text small text-muted">@lang('ledger_payroll.bank_file_ticked')</span>
                    </div>
                </div>
                <small class="text-muted ms-2" id="payrollPickCount"></small>
            </div>

            <div class="modal fade" id="payrollBulkPayModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('ledger_payroll.pay_ticked_payrolls')</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('ledger_payroll.btn_close')"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">@lang('ledger_payroll.bulk_pay_help')</p>
                        @include('ledger.payroll.payroll.pay_fields', ['form' => 'payrollBulkPayForm', 'suffix' => 'bulk'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> @lang('ledger_payroll.btn_close')</button>
                        <button type="button" class="btn btn-success" data-bulk-pay><i class="fas fa-money-check"></i> @lang('ledger_payroll.pay_ticked')</button>
                    </div>
                </div></div>
            </div>
            @endcan

            <div class="table-responsive">
                <table class="table table-striped table-hover ap-table" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 28px;"><input type="checkbox" id="payrollPickAll" title="@lang('ledger_payroll.tick_all')"></th>
                            <th>@lang('ledger_payroll.field_staff_id')</th>
                            <th>@lang('ledger_payroll.field_name')</th>
                            <th>@lang('ledger_payroll.field_department')</th>
                            <th>@lang('ledger_payroll.field_designation')</th>
                            <th>@lang('ledger_payroll.field_salary_type')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_net_salary')</th>
                            <th>@lang('ledger_payroll.field_status')</th>
                            <th>@lang('ledger_payroll.field_action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        @php $payroll_data = $payrolls->get($row->id); @endphp
                        <tr>
                            <td><input type="checkbox" class="payroll-pick" name="staff[]" value="{{ $row->id }}"></td>
                            <td>@can('staff-edit')<a href="{{ route('ledger.staff.edit', $row->id) }}">#{{ $row->staff_no }}</a>@else #{{ $row->staff_no }} @endcan</td>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->department->title ?? '' }}</td>
                            <td>{{ $row->designation->title ?? '' }}</td>
                            <td>@lang('ledger_payroll.salary_type_fixed')</td>
                            <td class="ap-num">{{ $payroll_data ? $money($payroll_data->net_salary) : '' }}</td>
                            <td>
                                @if($payroll_data)
                                    @if($payroll_data->isPaid())
                                    <span class="badge bg-success">@lang('ledger_payroll.status_paid')</span>
                                    @elseif($settings->require_approval && $payroll_data->approved_at)
                                    <span class="badge bg-info text-dark" title="{{ \App\Ledger\Services\PaymentBook::userName($payroll_data->approver) }}">@lang('ledger_payroll.approved')</span>
                                    @elseif($settings->require_approval)
                                    <span class="badge bg-warning text-dark">@lang('ledger_payroll.awaiting_approval')</span>
                                    @else
                                    <span class="badge bg-primary">@lang('ledger_payroll.status_generated')</span>
                                    @endif
                                @else
                                <span class="badge bg-danger">@lang('ledger_payroll.status_not_generated')</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if(! $payroll_data)
                                    @can('payroll-action')
                                    <a href="{{ route($route.'.generate', ['id' => $row->id, 'month' => $selected_month, 'year' => $selected_year]) }}" class="btn btn-icon btn-primary btn-sm" title="@lang('ledger_payroll.btn_generate')"><i class="fas fa-plus"></i></a>
                                    @endcan
                                @elseif(! $payroll_data->isPaid())
                                    @if($settings->require_approval && ! $payroll_data->approved_at)
                                        @can('payroll-approve')
                                        <form action="{{ route($route.'.approve', $payroll_data->id) }}" method="post" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-check-double"></i> @lang('ledger_payroll.btn_approve')</button>
                                        </form>
                                        @endcan
                                    @else
                                        @can('payroll-action')
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payModal-{{ $row->id }}"><i class="fas fa-money-check"></i> @lang('ledger_payroll.btn_pay')</button>
                                        @include('ledger.payroll.payroll.pay')
                                        @endcan
                                    @endif
                                    @can('payroll-action')
                                    <a href="{{ route($route.'.generate', ['id' => $row->id, 'month' => $selected_month, 'year' => $selected_year]) }}" class="btn btn-icon btn-primary btn-sm" title="@lang('ledger_payroll.modal_edit')"><i class="far fa-edit"></i></a>
                                    @endcan
                                @else
                                    @can('payroll-print')
                                    <a href="{{ route($route.'.print', $payroll_data->id) }}" target="_blank" class="btn btn-icon btn-dark btn-sm" title="@lang('ledger_payroll.print_payslip')"><i class="fas fa-print"></i></a>
                                    <a href="{{ route($route.'.pdf', $payroll_data->id) }}" target="_blank" class="btn btn-icon btn-outline-dark btn-sm" title="@lang('ledger_payroll.payslip_pdf')"><i class="far fa-file-pdf"></i></a>
                                    @endcan
                                    @can('payroll-action')
                                    <button type="button" class="btn btn-icon btn-danger btn-sm" title="@lang('ledger_payroll.status_unpaid')" data-bs-toggle="modal" data-bs-target="#unpayModal-{{ $row->id }}"><i class="fas fa-undo"></i></button>
                                    <div class="modal fade" id="unpayModal-{{ $row->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                            <form action="{{ route($route.'.unpay', $payroll_data->id) }}" method="post"
                                                  data-review="@lang('ledger_payroll.review_unpay')" data-review-label="@lang('ledger_payroll.reverse_payment')" data-review-class="btn-danger">
                                                @csrf
                                                <div class="modal-content">
                                                    <div class="modal-body text-center">
                                                        <h5 class="modal-title">@lang('ledger_payroll.modal_are_you_sure')</h5>
                                                        <p class="text-danger mt-2">@lang('ledger_payroll.modal_undo_warning')</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> @lang('ledger_payroll.btn_close')</button>
                                                        <button type="submit" class="btn btn-danger"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_confirm')</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted">@lang('ledger_payroll.no_staff')
                            @can('staff-create') <a href="{{ route('ledger.staff.create') }}">@lang('ledger_payroll.btn_add_new')</a>@endcan</td></tr>
                        @endforelse
                    </tbody>
                    <caption>{{ $monthLabel }}</caption>
                </table>
            </div>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
(function () {
    var all = document.getElementById('payrollPickAll');
    var picks = function () { return Array.prototype.slice.call(document.querySelectorAll('.payroll-pick')); };
    var count = document.getElementById('payrollPickCount');
    var T = {!! json_encode(['ticked' => __('ledger_payroll.ticked'), 'hint' => __('ledger_payroll.tick_hint')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    var refresh = function () {
        var n = picks().filter(function (c) { return c.checked; }).length;
        if (count) { count.textContent = n ? n + ' ' + T.ticked : T.hint; }
        document.querySelectorAll('[data-needs-pick]').forEach(function (b) { b.disabled = n === 0; });
    };
    var fill = function (form) {
        form.querySelectorAll('input[name="staff[]"]').forEach(function (i) { i.remove(); });
        picks().filter(function (c) { return c.checked; }).forEach(function (c) {
            var i = document.createElement('input'); i.type = 'hidden'; i.name = 'staff[]'; i.value = c.value; form.appendChild(i);
        });
    };
    if (all) { all.addEventListener('change', function () { picks().forEach(function (c) { c.checked = all.checked; }); refresh(); }); }
    picks().forEach(function (c) { c.addEventListener('change', refresh); });
    document.querySelectorAll('[data-bulk-action]').forEach(function (b) {
        b.addEventListener('click', function () {
            var question = b.getAttribute('data-confirm');
            if (question && !confirm(question)) { return; }
            var form = document.getElementById('payrollBulkForm');
            form.querySelector('input[name="action"]').value = b.getAttribute('data-bulk-action');
            fill(form);
            HTMLFormElement.prototype.submit.call(form);
        });
    });
    document.querySelectorAll('[data-bulk-pay]').forEach(function (b) {
        b.addEventListener('click', function () {
            var form = document.getElementById('payrollBulkPayForm');
            fill(form);
            form.removeAttribute('data-reviewed');
            // paying posts money, so it goes through the review dialog (requestSubmit fires the submit event it listens for)
            if (form.requestSubmit) { form.requestSubmit(); } else { form.dispatchEvent(new Event('submit', {cancelable: true})); }
        });
    });
    document.querySelectorAll('[data-bank-file]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var url = new URL(a.getAttribute('href'), window.location.href);
            picks().filter(function (c) { return c.checked; }).forEach(function (c) { url.searchParams.append('staff[]', c.value); });
            window.location.href = url.toString();
        });
    });
    refresh();
})();
</script>
@endsection
