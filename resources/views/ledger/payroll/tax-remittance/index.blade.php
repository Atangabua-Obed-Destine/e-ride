@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  paxhitestbed admin/tax-remittance/index: what is held for others (the oldest month owing called
  out), the check against the ledger, what is owed by authority and month (with each month's taxes
  for the declaration form), "Record a payment", and the payments recorded (void and reverse).
--}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    @include('ledger.account.errors')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h5 class="mb-0">{{ $title }}</h5><small class="text-muted">@lang('ledger_payroll.remittance_intro')</small></div>
            @can('tax-remittance-create')
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#remitModal"><i class="fas fa-plus"></i> @lang('ledger_payroll.record_payment')</button>
            @endcan
        </div>
        <div class="card-body">
            @if($total_owing > 0)
            <div class="ap-note is-warn">
                <strong>@lang('ledger_payroll.held_for_others'): {{ $money($total_owing) }} FCFA</strong><br>
                @if($oldest_owing)@lang('ledger_payroll.oldest_undeclared') <strong>{{ $oldest_owing['month_label'] }}</strong>, @lang('ledger_payroll.owed_to') {{ $oldest_owing['account_code'] }} {{ $oldest_owing['account_name'] }} ({{ $money($oldest_owing['outstanding']) }}).@endif
                <br><small>@lang('ledger_payroll.held_help')</small>
            </div>
            @else
            <div class="ap-note">@lang('ledger_payroll.all_declared')</div>
            @endif
            @php $disagree = array_filter($reconciliation, fn ($r) => ! $r['agrees']); @endphp
            @if($disagree)
            <div class="ap-note is-bad mt-2">
                <strong>@lang('ledger_payroll.ledger_disagrees')</strong> @lang('ledger_payroll.ledger_disagrees_help')
                <ul class="mb-0">@foreach($disagree as $d)<li>{{ $d['account_code'] }} {{ $d['account_name'] }}: @lang('ledger_payroll.months_total') {{ $money($d['per_month']) }}, @lang('ledger_payroll.ledger_holds') {{ $money($d['ledger']) }}, @lang('ledger_payroll.difference') {{ $money($d['difference']) }}</li>@endforeach</ul>
            </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.owed_by_authority')</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover ap-table">
                    <thead><tr><th>@lang('ledger_payroll.authority')</th><th>@lang('ledger_payroll.field_salary_month')</th><th class="ap-num">@lang('ledger_payroll.from_staff')</th><th class="ap-num">@lang('ledger_payroll.employer')</th><th class="ap-num">@lang('ledger_payroll.total_withheld')</th><th class="ap-num">@lang('ledger_payroll.paid_over')</th><th class="ap-num">@lang('ledger_payroll.outstanding')</th><th>@lang('ledger_payroll.field_status')</th><th></th></tr></thead>
                    <tbody>
                        @forelse($rows as $i => $row)
                        @php $parts = $breakdowns[$row['account_id'].'|'.$row['month']] ?? []; @endphp
                        <tr>
                            <td>{{ $row['account_code'] }} {{ $row['account_name'] }}</td>
                            <td>{{ $row['month_label'] }}</td>
                            <td class="ap-num">{{ $money($row['employee']) }}</td>
                            <td class="ap-num">{{ $money($row['employer']) }}</td>
                            <td class="ap-num">{{ $money($row['due']) }}@if(! $row['itemised'])<br><small class="text-muted">@lang('ledger_payroll.no_breakdown')</small>@endif</td>
                            <td class="ap-num">{{ $money($row['paid']) }}</td>
                            <td class="ap-num"><strong>{{ $money($row['outstanding']) }}</strong></td>
                            <td><span class="ap-chip {{ ['undeclared' => 'unpaid', 'part_paid' => 'part_paid', 'overpaid' => 'overdue', 'settled' => 'paid'][$row['status']] }}">@lang('ledger_payroll.remit_'.$row['status'])</span></td>
                            <td class="text-nowrap">
                                @if($parts)<button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#parts-{{ $i }}">@lang('ledger_payroll.details')</button>@endif
                                @can('tax-remittance-create')
                                @if($row['outstanding'] > 0.005)
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#remitModal"
                                        data-remit-account="{{ $row['account_id'] }}" data-remit-month="{{ $row['month'] }}" data-remit-amount="{{ round($row['outstanding']) }}">@lang('ledger_payroll.btn_pay')</button>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @if($parts)
                        <tr class="collapse" id="parts-{{ $i }}"><td colspan="9">
                            <table class="table table-sm mb-0"><tbody>
                                @foreach($parts as $p)<tr><td>{{ $p['label'] }}</td><td class="ap-num">@lang('ledger_payroll.staff_side') {{ $money($p['employee']) }}</td><td class="ap-num">@lang('ledger_payroll.employer_side') {{ $money($p['employer']) }}</td><td class="ap-num"><strong>{{ $money($p['total']) }}</strong></td><td>{{ $p['staff_count'] }} @lang('ledger_payroll.staff_lc')</td></tr>@endforeach
                            </tbody></table>
                        </td></tr>
                        @endif
                        @empty
                        <tr><td colspan="9" class="text-center text-muted">@lang('ledger_payroll.nothing_withheld')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.payments_recorded')</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped ap-table">
                    <thead><tr><th>@lang('ledger_payroll.paid_on')</th><th>@lang('ledger_payroll.authority')</th><th>@lang('ledger_payroll.for_salary_month')</th><th class="ap-num">@lang('ledger_payroll.field_amount')</th><th>@lang('ledger_payroll.field_paid_from')</th><th>@lang('ledger_payroll.receipt')</th><th>@lang('ledger_payroll.recorded_by')</th><th></th></tr></thead>
                    <tbody>
                        @forelse($remittances as $r)
                        <tr class="{{ $r->isVoided() ? 'text-muted' : '' }}">
                            <td>{{ $r->payment_date->format('d/m/Y') }}</td>
                            <td>{{ $r->liabilityAccount->account_code ?? '' }} {{ $r->liabilityAccount ? \App\Ledger\Services\Preview::accountName($r->liabilityAccount) : '' }}</td>
                            <td>{{ $r->salary_month->translatedFormat('F Y') }}</td>
                            <td class="ap-num">@if($r->isVoided())<s>{{ $money($r->amount) }}</s>@else{{ $money($r->amount) }}@endif</td>
                            <td>{{ $r->paymentAccount->title ?? '' }}</td>
                            <td>{{ $r->reference }}</td>
                            <td>{{ \App\Ledger\Services\PaymentBook::userName($r->creator) }}</td>
                            <td>
                                @if($r->isVoided())
                                <span class="ap-chip void">@lang('ledger_payroll.voided')</span>@if($r->void_reason)<br><small>{{ $r->void_reason }}</small>@endif
                                @else
                                @can('tax-remittance-void')
                                <form action="{{ route($route.'.void', $r->id) }}" method="post" class="d-inline"
                                      data-review="@lang('ledger_payroll.review_remit_void')" data-review-label="@lang('ledger_payroll.void_and_reverse')" data-review-class="btn-danger" data-review-reason="1">
                                    @csrf
                                    <input type="hidden" name="void_reason" value="">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">@lang('ledger_payroll.void')</button>
                                </form>
                                @endcan
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted">@lang('ledger_payroll.nothing_paid_over')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @can('tax-remittance-create')
    <div class="modal fade" id="remitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route($route.'.store') }}" method="post" id="remitForm"
                  data-review="@lang('ledger_payroll.review_remit')" data-review-label="@lang('ledger_payroll.record_and_post')">
                @csrf
                <div class="modal-header"><h5 class="modal-title">@lang('ledger_payroll.record_payment_authority')</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="liability_account_id">@lang('ledger_payroll.authority') <span class="text-danger">*</span></label>
                        <select class="form-select" name="liability_account_id" id="liability_account_id" required><option value="">@lang('ledger_payroll.select')</option>
                            @foreach($authorities as $a)<option value="{{ $a->id }}">{{ $a->account_code }} - {{ \App\Ledger\Services\Preview::accountName($a) }}</option>@endforeach</select></div>
                    <div class="mb-3"><label class="form-label" for="salary_month">@lang('ledger_payroll.salary_month_covers') <span class="text-danger">*</span></label>
                        <select class="form-select" name="salary_month" id="salary_month" required><option value="">@lang('ledger_payroll.select')</option>
                            @foreach(collect($rows)->pluck('month_label', 'month')->unique() as $m => $label)<option value="{{ $m }}">{{ $label }}</option>@endforeach</select>
                        <small class="text-muted">@lang('ledger_payroll.salary_month_help')</small></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label" for="amount">@lang('ledger_payroll.field_amount') (FCFA) <span class="text-danger">*</span></label><input type="number" class="form-control" name="amount" id="amount" min="1" step="1" required></div>
                        <div class="col-6 mb-3"><label class="form-label" for="payment_date">@lang('ledger_payroll.date_paid') <span class="text-danger">*</span></label><input type="date" class="form-control" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="payment_account_id">@lang('ledger_payroll.field_paid_from') <span class="text-danger">*</span></label>
                        <select class="form-select" name="payment_account_id" id="payment_account_id" required><option value="">@lang('ledger_payroll.select')</option>
                            @foreach($payment_accounts as $a)<option value="{{ $a->id }}">{{ $a->title }} ({{ $money($a->current_balance) }})</option>@endforeach</select>
                        <small class="text-muted">@lang('ledger_payroll.remit_from_help')</small></div>
                    <div class="mb-3"><label class="form-label" for="reference">@lang('ledger_payroll.receipt_number')</label><input type="text" class="form-control" name="reference" id="reference" maxlength="191" placeholder="@lang('ledger_payroll.as_issued')"></div>
                    <div class="mb-3"><label class="form-label" for="note">@lang('ledger_payroll.field_note')</label><textarea class="form-control" name="note" id="note" rows="2"></textarea></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_overpayment" value="1" id="allow_overpayment"><label class="form-check-label" for="allow_overpayment">@lang('ledger_payroll.allow_overpayment')</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="allow_additional" value="1" id="allow_additional"><label class="form-check-label" for="allow_additional">@lang('ledger_payroll.allow_additional')</label></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">@lang('ledger_payroll.btn_close')</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.record_and_post')</button>
                </div>
            </form>
        </div></div>
    </div>
    @endcan
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
document.querySelectorAll('[data-remit-account]').forEach(function (b) {
    b.addEventListener('click', function () {
        document.getElementById('liability_account_id').value = b.getAttribute('data-remit-account');
        document.getElementById('salary_month').value = b.getAttribute('data-remit-month');
        document.getElementById('amount').value = b.getAttribute('data-remit-amount');
    });
});
</script>
@endsection
