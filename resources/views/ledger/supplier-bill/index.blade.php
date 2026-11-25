@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
<style>
    .stat-tile .stat-figure { font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .stat-tile .stat-label { font-weight: 600; }
    .stat-tile .stat-note { font-size: .78rem; color: #64748b; }
    .stat-tile { border-left: 4px solid #1d4ed8; }
    .stat-tile.stat-danger { border-left-color: #b91c1c; }
    .stat-tile.stat-success { border-left-color: #15803d; }
    .stat-tile.stat-warning { border-left-color: #b45309; }
    .stat-tile.stat-info { border-left-color: #0e7490; }
</style>
@endsection

{{-- Supplier bills (paxhitestbed admin/supplier-bill/index): what is owed, what is overdue, the drafts, the list. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<div class="row">
    <div class="col-sm-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
                <h5 class="mb-0">{{ $title }}</h5>
                <div class="ap-toolbar">
                    @can('supplier-bill-create')
                    <a href="{{ route($route.'.create') }}" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> @lang('ledger_accounts.enter_a_bill')</a>
                    @endcan
                    @can('supplier-payment-create')
                    <a href="{{ route('ledger.supplier-payment.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-money-check-alt"></i> @lang('ledger_accounts.pay_a_supplier')</a>
                    @endcan
                </div>
            </div>
            <div class="card-body">@include('ledger.supplier-bill.nav')</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-tile stat-primary mb-3"><div class="card-body">
            <div class="stat-figure">{{ $money($total_owed) }}</div>
            <div class="stat-label">@lang('ledger_accounts.owed_to_suppliers')</div>
            <div class="stat-note">@lang('ledger_accounts.owed_to_suppliers_note')</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card stat-tile {{ $overdue_owed > 0 ? 'stat-danger' : 'stat-success' }} mb-3"><div class="card-body">
            <div class="stat-figure">{{ $money($overdue_owed) }}</div>
            <div class="stat-label">@lang('ledger_accounts.overdue')</div>
            <div class="stat-note">@lang('ledger_accounts.overdue_note')</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card stat-tile {{ $drafts > 0 ? 'stat-warning' : 'stat-info' }} mb-3"><div class="card-body">
            <div class="stat-figure">{{ $drafts }}</div>
            <div class="stat-label">@lang('ledger_accounts.drafts_awaiting')</div>
            <div class="stat-note">@lang('ledger_accounts.drafts_note')</div>
        </div></div>
    </div>
    <div class="col-sm-12">
        <div class="card mb-3">
            <div class="card-body">
                <form method="get" action="{{ route($route.'.index') }}" class="ap-filters mb-3">
                    <div class="form-group">
                        <label for="supplier_id">@lang('ledger_accounts.supplier')</label>
                        <select name="supplier_id" id="supplier_id" class="form-control">
                            <option value="">@lang('ledger_accounts.all_suppliers')</option>
                            @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">@lang('ledger_accounts.status')</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">@lang('ledger_accounts.all')</option>
                            @foreach(['draft', 'unpaid', 'part_paid', 'paid', 'overdue', 'void'] as $k)
                            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $k === 'overdue' ? __('ledger_accounts.overdue') : __('ledger_accounts.state_'.$k) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="from">@lang('ledger_accounts.bill_date_from')</label>
                        <input type="date" name="from" id="from" class="form-control" value="{{ request('from') }}">
                    </div>
                    <div class="form-group">
                        <label for="to">@lang('ledger_accounts.to')</label>
                        <input type="date" name="to" id="to" class="form-control" value="{{ request('to') }}">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> @lang('ledger_accounts.btn_filter')</button>
                        <a href="{{ route($route.'.index') }}" class="btn btn-light">@lang('ledger_accounts.clear')</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-striped table-hover ap-table" style="width:100%">
                        <thead>
                            <tr>
                                <th>@lang('ledger_accounts.number')</th>
                                <th>@lang('ledger_accounts.supplier')</th>
                                <th>@lang('ledger_accounts.their_invoice')</th>
                                <th>@lang('ledger_accounts.bill_date')</th>
                                <th>@lang('ledger_accounts.due')</th>
                                <th class="ap-num">@lang('ledger_accounts.total')</th>
                                <th class="ap-num">@lang('ledger_accounts.owed')</th>
                                <th>@lang('ledger_accounts.status')</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $bill)
                            <tr>
                                <td><a href="{{ route($route.'.show', $bill->id) }}">{{ $bill->number }}</a>
                                    @if($bill->isOpeningBalance())<div class="ap-muted">@lang('ledger_accounts.opening_balance')</div>@endif</td>
                                <td>{{ $bill->supplier->title ?? '-' }}</td>
                                <td>{{ $bill->supplier_reference ?? '-' }}</td>
                                <td>{{ $bill->bill_date->format('d/m/Y') }}</td>
                                <td>{{ $bill->due_date->format('d/m/Y') }}</td>
                                <td class="ap-num">{{ $money($bill->total) }}</td>
                                <td class="ap-num">{{ $bill->isApproved() ? $money($bill->owed) : '-' }}</td>
                                <td>@include('ledger.supplier-bill.chip', ['state' => $bill->state, 'overdue' => $bill->overdue])</td>
                                <td class="text-nowrap">
                                    <a href="{{ route($route.'.show', $bill->id) }}" class="btn btn-sm btn-outline-primary" title="@lang('ledger_accounts.open')"><i class="fas fa-eye"></i></a>
                                    @if($bill->isApproved() && $bill->owed > 0.005)
                                    @can('supplier-payment-create')
                                    <a href="{{ route('ledger.supplier-payment.create', ['supplier_id' => $bill->supplier_id, 'bill_id' => $bill->id]) }}" class="btn btn-sm btn-outline-success" title="@lang('ledger_accounts.pay')"><i class="fas fa-money-bill-wave"></i></a>
                                    @endcan
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="9" class="text-center ap-muted">@lang('ledger_accounts.no_bills')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @can('supplier-bill-approve')
        <div class="card mb-3">
            <div class="card-body">
                <form method="post" action="{{ route($route.'.settings') }}" class="ap-filters">
                    @csrf
                    <div class="form-group" style="min-width:320px">
                        <label for="payable_account_id">@lang('ledger_accounts.debts_owed_on')</label>
                        <select name="payable_account_id" id="payable_account_id" class="form-control">
                            @foreach($payable_accounts as $acc)
                            <option value="{{ $acc->id }}" @selected($payable_account_id == $acc->id)>{{ \App\Ledger\Services\PaymentBook::accountLabel($acc) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><button type="submit" class="btn btn-outline-primary">@lang('ledger_accounts.btn_save')</button></div>
                    <div class="ap-muted" style="max-width:520px">@lang('ledger_accounts.debts_owed_on_note')</div>
                </form>
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection
