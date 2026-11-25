@extends('layouts.app')
@section('title', __('ledger_accounts.payment_accounts'))
@section('css')
@include('ledger.partials.style')
@endsection

{{-- Payment accounts (paxhitestbed admin/payment-account/index): each cash box, bank account and mobile-money wallet, its ledger account and running balance. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    @if($unlinked_count > 0)
    <div class="alert alert-danger d-flex align-items-center justify-content-between" role="alert">
        <span><i class="fas fa-exclamation-circle me-2"></i><strong>{{ trans_choice('ledger_accounts.payments_not_linked', $unlinked_count, ['count' => $unlinked_count]) }}</strong></span>
        @can('payment-account-report-view')
        <a href="{{ route('ledger.payment-account-report.unlinked') }}" class="btn btn-light btn-sm">@lang('ledger_accounts.view_details')</a>
        @endcan
    </div>
    @endif
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0" style="font-size:1.1rem">@lang('ledger_accounts.payment_accounts')</h3>
            @can('payment-account-create')
            <a href="{{ route('ledger.payment-account.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> @lang('ledger_accounts.add_payment_account')</a>
            @endcan
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover ap-table">
                    <thead>
                        <tr>
                            <th width="5%">@lang('ledger_accounts.sl')</th>
                            <th>@lang('ledger_accounts.account_title')</th>
                            <th>@lang('ledger_accounts.account_number')</th>
                            <th>@lang('ledger_accounts.account_type')</th>
                            <th>@lang('ledger_accounts.ledger_account')</th>
                            <th width="12%" class="ap-num">@lang('ledger_accounts.opening_balance')</th>
                            <th width="12%" class="ap-num">@lang('ledger_accounts.current_balance')</th>
                            <th width="8%">@lang('ledger_accounts.status')</th>
                            <th width="16%">@lang('ledger_accounts.field_action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payment_accounts as $key => $account)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td><strong>{{ $account->title }}</strong>
                                @if($account->description)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($account->description, 50) }}</small>@endif</td>
                            <td>{{ $account->account_number ?? '-' }}</td>
                            <td><span class="badge bg-info text-dark">{{ $account->accountType?->label() }}</span></td>
                            <td>{{ \App\Ledger\Services\PaymentBook::accountLabel($account->ledgerAccount) }}</td>
                            <td class="ap-num">{{ $money($account->opening_balance) }}</td>
                            <td class="ap-num"><strong class="{{ $account->current_balance < 0 ? 'text-danger' : 'text-success' }}">{{ $money($account->current_balance) }}</strong></td>
                            <td class="text-center">
                                @if($account->status)<span class="badge bg-success">@lang('ledger_accounts.status_active')</span>
                                @else<span class="badge bg-danger">@lang('ledger_accounts.status_inactive')</span>@endif
                            </td>
                            <td class="text-nowrap">
                                @can('payment-account-book')
                                <a href="{{ route('ledger.payment-account.account-book', $account->id) }}" class="btn btn-sm btn-info" title="@lang('ledger_accounts.account_book')"><i class="fas fa-book"></i></a>
                                @endcan
                                @can('payment-account-deposit')
                                <a href="{{ route('ledger.payment-account.deposit', $account->id) }}" class="btn btn-sm btn-success" title="@lang('ledger_accounts.deposit_external_money')"><i class="fas fa-plus-circle"></i></a>
                                @endcan
                                @can('payment-account-withdraw')
                                <a href="{{ route('ledger.payment-account.withdraw', $account->id) }}" class="btn btn-sm btn-warning" title="@lang('ledger_accounts.withdraw_to_external')"><i class="fas fa-minus-circle"></i></a>
                                @endcan
                                @can('payment-account-transfer-create')
                                <a href="{{ route('ledger.payment-account-transfer.create', ['from' => $account->id]) }}" class="btn btn-sm btn-secondary" title="@lang('ledger_accounts.transfer_to_another_account')"><i class="fas fa-exchange-alt"></i></a>
                                @endcan
                                @can('payment-account-edit')
                                <a href="{{ route('ledger.payment-account.edit', $account->id) }}" class="btn btn-sm btn-primary" title="@lang('ledger_accounts.btn_edit')"><i class="fas fa-edit"></i></a>
                                @endcan
                                @can('payment-account-delete')
                                <form action="{{ route('ledger.payment-account.destroy', $account->id) }}" method="POST" class="d-inline" onsubmit="return confirm(@js(__('ledger_accounts.are_you_sure')))">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="@lang('ledger_accounts.btn_delete')"><i class="fas fa-trash"></i></button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center">@lang('ledger_accounts.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-light">
                            <th colspan="6" class="text-end">@lang('ledger_accounts.total_balance'):</th>
                            <th class="ap-num"><strong class="text-primary">{{ $money($payment_accounts->where('status', true)->sum('current_balance')) }}</strong></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
