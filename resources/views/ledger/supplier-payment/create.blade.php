@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  Paying a supplier (paxhitestbed admin/supplier-payment/create): choose the supplier, type how
  much of the payment goes to each unpaid bill (oldest due first), where it is paid from, then the
  review shows the ledger entry before anything is recorded.
--}}
@section('content')
@php
    $money = fn ($v) => \App\Ledger\Ledger::money($v);
    $oldAlloc = old('allocations', []);
    $totalOwed = round($bills->sum('owed'), 2);
    $today = now()->toDateString();
@endphp
@include('ledger.account.errors')
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">{{ $title }}</h5></div>
    <div class="card-body">
        <a href="{{ route($route.'.index') }}" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
        <form method="get" action="{{ route($route.'.create') }}" class="ap-filters mt-3">
            <div class="form-group" style="min-width:320px">
                <label for="pick_supplier">@lang('ledger_accounts.supplier') <span class="text-danger">*</span></label>
                <select name="supplier_id" id="pick_supplier" class="form-control" onchange="this.form.submit()">
                    <option value="">@lang('ledger_accounts.select')</option>
                    @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(optional($supplier)->id == $s->id)>{{ $s->title }}</option>@endforeach
                </select>
            </div>
            @if($suppliers->isEmpty())<div class="ap-muted">@lang('ledger_accounts.no_supplier_owed')</div>@endif
        </form>
    </div>
