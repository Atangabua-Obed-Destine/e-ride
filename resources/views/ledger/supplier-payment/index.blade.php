@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- Supplier payments (paxhitestbed admin/supplier-payment/index). --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
        <h5 class="mb-0">{{ $title }}</h5>
        @can('supplier-payment-create')
        <a href="{{ route($route.'.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-money-check-alt"></i> @lang('ledger_accounts.pay_a_supplier')</a>
        @endcan
    </div>
    <div class="card-body">
        @include('ledger.supplier-bill.nav')
        <form method="get" action="{{ route($route.'.index') }}" class="ap-filters my-3">
            <div class="form-group">
                <label for="supplier_id">@lang('ledger_accounts.supplier')</label>
                <select name="supplier_id" id="supplier_id" class="form-control">
                    <option value="">@lang('ledger_accounts.all_suppliers')</option>
                    @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->title }}</option>@endforeach
                </select>
            </div>
            <div class="form-group"><label for="from">@lang('ledger_accounts.from')</label><input type="date" name="from" id="from" class="form-control" value="{{ request('from') }}"></div>
            <div class="form-group"><label for="to">@lang('ledger_accounts.to')</label><input type="date" name="to" id="to" class="form-control" value="{{ request('to') }}"></div>
            <div class="form-group">
                <button type="submit" class="btn btn-info"><i class="fas fa-filter"></i> @lang('ledger_accounts.btn_filter')</button>
                <a href="{{ route($route.'.index') }}" class="btn btn-light">@lang('ledger_accounts.clear')</a>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-hover ap-table">
                <thead><tr><th>@lang('ledger_accounts.number')</th><th>@lang('ledger_accounts.date')</th><th>@lang('ledger_accounts.supplier')</th><th>@lang('ledger_accounts.bills_paid')</th>
                    <th>@lang('ledger_accounts.paid_from')</th><th>@lang('ledger_accounts.field_reference')</th><th class="ap-num">@lang('ledger_accounts.amount')</th><th>@lang('ledger_accounts.status')</th><th></th></tr></thead>
                <tbody>
                    @forelse($rows as $p)
                    <tr>
                        <td><a href="{{ route($route.'.show', $p->id) }}">{{ $p->number }}</a></td>
                        <td>{{ $p->payment_date->format('d/m/Y') }}</td>
                        <td>{{ $p->supplier->title ?? '-' }}</td>
                        <td>{{ $p->allocations->map(fn ($a) => $a->bill->number ?? '')->filter()->implode(', ') }}</td>
                        <td>{{ $p->paymentAccount->title ?? \App\Ledger\Services\PaymentBook::accountLabel($p->cashAccount) }}</td>
                        <td>{{ $p->reference ?: '-' }}</td>
                        <td class="ap-num">{{ $money($p->amount) }}</td>
                        <td>@if($p->isVoid())<span class="ap-chip void">@lang('ledger_accounts.state_void')</span>@else<span class="ap-chip posted">@lang('ledger_accounts.posted')</span>@endif</td>
                        <td><a href="{{ route($route.'.show', $p->id) }}" class="btn btn-sm btn-outline-primary" title="@lang('ledger_accounts.open')"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center ap-muted">@lang('ledger_accounts.no_payments')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
