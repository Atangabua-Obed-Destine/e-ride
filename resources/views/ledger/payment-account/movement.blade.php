@extends('layouts.app')
@section('title', $type === 'credit' ? __('ledger_accounts.deposit_money') : __('ledger_accounts.withdraw_money'))
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  Money into or out of a payment account from outside the books' other screens (paxhitestbed
  admin/payment-account/deposit and withdraw, one view here), through the review. E-Ride: naming
  the other side of the entry posts it; left empty, only the book moves (as in paxhitestbed).
--}}
@section('content')
@php $deposit = $type === 'credit'; @endphp
<section class="content">
    <div class="row"><div class="col-md-8 offset-md-2">
        @include('ledger.account.errors')
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0" style="font-size:1.1rem">{{ $deposit ? __('ledger_accounts.deposit_money') : __('ledger_accounts.withdraw_money') }}: {{ $payment_account->title }}</h3>
                <a href="{{ route('ledger.payment-account.account-book', $payment_account->id) }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
            </div>
            <form method="POST" enctype="multipart/form-data"
                  action="{{ $deposit ? route('ledger.payment-account.deposit.post', $payment_account->id) : route('ledger.payment-account.withdraw.post', $payment_account->id) }}"
                  data-review="@lang($deposit ? 'ledger_accounts.check_deposit' : 'ledger_accounts.check_withdraw')" data-review-label="@lang('ledger_accounts.confirm_and_post')">
                @csrf
                <div class="card-body">
                    <div class="alert {{ $deposit ? 'alert-info' : 'alert-warning' }}">
                        <strong>@lang('ledger_accounts.current_balance'):</strong> {{ \App\Ledger\Ledger::money($payment_account->current_balance) }} FCFA
                        <hr class="my-2"><small><i class="fas fa-info-circle"></i> @lang($deposit ? 'ledger_accounts.deposit_info_text' : 'ledger_accounts.withdraw_info_text')</small>
                    </div>
                    @include('ledger.payment-account.movement_fields', ['t' => null])
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn {{ $deposit ? 'btn-success' : 'btn-warning' }}"><i class="fas {{ $deposit ? 'fa-plus-circle' : 'fa-minus-circle' }}"></i>
                        @lang($deposit ? 'ledger_accounts.add_deposit' : 'ledger_accounts.add_withdrawal')</button>
                </div>
            </form>
        </div>
    </div></div>
</section>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
