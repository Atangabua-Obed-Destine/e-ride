@extends('layouts.app')
@section('title', __('ledger_accounts.cashflow_report'))
@section('css')
@include('ledger.partials.style')
@endsection

{{-- Cash flow report (paxhitestbed admin/payment-account/reports/cashflow): every movement on the payment accounts, filtered, with money in, money out and the net. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0" style="font-size:1.1rem">@lang('ledger_accounts.cashflow_report')</h3>
            <div class="ap-toolbar">
                @can('payment-account-report-summary')
                <a href="{{ route($route.'.summary') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-table"></i> @lang('ledger_accounts.summary_report')</a>
                @endcan
                @can('payment-account-report-statement')
                <a href="{{ route($route.'.unlinked') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-link"></i> @lang('ledger_accounts.payment_account_report')</a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route($route.'.cashflow') }}">
                <div class="row align-items-end g-2">
                    <div class="col-md-3">
                        <label for="account_id">@lang('ledger_accounts.field_payment_account')</label>
                        <select class="form-control" id="account_id" name="account_id">
                            <option value="">@lang('ledger_accounts.all_accounts')</option>
                            @foreach($accounts as $a)<option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label for="date_from">@lang('ledger_accounts.field_from_date')</label><input type="date" class="form-control" id="date_from" name="date_from" value="{{ request('date_from') }}"></div>
                    <div class="col-md-2"><label for="date_to">@lang('ledger_accounts.field_to_date')</label><input type="date" class="form-control" id="date_to" name="date_to" value="{{ request('date_to') }}"></div>
                    <div class="col-md-2">
                        <label for="transaction_type">@lang('ledger_accounts.transaction_type')</label>
                        <select class="form-control" id="transaction_type" name="transaction_type">
                            <option value="">@lang('ledger_accounts.all_types')</option>
                            <option value="credit" @selected(request('transaction_type') === 'credit')>@lang('ledger_accounts.credit_in')</option>
                            <option value="debit" @selected(request('transaction_type') === 'debit')>@lang('ledger_accounts.debit_out')</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="reference_type">@lang('ledger_accounts.source')</label>
                        <select class="form-control" id="reference_type" name="reference_type">
                            <option value="">@lang('ledger_accounts.all')</option>
                            <option value="manual" @selected(request('reference_type') === 'manual')>@lang('ledger_accounts.ref_manual')</option>
                            @foreach($reference_types as $r)<option value="{{ $r }}" @selected(request('reference_type') === $r)>{{ __('ledger_accounts.ref_'.$r) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-1"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button></div>
                </div>
                @if($methods->isNotEmpty())
                <div class="row g-2 mt-1"><div class="col-md-3">
                    <label for="payment_method">@lang('ledger_accounts.field_payment_method')</label>
                    <select class="form-control" id="payment_method" name="payment_method">
                        <option value="">@lang('ledger_accounts.all')</option>
                        @foreach($methods as $m)<option value="{{ $m }}" @selected(request('payment_method') === $m)>{{ \App\Ledger\Services\PaymentBook::methods()[$m] ?? $m }}</option>@endforeach
                    </select>
                </div></div>
                @endif
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3"><div class="card mb-3 border-success"><div class="card-body">
            <div class="ap-muted">@lang('ledger_accounts.total_credit')</div><h4 class="text-success mb-0">{{ $money($summary['total_credit']) }}</h4><small class="ap-muted">@lang('ledger_accounts.money_in')</small>
        </div></div></div>
        <div class="col-md-3"><div class="card mb-3 border-danger"><div class="card-body">
            <div class="ap-muted">@lang('ledger_accounts.total_debit')</div><h4 class="text-danger mb-0">{{ $money($summary['total_debit']) }}</h4><small class="ap-muted">@lang('ledger_accounts.money_out')</small>
        </div></div></div>
        <div class="col-md-3"><div class="card mb-3 border-primary"><div class="card-body">
            <div class="ap-muted">@lang('ledger_accounts.net_flow')</div><h4 class="mb-0 {{ $summary['net_flow'] < 0 ? 'text-danger' : 'text-primary' }}">{{ $money($summary['net_flow']) }}</h4><small class="ap-muted">@lang('ledger_accounts.credit_minus_debit') · {{ $summary['transaction_count'] }} @lang('ledger_accounts.records')</small>
        </div></div></div>
        <div class="col-md-3"><div class="card mb-3 border-info"><div class="card-body">
            <div class="ap-muted">@lang('ledger_accounts.total_balance')</div><h4 class="mb-0">{{ $money($total_balance) }}</h4><small class="ap-muted">@lang('ledger_accounts.all_accounts')</small>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped ap-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger_accounts.date')</th>
                            <th>@lang('ledger_accounts.field_account')</th>
                            <th>@lang('ledger_accounts.description')</th>
                            <th>@lang('ledger_accounts.field_payment_method')</th>
                            <th>@lang('ledger_accounts.field_reference')</th>
                            <th class="ap-num">@lang('ledger_accounts.debit_out')</th>
                            <th class="ap-num">@lang('ledger_accounts.credit_in')</th>
                            <th class="ap-num">@lang('ledger_accounts.field_account_balance')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $t)
                        <tr>
                            <td>{{ $t->transaction_date->format('d/m/Y') }}</td>
                            <td>{{ $t->paymentAccount->title ?? '-' }}</td>
                            <td>{{ $t->title }}@if($t->description)<br><small class="ap-muted">{{ \Illuminate\Support\Str::limit($t->description, 60) }}</small>@endif</td>
                            <td>{{ $t->payment_method ? (\App\Ledger\Services\PaymentBook::methods()[$t->payment_method] ?? $t->payment_method) : '-' }}</td>
                            <td><span class="badge bg-secondary">{{ __('ledger_accounts.ref_'.($t->reference_type ?: 'manual')) }}</span> {{ $t->payment_reference }}</td>
                            <td class="ap-num text-danger">{{ $t->transaction_type === 'debit' ? $money($t->amount) : '' }}</td>
                            <td class="ap-num text-success">{{ $t->transaction_type === 'credit' ? $money($t->amount) : '' }}</td>
                            <td class="ap-num">{{ $money($t->balance_after) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center">@lang('ledger_accounts.no_data_available')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $transactions->links() }}</div>
    </div>
</section>
@endsection
