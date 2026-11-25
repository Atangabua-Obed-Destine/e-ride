@extends('layouts.app')
@section('title', __('ledger_accounts.account_statement'))
@section('css')
@include('ledger.partials.style')
@endsection

{{-- One payment account over a period (paxhitestbed PaymentAccountReportController::accountStatement): opening balance, movements, closing balance. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0" style="font-size:1.1rem">@lang('ledger_accounts.account_statement'): {{ $account->title }}</h3>
            <a href="{{ route('ledger.payment-account.account-book', $account->id) }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.account_book')</a>
        </div>
        <div class="card-body">
            <form method="GET" class="ap-filters">
                <div class="form-group"><label for="date_from">@lang('ledger_accounts.field_from_date')</label><input type="date" class="form-control" id="date_from" name="date_from" value="{{ $date_from }}"></div>
                <div class="form-group"><label for="date_to">@lang('ledger_accounts.field_to_date')</label><input type="date" class="form-control" id="date_to" name="date_to" value="{{ $date_to }}"></div>
                <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> @lang('ledger_accounts.btn_filter')</button></div>
            </form>
            <dl class="ap-dl mt-3">
                <dt>@lang('ledger_accounts.opening_balance')</dt><dd>{{ $money($opening_balance) }}</dd>
                <dt>@lang('ledger_accounts.money_in')</dt><dd class="text-success">{{ $money($period_credit) }}</dd>
                <dt>@lang('ledger_accounts.money_out')</dt><dd class="text-danger">{{ $money($period_debit) }}</dd>
                <dt>@lang('ledger_accounts.closing_balance')</dt><dd><strong>{{ $money($closing_balance) }}</strong></dd>
            </dl>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered ap-table">
                    <thead><tr><th>@lang('ledger_accounts.date')</th><th>@lang('ledger_accounts.field_title')</th><th>@lang('ledger_accounts.source')</th>
                        <th class="ap-num">@lang('ledger_accounts.debit_out')</th><th class="ap-num">@lang('ledger_accounts.credit_in')</th><th class="ap-num">@lang('ledger_accounts.field_balance')</th></tr></thead>
                    <tbody>
                        <tr><td colspan="5">@lang('ledger_accounts.opening_balance')</td><td class="ap-num">{{ $money($opening_balance) }}</td></tr>
                        @foreach($transactions as $t)
                        <tr>
                            <td>{{ $t->transaction_date->format('d/m/Y') }}</td>
                            <td>{{ $t->title }}</td>
                            <td>{{ __('ledger_accounts.ref_'.($t->reference_type ?: 'manual')) }}</td>
                            <td class="ap-num">{{ $t->transaction_type === 'debit' ? $money($t->amount) : '' }}</td>
                            <td class="ap-num">{{ $t->transaction_type === 'credit' ? $money($t->amount) : '' }}</td>
                            <td class="ap-num">{{ $money($t->balance_after) }}</td>
                        </tr>
                        @endforeach
                        <tr class="ap-total-row"><td colspan="3">@lang('ledger_accounts.closing_balance')</td><td class="ap-num">{{ $money($period_debit) }}</td><td class="ap-num">{{ $money($period_credit) }}</td><td class="ap-num">{{ $money($closing_balance) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $transactions->links() }}</div>
    </div>
</section>
@endsection
