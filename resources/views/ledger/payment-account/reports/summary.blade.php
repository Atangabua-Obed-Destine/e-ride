@extends('layouts.app')
@section('title', __('ledger_accounts.summary_report'))
@section('css')
@include('ledger.partials.style')
@endsection

{{-- Each payment account's money in and out over a period (paxhitestbed PaymentAccountReportController::summary). --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0" style="font-size:1.1rem">@lang('ledger_accounts.summary_report')</h3>
            <a href="{{ route($route.'.cashflow') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.cashflow_report')</a>
        </div>
        <div class="card-body">
            <form method="GET" class="ap-filters mb-3">
                <div class="form-group"><label for="date_from">@lang('ledger_accounts.field_from_date')</label><input type="date" class="form-control" id="date_from" name="date_from" value="{{ $date_from }}"></div>
                <div class="form-group"><label for="date_to">@lang('ledger_accounts.field_to_date')</label><input type="date" class="form-control" id="date_to" name="date_to" value="{{ $date_to }}"></div>
                <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> @lang('ledger_accounts.btn_filter')</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-bordered ap-table">
                    <thead><tr><th>@lang('ledger_accounts.field_account')</th><th class="ap-num">@lang('ledger_accounts.money_in')</th><th class="ap-num">@lang('ledger_accounts.money_out')</th>
                        <th class="ap-num">@lang('ledger_accounts.net_flow')</th><th class="ap-num">@lang('ledger_accounts.transactions')</th><th class="ap-num">@lang('ledger_accounts.current_balance')</th></tr></thead>
                    <tbody>
                        @foreach($accounts_summary as $s)
                        <tr>
                            <td>@can('payment-account-report-statement')<a href="{{ route($route.'.statement', ['id' => $s['account']->id, 'date_from' => $date_from, 'date_to' => $date_to]) }}">{{ $s['account']->title }}</a>@else{{ $s['account']->title }}@endcan</td>
                            <td class="ap-num">{{ $money($s['credit']) }}</td><td class="ap-num">{{ $money($s['debit']) }}</td>
                            <td class="ap-num">{{ $money($s['net_flow']) }}</td><td class="ap-num">{{ $s['transaction_count'] }}</td><td class="ap-num">{{ $money($s['current_balance']) }}</td>
                        </tr>
                        @endforeach
                        <tr class="ap-total-row"><td>@lang('ledger_accounts.total')</td><td class="ap-num">{{ $money($grand_total['credit']) }}</td><td class="ap-num">{{ $money($grand_total['debit']) }}</td>
                            <td class="ap-num">{{ $money($grand_total['net_flow']) }}</td><td class="ap-num">{{ $grand_total['transaction_count'] }}</td><td class="ap-num">{{ $money($grand_total['current_balance']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
