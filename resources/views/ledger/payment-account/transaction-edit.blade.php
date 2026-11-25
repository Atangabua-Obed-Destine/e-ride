@extends('layouts.app')
@section('title', __('ledger_accounts.edit_transaction'))
@section('css')
@include('ledger.partials.style')
@endsection

{{-- Correcting a manual movement (paxhitestbed admin/payment-account/transaction-edit), through the review: its entry is re-posted. --}}
@section('content')
@php $t = $transaction; @endphp
<section class="content">
    <div class="row"><div class="col-md-8 offset-md-2">
        @include('ledger.account.errors')
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0" style="font-size:1.1rem">@lang('ledger_accounts.edit_transaction'): {{ $t->paymentAccount->title }}
                    ({{ $t->transaction_type === 'credit' ? __('ledger_accounts.credit_in') : __('ledger_accounts.debit_out') }})</h3>
                <a href="{{ route('ledger.payment-account.account-book', $t->payment_account_id) }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
            </div>
            <form method="POST" enctype="multipart/form-data" action="{{ route('ledger.payment-account.transaction.update', $t->id) }}"
                  data-review="@lang('ledger_accounts.check_edit_transaction')" data-review-label="@lang('ledger_accounts.confirm_and_post')">
                @csrf @method('PUT')
                <div class="card-body">
                    @include('ledger.payment-account.movement_fields', ['t' => $t])
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> @lang('ledger_accounts.btn_update')</button></div>
            </form>
        </div>
    </div></div>
</section>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
