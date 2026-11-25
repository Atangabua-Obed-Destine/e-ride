@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  paxhitestbed admin/staff-loan/index: "Record a loan or advance" beside the list (Being repaid,
  Repaid, Cancelled, All). Giving and cancelling go through the review dialog. E-Ride: the money
  leaves a payment account, chosen here (the Payroll settings' account by default).
--}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    @include('ledger.account.errors')
    <div class="row">
        @can('staff-loan-action')
        <div class="col-md-4">
            <form action="{{ route($route.'.store') }}" method="post"
                  data-review="@lang('ledger_payroll.review_loan')" data-review-label="@lang('ledger_payroll.give_and_post')">
                @csrf
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.record_loan')</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="staff_id" class="form-label">@lang('ledger_payroll.staff_member') <span class="text-danger">*</span></label>
                            <select class="form-select" name="staff_id" id="staff_id" required>
                                <option value="">@lang('ledger_payroll.select')</option>
                                @foreach($staff as $s)<option value="{{ $s->id }}" @selected(old('staff_id') == $s->id)>{{ $s->name }} ({{ $s->staff_no }})</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="kind" class="form-label">@lang('ledger_payroll.type') <span class="text-danger">*</span></label>
                            <select class="form-select" name="kind" id="kind" required>
                                @foreach($kinds as $k)<option value="{{ $k }}" @selected(old('kind') === $k)>@lang('ledger_payroll.kind_'.$k)</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="title" class="form-label">@lang('ledger_payroll.name_on_payslip')</label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" maxlength="100" placeholder="@lang('ledger_payroll.loan_title_eg')">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label for="amount" class="form-label">@lang('ledger_payroll.amount_lent') (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="amount" id="amount" value="{{ old('amount') }}" min="1" step="1" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="instalment" class="form-label">@lang('ledger_payroll.repaid_each_month') (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="instalment" id="instalment" value="{{ old('instalment') }}" min="1" step="1" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="given_on" class="form-label">@lang('ledger_payroll.given_on') <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="given_on" id="given_on" value="{{ old('given_on', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="start_month" class="form-label">@lang('ledger_payroll.first_repayment') <span class="text-danger">*</span></label>
                                <input type="month" class="form-control" name="start_month" id="start_month" value="{{ old('start_month', now()->addMonthNoOverflow()->format('Y-m')) }}" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="payment_account_id" class="form-label">@lang('ledger_payroll.field_paid_from') <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_account_id" id="payment_account_id" required>
                                <option value="">@lang('ledger_payroll.select')</option>
                                @foreach($payment_accounts as $a)<option value="{{ $a->id }}" @selected(old('payment_account_id', $settings->advance_payment_account_id) == $a->id)>{{ $a->title }} ({{ $money($a->current_balance) }})</option>@endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label">@lang('ledger_payroll.field_note')</label>
                            <textarea class="form-control" name="note" id="note" rows="2" maxlength="500">{{ old('note') }}</textarea>
                        </div>
                        <small class="text-muted d-block" id="loanPlan"></small>
                        <small class="text-success d-block mt-1"><i class="fas fa-check-circle"></i> @lang('ledger_payroll.loan_in_accounts')</small>
                    </div>
                    <div class="card-footer"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_save')</button></div>
                </div>
            </form>
        </div>
        @endcan
        <div class="col-md-{{ auth()->user()->can('staff-loan-action') ? '8' : '12' }}">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
                <div class="card-body">
                    <ul class="nav nav-pills mb-3">
                        @foreach(['open' => 'being_repaid', 'repaid' => 'repaid', 'cancelled' => 'cancelled', 'all' => 'all'] as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $selected_status === $key ? 'active' : '' }}" href="{{ route($route.'.index', ['status' => $key]) }}">@lang('ledger_payroll.'.$label)@if($key !== 'all') ({{ $counts[$key] ?? 0 }})@endif</a></li>
                        @endforeach
                    </ul>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover ap-table">
                            <thead>
                                <tr>
                                    <th>@lang('ledger_payroll.staff')</th>
                                    <th>@lang('ledger_payroll.loan')</th>
                                    <th class="ap-num">@lang('ledger_payroll.lent')</th>
                                    <th class="ap-num">@lang('ledger_payroll.each_month')</th>
                                    <th class="ap-num">@lang('ledger_payroll.repaid')</th>
                                    <th class="ap-num">@lang('ledger_payroll.still_owed')</th>
                                    <th>@lang('ledger_payroll.field_status')</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $loan)
                                @php $state = $states->get((string) $loan->id); @endphp
                                <tr>
                                    <td>{{ $loan->staff->name ?? '' }}<br><small class="text-muted">#{{ $loan->staff->staff_no ?? '' }}</small></td>
                                    <td>{{ $loan->title }}<br><small class="text-muted">@lang('ledger_payroll.given') {{ $loan->given_on->format('d/m/Y') }} · @lang('ledger_payroll.from') {{ $loan->start_month->translatedFormat('M Y') }}@if($loan->paymentAccount) · {{ $loan->paymentAccount->title }}@endif</small></td>
                                    <td class="ap-num">{{ $money($loan->amount) }}</td>
                                    <td class="ap-num">{{ $money($loan->instalment) }}</td>
                                    <td class="ap-num">{{ $money($loan->repaid_amount) }}@if($loan->pending_amount > 0)<br><small class="text-muted">+{{ $money($loan->pending_amount) }} @lang('ledger_payroll.on_unpaid_payrolls')</small>@endif</td>
                                    <td class="ap-num"><strong>{{ $money($loan->balance_amount) }}</strong></td>
                                    <td>
                                        @if($loan->state === 'open')<span class="badge bg-primary">@lang('ledger_payroll.being_repaid')</span>
                                        @elseif($loan->state === 'repaid')<span class="badge bg-success">@lang('ledger_payroll.repaid')</span>
                                        @else<span class="badge bg-secondary">@lang('ledger_payroll.cancelled')</span>@endif
                                        @if($state && $state->status === 'posted')<br><small class="text-muted">@lang('ledger_payroll.in_the_accounts')</small>
                                        @elseif($state && $state->status === 'failed')<br><small class="text-danger" title="{{ $state->error }}">@lang('ledger_payroll.not_posted')</small>@endif
                                    </td>
                                    <td>
                                        @can('staff-loan-action')
                                        @if($loan->status === 'active' && ! $loan->has_lines)
                                        <form action="{{ route($route.'.cancel', $loan->id) }}" method="post" class="d-inline"
                                              data-review="@lang('ledger_payroll.review_loan_cancel')" data-review-label="@lang('ledger_payroll.cancel_loan')" data-review-class="btn-danger">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="@lang('ledger_payroll.cancel_loan')"><i class="fas fa-times"></i></button>
                                        </form>
                                        @endif
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="8" class="text-center text-muted">@lang('ledger_payroll.no_loans')</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
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
    var amount = document.getElementById('amount'), inst = document.getElementById('instalment'), plan = document.getElementById('loanPlan');
    if (!amount || !inst || !plan) { return; }
    var T = {!! json_encode(['one' => __('ledger_payroll.repaid_in_one'), 'over' => __('ledger_payroll.repaid_over')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    var refresh = function () {
        var a = parseFloat(amount.value) || 0, i = parseFloat(inst.value) || 0;
        if (a <= 0 || i <= 0) { plan.textContent = ''; return; }
        var months = Math.ceil(a / i), last = a - (months - 1) * i;
        plan.textContent = months === 1 ? T.one : T.over.replace(':months', months).replace(':last', Math.round(last).toLocaleString('fr-FR'));
    };
    amount.addEventListener('input', refresh); inst.addEventListener('input', refresh); refresh();
})();
</script>
@endsection
