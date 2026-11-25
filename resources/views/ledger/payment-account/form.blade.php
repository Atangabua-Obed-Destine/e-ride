@extends('layouts.app')
@section('title', $payment_account ? __('ledger_accounts.edit_payment_account') : __('ledger_accounts.add_payment_account'))
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  Adding or editing a payment account (paxhitestbed admin/payment-account/create and edit).
  E-Ride: the ledger account is required; an opening balance may name the other side of its entry
  (preset to the opening-balance account), and then it posts, through the review.
--}}
@section('content')
@php $a = $payment_account; @endphp
<section class="content">
    <div class="row"><div class="col-md-8 offset-md-2">
        @include('ledger.account.errors')
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0" style="font-size:1.1rem">{{ $a ? __('ledger_accounts.edit_payment_account').': '.$a->title : __('ledger_accounts.add_payment_account') }}</h3>
                <a href="{{ route('ledger.payment-account.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
            </div>
            <form method="POST" action="{{ $a ? route('ledger.payment-account.update', $a->id) : route('ledger.payment-account.store') }}"
                  @unless($a) data-review="@lang('ledger_accounts.check_account')" data-review-label="@lang('ledger_accounts.confirm_and_post')" @endunless>
                @csrf
                @if($a) @method('PUT') @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title">@lang('ledger_accounts.account_title') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $a->title ?? '') }}" required maxlength="255">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="account_type_id">@lang('ledger_accounts.account_type') <span class="text-danger">*</span></label>
                            <select class="form-control" id="account_type_id" name="account_type_id" required>
                                <option value="">@lang('ledger_accounts.select')</option>
                                @foreach($account_types as $t)
                                <option value="{{ $t->id }}" data-kind="{{ $t->kind }}" @selected(old('account_type_id', $a->account_type_id ?? '') == $t->id)>{{ $t->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="account_number">@lang('ledger_accounts.account_number')</label>
                            <input type="text" class="form-control" id="account_number" name="account_number" value="{{ old('account_number', $a->account_number ?? '') }}" maxlength="255">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="chart_of_account_id">@lang('ledger_accounts.ledger_account') <span class="text-danger">*</span></label>
                            <select class="form-control" id="chart_of_account_id" name="chart_of_account_id" required>
                                <option value="">@lang('ledger_accounts.select')</option>
                                @foreach($ledger_accounts as $l)
                                <option value="{{ $l->id }}" data-code="{{ $l->account_code }}" @selected(old('chart_of_account_id', $a->chart_of_account_id ?? '') == $l->id)>{{ \App\Ledger\Services\PaymentBook::accountLabel($l) }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">@lang('ledger_accounts.ledger_account_help')</small>
                        </div>
                        @unless($a)
                        <div class="col-md-6 mb-3">
                            <label for="opening_balance">@lang('ledger_accounts.opening_balance') <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="0" class="form-control" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', 0) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contra_account_id">@lang('ledger_accounts.other_side')</label>
                            <select class="form-control" id="contra_account_id" name="contra_account_id">
                                <option value="">@lang('ledger_accounts.not_posted_option')</option>
                                @foreach($contra_accounts as $c)
                                <option value="{{ $c->id }}" @selected((string) old('contra_account_id', $opening_contra) === (string) $c->id)>{{ \App\Ledger\Services\PaymentBook::accountLabel($c) }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">@lang('ledger_accounts.other_side_opening_help')</small>
                        </div>
                        @endunless
                        <div class="col-md-6 mb-3">
                            <label for="status">@lang('ledger_accounts.status')</label>
                            <select class="form-control" id="status" name="status">
                                <option value="1" @selected((string) old('status', isset($a) ? (int) $a->status : 1) === '1')>@lang('ledger_accounts.status_active')</option>
                                <option value="0" @selected((string) old('status', isset($a) ? (int) $a->status : 1) === '0')>@lang('ledger_accounts.status_inactive')</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="description">@lang('ledger_accounts.description')</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $a->description ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> @lang('ledger_accounts.btn_save')</button></div>
            </form>
        </div>
    </div></div>
</section>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
(function () {
    // a kind of account preselects its usual ledger account (cash 57x, bank 52x, mobile money 552x) when none is chosen
    var type = document.getElementById('account_type_id'), coa = document.getElementById('chart_of_account_id');
    var prefix = { cash: '57', bank: '52', mobile_money: '552' };
    type.addEventListener('change', function () {
        if (coa.value) { return; }
        var p = prefix[(type.selectedOptions[0] || {}).getAttribute && type.selectedOptions[0].getAttribute('data-kind')];
        if (!p) { return; }
        for (var i = 0; i < coa.options.length; i++) {
            if ((coa.options[i].getAttribute('data-code') || '').indexOf(p) === 0) { coa.value = coa.options[i].value; break; }
        }
    });
})();
</script>
@endsection
