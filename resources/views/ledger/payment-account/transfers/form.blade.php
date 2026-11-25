@extends('layouts.app')
@section('title', __('ledger_accounts.fund_transfer'))
@section('css')
@include('ledger.partials.style')
@endsection

{{-- A new or corrected fund transfer (paxhitestbed admin/payment-account/transfers/create and edit), through the review. --}}
@section('content')
@php
    $t = $transfer;
    $from = old('from_account_id', $t->from_account_id ?? $from_account_id);
    $to = old('to_account_id', $t->to_account_id ?? $to_account_id);
@endphp
<section class="content">
    <div class="row"><div class="col-md-8 offset-md-2">
        @include('ledger.account.errors')
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0" style="font-size:1.1rem">{{ $t ? __('ledger_accounts.edit_transfer') : __('ledger_accounts.fund_transfer') }}</h3>
                <a href="{{ route('ledger.payment-account-transfer.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
            </div>
            <form method="POST" enctype="multipart/form-data" id="transfer-form"
                  action="{{ $t ? route('ledger.payment-account-transfer.update', $t->id) : route('ledger.payment-account-transfer.store') }}"
                  data-review="@lang('ledger_accounts.check_transfer')" data-review-label="@lang('ledger_accounts.transfer_and_post')">
                @csrf
                @if($t) @method('PUT') @endif
                <div class="card-body">
                    <div class="alert alert-info"><small><i class="fas fa-info-circle"></i> @lang('ledger_accounts.transfer_info_text')</small></div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="from_account_id">@lang('ledger_accounts.from_account') <span class="text-danger">*</span></label>
                            <select class="form-control" id="from_account_id" name="from_account_id" required>
                                <option value="">@lang('ledger_accounts.select')</option>
                                @foreach($payment_accounts as $a)
                                <option value="{{ $a->id }}" data-balance="{{ $a->current_balance }}" @selected((string) $from === (string) $a->id)>{{ $a->title }} ({{ \App\Ledger\Ledger::money($a->current_balance) }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted" id="from-balance"></small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="to_account_id">@lang('ledger_accounts.to_account') <span class="text-danger">*</span></label>
                            <select class="form-control" id="to_account_id" name="to_account_id" required>
                                <option value="">@lang('ledger_accounts.select')</option>
                                @foreach($payment_accounts as $a)
                                <option value="{{ $a->id }}" @selected((string) $to === (string) $a->id)>{{ $a->title }} ({{ \App\Ledger\Ledger::money($a->current_balance) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount">@lang('ledger_accounts.amount') (FCFA) <span class="text-danger">*</span></label>
                            <input type="number" step="1" min="1" class="form-control" id="amount" name="amount" value="{{ old('amount', $t ? (int) round($t->amount) : '') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="transfer_date">@lang('ledger_accounts.transfer_date') <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="transfer_date" name="transfer_date" max="{{ now()->toDateString() }}"
                                   value="{{ old('transfer_date', $t ? $t->transfer_date->format('Y-m-d') : date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="note">@lang('ledger_accounts.field_note')</label>
                            <textarea class="form-control" id="note" name="note" rows="2">{{ old('note', $t->note ?? '') }}</textarea>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="attach">@lang('ledger_accounts.attachment')</label>
                            <input type="file" class="form-control" id="attach" name="attach" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="text-muted">@lang('ledger_accounts.allowed_files')</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-exchange-alt"></i> @lang('ledger_accounts.transfer_funds')</button></div>
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
    var from = document.getElementById('from_account_id'), to = document.getElementById('to_account_id'), hint = document.getElementById('from-balance');
    var fmt = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
    function show() {
        var o = from.selectedOptions[0];
        hint.textContent = o && o.value ? @json(__('ledger_accounts.available_balance')) + ': ' + fmt.format(parseFloat(o.getAttribute('data-balance')) || 0) : '';
        to.setCustomValidity(from.value && from.value === to.value ? @json(__('ledger_accounts.cannot_transfer_to_same_account')) : '');
    }
    from.addEventListener('change', show);
    to.addEventListener('change', show);
    show();
})();
</script>
@endsection
