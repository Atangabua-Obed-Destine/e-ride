@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@if($print)<style>.ledger-nav, .ledger-top, .no-print { display: none !important; }</style>@endif
@endsection

{{-- A supplier's account (paxhitestbed admin/supplier-bill/statement): every bill, payment and correction, with a running balance. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">@lang('ledger_accounts.supplier_statement')</h5></div>
    <div class="card-body no-print">
        @include('ledger.supplier-bill.nav')
        <form method="get" class="ap-filters mt-3">
            <div class="form-group" style="min-width:260px">
                <label for="supplier_id">@lang('ledger_accounts.supplier')</label>
                <select name="supplier_id" id="supplier_id" class="form-control">
                    <option value="">@lang('ledger_accounts.select')</option>
                    @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected(optional($supplier)->id == $s->id)>{{ $s->title }}</option>@endforeach
                </select>
            </div>
            <div class="form-group"><label for="from">@lang('ledger_accounts.from')</label><input type="date" name="from" id="from" class="form-control" value="{{ request('from') }}"></div>
            <div class="form-group"><label for="to">@lang('ledger_accounts.to')</label><input type="date" name="to" id="to" class="form-control" value="{{ request('to') }}"></div>
            <div class="form-group">
                <button type="submit" class="btn btn-info">@lang('ledger_accounts.show')</button>
                @if($supplier)<a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" class="btn btn-light"><i class="fas fa-print"></i> @lang('ledger_accounts.btn_print')</a>@endif
            </div>
        </form>
    </div>
</div>
@if(! $statement)
<div class="ap-note">@lang('ledger_accounts.statement_choose')</div>
@else
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">{{ $supplier->title }} @if($supplier->tax_number)<span class="ap-muted">@lang('ledger_accounts.niu') {{ $supplier->tax_number }}</span>@endif</h6>
        <div class="ap-muted">{{ $statement['from'] ? \Carbon\Carbon::parse($statement['from'])->format('d/m/Y') : __('ledger_accounts.from_first_entry') }} - {{ \Carbon\Carbon::parse($statement['to'])->format('d/m/Y') }}</div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table ap-table">
                <thead><tr><th>@lang('ledger_accounts.date')</th><th>@lang('ledger_accounts.document')</th><th>@lang('ledger_accounts.their_reference')</th><th>@lang('ledger_accounts.details')</th>
                    <th class="ap-num">@lang('ledger_accounts.billed')</th><th class="ap-num">@lang('ledger_accounts.paid')</th><th class="ap-num">@lang('ledger_accounts.balance_owed')</th></tr></thead>
                <tbody>
                    <tr><td colspan="6">@lang('ledger_accounts.balance_brought_forward')</td><td class="ap-num">{{ $money($statement['opening']) }}</td></tr>
                    @foreach($statement['rows'] as $e)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($e['date'])->format('d/m/Y') }}</td>
                        <td><a href="{{ $e['link'] === 'bill' ? route('ledger.supplier-bill.show', $e['id']) : route('ledger.supplier-payment.show', $e['id']) }}">{{ $e['number'] }}</a></td>
                        <td>{{ $e['reference'] ?: '-' }}</td>
                        <td>{{ $e['description'] }}@if($e['due_date'])<div class="ap-muted">@lang('ledger_accounts.due') {{ \Carbon\Carbon::parse($e['due_date'])->format('d/m/Y') }}</div>@endif</td>
                        <td class="ap-num">{{ $e['billed'] != 0 ? $money($e['billed']) : '' }}</td>
                        <td class="ap-num">{{ $e['paid'] != 0 ? $money($e['paid']) : '' }}</td>
                        <td class="ap-num">{{ $money($e['balance']) }}</td>
                    </tr>
                    @endforeach
                    <tr class="ap-total-row"><td colspan="4">@lang('ledger_accounts.totals_and_balance')</td>
                        <td class="ap-num">{{ $money($statement['billed']) }}</td><td class="ap-num">{{ $money($statement['paid']) }}</td><td class="ap-num">{{ $money($statement['closing']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection

@section('javascript')
@if($print)<script>window.addEventListener('load', function () { window.print(); });</script>@endif
@endsection