</div>
@if($supplier)
<form method="post" action="{{ route($route.'.store') }}" class="card" id="pay-form"
      data-review="@lang('ledger_accounts.check_payment')" data-review-label="@lang('ledger_accounts.record_and_post')">
    @csrf
    <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
    <div class="card-body">
        <h6>@lang('ledger_accounts.unpaid_bills_of') {{ $supplier->title }}</h6>
        <p class="ap-muted">@lang('ledger_accounts.alloc_hint')</p>
        <div class="table-responsive">
            <table class="table ap-table" id="alloc-table">
                <thead><tr><th>@lang('ledger_accounts.bill')</th><th>@lang('ledger_accounts.their_invoice')</th><th>@lang('ledger_accounts.bill_date')</th><th>@lang('ledger_accounts.due')</th>
                    <th class="ap-num">@lang('ledger_accounts.total')</th><th class="ap-num">@lang('ledger_accounts.owed')</th><th class="ap-num" style="width:190px">@lang('ledger_accounts.pay_now')</th></tr></thead>
                <tbody>
                    @foreach($bills as $b)
                    @php $value = $oldAlloc[$b->id] ?? ($preselect_bill === (int) $b->id ? (int) round($b->owed) : ''); @endphp
                    <tr>
                        <td><a href="{{ route('ledger.supplier-bill.show', $b->id) }}" target="_blank">{{ $b->number }}</a>
                            @if($b->kind === 'opening_balance')<div class="ap-muted">@lang('ledger_accounts.opening_balance')</div>@endif</td>
                        <td>{{ $b->supplier_reference ?? '-' }}</td>
                        <td>{{ \Carbon\Carbon::parse($b->bill_date)->format('d/m/Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($b->due_date)->format('d/m/Y') }} @if($b->due_date < $today)<span class="ap-chip overdue">@lang('ledger_accounts.overdue')</span>@endif</td>
                        <td class="ap-num">{{ $money($b->total) }}</td>
                        <td class="ap-num">{{ $money($b->owed) }}</td>
                        <td><input type="number" step="1" min="0" max="{{ $b->owed }}" data-owed="{{ $b->owed }}" name="allocations[{{ $b->id }}]" class="form-control ap-num alloc" value="{{ $value }}"></td>
                    </tr>
                    @endforeach
                    <tr class="ap-total-row">
                        <td colspan="5" class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="pay-all">@lang('ledger_accounts.pay_everything')</button>
                            <button type="button" class="btn btn-sm btn-light" id="pay-none">@lang('ledger_accounts.clear')</button>
                        </td>
                        <td class="ap-num">{{ $money($totalOwed) }}</td>
                        <td class="ap-num"><span id="alloc-total">0</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="row mt-2">
            <div class="form-group col-md-3 mb-3">
                <label for="amount">@lang('ledger_accounts.amount_paid') <span class="text-danger">*</span></label>
                <input type="number" step="1" min="1" class="form-control ap-num" name="amount" id="amount" required readonly value="{{ old('amount') }}">
                <div class="ap-muted mt-1">@lang('ledger_accounts.sum_of_bills')</div>
            </div>
            <div class="form-group col-md-3 mb-3">
                <label for="payment_date">@lang('ledger_accounts.payment_date') <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="payment_date" id="payment_date" required max="{{ $today }}" value="{{ old('payment_date', $today) }}">
            </div>
            <div class="form-group col-md-3 mb-3">
                <label for="payment_method">@lang('ledger_accounts.field_payment_method')</label>
                <select name="payment_method" id="payment_method" class="form-control">
                    @foreach($methods as $k => $label)
                    <option value="{{ $k }}" data-account="{{ $method_accounts[$k] ?? '' }}" @selected(old('payment_method', 'momo') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3 mb-3">
                <label for="reference">@lang('ledger_accounts.cheque_or_transfer')</label>
                <input type="text" class="form-control" name="reference" id="reference" maxlength="100" value="{{ old('reference') }}">
            </div>
            @if($payment_accounts->isNotEmpty())
            <div class="form-group col-md-4 mb-3">
                <label for="payment_account_id">@lang('ledger_accounts.cash_box_or_bank')</label>
                <select name="payment_account_id" id="payment_account_id" class="form-control">
                    <option value="">@lang('ledger_accounts.not_tracked_there')</option>
                    @foreach($payment_accounts as $pa)
                    <option value="{{ $pa->id }}" data-account="{{ $pa->chart_of_account_id }}" @selected(old('payment_account_id') == $pa->id)>{{ $pa->title }} (@lang('ledger_accounts.field_balance') {{ $money($pa->current_balance) }})</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="form-group col-md-4 mb-3">
                <label for="cash_account_id">@lang('ledger_accounts.paid_from_ledger') <span class="text-danger">*</span></label>
                <select name="cash_account_id" id="cash_account_id" class="form-control" required>
                    <option value="">@lang('ledger_accounts.select')</option>
                    @foreach($cash_accounts as $acc)
                    <option value="{{ $acc->id }}" @selected(old('cash_account_id') == $acc->id)>{{ \App\Ledger\Services\PaymentBook::accountLabel($acc) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-4 mb-3">
                <label for="note">@lang('ledger_accounts.field_note')</label>
                <input type="text" class="form-control" name="note" id="note" maxlength="2000" value="{{ old('note') }}">
            </div>
        </div>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-success"><i class="fas fa-search-dollar"></i> @lang('ledger_accounts.review_the_payment')</button>
        <span class="ap-muted ms-2">@lang('ledger_accounts.review_payment_hint')</span>
    </div>
</form>
@endif
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
(function () {
    var form = document.getElementById('pay-form');
    if (!form) { return; }
    var fmt = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
    var inputs = form.querySelectorAll('.alloc');
    function sum() {
        var s = 0;
        inputs.forEach(function (i) {
            var owed = parseFloat(i.dataset.owed) || 0, v = parseFloat(i.value) || 0;
            i.classList.toggle('is-invalid', v > owed + 0.005);
            s += v;
        });
        s = Math.round(s);
        document.getElementById('alloc-total').textContent = fmt.format(s);
        document.getElementById('amount').value = s > 0 ? s : '';
    }
    inputs.forEach(function (i) { i.addEventListener('input', sum); });
    document.getElementById('pay-all').addEventListener('click', function () { inputs.forEach(function (i) { i.value = Math.round(i.dataset.owed); }); sum(); });
    document.getElementById('pay-none').addEventListener('click', function () { inputs.forEach(function (i) { i.value = ''; }); sum(); });
    sum();
    // the method and the payment account each point at their usual ledger account
    var cash = document.getElementById('cash_account_id');
    function preselect(el) {
        var opt = el.options[el.selectedIndex];
        var acc = opt ? opt.getAttribute('data-account') : '';
        if (acc) { cash.value = acc; }
    }
    var method = document.getElementById('payment_method');
    method.addEventListener('change', function () { preselect(method); });
    var box = document.getElementById('payment_account_id');
    if (box) {
        box.addEventListener('change', function () {
            preselect(box);
            cash.style.pointerEvents = box.value ? 'none' : '';
            cash.style.background = box.value ? '#f1f5f9' : '';
        });
    }
    if (!cash.value) { preselect(method); }
})();
</script>
@endsection
